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

        error_log(json_encode($data)); 
        error_log($username); 

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

        // Send OTP via email
        if (!$this->sendOtpEmail($user->email, $otp)) {
            return $this->response->setStatusCode(500, 'Internal Server Error')
                                  ->setContentType('application/json', 'UTF-8')
                                  ->setJsonContent(['error' => 'Failed to send OTP email']);
        }

        // Send OTP via SMS
        if (!$this->sendOtpSms($user->phone, $otp)) {
            return $this->response->setStatusCode(500, 'Internal Server Error')
                                  ->setContentType('application/json', 'UTF-8')
                                  ->setJsonContent(['error' => 'Failed to send OTP SMS']);
        }

        return $this->response->setStatusCode(200, 'OK')
                              ->setContentType('application/json', 'UTF-8')
                              ->setJsonContent(['message' => 'OTP sent to your email and phone']);
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

        // Reset password and update verification status
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
            $mail->SMTPDebug = 2; 
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

    private function sendOtpSms($recipientPhone, $otp)
    {
        $apiKey = '72fb66391d11db232f83555ff1371e3d'; 
        $shortCode = 'VasPro'; 
        $message = 'Your OTP code is ' . $otp;
        $callbackURL = ''; 

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.vaspro.co.ke/v3/BulkSMS/api/create",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode(array(
                "apiKey" => $apiKey,
                "shortCode" => $shortCode,
                "message" => $message,
                "recipient" => $recipientPhone,
                "callbackURL" => $callbackURL,
                "enqueue" => 0
            )),
            CURLOPT_HTTPHEADER => array(
                "content-type: application/json",
            ),
        ));
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            error_log("cURL Error #:" . $err);
            return false;
        } else {
            return true;
        }
    }
}
