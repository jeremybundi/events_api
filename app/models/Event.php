<?php

use Phalcon\Mvc\Model;

class Event extends Model
{
    public $id;
    public $name;
    public $date;
    public $start_time;
    public $end_time;
    public $venue;
    public $description;
    public $total_tickets;
    public $created_at;
    public $updated_at;

    public function initialize()
    {
        $this->setSource('event');
        
        $this->hasMany('id', 'Booking', 'event_id', [
            'alias' => 'booking'
        ]);

        $this->hasMany('id', 'TicketCategory', 'event_id', [
            'alias' => 'ticketCategory'
        ]);
    }
}
