<?php

namespace notamedia\relation\tests\unit;

use yii\db\ActiveRecord;

/**
 * Fake relation for many-to-many
 *
 * @property integer $news_id
 * @property integer $file_id
 * @property integer $sort
 * @property string $entity_type
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
            [['news_id', 'file_id', 'sort'], 'integer'],
            [['entity_type'], 'string']
        ];
    }
}