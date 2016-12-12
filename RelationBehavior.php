<?php
/**
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace notamedia\relation;

use yii\base\Behavior;
use yii\base\ModelEvent;
use yii\db\ActiveQuery;
use yii\db\ActiveQueryInterface;
use yii\db\ActiveRecord;
use yii\db\Query;
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
     * @var bool Indices finish of all saving operations
     */
    protected $relationalFinished = false;

    /**
     * @var array Relation attributes data.
     */
    protected $relationalData = [];

    /**
     * Process owner-model before save event.
     *
     * @param ModelEvent $event object of event called by model
     */
    public function beforeSave($event)
    {
        $this->loadData();
        $event->isValid = $this->validateData();
    }

    /**
     * Return relation data of attribute
     * @param $attribute string
     * @return mixed|null
     */
    public function getRelationData($attribute)
    {
        return isset($this->relationalData[$attribute]) ? $this->relationalData[$attribute]['data'] : null;
    }


    /**
     * Return saving state of relation data finished or not. It's will finished after all relations models will saved.
     * @return bool
     */
    public function isRelationalFinished()
    {
        return $this->relationalFinished;
    }

    /**
     * Process owner-model after save event. Save models.
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
     * @throws RelationException
     */
    protected function loadData()
    {
        /** @var ActiveQuery $activeQuery */
        foreach ($this->relationalData as $attribute => &$data) {

            $getter = 'get' . ucfirst($attribute);
            $data['activeQuery'] = $activeQuery = $this->owner->$getter();
            $data['newModels'] = [];
            $data['oldModels'] = [];
            $data['newRows'] = [];
            $data['oldRows'] = [];

            if (!$this->validateOnCondition($activeQuery)) {
                Yii::$app->getDb()->getTransaction()->rollBack();
                throw new RelationException('ON condition for attribute ' . $attribute . ' must be associative array');
            }

            if ($activeQuery->multiple) {
                if (empty($activeQuery->via)) { // one-to-many
                    $this->loadModelsOneToMany($attribute);
                } else { // many-to-many
                    if ($activeQuery->via instanceof ActiveQueryInterface) { // viaTable
                        $this->loadModelsManyToManyViaTable($attribute);
                    } else { // via
                        $this->loadModelsManyToManyVia($attribute);
                    }
                }

            } elseif (!empty($data['data'])) {
                // one-to-one
                $this->loadModelsOneToOne($attribute);
            }

            if (empty($activeQuery->via)) {
                $data['oldModels'] = $activeQuery->all();
            }
            unset($data['data']);

            foreach ($data['newModels'] as $i => $model) {
                $data['newModels'][$i] = $this->replaceExistingModel($model, $attribute);
            }
        }
    }

    /**
     * Validate relational models, return true only if all models successfully validated. Skip errors for foreign
     * columns.
     *
     * @return bool
     */
    protected function validateData()
    {
        foreach ($this->relationalData as $attribute => &$data) {
            /** @var ActiveRecord $model */
            /** @var ActiveQuery $activeQuery */
            $activeQuery = $data['activeQuery'];
            foreach ($data['newModels'] as &$model) {
                if (!$model->validate()) {
                    $_errors = $model->getErrors();
                    $errors = [];

                    foreach ($_errors as $relatedAttribute => $error) {
                        if (!$activeQuery->multiple || !isset($activeQuery->link[$relatedAttribute])) {
                            $errors[$relatedAttribute] = $error;
                        }
                    }

                    if (count($errors)) {
                        $this->owner->addError($attribute, $errors);

                        return false;
                    }
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
     */
    public function saveData()
    {
        $needSaveOwner = false;

        foreach ($this->relationalData as $attribute => $data) {

            // save models
            $this->saveModels($attribute);
            // delete models
            $this->deleteModels($attribute);

            if (!$data['activeQuery']->multiple && (count($data['newModels']) == 0 || !$data['newModels'][0]->isNewRecord)) {
                $needSaveOwner = true;
                foreach ($data['activeQuery']->link as $childAttribute => $parentAttribute) {
                    $this->owner->$parentAttribute = count($data['newModels']) ? $data['newModels'][0]->$childAttribute : null;
                }
            }
        }

        $this->relationalFinished = true;

        if ($needSaveOwner) {
            $model = $this->owner;
            $this->detach();
            if (!$model->save()) {
                Yii::$app->getDb()->getTransaction()->rollBack();
                throw new RelationException('Owner-model ' . $model::className() . ' not saved due to unknown error');
            }
        }
    }

    /**
     * Execute callback for each relation
     *
     * - if error occurred throws exception
     *
     * @param array $relations
     * @param callable $callback
     * @throws RelationException
     */
    protected function relationsMap($relations, $callback)
    {
        try {
            if (is_callable($callback)) {
                array_map($callback, $relations);
            }
        } catch (\Exception $e) {
            Yii::$app->getDb()->getTransaction()->rollBack();
            throw new RelationException('Owner-model not saved due to unknown error');
        }
    }

    /**
     * Return existing model if it found in old models
     *
     * @param ActiveRecord $model
     * @param $attribute
     *
     * @return ActiveRecord
     */
    protected function replaceExistingModel($model, $attribute)
    {
        $modelAttributes = $model->attributes;
        unset($modelAttributes[$model->primaryKey()[0]]);

        foreach ($this->relationalData[$attribute]['oldModels'] as $oldModel) {
            /** @var ActiveRecord $oldModel */
            $oldModelAttributes = $oldModel->attributes;
            unset($oldModelAttributes[$oldModel->primaryKey()[0]]);

            if ($oldModelAttributes == $modelAttributes) {
                return $oldModel;
            }
        }

        return $model;
    }

    /**
     * Check existing row if it found in old rows
     *
     * @param $row
     * @param $attribute
     * @return mixed
     */
    protected function isExistingRow($row, $attribute)
    {
        $rowAttributes = $row;
        unset($rowAttributes[$this->relationalData[$attribute]['junctionColumn']]);

        foreach ($this->relationalData[$attribute]['oldRows'] as $oldRow) {
            $oldModelAttributes = $oldRow;
            unset($oldModelAttributes[$this->relationalData[$attribute]['junctionColumn']]);
            if ($oldModelAttributes == $rowAttributes) {
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
     * Check if row was deleted (not found in new rows).
     *
     * @param $row
     * @param $attribute
     * @return bool
     */
    protected function isDeletedRow($row, $attribute)
    {
        $rowAttribute = $row;
        unset($rowAttribute[$this->relationalData[$attribute]['junctionColumn']]);

        foreach ($this->relationalData[$attribute]['newRows'] as $newRow) {
            $newRowAttributes = $newRow;
            unset($newRowAttributes[$this->relationalData[$attribute]['junctionColumn']]);
            if ($newRowAttributes == $rowAttribute) {
                return false;
            }
        }

        return true;
    }

    /**
     * Delete related models. Rollback transaction and throw RelationException, if error occurred while deleting.
     */
    public function afterDelete()
    {
        foreach ($this->relationalFields as $attribute) {
            $getter = 'get' . ucfirst($attribute);
            /** @var ActiveQuery $activeQuery */
            $activeQuery = $this->owner->$getter();

            $models = [];
            if (empty($activeQuery->via)) {
                $models = $activeQuery->all();
            } else {
                if ($activeQuery->via instanceof ActiveQueryInterface) { // viaTable

                    $junctionTable = $activeQuery->via->from[0];
                    list($junctionColumn) = array_keys($activeQuery->via->link);
                    list($relatedColumn) = array_values($activeQuery->link);

                    $rows = (new Query())
                        ->from($junctionTable)
                        ->select([
                            $junctionColumn,
                            $relatedColumn
                        ])
                        ->where([
                            $junctionColumn => $this->owner->getPrimaryKey(),
                        ])->all();

                    $this->relationsMap($rows, function($row) use ($junctionTable) {
                        Yii::$app->db->createCommand()
                            ->delete($junctionTable, $row)
                            ->execute();
                    });

                } else { // via
                    $junctionGetter = 'get' . ucfirst($activeQuery->via[0]);
                    $models = $this->owner->$junctionGetter()->all();
                }
            }

            foreach ($models as $model) {
                if (!$model->delete()) {
                    Yii::$app->getDb()->getTransaction()->rollBack();
                    throw new RelationException('Model ' . $model::className() . ' not deleted due to unknown error');
                }
            }
        }
    }

    /**
     * Validate ON condition in ActiveQuery
     *
     * @param ActiveQuery $activeQuery
     * @return bool
     */
    protected function validateOnCondition($activeQuery)
    {
        if (
            !ArrayHelper::isAssociative($activeQuery->on) &&
            !empty($activeQuery->on)
        ) {
            return false;
        }

        if (
            $activeQuery->multiple &&
            !empty($activeQuery->via) &&
            is_array($activeQuery->via) &&
            is_object($activeQuery->via[1]) &&
            !ArrayHelper::isAssociative($activeQuery->via[1]->on) &&
            !empty($activeQuery->via[1]->on)
        ) {
            return false;
        }

        return true;
    }

    /**
     * Load new model from POST for one-to-one relation
     *
     * @param $attribute
     */
    protected function loadModelsOneToOne($attribute)
    {
        $data = $this->relationalData[$attribute];

        $activeQuery = $data['activeQuery'];
        $class = $activeQuery->modelClass;

        $data['newModels'][] = new $class($data['data']);

        $this->relationalData[$attribute] = $data;
    }

    /**
     * Load new models from POST for one-to-many relation
     *
     * @param $attribute
     */
    protected function loadModelsOneToMany($attribute)
    {
        $data = $this->relationalData[$attribute];
        
        $activeQuery = $data['activeQuery'];
        $class = $activeQuery->modelClass;

        // default query conditions
        $params = !ArrayHelper::isAssociative($activeQuery->on) ? [] : $activeQuery->on;
        // one-to-many
        foreach ($activeQuery->link as $childAttribute => $parentAttribute) {
            $params[$childAttribute] = $this->owner->$parentAttribute;
        }

        if (!empty($data['data'])) {
            foreach ($data['data'] as $attributes) {
                $data['newModels'][] = new $class(
                    array_merge(
                        $params,
                        ArrayHelper::isAssociative($attributes) ? $attributes : []
                    )
                );
            }
        }

        $this->relationalData[$attribute] = $data;
    }

    /**
     * Load new models from POST for many-to-many relation with viaTable
     *
     * @param $attribute
     * @throws RelationException
     */
    protected function loadModelsManyToManyViaTable($attribute)
    {
        $data = $this->relationalData[$attribute];

        $activeQuery = $data['activeQuery'];
        /** @var ActiveRecord $class */
        $class = $activeQuery->modelClass;

        $via = $activeQuery->via;
        $data['junctionTable'] = $via->from[0];

        list($data['junctionColumn']) = array_keys($via->link);
        list($data['relatedColumn']) = array_values($activeQuery->link);
        $junctionColumn = $data['junctionColumn'];
        $relatedColumn = $data['relatedColumn'];

        if (!empty($data['data'])) {
            // make sure what all row's ids from POST exists in database
            $countManyToManyModels = $class::find()->where([$class::primaryKey()[0] => $data['data']])->count();
            if ($countManyToManyModels != count($data['data'])) {
                throw new RelationException('Related records for attribute ' . $attribute . ' not found');
            }
            // create new junction rows
            foreach ($data['data'] as $relatedModelId) {
                $junctionModel = array_merge(!ArrayHelper::isAssociative($via->on) ? [] : $via->on,
                    [$junctionColumn => $this->owner->getPrimaryKey()]);
                $junctionModel[$relatedColumn] = $relatedModelId;
                $data['newRows'][] = $junctionModel;
            }
        }

        if (!empty($this->owner->getPrimaryKey())) {
            $data['oldRows'] = (new Query())
                ->from($data['junctionTable'])
                ->select([
                    $junctionColumn,
                    $relatedColumn
                ])
                ->where([
                    $junctionColumn => $this->owner->getPrimaryKey(),
                ])->all();
        }

        $this->relationalData[$attribute] = $data;
    }

    /**
     * Load new models from POST for many-to-many relation with via
     *
     * @param $attribute
     * @throws RelationException
     */
    protected function loadModelsManyToManyVia($attribute)
    {
        $data = $this->relationalData[$attribute];

        $activeQuery = $data['activeQuery'];
        /** @var ActiveRecord $class */
        $class = $activeQuery->modelClass;

        if (!is_object($activeQuery->via[1])) {
            throw new RelationException('via condition for attribute ' . $attribute . ' cannot must be object');
        }

        $via = $activeQuery->via[1];
        $junctionGetter = 'get' . ucfirst($activeQuery->via[0]);
        /** @var ActiveRecord $junctionModelClass */
        $data['junctionModelClass'] = $junctionModelClass = $via->modelClass;
        $data['junctionTable'] = $junctionModelClass::tableName();

        list($data['junctionColumn']) = array_keys($via->link);
        list($data['relatedColumn']) = array_values($activeQuery->link);
        $junctionColumn = $data['junctionColumn'];
        $relatedColumn = $data['relatedColumn'];

        if (!empty($data['data'])) {
            // make sure what all model's ids from POST exists in database
            $countManyToManyModels = $class::find()->where([$class::primaryKey()[0] => $data['data']])->count();
            if ($countManyToManyModels != count($data['data'])) {
                throw new RelationException('Related records for attribute ' . $attribute . ' not found');
            }
            // create new junction models
            foreach ($data['data'] as $relatedModelId) {
                $junctionModel = new $junctionModelClass(array_merge(!ArrayHelper::isAssociative($via->on) ? [] : $via->on,
                    [$junctionColumn => $this->owner->getPrimaryKey()]));
                $junctionModel->$relatedColumn = $relatedModelId;
                $data['newModels'][] = $junctionModel;
            }
        }

        $data['oldModels'] = $this->owner->$junctionGetter()->all();

        $this->relationalData[$attribute] = $data;
    }

    /**
     * Save all new models for attribute
     *
     * @param $attribute
     * @throws RelationException
     */
    protected function saveModels($attribute)
    {
        $data = $this->relationalData[$attribute];

        /** @var ActiveRecord $model */
        foreach ($data['newModels'] as $model) {
            if ($model->isNewRecord) {
                if (!empty($data['activeQuery']->via)) {
                    // only for many-to-many
                    $junctionColumn = $data['junctionColumn'];
                    $model->$junctionColumn = $this->owner->getPrimaryKey();
                } elseif ($data['activeQuery']->multiple) {
                    // only one-to-many
                    foreach ($data['activeQuery']->link as $childAttribute => $parentAttribute) {
                        $model->$childAttribute = $this->owner->$parentAttribute;
                    }
                }
                if (!$model->save()) {
                    Yii::$app->getDb()->getTransaction()->rollBack();
                    throw new RelationException('Model ' . $model::className() . ' not saved due to unknown error');
                }
            }
        }

        // only for many-to-many
        $this->relationsMap($data['newRows'], function($row) use ($attribute, $data) {
            $junctionColumn = $data['junctionColumn'];
            $row[$junctionColumn] = $this->owner->getPrimaryKey();
            if (!$this->isExistingRow($row, $attribute)) {
                Yii::$app->db->createCommand()
                    ->insert($data['junctionTable'], $row)
                    ->execute();
            }
        });
    }

    /**
     * Delete all old models for attribute if it needed
     *
     * @param $attribute
     * @throws RelationException
     */
    protected function deleteModels($attribute)
    {
        $data = $this->relationalData[$attribute];

        /** @var ActiveRecord $model */
        foreach ($data['oldModels'] as $model) {
            if ($this->isDeletedModel($model, $attribute)) {
                if (!$model->delete()) {
                    Yii::$app->getDb()->getTransaction()->rollBack();
                    throw new RelationException('Model ' . $model::className() . ' not deleted due to unknown error');
                }
            }
        }

        $this->relationsMap($data['oldRows'], function($row) use ($attribute, $data) {
            if ($this->isDeletedRow($row, $attribute)) {
                Yii::$app->db->createCommand()
                    ->delete($data['junctionTable'], $row)
                    ->execute();
            }
        });
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