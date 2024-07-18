<?php
use Phalcon\Mvc\Controller;
use Phalcon\Http\Response;

class TicketProfileController extends Controller
{
    public function initialize()
    {
        $this->view->disable();
    }

    public function getTicketsAction($userId)
    {
        $response = new Response();

        try {
            // Check if user exists
            $user = Users::findFirstById($userId);
            if (!$user) {
                return $response->setStatusCode(404, 'Not Found')
                                ->setJsonContent(['status' => 'error', 'message' => 'User not found']);
            }

            // Create the query builder instance
            $queryBuilder = $this->modelsManager->createBuilder()
                ->columns([
                    'tp.id AS ticket_id',
                    'CONCAT(u.first_name, " ", u.second_name) AS user_name',
                    'tc.category_name',
                    'e.name AS event_name'
                ])
                ->from(['tp' => 'TicketProfile'])
                ->join('Users', 'tp.user_id = u.id', 'u') 
                ->join('Booking', 'tp.booking_id = b.id', 'b')
                ->join('TicketCategory', 'b.ticket_category_id = tc.category_id', 'tc')
                ->join('Event', 'b.event_id = e.id', 'e')
                ->where('tp.user_id = :user_id:', ['user_id' => $userId]);

            // Execute the query
            $tickets = $queryBuilder->getQuery()->execute();

            $result = [];
            foreach ($tickets as $ticket) {
                $result[] = [
                    'ticket_id' => $ticket->ticket_id,
                    'user_name' => $ticket->user_name,
                    'category_name' => $ticket->category_name,
                    'event_name' => $ticket->event_name
                ];
            }

            return $response->setStatusCode(200, 'OK')
                            ->setJsonContent([
                                'status' => 'success',
                                'tickets' => $result
                            ]);

        } catch (\Exception $e) {
            return $response->setStatusCode(500, 'Internal Server Error')
                            ->setJsonContent([
                                'status' => 'error',
                                'message' => 'An error occurred while fetching tickets: ' . $e->getMessage()
                            ]);
        }
    }
}
