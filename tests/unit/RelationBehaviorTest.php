<?php

namespace notamedia\relation\tests\unit;

use notamedia\relation\RelationBehavior;
use notamedia\relation\RelationException;
use yii\codeception\TestCase;

/**
 * Unit-tests for RelationBehavior.
 */
class RelationBehaviorTest extends TestCase
{
    /** @var string  */
    public $appConfig = '@tests/unit/config.php';

    /**
     * Testing method init():
     * - throw exception on invalid preProcessing configuration.
     *
     * @see RelationBehavior::init
     */
    public function testPreProcessingInitThrowException()
    {
        $this->expectException(\InvalidArgumentException::class);

        $behavior = new RelationBehavior([
            'relationalFields' => ['images'],
            'preProcessing' => ['images' => 'string']
        ]);

        $behavior->init();
    }

    /**
     * Testing method init():
     * - passed initialization.
     *
     * @see RelationBehavior::init
     */
    public function testPreProcessingInit()
    {
        $behavior = new RelationBehavior([
            'relationalFields' => ['images'],
            'preProcessing' => ['images' => function() {
                return true;
            }]
        ]);

        $behavior->init();
    }

    /**
     * Testing method getRelationData():
     * - contains correct data after setting attribute.
     *
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
     * Testing method canSetProperty():
     * - return true for valid data;
     * - return false for invalid data.
     *
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
     * Testing setters:
     * - contains correct relationalData after setting valid data;
     * - contains empty relationalData after setting invalid data.
     *
     * @see RelationBehavior::__set
     */
    public function testSetters()
    {
        /** @var FakeNewsModel|\PHPUnit_Framework_MockObject_MockObject $mockModel */
        $mockModel = $this->getMockBuilder(FakeNewsModel::className())
            ->setMethods(['addError', 'getErrors'])
            ->getMock();
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
     * Testing method loadData() for one-to-one relation:
     * - attribute relationalData must be correct.
     *
     * @see RelationBehavior::loadData
     */
    public function testLoadDataOneToOne()
    {
        /** @var FakeNewsModel|\PHPUnit_Framework_MockObject_MockObject $mockModel */
        $mockModel = $this->getMockBuilder(FakeNewsModel::className())
            ->setMethods(['getFile', 'getImage'])
            ->getMock();

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
                'newRows' => [],
                'oldRows' => []
            ]
        ];

