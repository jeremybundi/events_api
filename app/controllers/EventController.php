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

        $eventData = $data['event'];
        $ticketCategoriesData = $data['ticket_categories'];

        // Calculate the total number of tickets from all categories
        $totalCategoryTickets = array_sum(array_column($ticketCategoriesData, 'quantity_available'));

        if ($totalCategoryTickets != $eventData['total_tickets']) {
            return $response->setJsonContent([
                'status' => 'error',
                'message' => 'Total quantity of ticket categories must equal total tickets for the event'
            ]);
        }

        $this->db->begin();

        try {
            // Create the event
            $event = new Event();
            $event->name = $eventData['name'];
            $event->date = $eventData['date'];
            $event->start_time = $eventData['start_time'];
            $event->end_time = $eventData['end_time'];
            $event->venue = $eventData['venue'];
            $event->description = $eventData['description'];
            $event->total_tickets = $eventData['total_tickets'];

            if (!$event->save()) {
                throw new \Exception('Failed to save event');
            }

            // Create the ticket categories
            foreach ($ticketCategoriesData as $ticketCategoryData) {
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

    public function editAction($id)
    {
        $response = new Response();

       
        $data = $this->request->getJsonRawBody(true);

        if (!isset($data['event']) || !isset($data['ticket_categories'])) {
            return $response->setJsonContent([
                'status' => 'error',
                'message' => 'Invalid input data'
            ]);
        }

        $eventData = $data['event'];
        $ticketCategoriesData = $data['ticket_categories'];

        // Calculate the total 
        $totalCategoryTickets = array_sum(array_column($ticketCategoriesData, 'quantity_available'));

        if ($totalCategoryTickets != $eventData['total_tickets']) {
            return $response->setJsonContent([
                'status' => 'error',
                'message' => 'Total quantity of ticket categories must equal total tickets for the event'
            ]);
        }

        $this->db->begin();

        try {
            // Find if the event is existing 
            $event = Event::findFirst($id);
            if (!$event) {
                throw new \Exception('Event not found');
            }

            // Update
            $event->name = $eventData['name'];
            $event->date = $eventData['date'];
            $event->start_time = $eventData['start_time'];
            $event->end_time = $eventData['end_time'];
            $event->venue = $eventData['venue'];
            $event->description = $eventData['description'];
            $event->total_tickets = $eventData['total_tickets'];

            if (!$event->save()) {
                throw new \Exception('Failed to update event');
            }

            // Remove existing ticket categories
            $existingTicketCategories = TicketCategory::find([
                'conditions' => 'event_id = ?1',
                'bind'       => [
                    1 => $event->id
                ]
            ]);

            foreach ($existingTicketCategories as $existingTicketCategory) {
                if (!$existingTicketCategory->delete()) {
                    throw new \Exception('Failed to delete existing ticket categories');
                }
            }

            // Create new ticket categories
            foreach ($ticketCategoriesData as $ticketCategoryData) {
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
                'message' => 'Event and ticket categories updated successfully'
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
