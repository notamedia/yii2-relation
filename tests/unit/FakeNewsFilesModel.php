<?php

namespace notamedia\relation\tests\unit;

use yii\db\ActiveRecord;

/**
 * Fake relation for many-to-many
 */
class FakeNewsFilesModel extends ActiveRecord
{
    /** @inheritdoc */
    public static function tableName()
    {
        return 'news_files';
    }

    /** @inheritdoc */
    public function rules()
    {
        return [
            [['news_id', 'file_id'], 'integer'],
            [['entity_type'], 'string']
        ];
    }
}