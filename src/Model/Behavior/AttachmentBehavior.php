<?php
namespace Upload\Model\Behavior;

use Cake\Database\Type;
use Cake\Event\Event;
use Cake\ORM\Behavior;
use Cake\ORM\Entity;
use Cake\ORM\Query;
use Exception;
use Upload\Lib\ImageTransform;
use Upload\Lib\UploadPath;
use Upload\Lib\UploadPathInterface;

use Transit\Transit;
use Transit\File;
use Transit\Exception\ValidationException;
use Transit\Transformer\Image\CropTransformer;
use Transit\Transformer\Image\FlipTransformer;
use Transit\Transformer\Image\ResizeTransformer;
use Transit\Transformer\Image\ScaleTransformer;
use Transit\Transformer\Image\ExifTransformer;
use Transit\Transformer\Image\RotateTransformer;
use Transit\Transformer\Image\FitTransformer;
use Transit\Transporter\Aws\S3Transporter;
use Transit\Transporter\Aws\GlacierTransporter;
use Transit\Transporter\Rackspace\CloudFilesTransporter;

/**
 * Attachment behavior
 */
class AttachmentBehavior extends Behavior
{

    /**
     * Table instance
     *
     * @var \Cake\ORM\Table
     */
    protected $_table;

    /**
     * Default configuration.
     *
     * @var array {
     *      @type string $nameCallback  Method to format filename with
     *      @type string $append        What to append to the end of the filename
     *      @type string $prepend       What to prepend to the beginning of the filename
     *      @type string $tempDir       Directory to upload files to temporarily
     *      @type string $uploadDir     Directory to move file to after upload to make it publicly accessible
     *      @type string $transportDir  Directory to place files in after transporting
     *      @type string $finalPath     The final path to prepend to file names (like a domain)
     *      @type string $dbColumn      Database column to write file path to
     *      @type array $metaColumns    Database columns to write meta data to
     *      @type string $defaultPath   Default image if no file is uploaded
     *      @type bool $overwrite       Overwrite a file with the same name if it exists
     *      @type bool $stopSave        Stop save() if error exists during upload
     *      @type bool $allowEmpty      Allow an empty file upload to continue
     *      @type array $transforms     List of transforms to apply to the image
     *      @type array $transformers   List of custom transformers to class/namespaces
     *      @type array $transport      Settings for file transportation
     *      @type array $transporters   List of custom transporters to class/namespaces
     *      @type array $curl           List of cURL options to set for remote importing
     *      @type bool $cleanup         Remove old files when new files are being written
     * }
     */
    protected $_defaultConfig = [
        'nameCallback' => '',
        'append' => '',
        'prepend' => '',
        'tempDir' => TMP,
        'uploadDir' => '',
        'transportDir' => '',
        'finalPath' => '/files/uploads/',
        'dbColumn' => '',
        'metaColumns' => [],
        'defaultPath' => '',
        'overwrite' => false,
        'stopSave' => true,
        'allowEmpty' => true,
        'transforms' => [],
        'transformers' => [],
        'transport' => [],
        'transporters' => [],
        'curl' => [],
        'cleanup' => true
    ];

    /**
     * Build the behaviour
     *
     * @param array $config Passed configuration
     * @return void
     */
    public function initialize(array $config)
    {
        Type::map('upload.file', '\Upload\Database\Type\FileType');
        $schema = $this->_table->schema();
        foreach (array_keys($this->config()) as $field) {
            $schema->columnType($field, 'upload.file');
        }
        $this->_table->schema($schema);
        $settings = [];        
        foreach ($config as $field => $attachment) {
            $settings[$field] = $attachment + ['dbColumn' => $field] + $this->_config;
        }

        $this->_config = $settings;
        $this->setupAssociations();
    }

    /**
     * beforeMarshal event
     *
     * @param Event $event Event instance
     * @param ArrayObject $data Data to process
     * @param ArrayObject $options Array of options for event
     * @return void
     */
    public function beforeMarshal(Event $event, $data, $options)
    {
        foreach ($this->config() as $field => $settings) {
            if ($this->_table->validator()->isEmptyAllowed($field, false) &&
                isset($data[$field]['error']) && $data[$field]['error'] === UPLOAD_ERR_NO_FILE
            ) {
                unset($data[$field]);
            }
        }
    }

