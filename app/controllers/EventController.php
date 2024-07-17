<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Response;

class EventController extends Controller
{
    public function initialize()
    {
        
        $this->view->disable();
    }

    public function addAction()
    {
        $response = new Response();

      
        $data = $this->request->getJsonRawBody(true);

        if (!isset($data['event']) || !isset($data['ticket_categories'])) {
            return $response->setJsonContent([
                'status' => 'error',
                'message' => 'Invalid input data'
            ]);
        }

        $this->db->begin();

        try {
            // Create the event
            $event = new Event();
            $event->name = $data['event']['name'];
            $event->date = $data['event']['date'];
            $event->start_time = $data['event']['start_time'];
            $event->end_time = $data['event']['end_time'];
            $event->venue = $data['event']['venue'];
            $event->description = $data['event']['description'];
            $event->total_tickets = $data['event']['total_tickets'];

            if (!$event->save()) {
                throw new \Exception('Failed to save event');
            }

            // Create the ticket categories
            foreach ($data['ticket_categories'] as $ticketCategoryData) {
                $ticketCategory = new TicketCategory();
                $ticketCategory->event_id = $event->id;
                $ticketCategory->category_name = $ticketCategoryData['category_name'];
                $ticketCategory->price = $ticketCategoryData['price'];
                $ticketCategory->quantity_available = $ticketCategoryData['quantity_available'];

                if (!$ticketCategory->save()) {
                    throw new \Exception('Failed to save ticket category');
                }
            }

            $this->db->commit();

            return $response->setJsonContent([
                'status' => 'success',
                'message' => 'Event and ticket categories created successfully'
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
