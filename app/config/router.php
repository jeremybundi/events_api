<?php

$router = $di->getRouter();

// Define your routes here
//register user
$router->addPost('/user/create', [
    'controller' => 'user',
    'action' => 'create',
]);
//login
$router->addPost('/user/login', [
    'controller' => 'login',
    'action' => 'login',
]);
//add event
$router->addPost('/event/add', [
    'controller' => 'event',
    'action' => 'add'
]);
$router->handle($_SERVER['REQUEST_URI']);