    /**
     * beforeSave method
     *
     * @param Event $event The event
     * @param Entity $entity The entity
     * @param ArrayObject $options Array of options
     * @param UploadPathInterface $path Inject an instance of UploadPath
     * @return true
     * @throws Exception
     */
    public function beforeSave(Event $event, Entity $entity, $options, UploadPathInterface $path = null)
    {
        foreach ($this->config() as $field => $settings) {
            if ($entity->has($field) && is_array($entity->get($field)) &&
                $entity->get($field)['error'] === UPLOAD_ERR_OK) {
                // Allow path to be injected or set in config
                $file = $entity->get($field);
                $transit = new Transit($file);
                if (!empty($settings['pathClass'])) {
                    $path = new $settings['pathClass']($this->_table, $entity, $field, $settings);
                } elseif (!isset($path)) {
                    $path = new UploadPath($this->_table, $entity, $field, $settings);
                }
                $event = new Event('Upload.afterPath', $entity, ['path' => $path]);
                $this->_table->eventManager()->dispatch($event);
                if (!empty($event->result)) {
                    $path = $event->result;
                }
                $path->createPathFolder();
                if ($this->moveUploadedFile($entity->get($field)['tmp_name'], $path->fullPath())) {
                    $entity->set($field, $path->getFilename());
                    
                    //$entity->set($settings['finalPath'], $path->getSeed());
                    // Only generate thumbnails for image uploads
                    if (getimagesize($path->fullPath()) !== false && isset($settings['thumbnailSizes'])) {
                        // Allow the transformation class to be injected
                        if (!empty($settings['transformClass'])) {
                            $imageTransform = new $settings['transformClass']($this->_table, $path);
                        } else {
                            $imageTransform = new ImageTransform($this->_table, $path);
                        }
                        $imageTransform->processThumbnails($settings);
                    }
                } else {
                    throw new Exception('Cannot upload file');
                }
            }
            unset($path);
        }
        return true;
    }

    /**
     * afterDelete method
     *
     * Remove images from records which have been deleted, if they exist
     *
     * @param Event $event The passed event
     * @param Entity $entity The entity
     * @param ArrayObject $options Array of options
     * @param UploadPathInterface $path Inject and instance of UploadPath
     * @return bool
     */
    public function afterDelete(Event $event, Entity $entity, $options, UploadPathInterface $path = null)
    {
        foreach ($this->config() as $field => $settings) {
            $dir = $entity->get($settings['finalPath']);
            if (!empty($entity) && !empty($dir)) {
                if (!$path) {
                    $path = new UploadPath($this->_table, $entity, $field, $settings);
                }
                $path->deleteFiles($path->getFolder(), true);
            }
            $path = null;
        }
        return true;
    }

    /**
     * Wrapper method for move_uploaded_file
     * This will check if the file has been uploaded or not before picking the correct method to move the file
     *
     * @param string $file Path to the uploaded file
     * @param string $destination The destination file name
     * @return bool
     */
    protected function moveUploadedFile($file, $destination)
    {
        if (is_uploaded_file($file)) {
            return move_uploaded_file($file, $destination);
        }
        return rename($file, $destination);
    }


    /**
     * Creates the associations between the bound table and every field passed to
     * this method.
     *
     * Additionally it creates a `i18n` HasMany association that will be
     * used for fetching all versions for each record in the bound table
     *
     * @param string $table the table name to use for storing each field version
     * @return void
     */
    public function setupAssociations()
    {
        $alias = $this->_table->alias();
        $this->_table->hasOne('Uploads', [
            'className' => 'Upload.Uploads',
            'foreignKey' => 'foreign_key',
            'propertyName' => 'image',
            'conditions' => [
                'Uploads.class' => 'Cover'
            ]
        ]);   
    }

    /**
     * Modifies the entity before it is saved so that versioned fields are persisted
     * in the database too.
     *
     * @param \Cake\Event\Event $event The beforeSave event that was fired
     * @param \Cake\ORM\Entity $entity The entity that is going to be saved
     * @param \ArrayObject $options the options passed to the save method
     * @return void
     */
    public function beforeFind(Event $event, Query $query, $options, $primary)
    {
        $query->contain('Uploads');
    }
}
