<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Response;

class RolesController extends Controller
{
    
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

    
    public function updateRoleByUserIdAction($userId)
    {
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
                            'new_role' => $role->role_name
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
}
