<?php
use Phalcon\Mvc\Controller;
use Phalcon\Http\Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class TicketProfileController extends Controller
{
    public function initialize()
    {
        $this->view->disable();
    }

    private function getUserIdAndRoleFromToken()
    {
        $authHeader = $this->request->getHeader('Authorization');
        if (!$authHeader) {
            throw new \Exception('No authorization header provided');
        }

        $token = str_replace('Bearer ', '', $authHeader);
        $config = $this->di->getConfig();
        $secretKey = $config->jwt->secret_key;

        try {
            $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));
            return [
                'userId' => $decoded->data->userId,
                'role' => $decoded->data->role
            ];
        } catch (\Exception $e) {
            throw new \Exception('Invalid or expired token');
        }
    }

    public function getTicketsAction()
{
    $response = new Response();

    try {
        $userDetails = $this->getUserIdAndRoleFromToken();
        $userId = $userDetails['userId'];
        $role = $userDetails['role'];

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
                'e.name AS event_name',
                'p.payment_status_id',
                //'p.id AS payment_id', // Include payment ID for debugging
                //'p.total_amount' // Include total amount for debugging
            ])
            ->from(['tp' => 'TicketProfile'])
            ->join('Users', 'tp.user_id = u.id', 'u')
            ->join('Booking', 'tp.booking_id = b.id', 'b')
            ->join('TicketCategory', 'b.ticket_category_id = tc.category_id', 'tc')
            ->join('Event', 'b.event_id = e.id', 'e')
            ->leftJoin('Payment', 'tp.payment_id = p.id', 'p'); // Join with Payment table

        // Apply role-based filters
        if ($role === 'System Admin' || $role === 'Super Admin') {
            // System Admin and Super Admin can view all tickets
        } elseif ($role === 'Event Organizers') {
            $queryBuilder->where('e.UserId = :userId: OR EXISTS (SELECT 1 FROM UserEventAccess uea WHERE uea.event_id = e.id AND uea.user_id = :userId:)', ['userId' => $userId]);
        } elseif ($role === 'Validator') {
            $queryBuilder->where('EXISTS (SELECT 1 FROM UserEventAccess uea WHERE uea.event_id = e.id AND uea.user_id = :userId:)', ['userId' => $userId]);
        } elseif ($role === 'Customer') {
            $queryBuilder->where('tp.user_id = :userId:', ['userId' => $userId]);
        } else {
            return $response->setStatusCode(403, 'Forbidden')
                            ->setJsonContent(['status' => 'error', 'message' => 'Access denied']);
        }

        // Execute the query
        $tickets = $queryBuilder->getQuery()->execute();

        $result = [];
        foreach ($tickets as $ticket) {
            $result[] = [
                'ticket_id' => $ticket->ticket_id,
                'user_name' => $ticket->user_name,
                'category_name' => $ticket->category_name,
                'event_name' => $ticket->event_name,
                'payment_status' => $ticket->payment_status_id,
                //'payment_id' => $ticket->payment_id, // Include for debugging
                //'total_amount' => $ticket->total_amount // Include for debugging
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

    public function getPaidTicketsAction()
    {
        $response = new Response();

        try {
            $userDetails = $this->getUserIdAndRoleFromToken();
            $userId = $userDetails['userId'];
            $role = $userDetails['role'];

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
                    'e.name AS event_name',
                    'p.payment_status_id',
                    'tp.unique_code'
                ])
                ->from(['tp' => 'TicketProfile'])
                ->join('Users', 'tp.user_id = u.id', 'u')
                ->join('Booking', 'tp.booking_id = b.id', 'b')
                ->join('TicketCategory', 'b.ticket_category_id = tc.category_id', 'tc')
                ->join('Event', 'b.event_id = e.id', 'e')
                ->join('Payment', 'b.id = p.booking_id', 'p')
                ->where('p.payment_status_id = 1');

            // Apply role-based filters
            if ($role === 'System Admin' || $role === 'Super Admin') {
                // System Admin and Super Admin can view all paid tickets
            } elseif ($role === 'Event Organizers') {
                $queryBuilder->andWhere('e.UserId = :userId: OR EXISTS (SELECT 1 FROM UserEventAccess uea WHERE uea.event_id = e.id AND uea.user_id = :userId:)', ['userId' => $userId]);
            } elseif ($role === 'Validator') {
                $queryBuilder->andWhere('EXISTS (SELECT 1 FROM UserEventAccess uea WHERE uea.event_id = e.id AND uea.user_id = :userId:)', ['userId' => $userId]);
            } elseif ($role === 'Customer') {
                $queryBuilder->andWhere('tp.user_id = :userId:', ['userId' => $userId]);
            } else {
                return $response->setStatusCode(403, 'Forbidden')
                                ->setJsonContent(['status' => 'error', 'message' => 'Access denied']);
            }

            // Execute the query
            $tickets = $queryBuilder->getQuery()->execute();

            $result = [];
            foreach ($tickets as $ticket) {
                $qrCodeUrl = $this->url->get('ticket-profile/get-qr-code/' . $ticket->unique_code);
                $result[] = [
                    'ticket_id' => $ticket->ticket_id,
                    'user_name' => $ticket->user_name,
                    'category_name' => $ticket->category_name,
                    'event_name' => $ticket->event_name,
                    'qr_code_url' => $qrCodeUrl,
                    'unique_code' => $ticket->unique_code
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
