<?php
/**
 * HelpKonnect — API Gateway / Router
 *
 * All requests to /api/v1/* are routed here.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0'); // never show raw errors to users

require __DIR__ . '/config/Database.php';
Database::init();

// Class autoloader for the api/ namespace files
spl_autoload_register(function ($class) {
    $dirs = ['helpers', 'middleware', 'models', 'controllers', 'services', 'routes'];
    foreach ($dirs as $dir) {
        $file = __DIR__ . '/' . $dir . '/' . $class . '.php';
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});

Cors::handle();

try {
    Router::dispatch();
} catch (Throwable $e) {
    Database::logError('Unexpected: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    Response::serverError();
}
