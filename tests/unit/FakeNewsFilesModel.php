<?php

namespace notamedia\relation\tests\unit;

use yii\db\ActiveRecord;

/**
 * Тестовая модель связи многие-ко-многим
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
            [['news_id', 'file_id'], 'required'],
            [['news_id', 'file_id'], 'integer'],
        ];
    }
}