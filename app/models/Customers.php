<?php

use Phalcon\Mvc\Model;

class Customers extends Model
{
    public $id;
    public $email;
    public $phone;
    public $otp_code; // Optional, for storing OTPs
    public $otp_expiry; // Optional, for OTP expiry time
    public $created_at;
    public $updated_at;

    public function initialize()
    {
        // Define the table name if it's different from the model name
        $this->setSource('customers');
        // Define relationships or any additional model configurations here
        // For example:
        $this->hasMany('id', 'booking', 'customer_id');
        $this->hasMany('id', 'ticket_profiles', 'customer_id');
        $this->hasMany('id', 'payment', 'customer_id');
    }
}
