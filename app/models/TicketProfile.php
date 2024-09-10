<?php

use Phalcon\Mvc\Model;

class TicketProfile extends Model
{
    public $id;
    public $customer_id; // Updated from user_id
    public $booking_id;
    public $payment_id;
    public $created_at;
    public $qr_code;
    public $unique_code;
    public $updated_at;
    public $valid_status;
    public $category_id;
    public $redeemed_ticket;

    public function initialize()
    {
        $this->setSource('ticket_profiles');

        $this->belongsTo('customer_id', 'Customers', 'id', [
            'alias' => 'customer'
        ]);

        $this->belongsTo('booking_id', 'Booking', 'id', [
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
        $fileName = $text . '.png';
        $directory = '/Applications/XAMPP/xamppfiles/htdocs/projects/Events/public/qrcodes/';
        $filePath = $directory . $fileName;
        
        // Create the directory if it does not exist
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0777, true)) {
                throw new \Exception('Failed to create QR code directory');
            }
        }
        
        // Create a PNG writer instance
        $writer = new \Endroid\QrCode\Writer\PngWriter();
        
        // Save the QR code image to the file system
        try {
            $writer->write($qrCode)->saveToFile($filePath);
        } catch (\Exception $e) {
            error_log('Failed to save QR code image: ' . $e->getMessage());
            throw new \Exception('Failed to save QR code image');
        }
        
        // Return the full URL to the QR code
        // Adjust this URL to match your frontend's URL structure
        return 'http://localhost:8000/qrcodes/' . $fileName;
    }
    
    
}
