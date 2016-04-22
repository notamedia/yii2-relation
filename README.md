# Saving related data in Yii2

[![Build Status](https://travis-ci.org/notamedia/yii2-relation.svg)](https://travis-ci.org/notamedia/yii2-relation)
[![Latest Stable Version](https://poser.pugx.org/notamedia/yii2-relation/v/stable)](https://packagist.org/packages/notamedia/yii2-relation) 
[![Total Downloads](https://poser.pugx.org/notamedia/yii2-relation/downloads)](https://packagist.org/packages/notamedia/yii2-relation) 
[![License](https://poser.pugx.org/notamedia/yii2-relation/license)](https://packagist.org/packages/notamedia/yii2-relation)

Behavior for support relational data management.

- Insert related models from POST array.
- Delete related models from database which not exist in POST array
- Skip related models which already exist in database with same attributes
- Rollback database changes, if relational model save/delete error occurred
- Support one-to-one and one-to-many relations

This behavior uses getters for relational attribute in owner model, such getters must return `ActiveQuery` object.
If you use string values in ON condition in `ActiveQuery` object, then this behavior will throw exception.

## Installation

```bash
composer require notamedia/yii2-relation
```

## Examples

For make works this behavior you need: 
* Add all relational properties to rules as safe attribute
* Declare getter for relational attribute
* Put attribute to relationalFields property of behavior

### One-to-one
```php
<?php

...

class Entity extends ActiveRecord
{
    ...
    
    public function rules()
    {
        return [
            [['one_to_one_attribute'], 'safe']
        ];
    }
    
    public function getOne_to_one_attribute()
    {
        return $this->hasOne(OneToOneEntity::className(), 
            ['id' => 'one_to_one_entity_id']);
    }
    
    public function behaviors()
    {
        return [
            [
                'class' => RelationBehavior::className(),
                'relationalFields' => ['one_to_one_attribute']
            ]
        ];
    }
    
    public function transactions()
    {
        return [
            $this->getScenario() => static::OP_ALL
        ];
    }
    
    ...
}

```

### One-to-many

```php
<?php

...

class Entity extends ActiveRecord
{
    ...
    
    public function rules()
    {
        return [
            [['one_to_many_attribute'], 'safe']
        ];
    }
    
    public function getOne_to_many_attribute()
    {
        return $this->hasMany(OneToManyEntity::className(), 
            ['one_to_many_entity_id' => 'id']);
    }
    
    public function behaviors()
    {
        return [
            [
                'class' => RelationBehavior::className(),
                'relationalFields' => ['one_to_many_attribute']
            ]
        ];
    }
    
    public function transactions()
    {
        return [
            $this->getScenario() => static::OP_ALL
        ];
    }
    
    ...
}

```

### Many-to-many

```php
<?php

...

class Entity extends ActiveRecord
{
    ...
    
    public function rules()
    {
        return [
            [['many_to_many_attribute'], 'safe']
        ];
    }
       
    public function getEntityManyToManyModels()
    {
        return $this->hasMany(EntityToManyToManyModel::className(), 
            ['entity_id' => 'id']);
    }

    public function getMany_to_many_attribute()
    {
        return $this->hasMany(ManyToManyModel::className(), 
            ['id' => 'many_to_many_model_id'])
                ->via('entityManyToManyModels');
    }
    
    public function behaviors()
    {
        return [
            [
                'class' => RelationBehavior::className(),
                'relationalFields' => ['many_to_many_attribute']
            ]
        ];
    }
    
    public function transactions()
    {
        return [
            $this->getScenario() => static::OP_ALL
        ];
    }
    
    ...
}

```