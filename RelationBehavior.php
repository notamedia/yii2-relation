<?php

namespace notamedia\relation;

use yii\base\Behavior;
use yii\base\Exception;
use yii\base\ModelEvent;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;
use Yii;

/**
 * Behavior for support relational data management.
 *
 * - Insert related models from POST array.
 * - Delete related models from database which not exist in POST array
 * - Skip related models which already exist in database with same attributes
 * - Rollback database changes, if relational model save/delete error occurred
 * - Support one-to-one, one-to-many and many-to-many relations
 *
 * This behavior uses getters for relational attribute in owner model, such getters must return `ActiveQuery` object.
 * If you use string values in ON condition in `ActiveQuery` object, then this behavior will throw exception.
 * Also if many-to-many getter use ->viaTable(...) behavior will throw exception, use ->via(...) instead.
 *
 * For support transactions method transactions() in owner model must be defined:
 * ```php
 * <?php
 *
 * public function transactions()
 * {
 *      return [
 *          'default' => static::OP_ALL
 *      ];
 * }
 * ```
 *
 * @property ActiveRecord $owner
 */
class RelationBehavior extends Behavior
{
    /**
     * @var array Relation attributes list.
     */
    public $relationalFields = [];

    /**
     * @var array Relation attributes data.
     */
    protected $relationalData = [];

    /**
     * Process owner-model before save event.
     *
     * @param ModelEvent $event object of event called by model
     * @return bool
     * @throws Exception
     */
    public function beforeSave($event)
    {
        $this->loadData();
        $event->isValid = $this->validateData();
    }

    /**
     * Process owner-model after save event. Save models.
     *
     * @throws Exception
     * @throws \yii\db\Exception
     */
    public function afterSave()
    {
        $this->saveData();
    }

    /**
     * Permission for this behavior to set relational attributes.
     *
     * {@inheritdoc}
     */
    public function canSetProperty($name, $checkVars = true)
    {
        return in_array($name, $this->relationalFields) || parent::canSetProperty($name, $checkVars);
    }

    /**
     * Setter for relational attributes. Called only if attribute is exist in POST array. Method check value format
     * and put it to $this->relationalData array.
     *
     * {@inheritdoc}
     */
    public function __set($name, $value)
    {
        if (in_array($name, $this->relationalFields)) {
            if (!is_array($value) && !empty($value)) {
                $this->owner->addError($name,
                    Yii::$app->getI18n()->format(
                        Yii::t('yii', '{attribute} is invalid.'),
                        ['attribute' => $this->owner->getAttributeLabel($name)],
                        Yii::$app->language
                    )
                );
            } else {
                $this->relationalData[$name] = ['data' => $value];
            }
        } else {
            parent::__set($name, $value);
        }
    }

    /**
     * Load relational data from owner-model getter.
     *
     * - Create related ActiveRecord objects from POST array data.
     * - Load existing related ActiveRecord objects from database.
     * - Check ON condition format.
     * - Get ActiveQuery object from attribute getter method.
     *
     * Fill $this->relationalData array for each relational attribute:
     *
     * ```php
     * $this->relationalData[$attribute] = [
     *      'newModels' => ActiveRecord[],
     *      'oldModels' => ActiveRecord[],
     *      'activeQuery' => ActiveQuery,
     * ];
     * ```
     *
     * @throws Exception
     */
    protected function loadData()
    {
        /** @var ActiveQuery $activeQuery */
        foreach ($this->relationalData as $attribute => &$data) {

            $getter = "get" . ucfirst($attribute);
            $data['activeQuery'] = $activeQuery = $this->owner->$getter();
            $data['newModels'] = [];
            $class = $activeQuery->modelClass;

            if (!ArrayHelper::isAssociative($activeQuery->on) && !empty($activeQuery->on)) {
                Yii::$app->getDb()->getTransaction()->rollBack();
                throw new Exception('ON condition for attribute ' . $attribute . ' must be associative array');
            }

            $params = !ArrayHelper::isAssociative($activeQuery->on) ? [] : $activeQuery->on;

            if ($activeQuery->multiple) {
                if (empty($activeQuery->via)) {
                    // one-to-many
                    foreach ($activeQuery->link as $childAttribute => $parentAttribute) {
                        $params[$childAttribute] = $this->owner->$parentAttribute;
                    }

                    if (!empty($data['data'])) {
                        foreach ($data['data'] as $attributes) {
                            $data['newModels'][] = new $class(array_merge($params, $attributes));
                        }
                    }
                } else {
                    // many-to-many
                    if (!is_object($activeQuery->via[1])) {
                        throw new Exception('via condition for attribute ' . $attribute . ' cannot must be object');
                    }

                    $via = $activeQuery->via[1];
                    $junctionGetter = 'get' . ucfirst($activeQuery->via[0]);
                    $data['junctionModelClass'] = $junctionModelClass = $via->modelClass;
                    $data['junctionTable'] = $junctionModelClass::tableName();
                    list($data['junctionColumn']) = array_keys($via->link);
                    list($data['relatedColumn']) = array_values($activeQuery->link);
                    $relatedColumn = $data['relatedColumn'];

                    if (!empty($data['data'])) {
                        // make sure what all model's ids from POST exists in database
                        $countManyToManyModels = $class::find()->where([$class::primaryKey()[0] => $data['data']])->count();
                        if ($countManyToManyModels != count($data['data'])) {
                            throw new Exception('Related records for attribute ' . $attribute . ' not found');
                        }
                        // create new junction models
                        foreach ($data['data'] as $relatedModelId) {
                            $junctionModel = new $junctionModelClass();
                            $junctionModel->$relatedColumn = $relatedModelId;
                            $data['newModels'][] = $junctionModel;
                        }
                    }

                    $data['oldModels'] = $this->owner->$junctionGetter()->all();
                }

            } elseif (!empty($data['data'])) {
                // one-to-one
                $data['newModels'][] = new $class($data['data']);
            }

            if (empty($activeQuery->via)) {
                $data['oldModels'] = $activeQuery->all();
            }
            unset($data['data']);
        }
    }

