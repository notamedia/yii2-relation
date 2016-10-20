<?php

namespace notamedia\relation\tests\unit;

use notamedia\relation\RelationBehavior;
use yii\db\ActiveRecord;

/**
 * Тестовая модель Новости
 *
 * @property integer $file_id
 * @property string $name
 * @property FakeFilesModel $file
 * @property FakeFilesModel[] $images
 * @property FakeFilesModel[] $news_files
 * @property FakeFilesModel[] $news_files_via_table
 */
class FakeNewsModel extends ActiveRecord
{
    /** @inheritdoc */
    public static function tableName()
    {
        return 'news';
    }

    /** #@inheritdoc */
    public function rules()
    {
        return [
            [['file_id'], 'integer'],
            ['name', 'string', 'max' => 255],
            [['file', 'images', 'news_files', 'news_files_via_table'], 'safe'],
        ];
    }

    /** @inheritdoc */
    public function fields()
    {
        $fields = parent::fields();

        unset($fields['file_id']);

        return $fields;
    }


    /** @inheritdoc */
    public function extraFields()
    {
        return ['file', 'images', 'news_files', 'news_files_via_table'];
    }

    /** @inheritdoc */
    public function behaviors()
    {
        return [
            [
                'class' => RelationBehavior::class,
                'relationalFields' => ['file', 'images', 'news_files', 'news_files_via_table']
            ]
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getFile()
    {
        return $this->hasOne(FakeFilesModel::className(), ['id' => 'file_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getImages()
    {
        return $this->hasMany(FakeFilesModel::className(), ['entity_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getNewsFiles()
    {
        return $this->hasMany(FakeNewsFilesModel::className(), ['news_id' => 'id']);
    }

    /**
     * @return $this
     */
    public function getNews_files()
    {
        return $this->hasMany(FakeFilesModel::className(), ['id' => 'file_id'])->via('newsFiles');
    }

    /**
     * @return $this
     */
    public function getNews_files_via_table()
    {
        return $this->hasMany(FakeFilesModel::className(), ['id' => 'file_id'])->viaTable('news_files_via_table',
            ['news_id' => 'id']);
    }

    /** @inheritdoc */
    public function transactions()
    {
        return [
            $this->getScenario() => static::OP_ALL
        ];
    }
}