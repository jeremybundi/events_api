<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

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
            $decoded = JWT::decode($jwt, new Key($secretKey, 'HS256'));
            
            $role = $decoded->data->role;
            $UserId = $decoded->data->userId;

            if (!in_array($role, $allowedRoles)) {
                throw new \Exception('User role not allowed');
            }

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
        $userDetails = $this->validateRole(['Super Admin', 'System Admin', 'Event Organizers']);
        $UserId = $userDetails['UserId']; 

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

        // Start transaction
        $this->db->begin();

        // Create and save event
        $event = new Event();
        $event->name = $eventData['name'];
        $event->date = $eventData['date'];
        $event->start_time = $eventData['start_time'];
        $event->end_time = $eventData['end_time'];
        $event->venue = $eventData['venue'];
        $event->description = $eventData['description'];
        $event->total_tickets = $eventData['total_tickets'];
        $event->image_url = $eventData['image_url'];
        $event->UserId = $UserId; 

        if (!$event->save()) {
            throw new \Exception('Failed to save event: ' . implode(', ', $event->getMessages()));
        }

        // Create and save ticket categories
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

        // Commit transaction
        $this->db->commit();

        return $response->setJsonContent([
            'status' => 'success',
            'message' => 'Event and ticket categories created successfully'
        ]);

    } catch (\Exception $e) {
        // Rollback transaction if any exception occurs
        if ($this->db->isUnderTransaction()) {
            $this->db->rollback();
        }

        return $response->setJsonContent([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
}
    //edit events
    public function editAction($id)
{
    $response = new Response();

    try {
        // Validate user role
        $userDetails = $this->validateRole(['Super Admin', 'System Admin', 'Event Organizers']);
        $UserId = $userDetails['UserId'];
        $role = $userDetails['role'];

        // Get JSON data from the request
        $data = $this->request->getJsonRawBody(true);

        // Check if event and ticket categories are provided
        if (!isset($data['event']) || !isset($data['ticket_categories'])) {
            return $response->setJsonContent([
                'status' => 'error',
                'message' => 'Missing event or ticket categories data.'
            ]);
        }

        $eventData = $data['event'];
        $ticketCategoriesData = $data['ticket_categories'];

        // Validate event data
        $requiredEventFields = ['name', 'date', 'start_time', 'end_time', 'venue', 'description', 'total_tickets'];
        $missingEventFields = array_diff($requiredEventFields, array_keys($eventData));

        if (!empty($missingEventFields)) {
            return $response->setJsonContent([
                'status' => 'error',
                'message' => 'Missing event fields: ' . implode(', ', $missingEventFields)
            ]);
        }

        // Validate ticket categories
        if (empty($ticketCategoriesData)) {
            return $response->setJsonContent([
                'status' => 'error',
                'message' => 'Ticket categories cannot be empty.'
            ]);
        }

        $missingCategoryFields = [];
        foreach ($ticketCategoriesData as $index => $category) {
            $requiredCategoryFields = ['category_name', 'price', 'quantity_available'];
            $missingFields = array_diff($requiredCategoryFields, array_keys($category));
            if (!empty($missingFields)) {
                $missingCategoryFields[$index] = $missingFields;
            }
        }

        if (!empty($missingCategoryFields)) {
            $errors = [];
            foreach ($missingCategoryFields as $index => $fields) {
                $errors[] = "Missing fields in ticket category at index $index: " . implode(', ', $fields);
            }
            return $response->setJsonContent([
                'status' => 'error',
                'message' => implode('; ', $errors)
            ]);
        }

        // Check total quantity of ticket categories
        $totalCategoryTickets = array_sum(array_column($ticketCategoriesData, 'quantity_available'));
        if ($totalCategoryTickets != $eventData['total_tickets']) {
            return $response->setJsonContent([
                'status' => 'error',
                'message' => 'Total quantity of ticket categories must equal total tickets for the event'
            ]);
        }

        // Find and update event
        $event = Event::findFirst($id);
        if (!$event) {
            return $response->setJsonContent([
                'status' => 'error',
                'message' => 'Event not found'
            ]);
        }

        // Check if the user has permission to edit the event
        if ($role === 'Event Organizers' && $event->UserId != $UserId) {
            return $response->setJsonContent([
                'status' => 'error',
                'message' => 'You do not have permission to edit this event'
            ]);
        }

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

        // Update or create ticket categories
        foreach ($ticketCategoriesData as $ticketCategoryData) {
            $ticketCategory = TicketCategory::findFirst([
                'conditions' => 'event_id = ?1 AND category_name = ?2',
                'bind'       => [
                    1 => $event->id,
                    2 => $ticketCategoryData['category_name']
                ]
            ]);

            if (!$ticketCategory) {
                $ticketCategory = new TicketCategory();
                $ticketCategory->event_id = $event->id;
            }

            $ticketCategory->category_name = $ticketCategoryData['category_name'];
            $ticketCategory->price = $ticketCategoryData['price'];
            $ticketCategory->quantity_available = $ticketCategoryData['quantity_available'];

            if (!$ticketCategory->save()) {
                throw new \Exception('Failed to save ticket category: ' . implode(', ', $ticketCategory->getMessages()));
            }
        }

        return $response->setJsonContent([
            'status' => 'success',
            'message' => 'Event and ticket categories updated successfully'
        ]);

    } catch (\Exception $e) {
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
        // Validate user role and get UserId
        $userDetails = $this->validateRole(['Super Admin', 'System Admin', 'Event Organizers']);
        $role = $userDetails['role'];
        $UserId = $userDetails['UserId'];

        $eventsData = [];

        if ($role === 'Event Organizers') {
            // Retrieve events created by the user if they are an Event Organizer
            $createdEvents = Event::find([
                'conditions' => 'UserId = ?1',
                'bind'       => [
                    1 => $UserId
                ]
            ]);

            foreach ($createdEvents as $event) {
                $eventData = $this->getEventData($event);
                $eventData['date'] = (new \DateTime($event->date))->format('Y-m-d');
                $eventsData[$event->id] = $eventData;
            }
        }

        // If the user is an Event Organizer, filter events they have access to
        if ($role === 'Event Organizers') {
            $accessibleEvents = UserEventAccess::find([
                'conditions' => 'user_id = ?1',
                'bind'       => [
                    1 => $UserId
                ]
            ]);

            foreach ($accessibleEvents as $access) {
                $eventId = $access->event_id;
                $event = Event::findFirst($eventId);

                if ($event) {
                    $eventData = $this->getEventData($event);
                    $eventData['date'] = (new \DateTime($event->date))->format('Y-m-d');
                    $eventsData[$event->id] = $eventData;
                }
            }
        }

        // Retrieve all events if user is Super Admin or System Admin
        if (in_array($role, ['Super Admin', 'System Admin'])) {
            $allEvents = Event::find();

            foreach ($allEvents as $event) {
                $eventData = $this->getEventData($event);
                $eventData['date'] = (new \DateTime($event->date))->format('Y-m-d');
                $eventsData[$event->id] = $eventData;
            }
        }

        return $response->setJsonContent([
            'status' => 'success',
            'data' => $eventsData
        ]);

    } catch (\Exception $e) {
        return $response->setJsonContent([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
}



    public function publicListAction()
    {
        $response = new Response();

        try {
            $eventsData = [];

            // Retrieve all events
            $allEvents = Event::find();

            foreach ($allEvents as $event) {
                $eventsData[$event->id] = $this->getEventData($event);
            }

            return $response->setJsonContent([
                'status' => 'success',
                'data' => $eventsData
            ]);

        } catch (\Exception $e) {
            return $response->setJsonContent([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }
    public function getEventByIdAction($id)
    {
        // Fetch event details from the database by ID
        $event = Event::findFirst($id);
    
        if ($event) {
            $response = new Response();
            
            // Format the date to exclude time
            $eventDate = (new \DateTime($event->date))->format('Y-m-d');
    
            // Update the event data with the formatted date
            $eventData = $this->getEventData($event); // Use the helper method to get event data with ticket categories
            $eventData['date'] = $eventDate; // Replace date with formatted date
    
            $response->setJsonContent([
                'status' => 'success',
                'data' => $eventData
            ]);
            return $response;
        } else {
            $response = new Response();
            $response->setStatusCode(404, 'Not Found');
            $response->setJsonContent([
                'status' => 'error',
                'message' => 'Event not found'
            ]);
            return $response;
        }
    }
    


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
                'ticket_category_id'=> $ticketCategory->category_id,
                'category_name' => $ticketCategory->category_name,
                'price' => $ticketCategory->price,
                'quantity_available' => $ticketCategory->quantity_available
            ];
        }

        return [
            'id' => $event->id,
            'name' => $event->name,
            'date' => $event->date,
            'start_time' => $event->start_time,
            'end_time' => $event->end_time,
            'venue' => $event->venue,
            'image_url' => $event->image_url,
            'description' => $event->description,
            'total_tickets' => $event->total_tickets,
            'ticket_categories' => $ticketCategoriesData
        ];
    }
}
