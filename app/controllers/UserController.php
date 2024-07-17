<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Response;

class UserController extends Controller
{
    public function createAction()
    {
        $request = $this->request;

        // Disable view rendering (optional)
        $this->view->disable();

        // Check if request content type is JSON
        if ($request->getContentType() === 'application/json') {
            $data = json_decode($request->getRawBody(), true);

            // Extract data from JSON
            $firstName = isset($data['first_name']) ? $data['first_name'] : null;
            $lastName = isset($data['second_name']) ? $data['second_name'] : null;
            $username = isset($data['username']) ? $data['username'] : null;
            $email = isset($data['email']) ? $data['email'] : null;
            $password = isset($data['password']) ? $data['password'] : null;
            $phone = isset($data['phone']) ? $data['phone'] : null;
        } else {
            // Handle form data using $request->getPost()
            $firstName = $request->getPost('first_name', 'string');
            $lastName = $request->getPost('second_name', 'string');
            $username = $request->getPost('username', 'string');
            $email = $request->getPost('email', 'email');
            $password = $request->getPost('password', 'string');
            $phone = $request->getPost('phone', 'string');
        }

        // Create a new user object
        $user = new Users();
        $user->first_name = $firstName;
        $user->second_name = $lastName;
        $user->username = $username;
        $user->email = $email;

        // Securely hash the password using password_hash
        $user->password = password_hash($password, PASSWORD_BCRYPT);  // Use BCRYPT

        $user->phone = $phone;

        // Set default role_id to 1
        $user->role_id = 1;

        // Save the user data
        if ($user->save() === false) {
            $errors = [];
            foreach ($user->getMessages() as $message) {
                $errors[] = $message->getMessage();
            }

            $this->response->setStatusCode(400, 'Bad Request');
            $this->response->setContent(json_encode(['errors' => $errors]));
            return $this->response;
        }

        // User created successfully
        $this->response->setStatusCode(201, 'Created');
        $this->response->setContent(json_encode($user->toArray()));

        return $this->response;
    }
}
