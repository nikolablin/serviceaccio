<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'language' => 'ru-RU',
    'components' => [
        'formatter' => [
            'class' => yii\i18n\Formatter::class,
            'locale' => 'ru-RU',
            'defaultTimeZone' => 'Asia/Almaty', // если нужно
            'dateFormat' => 'php:d.m.Y',
            'datetimeFormat' => 'php:d.m.Y H:i',
            'timeFormat' => 'php:H:i',
        ],
        'session' => [
            'class' => 'yii\web\Session',
            'savePath' => '@runtime/sessions',
        ],
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => 'AIHPmDsykcFO6CHKNRbApKmutnm0lBIn',
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'user' => [
            'identityClass' => 'app\models\User',
            'enableAutoLogin' => true,
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'viewPath' => '@app/mail',
            // send all mails to a file by default.
            'useFileTransport' => true,
        ],

        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],

                // 1) создание заказа
                [
                    'class' => yii\log\FileTarget::class,
                    'levels' => ['info', 'warning', 'error'],
                    'categories' => ['order.create'],
                    'logFile' => '@runtime/logs/order_create.log',
                    'logVars' => [], // важно: не тащим $_SERVER
                    'prefix' => function () {
                        return date('Y-m-d H:i:s');
                    },
                ],

                // 2) обновление заказа
                [
                    'class' => yii\log\FileTarget::class,
                    'levels' => ['info', 'warning', 'error'],
                    'categories' => ['order.update'],
                    'logFile' => '@runtime/logs/order_update.log',
                    'logVars' => [],
                    'prefix' => function () {
                        return date('Y-m-d H:i:s');
                    },
                ],

                // 3) обновление отгрузки
                [
                    'class' => yii\log\FileTarget::class,
                    'levels' => ['info', 'warning', 'error'],
                    'categories' => ['demand.update'],
                    'logFile' => '@runtime/logs/demand_update.log',
                    'logVars' => [],
                    'prefix' => function () {
                        return date('Y-m-d H:i:s');
                    },
                ],

                // 3) обновление возвратов
                [
                    'class' => yii\log\FileTarget::class,
                    'levels' => ['info', 'warning', 'error'],
                    'categories' => ['salesreturn.update'],
                    'logFile' => '@runtime/logs/salesreturn_update.log',
                    'logVars' => [],
                    'prefix' => function () {
                        return date('Y-m-d H:i:s');
                    },
                ],
            ],
        ],

        'db' => $db,
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
              '<alias:[\w-]+>' => 'site/<alias>',
              'ajax/process' => 'ajax/process',
              'register' => 'site/signup',
              'media/file/<id:\d+>' => 'media/file',
              'media/delete/<id:\d+>' => 'media/delete',
              'POST wolt/get' => 'wolt/get',
              'GET wolt/get' => 'wolt/get',
            ],
        ],
        'dbExternal' => [
            'class' => 'yii\db\Connection',
            'dsn' => 'mysql:host=localhost;dbname=acciosto_db',
            'username' => 'acciosto_user',
            'password' => 'Uj524#b2l',
            'charset' => 'utf8',
        ]
    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;
