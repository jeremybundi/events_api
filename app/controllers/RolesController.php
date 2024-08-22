<?php
use Phalcon\Mvc\Controller;
use Phalcon\Http\Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class RolesController extends Controller
{
    private function getJwtSecret()
    {
        $config = $this->getDI()->getConfig();
        $secret = $config->jwt->secret_key; 
        
        if (!$secret) {
            throw new \Exception('JWT Secret is not set in the configuration.');
        }

        return $secret;
    }

    private function getRoleAndUserIdFromToken()
    {
        $token = $this->request->getHeader('Authorization');
        $token = str_replace('Bearer ', '', $token);
        
        $jwtSecret = $this->getJwtSecret(); 

        try {
            $decoded = JWT::decode($token, new Key($jwtSecret, 'HS256'));
            return [
                'role' => $decoded->data->role,
                'userId' => $decoded->data->userId
            ]; 
        } catch (\Exception $e) {
            // Log the exception for debugging
            error_log('JWT Decode Error: ' . $e->getMessage());
            return null;
        }
    }

    // Map role names to role IDs
    private function mapRoleNameToId($roleName)
    {
        $roleMap = [
            'Super Admin' => 5,
            'System Admin' => 3,
            'Event Organizers' => 2,
            'Validator' => 4,
            'Customer' => 1
        ];

        return $roleMap[$roleName] ?? null;
    }

    // Get role by user ID
    public function getRoleByUserIdAction($userId)
    {
        $user = Users::findFirstById($userId);

        if ($user) {
            $response = new Response();
            $response->setJsonContent([
                'status' => 'success',
                'data' => [
                    'user_id' => $user->id,
                    'username' => $user->username,
                    'role' => $user->getRoleName()
                ]
            ]);
        } else {
            $response = new Response();
            $response->setJsonContent([
                'status' => 'error',
                'message' => 'User not found'
            ]);
        }

        return $response;
    }

    // Update role by user ID with permission checks
    public function updateRoleByUserIdAction($userId)
    {
        // Get the current user's role and ID from the token
        $userDetails = $this->getRoleAndUserIdFromToken();
        if (!$userDetails) {
            $response = new Response();
            $response->setJsonContent([
                'status' => 'error',
                'message' => 'Invalid token or role'
            ]);
            return $response;
        }
        
        $currentRoleName = $userDetails['role'];
        $currentUserId = $userDetails['userId'];
        $currentRoleId = $this->mapRoleNameToId($currentRoleName);

        if (!$currentRoleId) {
            $response = new Response();
            $response->setJsonContent([
                'status' => 'error',
                'message' => 'Invalid token or role'
            ]);
            return $response;
        }

       // Ensure the user cannot update their own role
        if ($userId == $currentUserId) {
            $response = new Response();
            $response->setJsonContent([
                'status' => 'error',
                'message' => 'You cannot update your own role'
            ]);
            return $response;
        }

        // Get the JSON data from the request
        $request = $this->request->getJsonRawBody();

        // Check if role_id is provided
        if (!isset($request->role_id)) {
            $response = new Response();
            $response->setJsonContent([
                'status' => 'error',
                'message' => 'Role ID is required'
            ]);
            return $response;
        }

        // Role update logic based on the current user's role
        if ($currentRoleId === 5) { 
            // Super Admin can assign any role
        } elseif ($currentRoleId === 3) { 
            if (!in_array($request->role_id, [2, 4])) {
                $response = new Response();
                $response->setJsonContent([
                    'status' => 'error',
                    'message' => 'System Admin can only assign Event Organizer or Validator roles'
                ]);
                return $response;
            }
        } elseif ($currentRoleId === 2) { 
            // Allow Event Organizer to promote Customer (role ID 1) to Validator (role ID 4)
            $targetUserRole = $this->getRoleByUserId($userId);
            if ($request->role_id !== 4 || $targetUserRole !== 'Customer') {
                $response = new Response();
                $response->setJsonContent([
                    'status' => 'error',
                    'message' => 'Event Organizer can only promote a Customer to Validator'
                ]);
                return $response;
            }
        } else {
            $response = new Response();
            $response->setJsonContent([
                'status' => 'error',
                'message' => 'You do not have permission to update roles'
            ]);
            return $response;
        }

        // Find the user by ID
        $user = Users::findFirstById($userId);

        if ($user) {
            // Check if the role exists
            $role = Roles::findFirstById($request->role_id);

            if ($role) {
                // Update the user's role
                $user->role_id = $request->role_id;

                if ($user->save()) {
                    $response = new Response();
                    $response->setJsonContent([
                        'status' => 'success',
                        'message' => 'Role updated successfully',
                        'data' => [
                            'user_id' => $user->id,
                            'username' => $user->username,
                            'new role' => $role->role_name
                        ]
                    ]);
                } else {
                    $response = new Response();
                    $response->setJsonContent([
                        'status' => 'error',
                        'message' => 'Failed to update role',
                        'errors' => $user->getMessages()
                    ]);
                }
            } else {
                $response = new Response();
                $response->setJsonContent([
                    'status' => 'error',
                    'message' => 'Role not found'
                ]);
            }
        } else {
            $response = new Response();
            $response->setJsonContent([
                'status' => 'error',
                'message' => 'User not found'
            ]);
        }

        return $response;
    }

    private function getRoleByUserId($userId)
    {
        $user = Users::findFirstById($userId);
        return $user ? $user->getRoleName() : null;
    }
}
