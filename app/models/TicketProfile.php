<?php

use Phalcon\Mvc\Model;

class TicketProfile extends Model
{
    public $id;
    public $user_id;
    public $booking_id;
    public $payment_id;
    public $created_at;
    public $qr_code;
    public $unique_code;
    public $updated_at;
    public $payment_status;

    public function initialize()
    {
        $this->setSource('ticket_profiles');

        $this->belongsTo('user_id', 'Users', 'id', [
            'alias' => 'users'
        ]);

        $this->belongsTo('booking_id', 'Bookings', 'id', [
            'alias' => 'booking'
        ]);
        $this->belongsTo('payment_id', 'Payment', 'id', [
            'alias' => 'payment'
        ]);
    }

    public function generateUniqueCode()
    {
        return strtoupper(bin2hex(random_bytes(4)));
    }

    public function generateQrCode($text)
    {
        $qrCode = new \Endroid\QrCode\QrCode($text);
        $writer = new \Endroid\QrCode\Writer\PngWriter();
        $filePath = __DIR__ . '/../qrcodes/' . $text . '.png';
       // $writer->writeFile($qrCode, $filePath);

        return $filePath;
    }
}






















































