<?php

use Phalcon\Mvc\Model;

class Booking extends Model
{
    public $id;
    public $user_id;
    public $event_id;
    public $ticket_category_id;
    public $quantity;
    public $booking_date;
    public $created_at;
    public $updated_at;

    public function initialize()
    {
        $this->setSource('booking');

        $this->belongsTo('user_id', 'Users', 'id', [
            'alias' => 'users'
        ]);

        $this->belongsTo('event_id', 'Event', 'id', [
            'alias' => 'event'
        ]);

        $this->belongsTo('ticket_category_id', 'TicketCategory', 'id', [
            'alias' => 'ticketCategory'
        ]);

    }
}
