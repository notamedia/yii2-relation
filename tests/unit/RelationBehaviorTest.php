<?php

namespace notamedia\relation\tests\unit;

use notamedia\relation\RelationBehavior;
use yii\codeception\TestCase;
use yii\db\ActiveRecord;

/**
 * Unit-тест для RelationBehavior
 */
class RelationBehaviorTest extends TestCase
{
    /** @var string  */
    public $appConfig = '@tests/unit/_config.php';

    /** @inheritdoc */
    protected function setUp()
    {
        parent::setUp();
    }

    /** @see RelationBehavior::getRelationData */
    public function testGetRelationData()
    {
        $model = $this->createModel();
        $behavior = $model->getBehavior(0);

        $data = [['src' => '/images/image.new.png']];

        $model->images = $data;

        $this->assertEquals($data, $behavior->getRelationData('images'));
    }

    /** @see RelationBehavior::canSetProperty */
    public function testCanSetProperty()
    {
        $model = $this->createModel();
        $behavior = $model->getBehavior(0);

        // valid fields
        $validFields = [
            'file',
            'images',
        ];
        foreach ($validFields as $fieldCode) {
            $this->assertTrue($behavior->canSetProperty($fieldCode));
        }

        // invalid fields
        $invalidFields = [
            'name',
            'image',
        ];
        foreach ($invalidFields as $fieldCode) {
            $this->assertFalse($behavior->canSetProperty($fieldCode));
        }
    }

    /** @see RelationBehavior::__set */
    public function testSetters()
    {
        $model = $this->createModel();
        $behavior = $model->getBehavior(0);

        $validData = [
            'name' => 'News',
            'file' => [
                'src' => '/upload/test.new.png',
            ]
        ];
        foreach ($validData as $field => $value) {
            $model->$field = $value;
        }

        $this->assertAttributeEquals([
            'file' => [
                'data' => $validData['file']
            ]
        ], 'relationalData', $behavior);

        $nameField = 'name';
        $this->assertEquals($validData['name'], $model->$nameField);

        $invalidData = [
            'name' => 'News',
            'file' => '/upload/bad.value.png',
        ];

        foreach ($invalidData as $field => $value) {
            $model->$field = $value;

        }

        $this->assertNotEmpty($model->getErrors());
        $this->assertArrayHasKey('file', $model->getErrors());
    }

    /** @see RelationBehavior::loadData */
    public function testLoadData()
    {
        // one-to-one

        $model = $this->createModel();
        $behavior = $model->getBehavior(0);

        $file = new FakeFilesModel([
            'src' => '/images/file2.png',
        ]);
        $file->save();

        $model->file = ['src' => $file->src];

        $method = new \ReflectionMethod(
            $behavior, 'loadData'
        );
        $method->setAccessible(true);
        $method->invoke($behavior);

        $activeQuery = $behavior->owner->getFile();
        $expected = [
            'file' => [
                'activeQuery' => $activeQuery,
                'newModels' => [
                    new FakeFilesModel(['src' => $file->src]),
                ],
                'oldModels' => $activeQuery->all(),
            ]
        ];

        $this->assertAttributeEquals(
            $expected, 'relationalData', $behavior
        );

        // one-to-many

        $model = $this->createModel();
        $behavior = $model->getBehavior(0);

        $images = [];

        $image = new FakeFilesModel([
            'src' => '/images/image1.png',
        ]);
        $image->save();
        $images[] = ['src' => $image->src];

        $image = new FakeFilesModel([
            'src' => '/images/image2.png',
        ]);
        $image->save();
        $images[] = ['src' => $image->src];

        // set one-to-many relation
        $model->images = $images;

        $activeQuery = $behavior->owner->getImages();
        $expected = [
            'images' => [
                'activeQuery' => $activeQuery,
                'oldModels' => $activeQuery->all(),
            ]
        ];
        /** @var FakeFilesModel $image */
        foreach ($images as $image) {
            $expected['images']['newModels'][] = new FakeFilesModel(array_merge(['entity_id' => $model->id], $image));
        }

        $method = new \ReflectionMethod(
            $behavior, 'loadData'
        );
        $method->setAccessible(true);
        $method->invoke($behavior);

        $this->assertAttributeEquals(
            $expected, 'relationalData', $behavior
        );

        // many-to-many

        $model = $this->createModel();
        $behavior = $model->getBehavior(0);

        $files = [];
        $file = new FakeFilesModel([
            'src' => '/images/file1.png',
        ]);
        $file->save();
        $files[] = $file->id;
        $file = new FakeFilesModel([
            'src' => '/images/file2.png',
        ]);
        $file->save();
        $files[] = $file->id;

        $oldModels = $model->getNewsFiles()->all();

        $model->news_files = $files;

        $method = new \ReflectionMethod(
            $behavior, 'loadData'
        );
        $method->setAccessible(true);
        $method->invoke($behavior);

        $activeQuery = $behavior->owner->getNews_files();
        $expected = [
            'news_files' => [
                'activeQuery' => $activeQuery,
                'junctionModelClass' => FakeNewsFilesModel::className(),
                'junctionTable' => FakeNewsFilesModel::tableName(),
                'junctionColumn' => 'news_id',
                'relatedColumn' => 'file_id',
            ]
        ];
        foreach ($files as $file) {
            $expected['news_files']['newModels'][] = new FakeNewsFilesModel(['file_id' => $file]);
        }
        foreach ($oldModels as $oldModel) {
            $expected['news_files']['oldModels'] = $oldModels;
        }

        $this->assertAttributeEquals(
            $expected, 'relationalData', $behavior
        );


    }

