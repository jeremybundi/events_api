<?php

use Phalcon\Mvc\Model;
use Phalcon\Validation;
use Phalcon\Validation\Validator\PresenceOf;
use Phalcon\Validation\Validator\Email;
use Phalcon\Validation\Validator\Uniqueness;
use Phalcon\Validation\Validator\Regex;

class Users extends Model
{
    public $id;
    public $first_name;
    public $second_name;
    public $username;
    public $email;
    public $password;
    public $phone;
    public $role_id; 
    public $otp;
    public $otp_expires_at;

    public function initialize()
    {
        $this->setSource('Users');

        $this->belongsTo(
            'role_id',
            'Roles',
            'id',
            [
                'alias' => 'role',
            ]
        );
        $this->hasMany('id', 'TicketProfile', 'user_id', [
            'alias' => 'ticketProfiles'
        ]);

        $this->hasMany('id', 'Booking', 'user_id', [
            'alias' => 'booking'
        ]);
    }

    public function getRoleName()
    {
        $role = $this->getRelated('role');
        return $role ? $role->role_name : null;
    }

    public function validation()
    {
        $validator = new Validation();

        $validator->add(
            'first_name',
            new PresenceOf([
                'message' => 'The first name is required',
            ])
        );

        $validator->add(
            'second_name',
            new PresenceOf([
                'message' => 'The second name is required',
            ])
        );

        $validator->add(
            'username',
            new PresenceOf([
                'message' => 'The username is required',
            ])
        );

        $validator->add(
            'username',
            new Uniqueness([
                'message' => 'The username must be unique',
            ])
        );

        $validator->add(
            'email',
            new PresenceOf([
                'message' => 'The email is required',
            ])
        );

        $validator->add(
            'email',
            new Email([
                'message' => 'The email is not valid',
            ])
        );

        $validator->add(
            'email',
            new Uniqueness([
                'message' => 'The email must be unique',
            ])
        );

        $validator->add(
            'password',
            new PresenceOf([
                'message' => 'The password is required',
            ])
        );

        $validator->add(
            'phone',
            new PresenceOf([
                'message' => 'The phone number is required',
            ])
        );

        $validator->add(
            'phone',
            new Uniqueness([
                'message' => 'The phone number must be unique',
            ])
        );

        $validator->add(
            'phone',
            new Regex([
                'pattern' => '/^\+?\d{10,20}$/',
                'message' => 'The phone number is not valid',
            ])
        );

        return $this->validate($validator);
    }
}