    /**
     * Validate relational models, return true only if all models successfully validated.
     *
     * @return bool
     */
    protected function validateData()
    {
        foreach ($this->relationalData as $attribute => &$data) {
            /** @var ActiveRecord $model */
            foreach ($data['newModels'] as &$model) {
                if (!$model->validate()) {
                    $this->owner->addError($attribute, $model->getErrors());

                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Save changed related models.
     *
     * - Delete old related models, which not exist in POST array.
     * - Create new related models, which not exist in database.
     * - Update owner models for one-to-one relation.
     *
     * @throws Exception
     * @throws \Exception
     * @throws \yii\db\Exception
     */
    public function saveData()
    {
        $needSaveOwner = false;

        foreach ($this->relationalData as $attribute => $data) {
            /** @var ActiveRecord $model */
            foreach ($data['newModels'] as $model) {
                if (!$this->isExistingModel($model, $attribute)) {
                    if (!empty($data['activeQuery']->via)) {
                        // only for many-to-many
                        $junctionColumn = $data['junctionColumn'];
                        $model->$junctionColumn = $this->owner->getPrimaryKey();
                    }elseif ($data['activeQuery']->multiple) {
                        // only one-to-many
                        foreach ($data['activeQuery']->link as $childAttribute => $parentAttribute) {
                            $model->$childAttribute = $this->owner->$parentAttribute;
                        }
                    }
                    if (!$model->save()) {
                        Yii::$app->getDb()->getTransaction()->rollBack();
                        throw new Exception('Model ' . $model::className() . ' not saved due to unknown error');
                    }
                }
            }

            foreach ($data['oldModels'] as $model) {
                if ($this->isDeletedModel($model, $attribute)) {
                    if (!$model->delete()) {
                        Yii::$app->getDb()->getTransaction()->rollBack();
                        throw new Exception('Model ' . $model::className() . ' not deleted due to unknown error');
                    }
                }
            }

            if (!$data['activeQuery']->multiple && (count($data['newModels']) == 0 || !$data['newModels'][0]->isNewRecord)) {
                $needSaveOwner = true;
                foreach ($data['activeQuery']->link as $childAttribute => $parentAttribute) {
                    $this->owner->$parentAttribute = count($data['newModels']) ? $data['newModels'][0]->$childAttribute : null;
                }
            }
        }

        if ($needSaveOwner) {
            $model = $this->owner;
            $this->detach();

            if (!$model->save()) {
                Yii::$app->getDb()->getTransaction()->rollBack();
                throw new Exception('Owner-model ' . $model::className() . ' not saved due to unknown error');
            }
        }
    }

    /**
     * Check if model is existing (found in old models).
     *
     * @param ActiveRecord $model
     * @param $attribute
     *
     * @return bool
     */
    protected function isExistingModel($model, $attribute)
    {
        $modelAttributes = $model->attributes;
        unset($modelAttributes[$model->primaryKey()[0]]);

        foreach ($this->relationalData[$attribute]['oldModels'] as $oldModel) {
            /** @var ActiveRecord $oldModel */
            $oldModelAttributes = $oldModel->attributes;
            unset($oldModelAttributes[$oldModel->primaryKey()[0]]);

            if ($oldModelAttributes == $modelAttributes) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if model was deleted (not found in new models).
     *
     * @param ActiveRecord $model
     * @param $attribute
     *
     * @return bool
     */
    protected function isDeletedModel($model, $attribute)
    {
        $modelAttributes = $model->attributes;
        unset($modelAttributes[$model->primaryKey()[0]]);

        foreach ($this->relationalData[$attribute]['newModels'] as $newModel) {
            /** @var ActiveRecord $newModel */
            $newModelAttributes = $newModel->attributes;
            unset($newModelAttributes[$newModel->primaryKey()[0]]);

            if ($newModelAttributes == $modelAttributes) {
                return false;
            }
        }

        return true;
    }

    /**
     * Delete related models. Rollback transaction and throw Exception, if error occurred while deleting.
     *
     * @throws Exception
     * @throws \Exception
     * @throws \yii\db\Exception
     */
    public function afterDelete()
    {
        foreach ($this->relationalFields as $attribute) {
            $getter = 'get' . ucfirst($attribute);
            /** @var ActiveQuery $activeQuery */
            $activeQuery = $this->owner->$getter();
            $models = $activeQuery->all();
            foreach ($models as $model) {
                if (!$model->delete()) {
                    Yii::$app->getDb()->getTransaction()->rollBack();
                    throw new Exception('Model ' . $model::className() . ' not deleted due to unknown error');
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function events()
    {
        return [
            ActiveRecord::EVENT_BEFORE_INSERT => 'beforeSave',
            ActiveRecord::EVENT_BEFORE_UPDATE => 'beforeSave',
            ActiveRecord::EVENT_AFTER_INSERT => 'afterSave',
            ActiveRecord::EVENT_AFTER_UPDATE => 'afterSave',
            ActiveRecord::EVENT_AFTER_DELETE => 'afterDelete',
        ];
    }
}