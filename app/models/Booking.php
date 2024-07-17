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
    public $status;
    public $created_at;
    public $updated_at;

    public function initialize()
    {
        $this->belongsTo('user_id', User::class, 'id', [
            'alias' => 'User'
        ]);
        $this->belongsTo('event_id', Event::class, 'id', [
            'alias' => 'Event'
        ]);
        $this->belongsTo('ticket_category_id', TicketCategory::class, 'id', [
            'alias' => 'TicketCategory'
        ]);
    }
}
