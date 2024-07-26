<?php
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

        $data = $this->request->getJsonRawBody(true);

        // Input validation
        $validation = new Validation();
        $validation->add('user_id', new PresenceOf(['message' => 'The user_id field is required']));
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

        $userId = $data['user_id'];

        // Check if the user exists
        $user = Users::findFirst($userId);
        if (!$user) {
            return $response->setJsonContent(['status' => 'error', 'message' => 'User not found for user ID: ' . $userId]);
        }

        $this->db->begin();

        $totalAmount = 0;
        $bookingDetails = [];

        try {
            foreach ($data['booking'] as $bookingData) {
                if (!isset($bookingData['event_id'], $bookingData['ticket_category_id'], $bookingData['quantity'])) {
                    throw new \Exception('Invalid booking data');
                }

                // Find the ticket category
                $ticketCategory = TicketCategory::findFirst($bookingData['ticket_category_id']);
                if (!$ticketCategory) {
                    throw new \Exception('Ticket category not found');
                }

                // Check if there are enough tickets available
                if ($ticketCategory->quantity_available < $bookingData['quantity']) {
                    throw new \Exception('Not enough tickets available for ticket category ID: ' . $bookingData['ticket_category_id']);
                }

                // Find the event
                $event = Event::findFirst($bookingData['event_id']);
                if (!$event) {
                    throw new \Exception('Event not found for event ID: ' . $bookingData['event_id']);
                }

                // Check if the total tickets are sufficient
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
                    $ticketProfile->created_at = date('Y-m-d H:i:s');
                    $ticketProfile->updated_at = date('Y-m-d H:i:s');

                    if (!$ticketProfile->save()) {
                        throw new \Exception('Failed to save ticket profile');
                    }
                }
            }

            // Create payment record
            $paymentController = new PaymentController();
            $paymentResult = $paymentController->processPayment($userId, $totalAmount, $data['payment_method']);

            if ($paymentResult['status'] !== 'success') {
                throw new \Exception($paymentResult['message']);
            }

            $this->db->commit();

            return $response->setJsonContent([
                'status' => 'success',
                'message' => 'Bookings processed successfully',
                'booking_details' => $bookingDetails,
                'total_amount' => $totalAmount,
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
