<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Response;

class BookingController extends Controller
{
    public function initialize()
    {
     
        $this->view->disable();
    }

    public function createAction()
    {
        $response = new Response();

        // Assuming you receive JSON input
        $data = $this->request->getJsonRawBody(true);

        if (!isset($data['booking']) || !is_array($data['booking'])) {
            return $response->setJsonContent([
                'status' => 'error',
                'message' => 'Invalid input data'
            ]);
        }

        $this->db->begin();

        try {
            foreach ($data['booking'] as $bookingData) {
                if (!isset($bookingData['user_id'], $bookingData['event_id'], $bookingData['ticket_category_id'], $bookingData['quantity'])) {
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

                // Create a new Booking
                $booking = new Booking();
                $booking->user_id = $bookingData['user_id'];
                $booking->event_id = $bookingData['event_id'];
                $booking->ticket_category_id = $bookingData['ticket_category_id'];
                $booking->quantity = $bookingData['quantity'];
                $booking->booking_date = date('Y-m-d H:i:s');
                $booking->status = 'confirmed';
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
            }

            $this->db->commit();

            return $response->setJsonContent([
                'status' => 'success',
                'message' => 'Bookings created successfully'
            ]);

        } catch (\Exception $e) {
            $this->db->rollback();

            return $response->setJsonContent([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }
}
