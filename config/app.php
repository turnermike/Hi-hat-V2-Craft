<?php

/**
 * Yii Application Config
 *
 * Edit this file at your own risk!
 *
 * The array returned by this file will get merged with
 * vendor/craftcms/cms/src/config/app.php and app.[web|console].php, when
 * Craft's bootstrap script is defining the configuration for the entire
 * application.
 *
 * You can define custom modules and system components, and even override the
 * built-in system components.
 *
 * If you want to modify the application config for *only* web requests or
 * *only* console requests, create an app.web.php or app.console.php file in
 * your config/ folder, alongside this one.
 *
 * Read more about application configuration:
 * @link https://craftcms.com/docs/5.x/reference/config/app.html
 */

use craft\helpers\App;

$modulesBasePath = dirname(__DIR__) . '/modules';
spl_autoload_register(function (string $class) use ($modulesBasePath): void {
    $prefix = 'modules\\';
    if (str_starts_with($class, $prefix)) {
        $relative = substr($class, strlen($prefix));
        $path = $modulesBasePath . '/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($path)) {
            require_once $path;
        }
    }
});

return [
    'id' => App::env('CRAFT_APP_ID') ?: 'CraftCMS',
    'bootstrap' => ['contactform'],
    'modules' => [
        'contactform' => [
            'class' => modules\contactform\ContactFormModule::class,
        ],
    ],
    'components' => [
        'mailer' => [
            'class' => craft\mail\Mailer::class,
            'messageClass' => craft\mail\Message::class,
            'from' => [
                App::parseEnv(App::env('CRAFT_SMTP_FROM_EMAIL') ?: App::env('CRAFT_SMTP_USERNAME') ?: 'no-reply@localhost')
                => App::parseEnv(App::env('CRAFT_SMTP_FROM_NAME') ?: 'Website'),
            ],
            'transport' => [
                'scheme' => App::env('CRAFT_SMTP_ENCRYPTION') === 'ssl' ? 'smtps' : 'smtp',
                'host' => App::env('CRAFT_SMTP_HOST') ?: App::env('MAILPIT_SMTP_HOSTNAME') ?: '127.0.0.1',
                'port' => App::env('CRAFT_SMTP_PORT') ?: App::env('MAILPIT_SMTP_PORT') ?: 1025,
                'username' => App::env('CRAFT_SMTP_USERNAME'),
                'password' => App::env('CRAFT_SMTP_PASSWORD'),
            ],
        ],
    ],
];
