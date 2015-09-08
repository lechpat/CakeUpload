<?php
namespace Upload\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Upload\Model\Entity\Upload;

/**
 * Uploads Model
 *
 * @property \Cake\ORM\Association\BelongsTo $Uploads
 * @property \Cake\ORM\Association\HasMany $Images
 * @property \Cake\ORM\Association\HasMany $Uploads
 */
class UploadsTable extends Table
{

    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config)
    {
        parent::initialize($config);

        $this->table('uploads');
        $this->displayField('id');
        $this->primaryKey('id');

        $this->addBehavior('Timestamp');
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator)
    {
        $validator
            ->add('id', 'valid', ['rule' => 'numeric'])
            ->allowEmpty('id', 'create');

        $validator
            ->allowEmpty('class');

        $validator
            ->add('foreign_key', 'valid', ['rule' => 'numeric'])
            ->allowEmpty('foreign_key');

        $validator
            ->requirePresence('original_filename', 'create')
            ->notEmpty('original_filename');

        $validator
            ->requirePresence('unique_filename', 'create')
            ->notEmpty('unique_filename');

        $validator
            ->allowEmpty('subfolder');

        $validator
            ->allowEmpty('mimetype');

        $validator
            ->add('size', 'valid', ['rule' => 'numeric'])
            ->requirePresence('size', 'create')
            ->notEmpty('size');

        $validator
            ->requirePresence('hash', 'create')
            ->notEmpty('hash');

        $validator
            ->add('complete', 'valid', ['rule' => 'boolean'])
            ->requirePresence('complete', 'create')
            ->notEmpty('complete');

        $validator
            ->allowEmpty('label');

        $validator
            ->allowEmpty('data');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules)
    {
        return $rules;
    }
}
