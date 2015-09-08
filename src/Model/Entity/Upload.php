<?php
namespace Upload\Model\Entity;

use Cake\ORM\Entity;

/**
 * Upload Entity.
 */
class Upload extends Entity
{

    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     * Note that '*' is set to true, which allows all unspecified fields to be
     * mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove), and explicitly make individual fields accessible as needed.
     *
     * @var array
     */
    protected $_accessible = [
        '*' => true,
        'id' => false,
    ];

    protected function _getPath()
    {
        return $this->_properties['class'] . DS .
            $this->_properties['subfolder'] . DS .
            $this->_properties['unique_filename'];
    }
}
