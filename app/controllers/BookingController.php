<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Phalcon\Mvc\Controller;
use Phalcon\Http\Response;
use Phalcon\Validation;
use Phalcon\Validation\Validator\PresenceOf;

class BookingController extends Controller
{
    public function initialize()
    {
        $this->view->disable();
    }
    public function createAction()
{
    $response = new Response();

    // Extract token from the headers
    $authHeader = $this->request->getHeader('Authorization');
    if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        return $response->setStatusCode(401, 'Unauthorized')
                        ->setJsonContent(['status' => 'error', 'message' => 'Token not provided or invalid']);
    }

    $token = $matches[1];

    try {
        // Decode the token
        $config = $this->di->getConfig();
        $secretKey = $config->jwt->secret_key;
        $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));
    } catch (\Exception $e) {
        return $response->setStatusCode(401, 'Unauthorized')
                        ->setJsonContent(['status' => 'error', 'message' => 'Invalid token']);
    }

    // Extract userId from token
    $userId = $decoded->data->userId;

    $data = $this->request->getJsonRawBody(true);

    // Input validation
    $validation = new Validation();
    $validation->add('booking', new PresenceOf(['message' => 'The booking field is required']));
    $validation->add('payment_method', new PresenceOf(['message' => 'The payment_method field is required']));

    $messages = $validation->validate($data);
    if (count($messages)) {
        $errors = [];
        foreach ($messages as $message) {
            $errors[] = $message->getMessage();
        }

        return $response->setJsonContent(['status' => 'error', 'message' => implode(', ', $errors)]);
    }

    if (!is_array($data['booking']) || empty($data['booking'])) {
        return $response->setJsonContent(['status' => 'error', 'message' => 'Invalid input data']);
    }

    // Check if the user exists
    $user = Users::findFirst($userId);
    if (!$user) {
        return $response->setJsonContent(['status' => 'error', 'message' => 'User not found for user ID: ' . $userId]);
    }

    $this->db->begin();

    $totalAmount = 0;
    $bookingDetails = [];
    $paymentId = null;  

    try {
        foreach ($data['booking'] as $bookingData) {
            if (!isset($bookingData['event_id'], $bookingData['ticket_category_id'], $bookingData['quantity'])) {
                throw new \Exception('Invalid booking data');
            }

            $ticketCategory = TicketCategory::findFirst($bookingData['ticket_category_id']);
            if (!$ticketCategory) {
                throw new \Exception('Ticket category not found');
            }

            if ($ticketCategory->quantity_available < $bookingData['quantity']) {
                throw new \Exception('Not enough tickets available for ticket category ID: ' . $bookingData['ticket_category_id']);
            }

            $event = Event::findFirst($bookingData['event_id']);
            if (!$event) {
                throw new \Exception('Event not found for event ID: ' . $bookingData['event_id']);
            }

            if ($event->total_tickets < $bookingData['quantity']) {
                throw new \Exception('Not enough total tickets available for event ID: ' . $bookingData['event_id']);
            }

            // Calculate the subtotal for this ticket category
            $subtotal = $ticketCategory->price * $bookingData['quantity'];
            $totalAmount += $subtotal;

            // Create a new Booking
            $booking = new Booking();
            $booking->user_id = $userId;
            $booking->event_id = $bookingData['event_id'];
            $booking->ticket_category_id = $bookingData['ticket_category_id'];
            $booking->quantity = $bookingData['quantity'];
            $booking->booking_date = date('Y-m-d H:i:s');
            $booking->created_at = date('Y-m-d H:i:s');
            $booking->updated_at = date('Y-m-d H:i:s');

            if (!$booking->save()) {
                throw new \Exception('Failed to save booking for event ID: ' . $bookingData['event_id']);
            }

            // Subtract the booked quantity from the ticket category and total tickets
            $ticketCategory->quantity_available -= $bookingData['quantity'];
            if (!$ticketCategory->save()) {
                throw new \Exception('Failed to update ticket category ID: ' . $bookingData['ticket_category_id']);
            }

            $event->total_tickets -= $bookingData['quantity'];
            if (!$event->save()) {
                throw new \Exception('Failed to update event ID: ' . $bookingData['event_id']);
            }

            // Calculate and update purchased_tickets
            $ticketCategory->purchased_tickets += $bookingData['quantity'];  // Updated line

            if (!$ticketCategory->save()) {
                throw new \Exception('Failed to update purchased tickets for ticket category ID: ' . $bookingData['ticket_category_id']);
            }

            $bookingDetails[] = [
                'event_id' => $bookingData['event_id'],
                'ticket_category_id' => $bookingData['ticket_category_id'],
                'quantity' => $bookingData['quantity'],
                'price' => $ticketCategory->price,
                'subtotal' => $subtotal,
                'booking_id' => $booking->id,
            ];

            // Create ticket profiles
            for ($i = 0; $i < $bookingData['quantity']; $i++) {
                $ticketProfile = new TicketProfile();
                $ticketProfile->user_id = $userId;
                $ticketProfile->booking_id = $booking->id;
                $ticketProfile->category_id = $bookingData['ticket_category_id'];  // Set category_id
                $ticketProfile->created_at = date('Y-m-d H:i:s');
                $ticketProfile->updated_at = date('Y-m-d H:i:s');

                if (!$ticketProfile->save()) {
                    throw new \Exception('Failed to save ticket profile');
                }
            }
        }

        // Create payment record
        $paymentController = new PaymentController();
        $paymentResult = $paymentController->processPayment($userId, $totalAmount, $data['payment_method'], $booking->id);

        if ($paymentResult['status'] !== 'success') {
            throw new \Exception($paymentResult['message']);
        }

        $paymentId = $paymentResult['payment_id'];  // Retrieve payment ID

        // Update ticket profiles with payment ID
        foreach ($data['booking'] as $bookingData) {
            $ticketProfiles = TicketProfile::find([
                'conditions' => 'booking_id = ?1',
                'bind'       => [
                    1 => $booking->id,
                ],
            ]);

            foreach ($ticketProfiles as $ticketProfile) {
                $ticketProfile->payment_id = $paymentId;
                if (!$ticketProfile->save()) {
                    throw new \Exception('Failed to update ticket profile with payment ID');
                }
            }
        }

        $this->db->commit();

        return $response->setJsonContent([
            'status' => 'success',
            'message' => 'Bookings processed successfully',
            'booking_details' => $bookingDetails,
            'total_amount' => $totalAmount,
            'payment_status_id' => 0,
        ]);

    } catch (\Exception $e) {
        $this->db->rollback();

        return $response->setJsonContent([
            'status' => 'error',
            'message' => $e->getMessage(),
        ]);
    }
}

}
