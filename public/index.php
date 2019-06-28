<?php
date_default_timezone_set('Asia/Shanghai');
header("Content-Type: text/html;charset=utf-8");

use Phalcon\Loader;
use Phalcon\Mvc\View;
use Phalcon\Mvc\Application;
use Phalcon\DI\FactoryDefault;
use Phalcon\Db\Adapter\Pdo\Mysql as DbAdapter;
use Phalcon\Config\Adapter\Ini as ConfigIni;
use Phalcon\Http\Response\Cookies;

ini_set("display_errors", "On");
error_reporting(E_ALL ^ E_DEPRECATED);
try {
    define('APP_PATH', realpath('..') . '/app/');
    define('PUL_PATH', realpath('..') . '/public/');
    define('MY_DOMAIN', 'http://ht.dhgate.com');
    $config_path = 'config/config.ini';
    // Read the configuration
    $config = new ConfigIni(APP_PATH . $config_path);
    // Register an autoloader
    $loader = new Loader();
    $loader->registerDirs(array(
        $config->phalcon->controllersDir,
        $config->phalcon->modelsDir
    ));
    $loader->registerNamespaces(
            array(
                'Lib' => APP_PATH . 'lib',
                'Lib\Vendor' => APP_PATH . 'lib/Vendor',
                'Dh\Controllers' => APP_PATH . 'controllers',
            )
    );
    $loader->register();
    // Create a DI
    $di = new FactoryDefault();
    $di->set('router', function () {
        return require APP_PATH . '/config/routes.php';
    }, true);
    $di->set('db', function () use ($config) {
        $db_config = array(
            'host' => '127.0.0.1',
            'username' => 'root',
            'password' => 'root',
            'dbname' => 'dh',
            'charset' => 'utf8mb4'
        );
        return new DbAdapter($db_config);
    });
    $di->set('config', function() use ($config) {
        return $config;
    });

    // Setup the view component
    $di->set('view', function () use ($config) {
        $view = new View();
        $view->setViewsDir($config->phalcon->viewsDir);
        return $view;
    });
    //session	
    $di->set('session', function() {
        $session = new \Phalcon\Session\Adapter\Files;

        $session->start();

        return $session;
    });
    $di->set('cookies', function () {
        $cookies = new Cookies();

        return $cookies;
    });
    // Handle the request
    $application = new Application($di);

    echo $application->handle()->getContent();
} catch (\Exception $e) {
    echo "PhalconException: ", $e->getMessage();
}