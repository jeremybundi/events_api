<?php
use Phalcon\Mvc\Controller;
use Phalcon\Http\Response;
use Firebase\JWT\JWT;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class LoginController extends Controller
{
    public function loginAction()
    {
        $request = $this->request;
        $jsonData = $request->getRawBody();
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

        // Fetch user from database
        $user = Users::findFirstByUsername($username);

        if (!$user) {
            return $this->response->setStatusCode(401, 'Unauthorized')
                                  ->setContentType('application/json', 'UTF-8')
                                  ->setJsonContent(['error' => 'Invalid username or password']);
        }

        if ($user->is_verified != 1) {
            return $this->response->setStatusCode(401, 'Unauthorized')
                                  ->setContentType('application/json', 'UTF-8')
                                  ->setJsonContent(['error' => 'Please verify your account first']);
        }

        if (!password_verify($password, $user->password)) {
            return $this->response->setStatusCode(401, 'Unauthorized')
                                  ->setContentType('application/json', 'UTF-8')
                                  ->setJsonContent(['error' => 'Invalid username or password']);
        }

        // Generate session ID
        $sessionId = bin2hex(random_bytes(16));

        // Update user's session ID in the database
        $user->session_id = $sessionId;
        if (!$user->save()) {
            return $this->response->setStatusCode(500, 'Internal Server Error')
                                  ->setContentType('application/json', 'UTF-8')
                                  ->setJsonContent(['error' => 'Failed to update session ID']);
        }

        // Generate JWT token with session ID
        $issuedAt = time();
        $expire = $issuedAt + 36000;
        $payload = [
            'iss' => 'YOUR_APP_URL',
            'aud' => 'YOUR_APP_URL',
            'iat' => $issuedAt,
            'exp' => $expire,
            'data' => [
                'userId' => $user->id,
                'role' => $user->getRoleName(),
                'sessionId' => $sessionId,
            ],
        ];

        $config = $this->di->getConfig();
        $secretKey = $config->jwt->secret_key;
        $jwt = JWT::encode($payload, $secretKey, 'HS256');

        return $this->response->setStatusCode(200, 'OK')
                              ->setContentType('application/json', 'UTF-8')
                              ->setJsonContent([
                                  'token' => $jwt,
                                  'role' => $user->getRoleName(),
                              ]);
    }

    private function sendOtpEmail($recipientEmail, $otp)
    {
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->SMTPDebug = 2;
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'your_email@gmail.com'; // Update with your email
            $mail->Password   = 'your_password'; // Update with your email password or app-specific password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Recipients
            $mail->setFrom('your_email@gmail.com', 'Your Name or App Name'); // Update with your email and name
            $mail->addAddress($recipientEmail);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Your OTP Code';
            $mail->Body    = 'Your OTP code is ' . $otp;
            $mail->AltBody = 'Your OTP code is ' . $otp;

            $mail->send();
            error_log("OTP email sent to {$recipientEmail}");
            return true;
        } catch (Exception $e) {
            error_log("Mailer Error: {$mail->ErrorInfo}");
            return false;
        }
    }

    public function verifyOtpAction()
    {
        $request = $this->request;
        $jsonData = $request->getRawBody();
        $data = json_decode($jsonData, true);

        if (!$data) {
            return $this->response->setStatusCode(400, 'Bad Request')
                                  ->setContentType('application/json', 'UTF-8')
                                  ->setJsonContent(['error' => 'Invalid JSON data provided']);
        }

        $username = $data['username'] ?? null;
        $otp = $data['otp'] ?? null;

        if (!$username || !$otp) {
            return $this->response->setStatusCode(400, 'Bad Request')
                                  ->setContentType('application/json', 'UTF-8')
                                  ->setJsonContent(['error' => 'Username and OTP are required']);
        }

        $user = Users::findFirstByUsername($username);

        if (!$user || $user->otp !== $otp || time() > $user->otp_expires_at) {
            return $this->response->setStatusCode(401, 'Unauthorized')
                                  ->setContentType('application/json', 'UTF-8')
                                  ->setJsonContent(['error' => 'Invalid or expired OTP']);
        }

        // Generate JWT token
        $issuedAt = time();
        $expire = $issuedAt + 3600;
        $payload = [
            'iss' => 'YOUR_APP_URL',
            'aud' => 'YOUR_APP_URL',
            'iat' => $issuedAt,
            'exp' => $expire,
            'data' => [
                'userId' => $user->id,
                'role' => $user->getRoleName(),
                'sessionId' => $user->session_id,
            ],
        ];

        $config = $this->di->getConfig();
        $secretKey = $config->jwt->secret_key;
        $jwt = JWT::encode($payload, $secretKey, 'HS256');

        return $this->response->setStatusCode(200, 'OK')
                              ->setContentType('application/json', 'UTF-8')
                              ->setJsonContent([
                                  'token' => $jwt,
                                  'role' => $user->getRoleName(),
                              ]);
    }

    public function someProtectedAction()
    {
        $authHeader = $this->request->getHeader('Authorization');
        if (!$authHeader) {
            return $this->response->setStatusCode(401, 'Unauthorized')
                                  ->setContentType('application/json', 'UTF-8')
                                  ->setJsonContent(['error' => 'Authorization header missing']);
        }

        $jwt = str_replace('Bearer ', '', $authHeader);
        $config = $this->di->getConfig();
        $secretKey = $config->jwt->secret_key;

        try {
            $decoded = JWT::decode($jwt, $secretKey, ['HS256']);
        } catch (Exception $e) {
            return $this->response->setStatusCode(401, 'Unauthorized')
                                  ->setContentType('application/json', 'UTF-8')
                                  ->setJsonContent(['error' => 'Invalid token']);
        }

        $userId = $decoded->data->userId;
        $sessionId = $decoded->data->sessionId;

        $user = Users::findFirstById($userId);

        if (!$user || $user->session_id !== $sessionId) {
            return $this->response->setStatusCode(401, 'Unauthorized')
                                  ->setContentType('application/json', 'UTF-8')
                                  ->setJsonContent(['error' => 'Invalid session']);
        }

        // Proceed with the action
    }
}
