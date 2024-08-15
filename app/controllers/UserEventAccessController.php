<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Response;
use Firebase\JWT\JWT;

class UserEventAccessController extends Controller
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
                'UserId' => $UserId, // Use UserId from token
                'role' => $role,
            ];
        } catch (\Exception $e) {
            error_log('JWT decoding error: ' . $e->getMessage());
            throw new \Exception('Invalid or expired token');
        }
    }

    public function addUserEventAccessAction()
    {
        $response = new Response();

        try {
            $userDetails = $this->validateRole(['System Admin', 'Event Organizers']);
            $role = $userDetails['role'];
            $UserId = $userDetails['UserId']; // Use UserId from token

            $data = $this->request->getJsonRawBody(true);
            if (!isset($data['user_id']) || !isset($data['event_id'])) {
                return $response->setJsonContent([
                    'status' => 'error',
                    'message' => 'Invalid input data'
                ]);
            }

            $userId = $data['user_id'];
            $eventId = $data['event_id'];

            // Check if user exists
            $user = Users::findFirst($userId);
            if (!$user) {
                return $response->setJsonContent([
                    'status' => 'error',
                    'message' => 'User not found'
                ]);
            }

            // Check if event exists
            $event = Event::findFirst($eventId);
            if (!$event) {
                return $response->setJsonContent([
                    'status' => 'error',
                    'message' => 'Event not found'
                ]);
            }

            // Check if user is allowed to add access
            if ($role === 'Event Organizers') {
                // Check if the event was created by the Event Organizer
                if ($event->UserId != $UserId) {
                    return $response->setJsonContent([
                        'status' => 'error',
                        'message' => 'Event does not belong to this organizer'
                    ]);
                }
            }

            // Check if access record already exists
            $existingAccess = UserEventAccess::findFirst([
                'conditions' => 'user_id = ?1 AND event_id = ?2',
                'bind'       => [
                    1 => $userId,
                    2 => $eventId
                ]
            ]);
            if ($existingAccess) {
                return $response->setJsonContent([
                    'status' => 'error',
                    'message' => 'User already has access to this event'
                ]);
            }

            // Add access record
            $access = new UserEventAccess();
            $access->user_id = $userId;
            $access->event_id = $eventId;

            if (!$access->save()) {
                return $response->setJsonContent([
                    'status' => 'error',
                    'message' => 'Failed to add access: ' . implode(', ', $access->getMessages())
                ]);
            }

            return $response->setJsonContent([
                'status' => 'success',
                'message' => 'User access to event added successfully'
            ]);

        } catch (\Exception $e) {
            return $response->setStatusCode(401, 'Unauthorized')
                            ->setJsonContent([
                                'status' => 'error',
                                'message' => $e->getMessage()
                            ]);
        }
    }
}
