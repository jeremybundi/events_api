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
//get events with auth
$router->addGet('/event/get', [
    'controller' => 'event',
    'action' => 'list'
]);
//get events publicly
$router->addGet('/event/get/public', [
    'controller' => 'event',
    'action' => 'publiclist'
]);
//get events by id
$router->add(
    '/event/get/{id:[0-9]+}',
    [
        'controller' => 'event',
        'action'     => 'getEventById',
    ]
);
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
//get qr code
$router->addGet('/ticket/qr-code/{uniqueCode}', [
    'controller' => 'transaction',
    'action'     => 'getQrCode'
]);
// updating user roles these two below
$router->addGet('/roles/getRoleByUserId/{userId}', [
    'controller' => 'roles',
    'action' => 'getRoleByUserId',
]);

// Route to update the role of a user by user ID
$router->addPost('/roles/updateRoleByUserId/{userId}', [
    'controller' => 'roles',
    'action' => 'updateRoleByUserId',
]);
// Route to update ticket status
$router->addPost(
    '/update/ticket/status/{id:[0-9]+}',
    [
        'controller' => 'validTickets',
        'action'     => 'validate',
    ]
);
//event access
$router->addPost(
    '/event/access/add',
    [
        'controller' => 'userEventAccess',
        'action'     => 'addUserEventAccess'
    ]
);
// Route for event statistics
$router->addGet(
    '/analysis/event/statistics',
    [
        'controller' => 'analysis',
        'action'     => 'eventStatistics',
    ]
);

// Route for payment summary
$router->addGet(
    '/analysis/payment/summary',
    [
        'controller' => 'analysis',
        'action'     => 'payment',
    ]
);

// Route for getting tickets by duration
$router->addGet(
    '/analysis/get/tickets/byDuration',
    [
        'controller' => 'analysis',
        'action'     => 'getByDuration',
    ]
);
//get events by given date
$router->addGet(
    '/analysis/get/tickets/byDate',
    [
        'controller' => 'analysis',
        'action'     => 'getTicketsByDate',
    ]
);


$router->handle($_SERVER['REQUEST_URI']);