        $this->assertAttributeEquals(
            $expected, 'relationalData', $behavior
        );
    }

    /**
     * Testing method loadData() for one-to-many relation:
     * - attribute relationalData must be correct.
     *
     * @see RelationBehavior::loadData
     */
    public function testLoadDataOneToMany()
    {
        $behavior = new RelationBehavior([
            'relationalFields' => ['images'],
        ]);

        /** @var FakeNewsModel|\PHPUnit_Framework_MockObject_MockObject $mockModel */
        $mockModel = $this->getMockBuilder(FakeNewsModel::className())
            ->setMethods(['getImages'])
            ->getMock();
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
                'newRows' => [],
                'oldRows' => []
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
     * Testing method loadData() for many-to-many relation:
     * - attribute relationalData must be correct;
     * - expecting exception if model does not exists.
     *
     * @see RelationBehavior::loadData
     */
    public function testLoadDataManyToMany()
    {
        // success

        /** @var FakeNewsModel|\PHPUnit_Framework_MockObject_MockObject $mockModel */
        $mockModel = $this->getMockBuilder(FakeNewsModel::className())
            ->setMethods(['getNewsFiles', 'getNews_files', 'getFiles'])
            ->getMock();
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
                'newRows' => [],
                'oldRows' => []
            ]
        ];
        foreach ($file_ids as $file) {
            $expected['news_files']['newModels'][] = new FakeNewsFilesModel(['file_id' => $file, 'news_id' => $mockModel->id]);
        }

        $expected['news_files']['oldModels'] = $oldModels;

        $this->assertAttributeEquals(
            $expected, 'relationalData', $behavior
        );

        // fail

        $this->expectException(RelationException::className());
        $this->expectExceptionMessage('Related records for attribute files not found');

        $behavior->files = [100, 101];
        $method = new \ReflectionMethod(
            $behavior, 'loadData'
        );
        $method->setAccessible(true);
        $method->invoke($behavior);

        $this->expectException(RelationException::className());
        $this->expectExceptionMessage('Related records for attribute news_files not found');

        $behavior->news_files = [100, 101];
        $method = new \ReflectionMethod(
            $behavior, 'loadData'
        );
        $method->setAccessible(true);
        $method->invoke($behavior);
    }

    /**
     * Testing method loadData() for many-to-many relation with viaTable:
     * - attribute relationalData must be correct;
     * - expecting exception if related rows does not exists.
     */
    public function testLoadDataManyToManyViaTable()
    {
        // success
        /** @var FakeNewsModel|\PHPUnit_Framework_MockObject_MockObject $mockModel */
        $mockModel = $this->getMockBuilder(FakeNewsModel::className())
            ->setMethods(['getNews_files_via_table'])
            ->getMock();
        $mockModel->id = 1;

        $activeQuery = $mockModel->hasMany(FakeFilesModel::className(),
            ['id' => 'file_id'])->viaTable('news_files_via_table',
            ['news_id' => 'id']);

        $mockModel->expects($this->any())->method('getNews_files_via_table')->willReturn($activeQuery);

        $behavior = new RelationBehavior([
            'relationalFields' => ['news_files_via_table'],
        ]);
        $behavior->owner = $mockModel;

        $files = [
            ['src' => '/images/new.image1.png'],
            ['src' => '/images/new.image2.png'],
        ];
        $file_ids = [];
        foreach ($files as $data) {
            $file_ids[] = $this->createFile($data);
        }

        $oldRows = FakeNewsFilesModel::findAll(['news_id' => $mockModel->id]);

        $behavior->news_files_via_table = $file_ids;

        $method = new \ReflectionMethod(
            $behavior, 'loadData'
        );
        $method->setAccessible(true);
        $method->invoke($behavior);

        $expected = [
            'news_files_via_table' => [
                'activeQuery' => $activeQuery,
                'junctionTable' => 'news_files_via_table',
                'junctionColumn' => 'news_id',
                'relatedColumn' => 'file_id',
                'newModels' => [],
                'oldModels' => [],
                'newRows' => [],
                'oldRows' => []
            ]
        ];
        foreach ($file_ids as $file_id) {
            $expected['news_files_via_table']['newRows'][] = [
                'file_id' => $file_id,
                'news_id' => $mockModel->id
            ];
        }

        $expected['news_files_via_table']['oldRows'] = $oldRows;

        $this->assertAttributeEquals(
            $expected, 'relationalData', $behavior
        );

        // fail
        $this->expectException(RelationException::className());
        $this->expectExceptionMessage('Related records for attribute news_files_via_table not found');

        $behavior->news_files_via_table = [100, 101];
        $method = new \ReflectionMethod(
            $behavior, 'loadData'
        );
        $method->setAccessible(true);
        $method->invoke($behavior);
    }

    /**
     * Testing method validateData():
     * - return true for valid data;
     * - model has no errors for valid data;
     * - return false for invalid data;
     * - model has errors for invalid data.
     *
     * @see RelationBehavior::validateData
     */
    public function testValidateData()
    {
        $behavior = new RelationBehavior([
            'relationalFields' => ['file', 'images'],
        ]);

        /** @var FakeNewsModel|\PHPUnit_Framework_MockObject_MockObject $mockModel */
        $mockModel = $this->getMockBuilder(FakeNewsModel::className())
            ->setMethods(['getErrors', 'addError', 'getFile', 'getImages'])
            ->getMock();
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
                ['src' => '/images/image1.png'],
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
        $mockModel = $this->getMockBuilder(FakeNewsModel::className())
            ->setMethods(['getErrors', 'addError', 'getFile', 'getImages'])
            ->getMock();
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
     * Testing method replaceExistingModel():
     * - if added model already exists then return existing model;
     * - if added model does not exists then return new model.
     *
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
     * Testing method isDeletedModel():
     * - return true for deleted model;
     * - return false for old models.
     *
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
     * Testing method isDeletedRow():
     * - return true for deleted row;
     * - return false for old row.
     * 
     * @see RelationBehavior::isDeletedRows
     */
    public function testIsDeletedRow()
    {
        $behavior = new RelationBehavior([
            'relationalFields' => ['news_files_via_table'],
        ]);

        $prop = new \ReflectionProperty(
            $behavior,
            'relationalData'
        );

        $files = [
            [
                'news_id' => 1,
                'file_id' => 1,
            ],
            [
                'news_id' => 1,
                'file_id' => 2,
            ],
            [
                'news_id' => 1,
                'file_id' => 3,
            ],
        ];

        $deletedFiles = array_pop($files);

        $prop->setAccessible(true);
        $prop->setValue($behavior, [
            'news_files_via_table' => [
                'newRows' => $files,
                'junctionColumn' => 'news_id'
            ]
        ]);

        $method = new \ReflectionMethod(
            $behavior, 'isDeletedRow'
        );
        $method->setAccessible(true);

        $this->assertTrue($method->invokeArgs($behavior, [$deletedFiles, 'news_files_via_table']));
        foreach ($files as $file) {
            $this->assertFalse($method->invokeArgs($behavior, [$file, 'news_files_via_table']));
        }
    }

    /**
     * Testing method isExistingRow():
     * - if added row already exists in table then return true;
     * - if added row does not exists in table then return false.
     * 
     * @see RelationBehavior::isExistingRow
     */
    public function testIsExistingRow()
    {
        $behavior = new RelationBehavior([
            'relationalFields' => ['news_files_via_table'],
        ]);

        $prop = new \ReflectionProperty(
            $behavior,
            'relationalData'
        );

        $files = [
            [
                'news_id' => 1,
                'file_id' => 1,
            ],
            [
                'news_id' => 1,
                'file_id' => 3,
            ],
        ];

        $oldRow = ['news_id' => 1, 'file_id' => 1];
        $newRow = ['news_id' => 1, 'file_id' => 2];

        $prop->setAccessible(true);
        $prop->setValue($behavior, [
            'news_files_via_table' => [
                'oldRows' => $files,
                'junctionColumn' => 'news_id'
            ]
        ]);

        $method = new \ReflectionMethod(
            $behavior, 'isExistingRow'
        );
        $method->setAccessible(true);

        $this->assertTrue($method->invokeArgs($behavior, [$oldRow, 'news_files_via_table']));
        $this->assertFalse($method->invokeArgs($behavior, [$newRow, 'news_files_via_table']));
    }

    /**
     * Testing method validateOnCondition():
     * - if condition in onCondition part for one-to-many relation is an associative array, then return true;
     * - if condition in onCondition part for one-to-many relation isn't an associative array, then return false.
     *
     * @see RelationBehavior::validateOnCondition
     */
    public function testValidateOnCondition()
    {
        /** @var FakeNewsModel|\PHPUnit_Framework_MockObject_MockObject $mockModel */
        $mockModel = $this->getMockBuilder(FakeNewsModel::className())
            ->setMethods(['getImages'])
            ->getMock();
        $mockModel->id = 1;

        $behavior = new RelationBehavior();

        $method = new \ReflectionMethod(
            $behavior, 'validateOnCondition'
        );
        $method->setAccessible(true);

        // success
        $activeQuery = $mockModel
            ->hasMany(FakeFilesModel::className(), ['id' => 'file_id'])
            ->onCondition(['entity_type' => '050']);

        $this->assertTrue($method->invokeArgs($behavior, [$activeQuery]));

        // fail
        $activeQuery = $mockModel
            ->hasMany(FakeFilesModel::className(), ['id' => 'file_id'])
            ->onCondition(['like', 'entity_type', '050']);

        $this->assertFalse($method->invokeArgs($behavior, [$activeQuery]));
    }

    /**
     * Testing method validateOnCondition() with via:
     * - if condition in onCondition part for many-to-many relation is an associative array, then return true;
     * - if condition in onCondition part for many-to-many relation isn't not an associative array, then return false.
     *
     * @see RelationBehavior::validateOnCondition
     */
    public function testValidateOnConditionWithVia()
    {
        /** @var FakeNewsModel|\PHPUnit_Framework_MockObject_MockObject $mockModel */
        $mockModel = $this->getMockBuilder(FakeNewsModel::className())
            ->setMethods(['getImages'])
            ->getMock();
        $mockModel->id = 1;

        $behavior = new RelationBehavior();

        $method = new \ReflectionMethod(
            $behavior, 'validateOnCondition'
        );
        $method->setAccessible(true);

        // success
        $mockModel = $this->getMockBuilder(FakeNewsModel::className())
            ->setMethods(['getNewsFiles'])
            ->getMock();
        $mockModel->id = 1;


        $activeQuery = $mockModel
            ->hasMany(FakeNewsFilesModel::className(), ['news_id' => 'id'])
            ->onCondition(['entity_type' => '050']);
        $mockModel->expects($this->any())->method('getNewsFiles')->willReturn($activeQuery);

        $activeQuery = $mockModel
            ->hasMany(FakeFilesModel::className(), ['id' => 'file_id'])
            ->via('newsFiles');

        $this->assertTrue($method->invokeArgs($behavior, [$activeQuery]));

        // fail
        $mockModel = $this->getMockBuilder(FakeNewsModel::className())
            ->setMethods(['getNewsFiles'])
            ->getMock();
        $mockModel->id = 1;

        $activeQuery = $mockModel
            ->hasMany(FakeNewsFilesModel::className(), ['news_id' => 'id'])
            ->onCondition(['like', 'entity_type', '050']);

        $mockModel->expects($this->any())->method('getNewsFiles')->willReturn($activeQuery);

        $activeQuery = $mockModel
            ->hasMany(FakeFilesModel::className(), ['id' => 'file_id'])
            ->via('newsFiles');

        $this->assertFalse($method->invokeArgs($behavior, [$activeQuery]));
    }

    /**
     * Testing method loadModelsOneToOne():
     * - key newModels must contains correct models;
     * - newModels key must contain one model with entity_id set by preProcessing;
     * - model is an object of correct class.
     *
     * @see RelationBehavior::loadModelsOneToOne
     */
    public function testLoadModelsOneToOne()
    {
        $entity_id = 1090;
        $behavior = new RelationBehavior([
            'relationalFields' => ['file'],
            'preProcessing' => ['file' => function(FakeFilesModel $model) use ($entity_id) {
                $model->entity_id = $entity_id;

                return $model;
            }]
        ]);

        $file = ['src' => '/images/file2.png'];
        $activeQuery = (new FakeNewsModel())
            ->hasOne(FakeFilesModel::className(), ['id' => 'file_id']);
        
        $prop = new \ReflectionProperty(
            $behavior,
            'relationalData'
        );
        $prop->setAccessible(true);
        $prop->setValue($behavior, [
            'file' => [
                'activeQuery' => $activeQuery,
                'data' => $file,
            ]
        ]);

        $method = new \ReflectionMethod(
            $behavior, 'loadModelsOneToOne'
        );
        $method->setAccessible(true);
        $method->invokeArgs($behavior, ['file']);

        $models = $prop->getValue($behavior)['file']['newModels'];
        $this->assertEquals([new FakeFilesModel(array_merge($file, ['entity_id' => $entity_id]))], $models);
        $this->assertCount(1, $models);
        $this->assertInstanceOf(FakeFilesModel::className(), $models[0]);
    }

    /**
     * Testing method loadModelsOneToMany():
     * - key newModels must contains correct models with entity_id set by preProcessing;
     * - newModels key must contain correct count of models;
     * - model is an object of correct class.
     *
     * @see RelationBehavior::loadModelsOneToMany
     */
    public function testLoadModelsOneToMany()
    {
        /** @var FakeNewsModel|\PHPUnit_Framework_MockObject_MockObject $mockModel */
        $mockModel = $this->getMockBuilder(FakeNewsModel::className())
            ->setMethods(['getImages'])
            ->getMock();
        $mockModel->id = 1;
        $mockModel->expects($this->any())->method('getImages')->willReturn([]);

        $entity_id = 1090;
        $behavior = new RelationBehavior([
            'preProcessing' => ['images' => function(FakeFilesModel $model) use ($entity_id) {
                $model->entity_id = $entity_id;

                return $model;
            }]
        ]);

        $behavior->owner = $mockModel;

        $images = [
            ['src' => '/images/image1.png'],
            ['src' => '/images/image2.png'],
        ];
        $parentAttribute = 'file_id';
        $activeQuery = (new FakeNewsModel())
            ->hasOne(FakeFilesModel::className(), ['id' => $parentAttribute]);

        $prop = new \ReflectionProperty(
            $behavior,
            'relationalData'
        );
        $prop->setAccessible(true);
        $prop->setValue($behavior, [
            'images' => [
                'activeQuery' => $activeQuery,
                'data' => $images,
            ]
        ]);

        $method = new \ReflectionMethod(
            $behavior, 'loadModelsOneToMany'
        );
        $method->setAccessible(true);
        $method->invokeArgs($behavior, ['images']);

        $models = $prop->getValue($behavior)['images']['newModels'];

        $expected = [];
        foreach ($images as $attributes) {
            $attributes['id'] = $behavior->owner->$parentAttribute;
            $attributes['entity_id'] = $entity_id;
            $expected[] = new FakeFilesModel($attributes);
        }
        $this->assertEquals($expected, $models);
        $this->assertCount(count($expected), $models);
        foreach ($models as $model) {
            $this->assertInstanceOf(FakeFilesModel::className(), $model);
        }
    }

    /**
     * Testing method loadModelsManyToManyViaTable():
     * - key newRows must contains correct data with sort set by preProcessing;
     * - key oldRows must contains correct data.
     *
     * @see RelationBehavior::loadModelsManyToManyViaTable
     */
    public function testLoadModelsManyToManyViaTable()
    {
        $model = new FakeNewsModel();
        $model->name = 'testLoadModelsManyToManyViaTable';
        $model->save(false);

        $oldRowsExpected = [];
        $connection = \Yii::$app->db;
        for ($i = 0; $i < 2; $i++) {
            $fileId = $this->createFile(['src' => '/images/image.old.' . ($i + 1) . '.png']);
            $connection->createCommand()
                ->insert('news_files_via_table', [
                    'news_id' => $model->id,
                    'file_id' => $fileId,
                ])->execute();
            $oldRowsExpected[] = [
                'news_id' => $model->id,
                'file_id' => $fileId
            ];
        }

        $newRowsExpected = $newFileIds = [];
        for ($i = 0; $i < 2; $i++) {
            $fileId = $this->createFile(['src' => '/images/image.new.' . ($i + 1) . '.png']);
            $newRowsExpected[] = [
                'news_id' => $model->id,
                'file_id' => $fileId,
                'sort'    => $i
            ];
            $newFileIds[] = $fileId;
        }

        $sortOrder = 0;
        $behavior = new RelationBehavior([
            'preProcessing' => ['news_files_via_table' => function(array $model) use (&$sortOrder) {
                $model['sort'] = $sortOrder++;

                return $model;
            }]
        ]);
        $behavior->owner = FakeNewsModel::findOne($model->id);

        $activeQuery = (new FakeNewsModel())
            ->hasMany(FakeFilesModel::className(), ['id' => 'file_id'])
            ->viaTable('news_files_via_table', ['news_id' => 'id']);

        $prop = new \ReflectionProperty($behavior, 'relationalData');
        $prop->setAccessible(true);
        $prop->setValue($behavior, [
            'news_files_via_table' => [
                'activeQuery' => $activeQuery,
                'data' => $newFileIds,
            ]
        ]);

        $method = new \ReflectionMethod(
            $behavior, 'loadModelsManyToManyViaTable'
        );
        $method->setAccessible(true);
        $method->invokeArgs($behavior, ['news_files_via_table']);

        $this->assertEquals($newRowsExpected, $prop->getValue($behavior)['news_files_via_table']['newRows']);
        $this->assertEquals($oldRowsExpected, $prop->getValue($behavior)['news_files_via_table']['oldRows']);
    }

    /**
     * Testing method loadModelsManyToManyViaTable() with onCondition:
     * - key newRows must contains correct data with sort set by preProcessing;
     * - key oldRows must contains correct data.
     *
     * @see RelationBehavior::loadModelsManyToManyViaTable
     */
    public function testLoadModelsManyToManyViaTableWithOnCondition()
    {
        $model = new FakeNewsModel();
        $model->name = 'testLoadModelsManyToManyViaTableWithCondition';
        $model->save(false);

        $oldRowsExpected = [];
        $connection = \Yii::$app->db;
        for ($i = 0; $i < 2; $i++) {
            $fileId = $this->createFile(['src' => '/images/image.old.' . ($i + 1) . '.png']);
            $row = [
                'type' => 'with_condition',
                'news_id' => $model->id,
                'file_id' => $fileId,
            ];
            $connection->createCommand()
                ->insert('news_files_via_table_w_cond', $row)->execute();
            $oldRowsExpected[] = $row;
        }

        $newRowsExpected = $newFileIds = [];
        for ($i = 0; $i < 2; $i++) {
            $fileId = $this->createFile(['src' => '/images/image.new.' . ($i + 1) . '.png']);
            $newRowsExpected[] = [
                'type' => 'with_condition',
                'news_id' => $model->id,
                'file_id' => $fileId,
                'sort'    => $i
            ];
            $newFileIds[] = $fileId;
        }

        $sortOrder = 0;
        $behavior = new RelationBehavior([
            'preProcessing' => ['news_files_via_table_w_cond' => function(array $model) use (&$sortOrder) {
                $model['sort'] = $sortOrder++;

                return $model;
            }]
        ]);
        $behavior->owner = FakeNewsModel::findOne($model->id);

        $activeQuery = (new FakeNewsModel())
            ->hasMany(FakeFilesModel::className(), ['id' => 'file_id'])
            ->viaTable('news_files_via_table_w_cond', ['news_id' => 'id'], function($query) {
                return $query->onCondition(['type' => 'with_condition']);
            });

        $prop = new \ReflectionProperty($behavior, 'relationalData');
        $prop->setAccessible(true);
        $prop->setValue($behavior, [
            'news_files_via_table_w_cond' => [
                'activeQuery' => $activeQuery,
                'data' => $newFileIds,
            ]
        ]);

        $method = new \ReflectionMethod(
            $behavior, 'loadModelsManyToManyViaTable'
        );
        $method->setAccessible(true);
        $method->invokeArgs($behavior, ['news_files_via_table_w_cond']);

        $this->assertEquals($newRowsExpected, $prop->getValue($behavior)['news_files_via_table_w_cond']['newRows']);
        $this->assertEquals($oldRowsExpected, $prop->getValue($behavior)['news_files_via_table_w_cond']['oldRows']);
    }

    /**
     * Testing method loadModelsManyToManyVia():
     * - key newModels must contains correct models with sort set by preProcessing;
     * - key oldModels must contains correct models.
     *
     * @see RelationBehavior::loadModelsManyToManyVia
     */
    public function testLoadModelsManyToManyVia()
    {
        $model = new FakeNewsModel();
        $model->name = 'loadModelsManyToManyVia';
        $model->save(false);

        $connection = \Yii::$app->db;
        for ($i = 0; $i < 2; $i++) {
            $fileId = $this->createFile(['src' => '/images/image.old.' . ($i + 1) . '.png']);
            $connection->createCommand()
                ->insert('news_files', [
                    'news_id' => $model->id,
                    'file_id' => $fileId,
                ])->execute();
        }
        $oldModelsExpected = (new FakeNewsFilesModel())->find()->all();

        $newModelsExpected = $newFileIds = [];
        for ($i = 0; $i < 2; $i++) {
            $fileId = $this->createFile(['src' => '/images/image.new.' . ($i + 1) . '.png']);
            $newModelsExpected[] = new FakeNewsFilesModel([
                'news_id' => $model->id,
                'file_id' => $fileId,
                'sort'    => $i
            ]);
            $newFileIds[] = $fileId;
        }

        $sortOrder = 0;
        $behavior = new RelationBehavior([
            'preProcessing' => ['news_files' => function(FakeNewsFilesModel $model) use (&$sortOrder) {
                $model->sort = $sortOrder++;

                return $model;
            }]
        ]);
        $behavior->owner = FakeNewsModel::findOne($model->id);

        $activeQuery = (new FakeNewsModel())
            ->hasMany(FakeFilesModel::className(), ['id' => 'file_id'])
            ->via('newsFiles');

        $prop = new \ReflectionProperty($behavior, 'relationalData');
        $prop->setAccessible(true);
        $prop->setValue($behavior, [
            'news_files' => [
                'activeQuery' => $activeQuery,
                'data' => $newFileIds,
            ]
        ]);

        $method = new \ReflectionMethod(
            $behavior, 'loadModelsManyToManyVia'
        );
        $method->setAccessible(true);
        $method->invokeArgs($behavior, ['news_files']);

        $this->assertEquals($newModelsExpected, $prop->getValue($behavior)['news_files']['newModels']);
        $this->assertEquals($oldModelsExpected, $prop->getValue($behavior)['news_files']['oldModels']);
    }

    /**
     * Testing method loadModelsManyToManyVia() with OnCondition:
     * - key newModels must contains correct models with sort set by preProcessing;
     * - key oldModels must contains correct models.
     *
     * @see RelationBehavior::loadModelsManyToManyVia
     */
    public function testLoadModelsManyToManyViaWithOnCondition()
    {
        $model = new FakeNewsModel();
        $model->name = 'loadModelsManyToManyViaWithOnCondition';
        $model->save(false);

        $connection = \Yii::$app->db;
        for ($i = 0; $i < 2; $i++) {
            $fileId = $this->createFile(['src' => '/images/image.old.' . ($i + 1) . '.png']);
            $connection->createCommand()
                ->insert('news_files', [
                    'entity_type' => 'with_condition',
                    'news_id' => $model->id,
                    'file_id' => $fileId,
                ])->execute();
        }
        $oldModelsExpected = (new FakeNewsFilesModel())->find()->all();

        $newModelsExpected = $newFileIds = [];
        for ($i = 0; $i < 2; $i++) {
            $fileId = $this->createFile(['src' => '/images/image.new.' . ($i + 1) . '.png']);
            $newModelsExpected[] = new FakeNewsFilesModel([
                'entity_type' => 'with_condition',
                'news_id' => $model->id,
                'file_id' => $fileId,
                'sort'    => $i
            ]);
            $newFileIds[] = $fileId;
        }

        $sortOrder = 0;
        $behavior = new RelationBehavior([
            'preProcessing' => ['news_files_w_cond' => function(FakeNewsFilesModel $model) use (&$sortOrder) {
                $model->sort = $sortOrder++;

                return $model;
            }]
        ]);
        $behavior->owner = FakeNewsModel::findOne($model->id);

        $activeQuery = (new FakeNewsModel())
            ->hasMany(FakeFilesModel::className(), ['id' => 'file_id'])
            ->via('newsFilesWithCond');

        $prop = new \ReflectionProperty($behavior, 'relationalData');
        $prop->setAccessible(true);
        $prop->setValue($behavior, [
            'news_files_w_cond' => [
                'activeQuery' => $activeQuery,
                'data' => $newFileIds,
            ]
        ]);

        $method = new \ReflectionMethod(
            $behavior, 'loadModelsManyToManyVia'
        );
        $method->setAccessible(true);
        $method->invokeArgs($behavior, ['news_files_w_cond']);

        $this->assertEquals($newModelsExpected, $prop->getValue($behavior)['news_files_w_cond']['newModels']);
        $this->assertEquals($oldModelsExpected, $prop->getValue($behavior)['news_files_w_cond']['oldModels']);
    }

    /**
     * Create model File.
     * 
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