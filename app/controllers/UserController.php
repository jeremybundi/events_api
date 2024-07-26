<?php
use Phalcon\Mvc\Controller;
use Phalcon\Http\Response;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class UserController extends Controller
{
    public function createAction()
    {
        $request = $this->request;

        $this->view->disable();

        if ($request->getContentType() === 'application/json') {
            $data = json_decode($request->getRawBody(), true);

            $firstName = isset($data['first_name']) ? $data['first_name'] : null;
            $lastName = isset($data['second_name']) ? $data['second_name'] : null;
            $username = isset($data['username']) ? $data['username'] : null;
            $email = isset($data['email']) ? $data['email'] : null;
            $password = isset($data['password']) ? $data['password'] : null;
            $phone = isset($data['phone']) ? $data['phone'] : null;
        } else {
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
        $user->password = password_hash($password, PASSWORD_BCRYPT);
        $user->phone = $phone;
        $user->role_id = 1;

        // Generate OTP
        $otp = rand(100000, 999999);
        $user->otp = $otp;
        $user->otp_expires_at = time() + 300;
        $user->is_verified = 0;

        if ($user->save() === false) {
            $errors = [];
            foreach ($user->getMessages() as $message) {
                $errors[] = $message->getMessage();
            }

            $this->response->setStatusCode(400, 'Bad Request');
            $this->response->setContent(json_encode(['errors' => $errors]));
            return $this->response;
        }

        // Send OTP to user's email
        if (!$this->sendOtpEmail($user->email, $otp)) {
            $this->response->setStatusCode(500, 'Internal Server Error');
            $this->response->setContent(json_encode(['error' => 'Failed to send OTP email']));
            return $this->response;
        }

        $this->response->setStatusCode(201, 'Created');
        $this->response->setContent(json_encode(['message' => 'User created. OTP sent to your email.', 'user' => $user->toArray()]));

        return $this->response;
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

        // Mark user as verified
        $user->is_verified = 1;
        $user->otp = null;
        $user->otp_expires_at = null;

        if (!$user->save()) {
            return $this->response->setStatusCode(500, 'Internal Server Error')
                                  ->setContentType('application/json', 'UTF-8')
                                  ->setJsonContent(['error' => 'Failed to update user verification status']);
        }

        return $this->response->setStatusCode(200, 'OK')
                              ->setContentType('application/json', 'UTF-8')
                              ->setJsonContent(['message' => 'User verified successfully.']);
    }
   
}
