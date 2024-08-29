<?php
 use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Phalcon\Mvc\Controller;
use Phalcon\Http\Response;
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
            $mail->Body    = nl2br($body); // Converts newlines to <br> tags for HTML emails
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

        // Check if the user has the right to validate the ticket
        if ($role === 'Event Organizers') {
            $event = Event::findFirst($ticketProfile->event_id);
            if (!$event || ($event->UserId != $userId && !$this->hasEventAccess($userId, $ticketProfile->event_id))) {
                return $response->setJsonContent(['status' => 'error', 'message' => 'You do not have permission to validate this ticket']);
            }
        } elseif ($role === 'Validator') {
            $event = Event::findFirst($ticketProfile->event_id);
            if (!$event || !$this->hasEventAccess($userId, $ticketProfile->event_id)) {
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
        $user = Users::findFirst($ticketProfile->user_id);
        if ($user) {
            $emailContent = "Dear {$user->name},\n\n";
            $emailContent .= "Your ticket has been successfully validated.\n";
            $emailContent .= "Here is your unique ticket code: {$ticketProfile->unique_code}\n";
            $emailContent .= "Please keep this code safe as it will be required for entry.\n\n";
            $emailContent .= "Thank you for choosing our event.\n\n";
            $emailContent .= "Best Regards,\n";
            $emailContent .= "Event Team";

            if (!$this->sendEmail($user->email, "Ticket Validation Successful", $emailContent)) {
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
    
        // Debug log
        error_log("Received unique_code: " . $uniqueCode);
    
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
    
        // Check if the ticket is valid and not already redeemed
        if ($ticketProfile->valid_status != 1) {
            return $response->setJsonContent(['status' => 'error', 'message' => 'Ticket is not valid']);
        }
    
        if ($ticketProfile->redeemed_ticket == 1) {
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
