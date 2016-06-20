<?php

namespace notamedia\relation\tests\unit;

use yii\db\ActiveRecord;

class FakeNewsFilesModel extends ActiveRecord
{
    public static function tableName()
    {
        return 'news_files';
    }

    public function rules()
    {
        return [
            [['news_id', 'file_id'], 'required'],
            [['news_id', 'file_id'], 'integer'],
        ];
    }
}