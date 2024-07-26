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
 //verify with otp
 $router->addPost('/verify/otp', [
    'controller' => 'login',
    'action' => 'verifyOtp',
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
// get paid tickets
$router->addGet('/tickets/paid/{userId}', [
    'controller' => 'TicketProfile',
    'action' => 'getPaidTickets'
]);
//pay
$router->add(
    '/transaction/pay/{id}',
    [
        'controller' => 'transaction',
        'action'     => 'pay',
    ]
);
//callback

$router->addGet('/card/payment/{paymentId}', [
    'controller' => 'payment',
    'action' => 'mpesaCallback'
]);
$router->handle($_SERVER['REQUEST_URI']);
