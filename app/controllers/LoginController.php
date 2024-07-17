<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Response;
use Firebase\JWT\JWT;

class LoginController extends Controller
{
    public function loginAction()
    {
        $request = $this->request;

        // Assuming the request body is JSON
        $jsonData = $request->getRawBody();

        // Decode the JSON data
        $data = json_decode($jsonData, true);

        if (!$data) {
            return $this->response->setStatusCode(400, 'Bad Request')
                                  ->setContentType('application/json', 'UTF-8')
                                  ->setJsonContent(['error' => 'Invalid JSON data provided']);
        }

        $username = $data['username'] ?? null;
        $password = $data['password'] ?? null;

        if (!$username || !$password) {
            return $this->response->setStatusCode(400, 'Bad Request')
                                  ->setContentType('application/json', 'UTF-8')
                                  ->setJsonContent(['error' => 'Username and password are required']);
        }

        // Validate username and password (implement your validation logic)
        $user = Users::findFirstByUsername($username);

        if (!$user || !password_verify($password, $user->password)) {
            return $this->response->setStatusCode(401, 'Unauthorized')
                                  ->setContentType('application/json', 'UTF-8')
                                  ->setJsonContent(['error' => 'Invalid username or password']);
        }

        $issuedAt = time();
        $expire = $issuedAt + 3600; // One hour expiration

        $payload = [
            'iss' => 'YOUR_APP_URL',
            'aud' => 'YOUR_APP_URL',
            'iat' => $issuedAt,
            'exp' => $expire,
            'data' => [
                'userId' => $user->id,
                'role' => $user->getRoleName(), // Retrieve the role name
            ],
        ];

        // Load the configuration
        $config = $this->di->getConfig();

        // Retrieve the secret key
        $secretKey = $config->jwt->secret_key;

        $jwt = JWT::encode($payload, $secretKey, 'HS256');

        return $this->response->setStatusCode(200, 'OK')
                              ->setContentType('application/json', 'UTF-8')
                              ->setJsonContent([
                                  'token' => $jwt,
                                  'role' => $user->getRoleName(), 
                              ]);
    }
}
