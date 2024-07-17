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

    public function initialize()
    {
        $this->hasMany('id', TicketCategory::class, 'event_id', [
            'alias' => 'TicketCategories'
        ]);
    }
}
