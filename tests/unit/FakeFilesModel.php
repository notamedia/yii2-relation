<?php

namespace notamedia\relation\tests\unit;

use yii\db\ActiveRecord;

/**
 * Fake file model
 *
 * @property integer $id
 * @property string $src
 * @property integer $entity_id
 * @property string $entity_type
 */
class FakeFilesModel extends ActiveRecord
{
    /** @inheritdoc */
    public static function tableName()
    {
        return 'files';
    }

    /** @inheritdoc */
    public function rules()
    {
        return [
            [['src'], 'required'],
            [['entity_id',], 'integer'],
            [['src'], 'string', 'max' => 255],
        ];
    }

    /** @inheritdoc */
    public function fields()
    {
        $fields = parent::fields();

        unset($fields['entity_id'], $fields['id']);

        return $fields;
    }
}