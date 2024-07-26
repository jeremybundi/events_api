<?php
use Phalcon\Mvc\Model;

class Payment extends Model
{
    public $id;
    public $user_id;
    public $total_amount;
    public $payment_method;
    public $mpesa_reference;
    public $payment_status_id;
    public $created_at;
    public $updated_at;

    public function initialize()
    {
        $this->setSource('payment');
        $this->belongsTo('booking_id', 'Booking', 'id');
    }
}
