<?php
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
    public $created_at;
    public $updated_at;

    public function initialize()
    {
        $this->setSource('payment');
    }
}
