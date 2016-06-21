<?php

namespace notamedia\relation\tests\unit;

use notamedia\relation\RelationBehavior;
use yii\db\ActiveRecord;

/**
 *
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
            [['file', 'images', 'news_files'], 'safe'],
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
        return ['file', 'images', 'news_files'];
    }

    /** @inheritdoc */
    public function behaviors()
    {
        return [
            [
                'class' => RelationBehavior::class,
                'relationalFields' => ['file', 'images', 'news_files']
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

    /** @inheritdoc */
    public function transactions()
    {
        return [
            $this->getScenario() => static::OP_ALL
        ];
    }
}