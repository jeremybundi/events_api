<?php

use Phalcon\Mvc\Model;

class TicketCategory extends Model
{
    public $category_id;
    public $event_id;
    public $category_name;
    public $price;
    public $quantity_available;
    public $created_at;
    public $updated_at;
    public $validated_tickets;

    public function initialize()
    {
        $this->setSource('ticket_category');

        $this->belongsTo('event_id', 'Event', 'id', [
            'alias' => 'event'
        ]);

        $this->hasMany('id', 'Booking', 'ticket_category_id', [
            'alias' => 'booking'
        ]);
    }
}