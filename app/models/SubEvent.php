<?php
use Phalcon\Mvc\Model\Validator\Uniqueness;

class SubEvent extends \Phalcon\Mvc\Model
{
    public $id;
    public $event_id;
    public $name;
    public $date;
    public $start_time;
    public $end_time;
    public $venue;
    public $description;
    public $total_tickets;

    public function initialize()
    {
        $this->setSource("sub_events");

        $this->belongsTo("event_id", "Event", "id");

        $this->hasMany("id", "TicketCategory", "sub_event_id");

        $this->addValidator(new Uniqueness(
            [
                "field" => "name",
                "message" => "Sub-event name must be unique"
            ]
        ));
    }
}
