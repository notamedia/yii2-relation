<?php

namespace notamedia\relation\tests\unit;

use notamedia\relation\RelationBehavior;
use yii\db\ActiveRecord;

/**
 * Fake news model
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
        return ['file', 'images', 'news_files', 'news_files_via_table', 'news_files_via_table_w_cond'];
    }

    /** @inheritdoc */
    public function behaviors()
    {
        return [
            [
                'class' => RelationBehavior::class,
                'relationalFields' => ['file', 'images', 'news_files', 'news_files_via_table', 'news_files_via_table_w_cond']
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
     * @return \yii\db\ActiveQuery
     */
    public function getNewsFilesWithCond()
    {
        return $this
            ->hasMany(FakeNewsFilesModel::className(), ['news_id' => 'id'])
            ->onCondition(['entity_type' => 'with_condition']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getNews_files()
    {
        return $this->hasMany(FakeFilesModel::className(), ['id' => 'file_id'])->via('newsFiles');
    }


    /**
     * @return \yii\db\ActiveQuery
     */
    public function getNews_files_w_cond()
    {
        return $this->hasMany(FakeFilesModel::className(), ['id' => 'file_id'])->via('newsFilesWithCond');
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getNews_files_via_table()
    {
        return $this->hasMany(FakeFilesModel::className(), ['id' => 'file_id'])->viaTable('news_files_via_table',
            ['news_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getNews_files_via_table_w_cond()
    {
        return $this
            ->hasMany(FakeFilesModel::className(), ['id' => 'file_id'])
            ->viaTable('news_files_via_table_w_cond', ['news_id' => 'id'], function($query) {
                return $query->onCondition(['entity_type' => 'with_cond']);
            });
    }

    /** @inheritdoc */
    public function transactions()
    {
        return [
            $this->getScenario() => static::OP_ALL
        ];
    }
}