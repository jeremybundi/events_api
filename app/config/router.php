<?php

$router = $di->getRouter();

// Define your routes here
$router->addPost('/user/create', [
    'controller' => 'user',
    'action' => 'create',
]);
$router->addPost('/user/login', [
    'controller' => 'login',
    'action' => 'login',
]);
$router->handle($_SERVER['REQUEST_URI']);
