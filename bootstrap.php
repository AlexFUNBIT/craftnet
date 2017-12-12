<?php
/**
 * Craft web bootstrap file
 */

switch($_SERVER['HTTP_HOST']) {
    case 'api.craftcms.com':
    case 'api.craftcms.test':
        define('CRAFT_SITE', 'api');
        break;
    case 'composer.craftcms.com':
    case 'composer.craftcms.test':
        define('CRAFT_SITE', 'composer');
        break;
    case 'id.craftcms.com':
    case 'id.craftcms.test':
        define('CRAFT_SITE', 'craftId');
        break;
    case 'plugins.craftcms.com':
    case 'plugins.craftcms.test':
        define('CRAFT_SITE', 'plugins');
        break;
}

define('CRAFT_BASE_PATH', __DIR__);
define('CRAFT_VENDOR_PATH', CRAFT_BASE_PATH.'/vendor');

// Composer autoloader
require_once CRAFT_VENDOR_PATH.'/autoload.php';

// dotenv
if (file_exists(CRAFT_BASE_PATH.'/.env')) {
    $dotenv = new Dotenv\Dotenv(CRAFT_BASE_PATH);
    $dotenv->load();
}

if ($storagePath = getenv('CRAFT_STORAGE_PATH')) {
    define('CRAFT_STORAGE_PATH', $storagePath);
}
if ($keyPath = getenv('LICENSE_KEY_PATH')) {
    define('CRAFT_LICENSE_KEY_PATH', $keyPath);
}

define('CRAFT_ENVIRONMENT', getenv('CRAFT_ENV') ?: 'prod');

return require CRAFT_VENDOR_PATH.'/craftcms/cms/bootstrap/web.php';
