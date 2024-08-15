<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Phalcon\Mvc\Controller;
use Phalcon\Http\Response;

class ValidTicketsController extends Controller
{
    public function initialize()
    {
        $this->view->disable();
    }

    private function checkRole($allowedRoles)
    {
        $authHeader = $this->request->getHeader('Authorization');
        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return false;
        }

        $token = $matches[1];
        try {
            $config = $this->di->getConfig();
            $secretKey = $config->jwt->secret_key;
            $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));

            return in_array($decoded->data->role, $allowedRoles);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function validateAction()
    {
        $response = new Response();

        // Check user role
        if (!$this->checkRole(['Validator', 'Event Organizers', 'System Admin'])) {
            return $response->setStatusCode(403, 'Forbidden')
                            ->setJsonContent(['status' => 'error', 'message' => 'Access denied']);
        }

        // Extract token from the headers
        $authHeader = $this->request->getHeader('Authorization');
        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return $response->setStatusCode(401, 'Unauthorized')
                            ->setJsonContent(['status' => 'error', 'message' => 'Token not provided or invalid']);
        }

        $token = $matches[1];

        try {
            // Decode the token
            $config = $this->di->getConfig();
            $secretKey = $config->jwt->secret_key;
            $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));
        } catch (\Exception $e) {
            return $response->setStatusCode(401, 'Unauthorized')
                            ->setJsonContent(['status' => 'error', 'message' => 'Invalid token']);
        }

        $userId = $decoded->data->userId;

        // Get ticket ID from the URL
        $ticketId = $this->dispatcher->getParam('id');
        if (!$ticketId) {
            return $response->setJsonContent(['status' => 'error', 'message' => 'Ticket ID is required']);
        }

        // Find the ticket profile
        $ticketProfile = TicketProfile::findFirst($ticketId);
        if (!$ticketProfile) {
            return $response->setJsonContent(['status' => 'error', 'message' => 'Ticket not found']);
        }

        // Validate the ticket
        $ticketProfile->valid_status = 1;  

        if (!$ticketProfile->save()) {
            return $response->setJsonContent(['status' => 'error', 'message' => 'Failed to validate ticket']);
        }

        // Find the associated ticket category using category_id
        $ticketCategory = TicketCategory::findFirst([
            'conditions' => 'category_id = :category_id:',
            'bind'       => ['category_id' => $ticketProfile->category_id]
        ]);

        if ($ticketCategory) {
            // Increment validated_tickets
            $ticketCategory->validated_tickets += 1;
            if (!$ticketCategory->save()) {
                return $response->setJsonContent(['status' => 'error', 'message' => 'Failed to update ticket category']);
            }
        } else {
            return $response->setJsonContent(['status' => 'error', 'message' => 'Ticket category not found']);
        }

        return $response->setJsonContent([
            'status' => 'success',
            'message' => 'Ticket validated successfully',
        ]);
    }
}
