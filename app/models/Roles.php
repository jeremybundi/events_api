<?php

use Phalcon\Mvc\Model;

class Roles extends Model
{
    public $id;
    public $role_name;

    public function initialize()
    {
        $this->setSource('roles');
    }
}
