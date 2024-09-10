<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class ValidTicketsController extends Controller
{
    public function initialize()
    {
        $this->view->disable();
    }

    private function checkRole($allowedRoles)
    {
        $authHeader = $this->request->getHeader('Authorization');
        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return false;
        }

        $token = $matches[1];
        try {
            $config = $this->di->getConfig();
            $secretKey = $config->jwt->secret_key;
            $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));

            return in_array($decoded->data->role, $allowedRoles);
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getUserDetails()
    {
        $authHeader = $this->request->getHeader('Authorization');
        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            throw new \Exception('Token not provided or invalid');
        }

        $token = $matches[1];
        try {
            $config = $this->di->getConfig();
            $secretKey = $config->jwt->secret_key;
            $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));

            return [
                'UserId' => $decoded->data->userId,
                'role' => $decoded->data->role,
            ];
        } catch (\Exception $e) {
            throw new \Exception('Invalid token');
        }
    }

    private function hasEventAccess($userId, $eventId)
    {
        return UserEventAccess::findFirst([
            'conditions' => 'user_id = ?1 AND event_id = ?2',
            'bind'       => [
                1 => $userId,
                2 => $eventId
            ]
        ]);
    }

    private function sendEmail($recipientEmail, $subject, $body)
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
            $mail->Subject = $subject;
            $mail->Body    = nl2br($body); 
            $mail->AltBody = $body;

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Mailer Error: {$mail->ErrorInfo}");
            return false;
        }
    }

    public function validateAction()
    {
        $response = new Response();
    
        // Check user role
        if (!$this->checkRole(['Validator', 'Event Organizers', 'System Admin', 'Super Admin'])) {
            return $response->setStatusCode(403, 'Forbidden')
                            ->setJsonContent(['status' => 'error', 'message' => 'Access denied']);
        }
    
        $userDetails = $this->getUserDetails();
        $userId = $userDetails['UserId'];
        $role = $userDetails['role'];
    
        // Get ticket ID from the URL
        $ticketId = $this->dispatcher->getParam('id');
        if (!$ticketId) {
            return $response->setJsonContent(['status' => 'error', 'message' => 'Ticket ID is required']);
        }
    
        // Find the ticket profile
        $ticketProfile = TicketProfile::findFirst($ticketId);
        if (!$ticketProfile) {
            return $response->setJsonContent(['status' => 'error', 'message' => 'Ticket not found']);
        }
    
        // Check payment status
        $payment = Payment::findFirst([
            'conditions' => 'id = ?1',
            'bind'       => [1 => $ticketProfile->payment_id]
        ]);
    
        if (!$payment || $payment->payment_status_id != 1) {
            return $response->setJsonContent(['status' => 'error', 'message' => 'Payment not completed']);
        }
    
        // Get event ID from booking
        $booking = Booking::findFirst($ticketProfile->booking_id);
        if (!$booking) {
            return $response->setJsonContent(['status' => 'error', 'message' => 'Booking not found']);
        }
        $eventId = $booking->event_id;
    
        // Check if the user has the right to validate the ticket
        if ($role === 'Event Organizers') {
            $event = Event::findFirst($eventId);
            if (!$event || ($event->UserId != $userId && !$this->hasEventAccess($userId, $eventId))) {
                return $response->setJsonContent(['status' => 'error', 'message' => 'You do not have permission to validate this ticket']);
            }
        } elseif ($role === 'Validator') {
            if (!$this->hasEventAccess($userId, $eventId)) {
                return $response->setJsonContent(['status' => 'error', 'message' => 'You do not have permission to validate this ticket']);
            }
        }
    
        // Validate the ticket
        $ticketProfile->valid_status = 1;
    
        if (!$ticketProfile->save()) {
            return $response->setJsonContent(['status' => 'error', 'message' => 'Failed to validate ticket']);
        }
    
        // Find the associated ticket category using category_id
        $ticketCategory = TicketCategory::findFirst([
            'conditions' => 'category_id = :category_id:',
            'bind'       => ['category_id' => $ticketProfile->category_id]
        ]);
    
        if ($ticketCategory) {
            $ticketCategory->validated_tickets += 1;
            if (!$ticketCategory->save()) {
                return $response->setJsonContent(['status' => 'error', 'message' => 'Failed to update ticket category']);
            }
        } else {
            return $response->setJsonContent(['status' => 'error', 'message' => 'Ticket category not found']);
        }
    
        // Send email to user with the unique code
        $customer = Customers::findFirst($ticketProfile->customer_id);
        if ($customer) {
            $emailContent = "Dear customer,<br><br>";
            $emailContent .= "Your ticket has been successfully validated. Ticket Number:{$ticketProfile->id}<br>";
            $emailContent .= "Here is your unique ticket code: <strong>{$ticketProfile->unique_code}</strong><br>";
            $emailContent .= "Please keep this code safe as it will be required for entry.<br><br>";
            $emailContent .= "Thank you for choosing our event.<br><br>";
            $emailContent .= "Best Regards,<br>";
            $emailContent .= "Legacy events";
    
            if (!$this->sendEmail($customer->email, "Ticket Validation Successful", $emailContent)) {
                return $response->setJsonContent(['status' => 'success', 'message' => 'Ticket validated successfully, but failed to send email notification']);
            }
        }
    
        return $response->setJsonContent([
            'status' => 'success',
            'message' => 'Ticket validated successfully',
        ]);
    }

    public function redeemAction()
    {
        $response = new Response();

        // Check user role
        if (!$this->checkRole(['Validator', 'Event Organizers', 'System Admin', 'Super Admin'])) {
            return $response->setStatusCode(403, 'Forbidden')
                            ->setJsonContent(['status' => 'error', 'message' => 'Access denied']);
        }

        // Get the unique_code from the URL
        $uniqueCode = $this->dispatcher->getParam('unique_code');

        if (!$uniqueCode) {
            return $response->setJsonContent(['status' => 'error', 'message' => 'Unique code is required']);
        }

        // Find the ticket profile by unique code
        $ticketProfile = TicketProfile::findFirst([
            'conditions' => 'unique_code = :unique_code:',
            'bind'       => ['unique_code' => $uniqueCode]
        ]);

        if (!$ticketProfile) {
            return $response->setJsonContent(['status' => 'error', 'message' => 'Invalid unique code']);
        }

        // Check payment status
        $payment = Payment::findFirst([
            'conditions' => 'id = ?1',
            'bind'       => [1 => $ticketProfile->payment_id]
        ]);

        if (!$ticketProfile || $ticketProfile->valid_status != 1) {
            return $response->setJsonContent(['status' => 'error', 'message' => 'Ticket has not been Validated, please validate first']);
        }

        // Check if the ticket is already redeemed
        if ($ticketProfile->redeemed_ticket) {
            return $response->setJsonContent(['status' => 'error', 'message' => 'Ticket has already been redeemed']);
        }

        // Redeem the ticket
        $ticketProfile->redeemed_ticket = 1;

        if (!$ticketProfile->save()) {
            return $response->setJsonContent(['status' => 'error', 'message' => 'Failed to redeem ticket']);
        }

        return $response->setJsonContent([
            'status' => 'success',
            'message' => 'Ticket redeemed successfully',
        ]);
    }
}
