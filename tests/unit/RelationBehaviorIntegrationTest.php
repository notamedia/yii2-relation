<?php

namespace notamedia\relation\tests\unit;

use notamedia\relation\RelationBehavior;
use yii\codeception\TestCase;
use yii\db\ActiveRecord;
use yii\db\Query;

/**
 * Integration test for RelationBehavior.
 */
class RelationBehaviorIntegrationTest extends TestCase
{
    /** @var string */
    public $appConfig = '@tests/unit/config.php';

    /** @var  ActiveRecord */
    protected $model;

    /** @inheritdoc */
    protected function setUp()
    {
        parent::setUp();

        $model = new FakeNewsModel();

        $model->name = 'News 1';
        $model->file = ['src' => '/images/file.pdf'];
        $model->images = [
            ['src' => '/images/image1.png',],
            ['src' => '/images/image2.png',],
        ];

        $files = [
            ['src' => '/images/file1.png'],
            ['src' => '/images/file2.png'],
        ];
        $fileIds = [];
        foreach ($files as $data) {
            $file = new FakeFilesModel($data);
            $file->save(false);
            $fileIds[] = $file->id;
        }
        $model->news_files = $fileIds;

        $files = [
            ['src' => '/images/file3.png'],
            ['src' => '/images/file4.png'],
        ];
        $fileIds = [];
        foreach ($files as $data) {
            $file = new FakeFilesModel($data);
            $file->save(false);
            $fileIds[] = $file->id;
        }
        $model->news_files_via_table = $fileIds;

        $model->save();

        $this->model = $model;
    }

    /**
     * Test saving models and related entities:
     * - save is successful, method save returned true;
     * - saving model attributes equals  saved model attributes;
     * - update of related entities was completed without errors;
     * - related entities equals with saved models.
     */
    public function testSaveModels()
    {
        $model = new FakeNewsModel();

        $model->name = 'News 2';
        $model->file = ['src' => '/images/file.pdf'];
        $model->images = [
            ['src' => '/images/image1.png',],
            ['src' => '/images/image2.png',],
        ];

        $files = [
            ['src' => '/images/file1.png'],
            ['src' => '/images/file2.png'],
        ];
        $fileIds = [];
        foreach ($files as $data) {
            $file = new FakeFilesModel($data);
            $file->save(false);
            $fileIds[] = $file->id;
        }
        $model->news_files_via_table = $fileIds;

        $this->assertTrue($model->save());

        $savedModel = FakeNewsModel::findOne($model->id);

        $this->assertEquals($model->getAttributes(), $savedModel->getAttributes());

        $this->assertTrue($model->isRelationalFinished());

        $model->refresh();

        $this->assertEquals($model->images, $savedModel->images);
        $this->assertEquals($model->news_files_via_table, $savedModel->news_files_via_table);
    }

    /**
     * Test deleting model and related entity model:
     * - method save returned value other than false;
     * - related entities deleted from the database.
     *
     * @throws \Exception
     */
    public function testDeleteModels()
    {
        $id = $this->model->id;

        $model = FakeNewsModel::findOne($this->model->id);

        $this->assertTrue($model->delete() !== false);

        $this->assertEmpty(FakeFilesModel::find()->where(['entity_id' => $id])->all());
        $this->assertEmpty((new Query())->from('news_files_via_table')->where(['news_id' => $id])->all());
    }

    /**
     *  Test adding/removing related entities:
     *  - save returned true;
     *  - updating of related entities was completed without errors;
     *  - removed entity is removed from the database;
     *  - list of added / modified images equals with the input list.
     */
    public function testUpdateModels()
    {
        $model = FakeNewsModel::findOne($this->model->id);

        $images = [];
        foreach ($model->images as $image) {
            $images[] = $image->getAttributes();
        }

        $deletedImage = array_pop($images);

        $images = array_merge($images, [
            ['src' => '/images/image3.png'],
            ['src' => '/images/image4.png'],
            ['src' => '/images/image5.png'],
        ]);

        $model->images = $images;

        $this->assertTrue($model->save());

        $this->assertTrue($model->isRelationalFinished());

        $this->assertEmpty(FakeFilesModel::findOne($deletedImage['src']));

        $model = FakeNewsModel::findOne($this->model->id);
        $this->assertEquals(array_column($images, 'src'), array_map(function($model) {return $model->src;}, $model->images));
    }

