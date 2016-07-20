<?php

namespace notamedia\relation\tests\unit;

use notamedia\relation\RelationBehavior;
use notamedia\relation\RelationException;
use yii\codeception\TestCase;

/**
 * Unit-tests for RelationBehavior
 */
class RelationBehaviorTest extends TestCase
{
    /** @var string  */
    public $appConfig = '@tests/unit/_config.php';

    /**
     * Testing method getRelationData()
     * - contains correct data after setting attribute
     * @see RelationBehavior::getRelationData
     */
    public function testGetRelationData()
    {
        $behavior = new RelationBehavior([
            'relationalFields' => ['images']
        ]);

        $data = [['src' => '/images/image.new.png']];

        $behavior->images = $data;

        $this->assertEquals($data, $behavior->getRelationData('images'));
    }

    /**
     * Testing method canSetProperty()
     * - return true for valid data
     * - return false for invalid data
     * @see RelationBehavior::canSetProperty
     */
    public function testCanSetProperty()
    {
        $behavior = new RelationBehavior([
            'relationalFields' => ['file', 'images'],
        ]);

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

    /**
     * Testing setters
     * - contains correct relationalData after setting valid data
     * - contains empty relationalData after setting invalid data
     * @see RelationBehavior::__set
     */
    public function testSetters()
    {
        /** @var FakeNewsModel|\PHPUnit_Framework_MockObject_MockObject $mockModel */
        $mockModel = $this->getMock(FakeNewsModel::className(), ['addError', 'getErrors']);
        $mockModel->expects($this->once())->method('addError');
        $mockModel->expects($this->any())->method('getErrors')->willReturn(['file' => ['File is invalid']]);

        $behavior = new RelationBehavior([
            'relationalFields' => ['file'],
        ]);
        $behavior->owner = $mockModel;

        $validData = [
            'file' => [
                'src' => '/upload/test.new.png',
            ]
        ];
        foreach ($validData as $field => $value) {
            $behavior->$field = $value;
        }

        $this->assertAttributeEquals([
            'file' => [
                'data' => $validData['file']
            ]
        ], 'relationalData', $behavior);

        $behavior = new RelationBehavior([
            'relationalFields' => ['file'],
        ]);
        $behavior->owner = $mockModel;

        $invalidData = [
            'file' => '/upload/bad.value.png',
        ];

        foreach ($invalidData as $field => $value) {
            $behavior->$field = $value;
        }

        $this->assertAttributeEmpty('relationalData', $behavior);
    }

    /**
     * Testing method loadData() for one-to-one relation
     * - attribute relationalData must be correct
     * @see RelationBehavior::loadData
     */
    public function testLoadDataOneToOne()
    {
        /** @var FakeNewsModel|\PHPUnit_Framework_MockObject_MockObject $mockModel */
        $mockModel = $this->getMock(FakeNewsModel::className(), ['getFile', 'getImage']);

        $activeQuery = $mockModel->hasOne(FakeFilesModel::className(), ['id' => 'file_id']);
        $mockModel->expects($this->any())->method('getFile')->willReturn($activeQuery);

        $behavior = new RelationBehavior([
            'relationalFields' => ['file', 'image'],
        ]);
        $behavior->owner = $mockModel;

        $behavior->file = ['src' => '/images/file2.png'];

        $method = new \ReflectionMethod(
            $behavior, 'loadData'
        );
        $method->setAccessible(true);
        $method->invoke($behavior);

        $expected = [
            'file' => [
                'activeQuery' => $activeQuery,
                'newModels' => [
                    new FakeFilesModel(['src' => '/images/file2.png']),
                ],
                'oldModels' => $activeQuery->all(),
            ]
        ];

        $this->assertAttributeEquals(
            $expected, 'relationalData', $behavior
        );
    }

    /**
     * Testing method loadData() for one-to-many relation
     * - attribute relationalData must be correct
     * @see RelationBehavior::loadData
     */
    public function testLoadDataOneToMany()
    {
        $behavior = new RelationBehavior([
            'relationalFields' => ['images'],
        ]);

        /** @var FakeNewsModel|\PHPUnit_Framework_MockObject_MockObject $mockModel */
        $mockModel = $this->getMock(FakeNewsModel::className(), ['getImages']);
        $mockModel->id = 1;

        $activeQuery = $mockModel->hasMany(FakeFilesModel::className(), ['entity_id' => 'id']);
        $mockModel->expects($this->any())->method('getImages')->willReturn($activeQuery);

        $behavior->owner = $mockModel;

        $images = [
            ['src' => '/images/image1.png'],
            ['src' => '/images/image2.png'],
        ];

        // set one-to-many relation
        $behavior->images = $images;
        $expected = [
            'images' => [
                'activeQuery' => $activeQuery,
                'oldModels' => $activeQuery->all(),
            ]
        ];
        /** @var FakeFilesModel $image */
        foreach ($images as $image) {
            $expected['images']['newModels'][] = new FakeFilesModel(array_merge(['entity_id' => $mockModel->id], $image));
        }

        $method = new \ReflectionMethod(
            $behavior, 'loadData'
        );
        $method->setAccessible(true);
        $method->invoke($behavior);

        $this->assertAttributeEquals(
            $expected, 'relationalData', $behavior
        );
    }

    /**
     * Testing method loadData() for one-to-many relation
     * - attribute relationalData must be correct
     * - expecting exception if model does not exists
     * @see RelationBehavior::loadData
     */
    public function testLoadDataManyToMany()
    {
        /** @var FakeNewsModel|\PHPUnit_Framework_MockObject_MockObject $mockModel */
        $mockModel = $this->getMock(FakeNewsModel::className(), ['getNewsFiles', 'getNews_files', 'getFiles']);
        $mockModel->id = 1;

        $mockModel->expects($this->any())->method('getNewsFiles')->willReturn($mockModel->hasMany(FakeNewsFilesModel::className(), ['news_id' => 'id']));

        $activeQuery = $mockModel->hasMany(FakeFilesModel::className(), ['id' => 'file_id'])->via('newsFiles');
        $mockModel->expects($this->any())->method('getNews_files')->willReturn($activeQuery);
        $mockModel->expects($this->any())->method('getFiles')->willReturn($mockModel->hasMany(FakeFilesModel::className(), ['id' => 'file_id'])->via('newsFiles'));

        $behavior = new RelationBehavior([
            'relationalFields' => ['news_files', 'files'],
        ]);
        $behavior->owner = $mockModel;

        $file_ids = [];
        $files = [
            ['src' => '/images/new.image1.png'],
            ['src' => '/images/new.image2.png'],
        ];
        foreach ($files as $data) {
            $file_ids[] = $this->createFile($data);
        }

        $oldModels = $behavior->owner->getNewsFiles()->all();

        $behavior->news_files = $file_ids;

        $method = new \ReflectionMethod(
            $behavior, 'loadData'
        );
        $method->setAccessible(true);
        $method->invoke($behavior);

        $expected = [
            'news_files' => [
                'activeQuery' => $activeQuery,
                'junctionModelClass' => FakeNewsFilesModel::className(),
                'junctionTable' => FakeNewsFilesModel::tableName(),
                'junctionColumn' => 'news_id',
                'relatedColumn' => 'file_id',
            ]
        ];
        foreach ($file_ids as $file) {
            $expected['news_files']['newModels'][] = new FakeNewsFilesModel(['file_id' => $file]);
        }

        $expected['news_files']['oldModels'] = $oldModels;

        $this->assertAttributeEquals(
            $expected, 'relationalData', $behavior
        );

        $this->expectException(RelationException::class);
        $this->expectExceptionMessage('Related records for attribute files not found');

        $behavior->files = [100, 101];
        $method = new \ReflectionMethod(
            $behavior, 'loadData'
        );
        $method->setAccessible(true);
        $method->invoke($behavior);
    }

    /**
     * Testing method validateData()
     * - return true for valid data
     * - model has no errors for valid data
     * - return false for invalid data
     * - model has errors for invalid data
     * @see RelationBehavior::validateData
     */
    public function testValidateData()
    {
        $behavior = new RelationBehavior([
            'relationalFields' => ['file', 'images'],
        ]);

        /** @var FakeNewsModel|\PHPUnit_Framework_MockObject_MockObject $mockModel */
        $mockModel = $this->getMock(FakeNewsModel::className(), ['getErrors', 'addError', 'getFile', 'getImages']);
        $mockModel->expects($this->any())->method('addError');
        $mockModel->expects($this->any())->method('getErrors')->willReturn([]);

        $activeQuery = $mockModel->hasOne(FakeFilesModel::className(), ['id' => 'file_id']);
        $mockModel->expects($this->any())->method('getFile')->willReturn($activeQuery);

        $activeQuery = $mockModel->hasMany(FakeFilesModel::className(), ['entity_id' => 'id']);
        $mockModel->expects($this->any())->method('getImages')->willReturn($activeQuery);

        $mockModel->id = 1;

        $behavior->owner = $mockModel;

        // success
        $validData = [
            'file' => ['src' => '/images/file.png'],
            'images' => [
                ['src' => '/images/image2.png'],
                ['src' => '/images/image2.png'],
            ],
        ];

        foreach ($validData as $field => $data) {

            $behavior->$field = $data;

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
        $mockModel = $this->getMock(FakeNewsModel::className(), ['getErrors', 'addError', 'getFile', 'getImages']);
        $mockModel->expects($this->any())->method('addError');
        $mockModel->expects($this->any())->method('getErrors')->willReturn([
            'file' => ['File is invalid'],
            'images' => ['Images is invalid'],
        ]);

        $activeQuery = $mockModel->hasOne(FakeFilesModel::className(), ['id' => 'file_id']);
        $mockModel->expects($this->any())->method('getFile')->willReturn($activeQuery);

        $activeQuery = $mockModel->hasMany(FakeFilesModel::className(), ['entity_id' => 'id']);
        $mockModel->expects($this->any())->method('getImages')->willReturn($activeQuery);

        $mockModel->id = 1;

        $behavior->owner = $mockModel;

        $invalidData = [
            'file' => ['src' => ''],
            'images' => [
                ['src' => '/images/image1.png'],
                ['src' => ''],
            ],
        ];

        foreach ($invalidData as $field => $data) {

            $behavior->$field = $data;

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

    /**
     * Testing method replaceExistingModel()
     * - if added model already exists then return existing model
     * - if added model does not exists then return new model
     * @see RelationBehavior::replaceExistingModel
     */
    public function testReplaceExistingModel()
    {
        $behavior = new RelationBehavior([
            'relationalFields' => ['images'],
        ]);

        $prop = new \ReflectionProperty(
            $behavior,
            'relationalData'
        );

        $images = [
            new FakeFilesModel(['id' => 1, 'src' => '/upload/image1.png']),
            new FakeFilesModel(['id' => 2, 'src' => '/upload/image2.png']),
            new FakeFilesModel(['id' => 3, 'src' => '/upload/image3.png']),
        ];

        $oldModel = new FakeFilesModel(['src' => '/upload/image2.png']);
        $newModel = new FakeFilesModel(['src' => '/upload/image4.png']);

        $prop->setAccessible(true);
        $prop->setValue($behavior, [
            'images' => [
                'oldModels' => $images
            ]
        ]);

        $method = new \ReflectionMethod(
            $behavior, 'replaceExistingModel'
        );
        $method->setAccessible(true);

        $this->assertEquals($images[1], $method->invokeArgs($behavior, [$oldModel, 'images']));
        $this->assertEquals($newModel, $method->invokeArgs($behavior, [$newModel, 'images']));
    }

    /**
     * Testing method isDeletedModel()
     * - return true for deleted model
     * - return false for old models
     * @see RelationBehavior::isDeletedModel
     */
    public function testIsDeletedModel()
    {
        $behavior = new RelationBehavior([
            'relationalFields' => ['images'],
        ]);

        $prop = new \ReflectionProperty(
            $behavior,
            'relationalData'
        );

        $images = [
            new FakeFilesModel(['id' => 1, 'src' => '/upload/image1.png']),
            new FakeFilesModel(['id' => 2, 'src' => '/upload/image2.png']),
            new FakeFilesModel(['id' => 3, 'src' => '/upload/image3.png']),
        ];

        $deletedImage = array_pop($images);

        $prop->setAccessible(true);
        $prop->setValue($behavior, [
            'images' => [
                'newModels' => $images
            ]
        ]);

        $method = new \ReflectionMethod(
            $behavior, 'isDeletedModel'
        );
        $method->setAccessible(true);

        $this->assertTrue($method->invokeArgs($behavior, [$deletedImage, 'images']));
        foreach ($images as $image) {
            $this->assertFalse($method->invokeArgs($behavior, [$image, 'images']));
        }
    }

    /**
     * Create model File
     * @param array $data
     * @return mixed
     */
    protected function createFile($data = [])
    {
        $file = new FakeFilesModel($data);
        $file->save(false);
        return $file->id;
    }

    /**
     * @inheritdoc
     */
    protected function tearDown()
    {
        FakeFilesModel::deleteAll();
    }
}