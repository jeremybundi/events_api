<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class CustomerTicketsController extends Controller
{
    private function getCustomerIdFromToken()
    {
        $authHeader = $this->request->getHeader('Authorization');
        if (!$authHeader) {
            return null;
        }

        // Extract token from the header
        $token = str_replace('Bearer ', '', $authHeader);

        try {
            // Load the secret key from the config
            $secretKey = $this->config->jwt->secret_key;

            // Decode the JWT token using the secret key
            $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));
            return $decoded->sub; // Extract customer_id from the sub claim
        } catch (Exception $e) {
            // Handle token decoding error
            return null;
        }
    }

    public function getTicketsAction()
    {
        $response = new Response();
        $customerId = $this->getCustomerIdFromToken();

        if (!$customerId) {
            return $response->setJsonContent([
                'status' => 'error',
                'message' => 'Invalid or missing JWT token'
            ]);
        }

        // Fetch customer
        $customer = Customers::findFirst($customerId);
        if (!$customer) {
            return $response->setJsonContent([
                'status' => 'error',
                'message' => 'Customer not found'
            ]);
        }

        // Fetch tickets associated with the customer
        $ticketProfiles = TicketProfile::find([
            'conditions' => 'customer_id = :customer_id:',
            'bind' => [
                'customer_id' => $customerId
            ]
        ]);

        $tickets = [];
        foreach ($ticketProfiles as $ticketProfile) {
            $booking = Booking::findFirst($ticketProfile->booking_id);
            if ($booking) {
                $event = Event::findFirst($booking->event_id);
                $ticketCategory = TicketCategory::findFirst($ticketProfile->category_id);
                $payment = Payment::findFirst($ticketProfile->payment_id); // Use payment_id from ticket_profiles
                $paymentStatusId = $payment ? $payment->payment_status_id : null; // Fetch payment_status_id from payment

                $tickets[] = [
                    'ticket_id' => $ticketProfile->id,
                    'event_name' => $event ? $event->name : null,
                    'category_name' => $ticketCategory ? $ticketCategory->category_name : null,
                    'payment_status' => $paymentStatusId,
                    'unique_code' => $ticketProfile->unique_code,
                    'qr_code' => $ticketProfile->qr_code,
                    'valid_status' => $ticketProfile->valid_status,
                    'redeemed_ticket' => $ticketProfile->redeemed_ticket
                ];
            }
        }

        return $response->setJsonContent([
            'status' => 'success',
            'tickets' => $tickets
        ]);
    }

    public function getPaidTicketsAction()
    {
        $response = new Response();
        $customerId = $this->getCustomerIdFromToken();

        if (!$customerId) {
            return $response->setJsonContent([
                'status' => 'error',
                'message' => 'Invalid or missing JWT token'
            ]);
        }

        // Fetch customer
        $customer = Customers::findFirst($customerId);
        if (!$customer) {
            return $response->setJsonContent([
                'status' => 'error',
                'message' => 'Customer not found'
            ]);
        }

        // Fetch tickets associated with the customer and payment_status_id = 1
        $ticketProfiles = TicketProfile::find([
            'conditions' => 'customer_id = :customer_id:',
            'bind' => [
                'customer_id' => $customerId
            ]
        ]);

        $tickets = [];
        foreach ($ticketProfiles as $ticketProfile) {
            $payment = Payment::findFirst($ticketProfile->payment_id);
            if ($payment && $payment->payment_status_id == 1) {
                $booking = Booking::findFirst($ticketProfile->booking_id);
                if ($booking) {
                    $event = Event::findFirst($booking->event_id);
                    $ticketCategory = TicketCategory::findFirst($ticketProfile->category_id);

                    $tickets[] = [
                        'ticket_id' => $ticketProfile->id,
                        'event_name' => $event ? $event->name : null,
                        'category_name' => $ticketCategory ? $ticketCategory->category_name : null,
                        'payment_status' => $payment->payment_status_id,
                        'unique_code' => $ticketProfile->unique_code,
                        'qr_code' => $ticketProfile->qr_code,
                        'valid_status' => $ticketProfile->valid_status,
                        'redeemed_ticket' => $ticketProfile->redeemed_ticket
                    ];
                }
            }
        }

        return $response->setJsonContent([
            'status' => 'success',
            'tickets' => $tickets
        ]);
    }
}