    /** @see RelationBehavior::validateData */
    public function testValidateData()
    {
        // create additional files
        $files = [];
        $file = new FakeFilesModel([
            'src' => '/images/file1.png',
        ]);
        $file->save();
        $files[] = $file->id;
        $file = new FakeFilesModel([
            'src' => '/images/file2.png',
        ]);
        $file->save();
        $files[] = $file->id;

        // success

        $validData = [
            'file' => ['src' => '/images/file.png'],
            'images' => [
                ['src' => '/images/image2.png'],
                ['src' => '/images/image2.png'],
            ],
        ];

        foreach ($validData as $field => $data) {

            $model = $this->createModel();
            $behavior = $model->getBehavior(0);

            $model->$field = $data;

            $method = new \ReflectionMethod(
                $behavior, 'loadData'
            );
            $method->setAccessible(true);
            $method->invoke($behavior);

            $method = new \ReflectionMethod(
                $behavior, 'validateData'
            );
            $method->setAccessible(true);

            $this->assertTrue($method->invoke($behavior));
        }

        $this->assertEmpty($behavior->owner->getErrors());

        // fail

        $invalidData = [
            'file' => ['src' => ''],
            'images' => [
                ['src' => '/images/image1.png'],
                ['src' => ''],
            ],
        ];

        foreach ($invalidData as $field => $data) {

            $model = $this->createModel();
            $behavior = $model->getBehavior(0);

            $model->$field = $data;

            $method = new \ReflectionMethod(
                $behavior, 'loadData'
            );
            $method->setAccessible(true);
            $method->invoke($behavior);

            $method = new \ReflectionMethod(
                $behavior, 'validateData'
            );
            $method->setAccessible(true);

            $this->assertFalse($method->invoke($behavior));
        }

        $this->assertNotEmpty($behavior->owner->getErrors());

    }

    /** @see RelationBehavior::replaceExistingModel */
    public function testReplaceExistingModel()
    {
        $model = $this->createModel();
        $behavior = $model->getBehavior(0);

        $expected = [];

        $images = [];
        foreach ($model->images as $image) {
            $images[] = [
                'entity_id' => $image->entity_id,
                'src' => $image->src,
            ];

            $expected[] = $image;
        }

        $data = ['src' => '/images/image.new.png', 'entity_id' => $model->id];
        $images[] = $data;

        $expected[] = new FakeFilesModel($data);

        $model->images = $images;

        $method = new \ReflectionMethod(
            $behavior, 'loadData'
        );
        $method->setAccessible(true);
        $method->invoke($behavior);

        $prop = new \ReflectionProperty(
            $behavior,
            'relationalData'
        );
        $prop->setAccessible(true);
        $relationalDataValue = $prop->getValue($behavior);

        $this->assertEquals($expected, $relationalDataValue['images']['newModels'], $behavior);
    }

    /** @see RelationBehavior::isDeletedModel */
    public function testIsDeletedModel()
    {
        $model = $this->createModel();
        $behavior = $model->getBehavior(0);

        $expected = [];

        $images = [];

        foreach ($model->images as $image) {
            $images[] = [
                'entity_id' => $image->entity_id,
                'src' => $image->src,
            ];
            $expected[] = $image;
        }

        array_pop($images);
        $deletedModel = array_pop($expected);

        $data = ['src' => '/images/image.new.png', 'entity_id' => $model->id];

        $images[] = $data;

        $expected[] = new FakeFilesModel(['src' => '/images/image.new.png', 'entity_id' => $model->id]);

        $model->images = $images;

        $method = new \ReflectionMethod(
            $behavior, 'loadData'
        );
        $method->setAccessible(true);
        $method->invoke($behavior);

        $prop = new \ReflectionProperty(
            $behavior,
            'relationalData'
        );
        $prop->setAccessible(true);
        $relationalDataValue = $prop->getValue($behavior);

        $this->assertEquals($expected, $relationalDataValue['images']['newModels'], $behavior);

        $method = new \ReflectionMethod(
            $behavior, 'isDeletedModel'
        );
        $method->setAccessible(true);

        $this->assertTrue($method->invokeArgs($behavior, [$deletedModel, 'images']));

        foreach ($expected as $model) {
            $this->assertFalse($method->invokeArgs($behavior, [$model, 'images']));
        }
    }

    /**
     * @return FakeNewsModel
     */
    protected function createModel()
    {
        $file = new FakeFilesModel([
            'src' => '/images/test.png',
        ]);
        $file->save(false);

        $model = new FakeNewsModel([
            'name' => 'News',
            'file_id' => $file->id,
        ]);
        $model->save(false);

        $image = new FakeFilesModel([
            'src' => '/images/images1.png',
            'entity_id' => $model->id,
        ]);
        $image->save(false);

        $image = new FakeFilesModel([
            'src' => '/images/images2.png',
            'entity_id' => $model->id,
        ]);
        $image->save(false);

        (new FakeNewsFilesModel(['news_id' => $model->id, 'file_id' => $file->id]))->save();

        return $model;
    }
}