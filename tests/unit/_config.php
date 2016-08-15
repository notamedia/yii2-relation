<?php
$params = array_merge(
    require(__DIR__ . '/../../../../../common/config/params.php'),
    require(__DIR__ . '/../../../../../common/config/params-local.php')
);

return [
    'id' => 'app-tests',
    'class' => 'yii\console\Application',
    'basePath' => \Yii::getAlias('@tests'),
    'runtimePath' => \Yii::getAlias('@tests/_output'),
    'bootstrap' => [],
    'params' => $params,
    'components' => [
        'db' => [
            'class' => '\yii\db\Connection',
            'dsn' => 'sqlite:'.\Yii::getAlias('@tests/_output/temp.db'),
            'username' => '',
            'password' => '',
        ]
    ]
];