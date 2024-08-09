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
//get events
$router->addGet('/event/get', [
    'controller' => 'event',
    'action' => 'list'
]);
//book
$router->addPost('/booking/create', [
    'controller' => 'booking',
    'action' => 'create'
]);
//get tickets

$router->addGet('/get/tickets', [
    'controller' => 'ticketprofile',
    'action' => 'getTickets'
]);
// get paid tickets
$router->addGet('/tickets/paid', [
    'controller' => 'TicketProfile',
    'action' => 'getPaidTickets'
]);
//pay mpesa
$router->add(
    '/transaction/pay/{id}',
    [
        'controller' => 'transaction',
        'action'     => 'pay',
    ]
);
//callback
$router->addPost('/transaction/callback', [
    'controller' => 'transaction',
    'action' => 'callback'
]);
/*barcode
$router->add(
    '/transaction/barcode/{id:[0-9]+}',
    [
        'controller' => 'transaction',
        'action'     => 'pay',
    ]
);*/
$router->handle($_SERVER['REQUEST_URI']);
