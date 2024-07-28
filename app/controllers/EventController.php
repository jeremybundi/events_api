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

    // Helper function to validate the JWT and check the user's role
    private function validateRole(array $allowedRoles)
    {
        $authHeader = $this->request->getHeader('Authorization');
        if (!$authHeader) {
            throw new \Exception('Authorization header not found');
        }
    
        if (strpos($authHeader, 'Bearer ') !== 0) {
            throw new \Exception('Invalid authorization token format');
        }
    
        $jwt = substr($authHeader, 7); // Extract the token after "Bearer "
        if (!$jwt) {
            throw new \Exception('Invalid authorization token format');
        }
    
        $config = $this->di->getConfig();
        $secretKey = $config->jwt->secret_key;
    
        try {
            $decoded = JWT::decode($jwt, new \Firebase\JWT\Key($secretKey, 'HS256'));
            $role = $decoded->data->role;
    
            if (!in_array($role, $allowedRoles)) {
                throw new \Exception('User role not allowed');
            }
        } catch (\Exception $e) {
            error_log('JWT decoding error: ' . $e->getMessage());
            throw new \Exception('Invalid or expired token');
        }
    }
    
    public function addAction()
    {
        $response = new Response();
    
        try {
            $this->validateRole(['System support', 'Admin']);
    
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
    
            try {
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
    
        } catch (\Exception $e) {
            return $response->setStatusCode(401, 'Unauthorized')
                            ->setJsonContent([
                                'status' => 'error',
                                'message' => $e->getMessage()
                            ]);
        }
    }
    
    public function editAction($id)
    {
        $response = new Response();
    
        try {
            $this->validateRole(['Admin']);
    
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
    
            try {
                $event = Event::findFirst($id);
                if (!$event) {
                    throw new \Exception('Event not found');
                }
    
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
    
        } catch (\Exception $e) {
            return $response->setStatusCode(401, 'Unauthorized')
                            ->setJsonContent([
                                'status' => 'error',
                                'message' => $e->getMessage()
                            ]);
        }
    }
    
    
}
