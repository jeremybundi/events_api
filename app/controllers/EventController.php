<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Response;
use Firebase\JWT\JWT;

class EventController extends Controller
{
    public function initialize()
    {
        $this->view->disable();
    }

    private function validateRole(array $allowedRoles)
    {
        $authHeader = $this->request->getHeader('Authorization');
        if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
            throw new \Exception('Authorization header not found or format invalid');
        }

        $jwt = substr($authHeader, 7); 
        if (!$jwt) {
            throw new \Exception('Invalid authorization token format');
        }

        $config = $this->di->getConfig();
        $secretKey = $config->jwt->secret_key;

        try {
            $decoded = JWT::decode($jwt, new \Firebase\JWT\Key($secretKey, 'HS256'));
            
            $role = $decoded->data->role;
            $UserId = $decoded->data->userId; // Use UserId from token

            if (!in_array($role, $allowedRoles)) {
                throw new \Exception('User role not allowed');
            }

            // Return the decoded data, including UserId and role
            return [
                'UserId' => $UserId, 
                'role' => $role,
            ];
        } catch (\Exception $e) {
            error_log('JWT decoding error: ' . $e->getMessage());
            throw new \Exception('Invalid or expired token');
        }
    }

    public function addAction()
    {
        $response = new Response();
    
        try {
            $userDetails = $this->validateRole(['System Admin', 'Event Organizers']);
            $UserId = $userDetails['UserId']; // Use UserId from token
    
            $data = $this->request->getJsonRawBody(true);
            if (!isset($data['event']) || !isset($data['ticket_categories'])) {
                return $response->setJsonContent([
                    'status' => 'error',
                    'message' => 'Invalid input data'
                ]);
            }
    
            $eventData = $data['event'];
            $ticketCategoriesData = $data['ticket_categories'];
    
            $totalCategoryTickets = array_sum(array_column($ticketCategoriesData, 'quantity_available'));
    
            if ($totalCategoryTickets != $eventData['total_tickets']) {
                return $response->setJsonContent([
                    'status' => 'error',
                    'message' => 'Total quantity of ticket categories must equal total tickets for the event'
                ]);
            }
    
            $this->db->begin();
    
            $event = new Event();
            $event->name = $eventData['name'];
            $event->date = $eventData['date'];
            $event->start_time = $eventData['start_time'];
            $event->end_time = $eventData['end_time'];
            $event->venue = $eventData['venue'];
            $event->description = $eventData['description'];
            $event->total_tickets = $eventData['total_tickets'];
            $event->UserId = $UserId; // Set the UserId column
    
            if (!$event->save()) {
                throw new \Exception('Failed to save event: ' . implode(', ', $event->getMessages()));
            }
    
            foreach ($ticketCategoriesData as $ticketCategoryData) {
                $ticketCategory = new TicketCategory();
                $ticketCategory->event_id = $event->id;
                $ticketCategory->category_name = $ticketCategoryData['category_name'];
                $ticketCategory->price = $ticketCategoryData['price'];
                $ticketCategory->quantity_available = $ticketCategoryData['quantity_available'];
    
                if (!$ticketCategory->save()) {
                    throw new \Exception('Failed to save ticket category: ' . implode(', ', $ticketCategory->getMessages()));
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
    
        try {
            // Validate the role and retrieve UserId and role from the token
            $userDetails = $this->validateRole(['System Admin', 'Event Organizers']);
            $UserId = $userDetails['UserId']; 
            $role = $userDetails['role'];
    
            // Fetch the event by ID
            $event = Event::findFirst($id);
            if (!$event) {
                throw new \Exception('Event not found');
            }
    
            // Check if the user is an event organizer and owns the event
            if ($role === 'Event Organizers' && $event->UserId !== $UserId) {
                return $response->setStatusCode(403, 'Forbidden')
                                ->setJsonContent([
                                    'status' => 'error',
                                    'message' => 'You are not allowed to edit this event'
                                ]);
            }
    
            // Get the raw JSON data from the request
            $data = $this->request->getJsonRawBody(true);
            if (!isset($data['event']) || !isset($data['ticket_categories'])) {
                return $response->setJsonContent([
                    'status' => 'error',
                    'message' => 'Invalid input data'
                ]);
            }
    
            $eventData = $data['event'];
            $ticketCategoriesData = $data['ticket_categories'];
    
            // Validate the total number of tickets
            $totalCategoryTickets = array_sum(array_column($ticketCategoriesData, 'quantity_available'));
            if ($totalCategoryTickets != $eventData['total_tickets']) {
                return $response->setJsonContent([
                    'status' => 'error',
                    'message' => 'Total quantity of ticket categories must equal total tickets for the event'
                ]);
            }
    
            $this->db->begin();
    
            // Update the event details
            $event->name = $eventData['name'];
            $event->date = $eventData['date'];
            $event->start_time = $eventData['start_time'];
            $event->end_time = $eventData['end_time'];
            $event->venue = $eventData['venue'];
            $event->description = $eventData['description'];
            $event->total_tickets = $eventData['total_tickets'];
    
            if (!$event->save()) {
                throw new \Exception('Failed to update event: ' . implode(', ', $event->getMessages()));
            }
    
            // Delete existing ticket categories
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
    
            // Save the new ticket categories
            foreach ($ticketCategoriesData as $ticketCategoryData) {
                $ticketCategory = new TicketCategory();
                $ticketCategory->event_id = $event->id;
                $ticketCategory->category_name = $ticketCategoryData['category_name'];
                $ticketCategory->price = $ticketCategoryData['price'];
                $ticketCategory->quantity_available = $ticketCategoryData['quantity_available'];
    
                if (!$ticketCategory->save()) {
                    throw new \Exception('Failed to save ticket category: ' . implode(', ', $ticketCategory->getMessages()));
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
    
    public function listAction()
    {
        $response = new Response();

        try {
            $userDetails = $this->validateRole(['System Admin', 'Event Organizers', 'Customer', 'Validator']);
            $role = $userDetails['role'];
            $UserId = $userDetails['UserId'];

        
            $eventsData = [];

            // Retrieve events created by the user (if they are an Event Organizer)
            if ($role === 'Event Organizers') {
                $createdEvents = Event::find([
                    'conditions' => 'UserId = ?1',
                    'bind'       => [
                        1 => $UserId
                    ]
                ]);

                foreach ($createdEvents as $event) {
                    $eventsData[$event->id] = $this->getEventData($event);
                }
            }

            // Retrieve events the user has access to through UserEventAccess
            if (in_array($role, ['Event Organizers', 'Customer', 'Validator'])) {
                $accessibleEvents = $this->modelsManager->createBuilder()
                    ->from('Event')
                    ->innerJoin('UserEventAccess', 'uea.event_id = Event.id', 'uea')
                    ->where('uea.user_id = :UserId:', ['UserId' => $UserId])
                    ->getQuery()
                    ->execute();

                foreach ($accessibleEvents as $event) {
                    if (!isset($eventsData[$event->id])) {
                        $eventsData[$event->id] = $this->getEventData($event);
                    }
                }
            }

            // Retrieve all events if the user is a System Admin
            if ($role === 'System Admin') {
                $allEvents = Event::find();
                foreach ($allEvents as $event) {
                    $eventsData[$event->id] = $this->getEventData($event);
                }
            }

            // Check if any events are found
            if (empty($eventsData)) {
                return $response->setJsonContent([
                    'status' => 'error',
                    'message' => 'No events found'
                ]);
            }

            return $response->setJsonContent([
                'status' => 'success',
                'data' => array_values($eventsData)
            ]);

        } catch (\Exception $e) {
            return $response->setStatusCode(401, 'Unauthorized')
                            ->setJsonContent([
                                'status' => 'error',
                                'message' => $e->getMessage()
                            ]);
        }
    }

    // metthod to get categories
    private function getEventData($event)
    {
        $ticketCategories = TicketCategory::find([
            'conditions' => 'event_id = ?1',
            'bind'       => [
                1 => $event->id
            ]
        ]);

        $ticketCategoriesData = [];
        foreach ($ticketCategories as $ticketCategory) {
            $ticketCategoriesData[] = [
                'id' => $ticketCategory->category_id,
                'category_name' => $ticketCategory->category_name,
                'price' => $ticketCategory->price,
                'quantity_available' => $ticketCategory->quantity_available
            ];
        }

        return [
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'date' => $event->date,
                'start_time' => $event->start_time,
                'end_time' => $event->end_time,
                'venue' => $event->venue,
                'description' => $event->description,
                'total_tickets' => $event->total_tickets,
                'image_url' => $event->image_url
            ],
            'ticket_categories' => $ticketCategoriesData
        ];
    }

}
