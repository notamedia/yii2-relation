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
