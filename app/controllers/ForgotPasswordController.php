<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Response;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class ForgotPasswordController extends Controller
{
    public function sendOtpAction()
       {
        $request = $this->request;
        $jsonData = $request->getRawBody();
        $data = json_decode($jsonData, true);

        if (!$data || !isset($data['username'])) {
            return $this->response->setStatusCode(400, 'Bad Request')
                                  ->setContentType('application/json', 'UTF-8')
                                  ->setJsonContent(['error' => 'Username is required']);
        }

        $username = $data['username'];

        error_log(json_encode($data)); // For debugging
        error_log($username); // For debugging

        $user = Users::findFirstByUsername($username);

   
           if (!$user) {
               return $this->response->setStatusCode(404, 'Not Found')
                                     ->setContentType('application/json', 'UTF-8')
                                     ->setJsonContent(['error' => 'User not found']);
           }
   
           // Generate OTP
           $otp = rand(100000, 999999);
           $user->otp = $otp;
           $user->otp_expires_at = time() + 300;
           $user->save();
   
           // Send OTP email
           if (!$this->sendOtpEmail($user->email, $otp)) {
               return $this->response->setStatusCode(500, 'Internal Server Error')
                                     ->setContentType('application/json', 'UTF-8')
                                     ->setJsonContent(['error' => 'Failed to send OTP email']);
           }
   
           return $this->response->setStatusCode(200, 'OK')
                                 ->setContentType('application/json', 'UTF-8')
                                 ->setJsonContent(['message' => 'OTP sent to your email']);
       }
   

    public function verifyOtpAndResetPasswordAction()
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
        $newPassword = $data['newPassword'] ?? null;

        if (!$username || !$otp || !$newPassword) {
            return $this->response->setStatusCode(400, 'Bad Request')
                                  ->setContentType('application/json', 'UTF-8')
                                  ->setJsonContent(['error' => 'Username, OTP, and new password are required']);
        }

        $user = Users::findFirstByUsername($username);

        if (!$user) {
            return $this->response->setStatusCode(404, 'Not Found')
                                  ->setContentType('application/json', 'UTF-8')
                                  ->setJsonContent(['error' => 'User not found']);
        }

        if ($user->otp !== $otp || time() > $user->otp_expires_at) {
            return $this->response->setStatusCode(401, 'Unauthorized')
                                  ->setContentType('application/json', 'UTF-8')
                                  ->setJsonContent(['error' => 'Invalid or expired OTP']);
        }

        // Reset password
        $user->password = password_hash($newPassword, PASSWORD_BCRYPT);
        $user->otp = null;
        $user->otp_expires_at = null;

        if (!$user->save()) {
            return $this->response->setStatusCode(500, 'Internal Server Error')
                                  ->setContentType('application/json', 'UTF-8')
                                  ->setJsonContent(['error' => 'Failed to reset password']);
        }

        return $this->response->setStatusCode(200, 'OK')
                              ->setContentType('application/json', 'UTF-8')
                              ->setJsonContent(['message' => 'Password reset successfully']);
    }

    private function sendOtpEmail($recipientEmail, $otp)
    {
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->SMTPDebug = 0; // Set to 2 for debugging
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'jeremybundi45@gmail.com'; // Replace with your email
            $mail->Password   = 'mwpf auuq oolg pdwm'; // Replace with your password
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
}
