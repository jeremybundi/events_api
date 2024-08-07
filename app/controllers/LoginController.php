<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Response;
use Firebase\JWT\JWT;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class LoginController extends Controller
{
    public function initialize()
    {
        // Start the session
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }

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

        // Check if the user is verified
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

        // Check if the email is valid
        if (empty($user->email)) {
            return $this->response->setStatusCode(400, 'Bad Request')
                                  ->setContentType('application/json', 'UTF-8')
                                  ->setJsonContent(['error' => 'Email not found for the user']);
        }

        // Generate and update OTP
        $otp = rand(100000, 999999);
        $user->otp = $otp;
        $user->otp_expires_at = time() + 300;
        if (!$user->save()) {
            return $this->response->setStatusCode(500, 'Internal Server Error')
                                  ->setContentType('application/json', 'UTF-8')
                                  ->setJsonContent(['error' => 'Failed to save OTP']);
        }

        // Send OTP to user's email
        if (!$this->sendOtpEmail($user->email, $otp)) {
            return $this->response->setStatusCode(500, 'Internal Server Error')
                                  ->setContentType('application/json', 'UTF-8')
                                  ->setJsonContent(['error' => 'Failed to send OTP email']);
        }

        // Set session data after OTP is sent
        $_SESSION['username'] = $username;
        $_SESSION['otp_sent_time'] = time();

        return $this->response->setStatusCode(200, 'OK')
                              ->setContentType('application/json', 'UTF-8')
                              ->setJsonContent(['message' => 'OTP sent to your email.']);
    }

    private function sendOtpEmail($recipientEmail, $otp)
    {
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->SMTPDebug = 0; 
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'jeremybundi45@gmail.com';
            $mail->Password   = 'mwpfauuqoolgpdwm'; 
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Recipients
            $mail->setFrom('jeremybundi45@gmail.com', 'Legacy');
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
        $expire = $issuedAt + 300;
        $payload = [
            'iss' => 'YOUR_APP_URL',
            'aud' => 'YOUR_APP_URL',
            'iat' => $issuedAt,
            'exp' => $expire,
            'data' => [
                'userId' => $user->id,
                'role' => $user->getRoleName(),
            ],
        ];

        $config = $this->di->getConfig();
        $secretKey = $config->jwt->secret_key;
        $jwt = JWT::encode($payload, $secretKey, 'HS256');

        // Set session data after successful login
        $_SESSION['user_id'] = $user->id;
        $_SESSION['role'] = $user->getRoleName();
        $_SESSION['token'] = $jwt;

        return $this->response->setStatusCode(200, 'OK')
                              ->setContentType('application/json', 'UTF-8')
                              ->setJsonContent([
                                  'token' => $jwt,
                                  'role' => $user->getRoleName(),
                                  'name' => $user->first_name,
                                  'session_id' => session_id(), 
                              ]);
    }
}
