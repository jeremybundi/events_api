<?php

use Phalcon\Mvc\Model;

class TicketProfile extends Model
{
    public $id;
    public $user_id;
    public $booking_id;
    public $created_at;
    public $updated_at;

    public function initialize()
    {
        $this->setSource('ticket_profiles');

        $this->belongsTo('user_id', 'Users', 'id', [
            'alias' => 'users'
        ]);

        $this->belongsTo('booking_id', 'Bookings', 'id', [
            'alias' => 'booking'
        ]);
    }
}
