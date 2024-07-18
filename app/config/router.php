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
//edit event
$router->addPut('/event/edit/{id}', [
    'controller' => 'event',
    'action' => 'edit'
]);
//book
$router->addPost('/booking/create', [
    'controller' => 'booking',
    'action' => 'create'
]);
//get tickets

$router->addGet('/get/tickets/{userId}', [
    'controller' => 'ticketprofile',
    'action' => 'getTickets'
]);
$router->handle($_SERVER['REQUEST_URI']);
