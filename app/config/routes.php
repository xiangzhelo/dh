<?php

$router = new Phalcon\Mvc\Router(false);

$router->add('/:controller/:action/:params', array(
    'namespace' => 'Dh\Controllers',
    'controller' => 1,
    'action' => 2,
    'params' => 3,
));
$router->add('/:controller', array(
    'namespace' => 'Dh\Controllers',
    'controller' => 1
));
$router->add('/', array(
	'namespace' => 'Dh\Controllers',
	'controller' => 'index',
	'action' => 'index'
));
$router->add('/:controller', array(
	'namespace' => 'Dh\Controllers',
	'controller' => 1,
	'action' => 'index'
));
$router->add('/:controller/', array(
	'namespace' => 'Dh\Controllers',
	'controller' => 1,
	'action' => 'index'
));

$router->add('/:controller/:action', array(
	'namespace' => 'Dh\Controllers',
	'controller' => 1,
	'action' => 2
));
$router->add('/:controller/:action/', array(
	'namespace' => 'Dh\Controllers',
	'controller' => 1,
	'action' => 2
));

return $router;