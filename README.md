# Saving related data in Yii2

[![Build Status](https://travis-ci.org/notamedia/yii2-relation.svg)](https://travis-ci.org/notamedia/yii2-relation)
[![Latest Stable Version](https://poser.pugx.org/notamedia/yii2-relation/v/stable)](https://packagist.org/packages/notamedia/yii2-relation) 
[![Total Downloads](https://poser.pugx.org/notamedia/yii2-relation/downloads)](https://packagist.org/packages/notamedia/yii2-relation) 
[![License](https://poser.pugx.org/notamedia/yii2-relation/license)](https://packagist.org/packages/notamedia/yii2-relation)

Behavior for support relational data management.

- Insert related models from POST array.
- Pre-processing for new models via callback function.
- Delete related models from database which not exist in POST array.
- Skip related models which already exist in database with same attributes.
- Rollback database changes, if relational model save/delete error occurred.
- Support one-to-one and one-to-many relations.

With pre-processing you can set additional logic before create related models.
For example, to add additional columns data to the junction table in a many-to-many relationship.

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
* Put attribute or attribute with callback to relations property of behavior
* All used models need to have only one primary key column

### One-to-one
```php
<?php

...

class News extends ActiveRecord
{
    ...
    
    public function rules()
    {
        return [
            [['file'], 'safe']
        ];
    }
    
    public function getFile()
    {
        return $this->hasOne(News::className(), 
            ['id' => 'file_id']);
    }
    
    public function behaviors()
    {
        return [
            [
                'class' => RelationBehavior::className(),
                'relations' => ['file']
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

class News extends ActiveRecord
{
    ...
    
    public function rules()
    {
        return [
            [['images'], 'safe']
        ];
    }
    
    public function getImages()
    {
        return $this->hasMany(Image::className(), 
            ['news_id' => 'id']);
    }
    
    public function behaviors()
    {
        return [
            [
                'class' => RelationBehavior::className(),
                'relations' => ['images']
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

class News extends ActiveRecord
{
    ...
    
    public function rules()
    {
        return [
            [['categories'], 'safe']
        ];
    }
       
    public function getNewsHasCategories()
    {
        return $this->hasMany(NewsHasCategory::className(), 
            ['news_id' => 'id']);
    }

    public function getCategories()
    {
        return $this->hasMany(Category::className(), 
            ['id' => 'category_id'])
                ->via('newsHasCategories');
    }
    
    public function behaviors()
    {
        return [
            [
                'class' => RelationBehavior::className(),
                'relations' => ['categories']
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

or

```php
<?php

...

class News extends ActiveRecord
{
    ...
    
    public function rules()
    {
        return [
            [['categories', 'categories_type_archive'], 'safe']
        ];
    }

    public function getCategories()
    {
        return $this->hasMany(Category::className(), 
            ['id' => 'category_id'])
                ->viaTable('news_has_categories', ['news_id' => 'id']);
    }
    
    // with onCondition
    public function getCategories_type_archive()
    {
        return $this->hasMany(Category::className(), 
            ['id' => 'category_id'])
            ->viaTable('news_has_categories', ['news_id' => 'id'], function($query) {
                 /** @var ActiveQuery $query */
                 return $query->onCondition(['type' => 'archive']);
            });
    }
    
    public function behaviors()
    {
        return [
            [
                'class' => RelationBehavior::className(),
                'relations' => ['categories', 'categories_type_archive']
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

with sort via callback-function

```php
<?php
...

class News extends ActiveRecord
{
    ...
    
    public function rules()
    {
        return [
            [['categories'], 'safe']
        ];
    }
       
    public function getNewsHasCategories()
    {
        return $this->hasMany(NewsHasCategory::className(), 
            ['news_id' => 'id']);
    }
    
    public function getCategories()
    {
        return $this->hasMany(Category::className(), 
            ['id' => 'category_id'])
                ->via('newsHasCategories');
    }
    
    public function behaviors()
    {
        $postSortIndex = 1;
        
        return [
            [
                'class' => RelationBehavior::className(),
                'relations' => [
                    'categories' => function (NewsHasCategory $model) use (&$postSortIndex) {
                        $model->sort = $postSortIndex++;
    
                        return $model;
                    }
                ]
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