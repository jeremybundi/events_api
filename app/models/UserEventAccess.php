<?php

use Phalcon\Mvc\Model;

class UserEventAccess extends Model
{
    public $id;

    public $user_id;


    public $event_id;

    public function initialize()
    {
     
        $this->setSource('user_event_access');

        // Define relationships
        $this->belongsTo('user_id', 'Users', 'id', [
            'alias' => 'User'
        ]);
        $this->belongsTo('event_id', 'Events', 'id', [
            'alias' => 'Event'
        ]);
    }


}
