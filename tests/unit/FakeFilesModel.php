<?php

namespace notamedia\relation\tests\unit;

use yii\db\ActiveRecord;

/**
 *
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