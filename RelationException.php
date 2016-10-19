<?php

namespace notamedia\relation;

use yii\base\Exception;

/**
 * Exception for throw when relation errors occured
 */
class RelationException extends Exception
{
    /**
     * @return string the user-friendly name of this exception
     */
    public function getName()
    {
        return 'Relation exception';
    }

    /**
     * Returns the fully qualified name of this class.
     * @return string the fully qualified name of this class.
     */
    public static function className()
    {
        return get_called_class();
    }
}