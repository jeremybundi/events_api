<?php

use Phalcon\Mvc\Model;

class TicketCategory extends Model
{
    public $id;
    public $event_id;
    public $category_name;
    public $price;
    public $quantity_available;

    public function initialize()
    {
        $this->belongsTo('event_id', Event::class, 'id', [
            'alias' => 'Event'
        ]);
    }
}
