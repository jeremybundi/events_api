<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AnalysisController extends Controller
{
    public function initialize()
    {
        $this->view->disable();
    }

    private function validateRole(array $allowedRoles)
    {
        try {
            // Retrieve the Authorization header
            $authHeader = $this->request->getHeader('Authorization');
            if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
                throw new \Exception('Authorization header not found or format invalid');
            }

            // Extract the JWT from the Authorization header
            $jwt = substr($authHeader, 7);
            if (!$jwt) {
                throw new \Exception('Invalid authorization token format');
            }

            // Retrieve the secret key from the configuration
            $config = $this->di->getConfig();
            $secretKey = $config->jwt->secret_key;

            // Decode the JWT
            $decoded = JWT::decode($jwt, new Key($secretKey, 'HS256'));

            // Check if the role is allowed
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
            // Log the specific error for debugging
            error_log('JWT decoding error: ' . $e->getMessage());
            throw new \Exception('Invalid or expired token' .   $e->getMessage ());
        }
    }

    public function eventStatisticsAction()
{
    $response = new Response();

    try {
        $userDetails = $this->validateRole(['Super Admin', 'System Admin', 'Event Organizers']);
        $role = $userDetails['role'];
        $UserId = $userDetails['UserId'];

        $statistics = [];

        // Fetch statistics for all events if the user is an admin
        if (in_array($role, ['Super Admin', 'System Admin'])) {
            $events = Event::find();
        }

        // Fetch statistics for events created by the Event Organizer
        if ($role === 'Event Organizers') {
            $events = Event::find([
                'conditions' => 'UserId = ?1',
                'bind'       => [
                    1 => $UserId
                ]
            ]);
        }

        foreach ($events as $event) {
            $statistics[$event->id] = $this->getEventStatistics($event);
        }

        return $response->setJsonContent([
            'status' => 'success',
            'data' => $statistics
        ]);

    } catch (\Exception $e) {
        return $response->setJsonContent([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
}

   
    public function paymentAction()
    {
        $response = new Response();
    
        try {
            $userDetails = $this->validateRole(['Super Admin', 'System Admin']);
            $role = $userDetails['role'];
            $UserId = $userDetails['UserId'];
    
            // Fetch payment summary data
            $payments = Payment::find();
    
            // Initialize totals
            $totalPaidAmount = 0;
            $totalPendingAmount = 0;
            $totalMpesaAmount = 0;
            $totalCardAmount = 0;
            $successfulPayments = 0;
            $failedPayments = 0;
    
            // Calculate totals based on payment method and status
            foreach ($payments as $payment) {
                if ($payment->payment_status_id == 1) { 
                    $totalPaidAmount += $payment->total_amount;
                    $successfulPayments++;
    
                    if ($payment->payment_method === 'mpesa') {
                        $totalMpesaAmount += $payment->total_amount;
                    } elseif ($payment->payment_method === 'card') {
                        $totalCardAmount += $payment->total_amount;
                    }
                } else { 
                    $totalPendingAmount += $payment->total_amount;
                    $failedPayments++;
                }
            }
    
            // Prepare the summary
            $summary = [
                'total_payments' => count($payments),
                'total_paid_amount' => $totalPaidAmount,
                'total_pending_amount' => $totalPendingAmount,
                'successful_payments' => $successfulPayments,
                'failed_payments' => $failedPayments,
                'total_mpesa_amount' => $totalMpesaAmount,
                'total_card_amount' => $totalCardAmount,
            ];
    
            return $response->setJsonContent([
                'status' => 'success',
                'data' => $summary
            ]);
    
        } catch (\Exception $e) {
            return $response->setJsonContent([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }
    

    public function getByDurationAction()
{
    $response = new Response();

    try {
        $userDetails = $this->validateRole(['Super Admin', 'System Admin', 'Event Organizers']);
        $role = $userDetails['role'];
        $UserId = $userDetails['UserId'];

        // Get start_date and end_date from query parameters
        $startDate = $this->request->getQuery('start_date', 'string');
        $endDate = $this->request->getQuery('end_date', 'string');

        if (!$startDate || !$endDate) {
            throw new \Exception('start_date and end_date parameters are required');
        }

        // Convert dates to a format suitable for database querying
        $startDate = date('Y-m-d H:i:s', strtotime($startDate));
        $endDate = date('Y-m-d H:i:s', strtotime($endDate));

        // Initialize the conditions and bind parameters for the query
        $conditions = 'tp.created_at BETWEEN :start: AND :end:';
        $bindParams = [
            'start' => $startDate,
            'end'   => $endDate,
        ];

        // Additional filtering if the user is an Event Organizer
        if ($role === 'Event Organizers') {
            $conditions .= ' AND tp.UserId = :userId:';
            $bindParams['userId'] = $UserId;
        }

        // Use a query builder to join the TicketProfile and TicketCategory tables
        $tickets = $this->modelsManager->createBuilder()
            ->from(['tp' => 'TicketProfile'])
            ->join('TicketCategory', 'tp.category_id = tc.category_id', 'tc')
            ->where($conditions, $bindParams)
            ->columns([
                'tp.id as ticket_id',
                'tc.event_id as event_id',
                'tc.price as price',
                'tp.created_at as created_at',
            ])
            ->getQuery()
            ->execute();

        // Summarize the data
        $summary = [
            'total_tickets_booked' => count($tickets),
            'total_revenue' => array_sum(array_column($tickets->toArray(), 'price')),
            'tickets' => []
        ];

        foreach ($tickets as $ticket) {
            $summary['tickets'][] = [
                'ticket_id' => $ticket->ticket_id,
                'event_id' => $ticket->event_id,
                'price' => $ticket->price,
                'created_at' => $ticket->created_at,
            ];
        }

        return $response->setJsonContent([
            'status' => 'success',
            'data' => $summary
        ]);

    } catch (\Exception $e) {
        return $response->setJsonContent([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
}
private function getEventStatistics($event)
{
    $totalTicketsSold = 0;
    $totalRevenue = 0;

    // Fetch all ticket categories for the event
    $ticketCategories = TicketCategory::find([
        'conditions' => 'event_id = ?1',
        'bind'       => [
            1 => $event->id
        ]
    ]);

    // Calculate total tickets sold and total revenue for the event
    foreach ($ticketCategories as $ticketCategory) {
        $ticketsSold = $ticketCategory->purchased_tickets;
        $revenue = $ticketsSold * $ticketCategory->price;

        $totalTicketsSold += $ticketsSold;
        $totalRevenue += $revenue;
    }

    // Return event statistics
    return [
        'event_name' => $event->name,
        'total_tickets_sold' => $totalTicketsSold,
        'total_revenue' => $totalRevenue,
        'date' => $event->date,
    ];
}


}
