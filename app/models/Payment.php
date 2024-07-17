<?php

namespace App\Models;

use Phalcon\Mvc\Model;

class Payment extends Model
{
    
    public $id;

    public $user_id;


    public $booking_id;

  
    public $amount;

  
    public $payment_method;

   
    public $status;

    public $mpesa_reference;

    public function initialize()
    {
        $this->belongsTo('user_id', 'Users', 'id');
        $this->belongsTo('booking_id', 'Booking', 'id');
    }
}
