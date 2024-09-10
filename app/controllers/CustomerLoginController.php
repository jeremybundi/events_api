<?php
use Phalcon\Mvc\Controller;
use Phalcon\Http\Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class CustomerLoginController extends Controller
{
    private $otpExpiryTime;
    private $jwtSecretKey;

    public function initialize()
    {
        $this->otpExpiryTime = 3000; 
        $this->jwtSecretKey = $this->config->jwt->secret_key; 
    }

    public function sendOtpAction()
    {
        $response = new Response();

        // Manually decode the JSON body
        $rawBody = $this->request->getRawBody();
        $postData = json_decode($rawBody, true);

        if (empty($postData['email'])) {
            return $response->setJsonContent(['status' => 'error', 'message' => 'Email is required']);
        }

        $email = $postData['email'];
        $customer = Customers::findFirstByEmail($email);

        if (!$customer) {
            return $response->setJsonContent(['status' => 'error', 'message' => 'Customer not found']);
        }

        // Generate OTP
        $otp = random_int(100000, 999999);
        $customer->otp_code = $otp;
        $customer->otp_expiry = date('Y-m-d H:i:s', time() + $this->otpExpiryTime);

        if (!$customer->save()) {
            return $response->setJsonContent(['status' => 'error', 'message' => 'Failed to generate OTP']);
        }

        // Send OTP to email using PHPMailer
        if (!$this->sendOtpEmail($email, $otp)) {
            return $response->setJsonContent(['status' => 'error', 'message' => 'Failed to send OTP email']);
        }

        return $response->setJsonContent(['status' => 'success', 'message' => 'OTP sent to email']);
    }

    public function verifyOtpAction()
    {
        $response = new Response();
    
        // Manually decode the JSON body
        $rawBody = $this->request->getRawBody();
        $postData = json_decode($rawBody, true);
    
        if (empty($postData['email']) || empty($postData['otp'])) {
            return $response->setJsonContent(['status' => 'error', 'message' => 'Email and OTP are required']);
        }
    
        $email = $postData['email'];
        $otp = $postData['otp'];
    
        $customer = Customers::findFirstByEmail($email);
    
        if (!$customer) {
            return $response->setJsonContent(['status' => 'error', 'message' => 'Customer not found']);
        }
    
        // Check if OTP is correct and not expired
        if ($customer->otp_code != $otp || strtotime($customer->otp_expiry) < time()) {
            return $response->setJsonContent(['status' => 'error', 'message' => 'Invalid or expired OTP']);
        }
    
        // OTP is valid; generate JWT token
        $token = $this->generateJwtToken($customer);
    
        // Clear OTP after successful verification
        $customer->otp_code = null;
        $customer->otp_expiry = null;
        $customer->save();
    
        return $response->setJsonContent([
            'status' => 'success',
            'message' => 'OTP verified successfully',
            'token' => $token,
            'email' => $customer->email
        ]);
    }
    

    private function generateJwtToken(Customers $customer)
    {
        $payload = [
            'iss' => 'your_website.com', 
            'aud' => 'your_website.com',
            'iat' => time(),
            'exp' => time() + 3600, 
            'sub' => $customer->id, 
            'email' => $customer->email
        ];

        return JWT::encode($payload, $this->jwtSecretKey, 'HS256');
    }

    private function sendOtpEmail($email, $otp)
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
            $mail->addAddress($email);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Your OTP Code';
            $mail->Body = "Your OTP code is: $otp";

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("PHPMailer Error: " . $mail->ErrorInfo);
            return false;
        }
    }
}
