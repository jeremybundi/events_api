<?php

use Phalcon\Mvc\Model;

class Card extends Model
{
    public $id;
    public $card_number;
    public $card_type;
    public $balance;
    public $status;
    public $barcode;
    public $created_at;
    public $updated_at;

    public function initialize()
    {
        $this->hasMany('id', 'Payment', 'card_id');
    }
}
