<?php

$router = $di->getRouter();

//register user
$router->addPost('/user/create', [
    'controller' => 'user',
    'action' => 'create',
]);
//verify user with otp during signin
$router->addPost('/user/verify/otp', [
    'controller' => 'user',
    'action' => 'verifyotp',
]);
//send otp to reset password
$router->addPost('/forgot/password/otp', [
    'controller' => 'forgotpassword',
    'action' => 'sendotp',
]);
//verify otp and reset password
$router->addPost('/reset/password', [
    'controller' => 'forgotpassword',
    'action' => 'verifyOtpAndResetPassword',
]);

//login
$router->addPost('/login', [
    'controller' => 'login',
    'action' => 'login',
]);
 //login with otp 
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