    /**
     *  Test adding/removing related entities:
     *  - save returned true;
     *  - updating of related entities was completed without errors;
     *  - removed entity is removed from the database;
     *  - list of added / modified images equals with the input list.
     */
    public function testUpdateModelsWithPreProcess()
    {
        $model = FakeNewsModel::findOne($this->model->id);
//
//        $images = [];
//        foreach ($model->images as $image) {
//            $images[] = $image->getAttributes();
//        }
//
//        $deletedImage = array_pop($images);
//
//        $images = array_merge($images, [
//            ['src' => '/images/image3.png'],
//            ['src' => '/images/image4.png'],
//            ['src' => '/images/image5.png'],
//        ]);
//
//        $model->images = $images;
//
//        $this->assertTrue($model->save());
//
//        $this->assertTrue($model->isRelationalFinished());
//
//        $this->assertEmpty(FakeFilesModel::findOne($deletedImage['src']));
//
//        $model = FakeNewsModel::findOne($this->model->id);
//        $this->assertEquals(array_column($images, 'src'), array_map(function($model) {return $model->src;}, $model->images));
    }

    /**
     * Test calling handlers when adding model:
     * - expected one-time calling handler RelationBehavior::beforeSave;
     * - expected one-time calling handler RelationBehavior::afterSave.
     */
    public function testTriggerEventInsert()
    {
        $mockBehavior = $this->getMockBuilder(RelationBehavior::className())
            ->setMethods(['beforeSave', 'afterSave'])
            ->getMock();
        $mockBehavior->relationalFields = ['file', 'images', 'news_files'];

        $mockBehavior->expects($this->once())->method('beforeSave');
        $mockBehavior->expects($this->once())->method('afterSave');

        $model = new FakeNewsModel();

        $model->detachBehaviors();

        $model->attachBehavior('rel', $mockBehavior);

        $model->name = 'News 3';
        $model->file = ['src' => '/images/news3.file.txt'];
        $model->images = [
            ['src' => '/images/news3.image1.png'],
            ['src' => '/images/news3.image2.png'],
        ];
        $model->save();
    }

    /**
     * Test calling handlers when update model:
     * - expected one-time calling handler RelationBehavior::beforeSave;
     * - expected one-time calling handler RelationBehavior::afterSave.
     */
    public function testTriggerEventUpdate()
    {
        $mockBehavior = $this->getMockBuilder(RelationBehavior::className())
            ->setMethods(['beforeSave', 'afterSave'])
            ->getMock();
        $mockBehavior->relationalFields = ['file', 'images', 'news_files'];

        $mockBehavior->expects($this->once())->method('beforeSave');
        $mockBehavior->expects($this->once())->method('afterSave');

        $model = FakeNewsModel::findOne($this->model->id);

        $model->detachBehaviors();

        $model->attachBehavior('rel', $mockBehavior);

        $model->name = 'News 3';
        $model->file = ['src' => '/images/news3.file.txt'];
        $model->images = [
            ['src' => '/images/news3.image1.png'],
            ['src' => '/images/news3.image1.png'],
        ];
        $model->save();
    }

    /**
     * Test calling handlers when delete model:
     * - expected one-time calling handler RelationBehavior::afterDelete.
     */
    public function testTriggerEventDelete()
    {
        $mockBehavior = $this->getMockBuilder(RelationBehavior::className())
            ->setMethods(['afterDelete'])
            ->getMock();
        $mockBehavior->relationalFields = ['file', 'images', 'news_files'];

        $mockBehavior->expects($this->once())->method('afterDelete');

        $model = FakeNewsModel::findOne($this->model->id);

        $model->detachBehaviors();

        $model->attachBehavior('rel', $mockBehavior);

        $model->delete();
    }
}