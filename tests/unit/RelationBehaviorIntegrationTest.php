<?php

namespace notamedia\relation\tests\unit;

use notamedia\relation\RelationBehavior;
use yii\codeception\TestCase;
use yii\db\ActiveRecord;

/**
 * Интеграционный тест поведения RelationBehavior
 */
class RelationBehaviorIntegrationTest extends TestCase
{
    /** @var string */
    public $appConfig = '@tests/unit/_config.php';

    /** @var  ActiveRecord */
    protected $model;

    /** @inheritdoc */
    protected function setUp()
    {
        parent::setUp();

        $model = $model = new FakeNewsModel();

        $model->name = 'News 1';
        $model->file = ['src' => '/images/file.pdf'];
        $model->images = [
            ['src' => '/images/image1.png',],
            ['src' => '/images/image2.png',],
        ];
        $model->save();

        $this->model = $model;
    }

    /**
     * Тест сохраннеия модели и свзанныйх сущностей
     *
     * - сохранение прошло успешно, метод save вернул true
     * - атрибуты модели из БД после сохранения идентична сохраняемой модели
     * - обновление связанныйх сущностей завершилось без ошибок
     * - связанные сущности совпадают с сохраняемыми
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

        $this->assertTrue($model->save());

        $savedModel = FakeNewsModel::findOne($model->id);

        $this->assertEquals($model->getAttributes(), $savedModel->getAttributes());

        $this->assertTrue($model->isRelationalFinished());

        $this->assertEquals($model->getImages()->all(), $savedModel->images);
    }

    /**
     * Тест на удаление модели и связанных с моделью сущностей
     *
     * - метод delete модели вернул значение отличное от false
     * - связанные сущности удалены из БД
     * @throws \Exception
     */
    public function testDeleteModels()
    {
        $id = $this->model->id;

        $model = FakeNewsModel::findOne($this->model->id);

        $this->assertTrue($model->delete() !== false);

        $this->assertEmpty(FakeFilesModel::find()->where(['entity_id' => $id])->all());
    }

    /**
     *  Тест на добавление/удаление связанныйх сущностей
     *
     *  - сохранение модели вернуло true
     *  - обновление связанныйх сущностей завершилось без ошибок
     *  - удаленная сущность удалена из БД
     *  - список изображений добавленных/изменненых совпадает со входным списком
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

        $this->assertEmpty(FakeFilesModel::findOne($deletedImage['id']));

        $model = FakeNewsModel::findOne($this->model->id);
        $this->assertEquals(array_column($images, 'src'), array_map(function($model) {return $model->src;}, $model->images));
    }

    /**
     * Тест вызова методов-обработчиков при добавлении модели
     *
     * - ожидается разовый вызов метода-обработчика RelationBehavior::beforeSave
     * - ожидается разовый вызов метода-обработчика RelationBehavior::afterSave
     */
    public function testTriggerEventInsert()
    {
        $mockBehavior = $this->getMock(
            RelationBehavior::class,
            ['beforeSave', 'afterSave']
        );
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
            ['src' => '/images/news3.image1.png'],
        ];
        $model->save();
    }

    /**
     * Тест вызова методов-обработчиков при обновлении модели
     * - ожидается разовый вызов метода-обработчика RelationBehavior::beforeSave
     * - ожидается разовый вызов метода-обработчика RelationBehavior::afterSave
     */
    public function testTriggerEventUpdate()
    {
        $mockBehavior = $this->getMock(
            RelationBehavior::class,
            ['beforeSave', 'afterSave']
        );
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
     * Тест вызова методов-обработчиков при удалении модели
     *
     * - ожидается разовый вызов метода-обработчика RelationBehavior::afterDelete
     */
    public function testTriggerEventDelete()
    {
        $mockBehavior = $this->getMock(
            RelationBehavior::class,
            ['afterDelete']
        );
        $mockBehavior->relationalFields = ['file', 'images', 'news_files'];

        $mockBehavior->expects($this->once())->method('afterDelete');

        $model = FakeNewsModel::findOne($this->model->id);

        $model->detachBehaviors();

        $model->attachBehavior('rel', $mockBehavior);

        $model->delete();
    }
}