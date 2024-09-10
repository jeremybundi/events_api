<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Response;
use Phalcon\Validation;
use Phalcon\Validation\Validator\PresenceOf;
use Phalcon\Validation\Validator\Email as EmailValidator;
use Phalcon\Validation\Validator\Regex as RegexValidator;

class BookingController extends Controller
{
    private $mpesaConfig = [
        'consumer_key' => 'FRgqLoVwjGEGomglkNJfspqlgPX7uyk9TwtZt9508xPMOoqF',
        'consumer_secret' => 'a8tM9QuyTGb2MXLmlWKc9pczBAfd4RHEZxZkjIHGeyKbzdCA1ask2qOj9ymFaR98',
        'shortcode' => '174379',
        'passkey' => 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919',
        'stk_push_url' => 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest',
        'oauth_url' => 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials',
    ];

    public function initialize()
    {
        $this->view->disable();
    }

    public function createAction()
    {
        $response = new Response();
        $data = $this->request->getJsonRawBody(true);
    
        // Debugging: Log incoming request data
        error_log('Incoming request data: ' . print_r($data, true));
    
        // Validate input data
        $validation = new Validation();
        $validation->add('phone', new PresenceOf(['message' => 'The phone field is required']));
        $validation->add('phone', new RegexValidator([
            'pattern' => '/^\+?[0-9]{10,15}$/',
            'message' => 'The phone number is invalid'
        ]));
        $validation->add('email', new PresenceOf(['message' => 'The email field is required']));
        $validation->add('email', new EmailValidator(['message' => 'The email is not valid']));
        $validation->add('booking', new PresenceOf(['message' => 'The booking field is required']));
    
        $messages = $validation->validate($data);
        if (count($messages)) {
            $errors = [];
            foreach ($messages as $message) {
                $errors[] = $message->getMessage();
            }
            return $response->setJsonContent(['status' => 'error', 'message' => implode(', ', $errors)]);
        }
    
        if (!is_array($data['booking']) || empty($data['booking'])) {
            return $response->setJsonContent(['status' => 'error', 'message' => 'Invalid input data']);
        }
    
        // Check if the customer already exists
        $customer = Customers::findFirstByEmail($data['email']);
        if (!$customer) {
            // Create a new customer
            $customer = new Customers();
            $customer->email = $data['email'];
            $customer->phone = $data['phone'];
            $customer->created_at = date('Y-m-d H:i:s');
            $customer->updated_at = date('Y-m-d H:i:s');
            $customer->otp_verification = 0;
    
            if (!$customer->save()) {
                $errors = $customer->getMessages();
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                error_log('Failed to create customer: ' . implode(', ', $errorMessages));
                return $response->setJsonContent(['status' => 'error', 'message' => 'Failed to create customer', 'details' => implode(', ', $errorMessages)]);
            }
        }
    
        // Save booking data
        $totalAmount = 0;
        $bookingIds = [];
        foreach ($data['booking'] as $bookingData) {
            // Debugging: Log each booking data
            error_log('Processing booking data: ' . print_r($bookingData, true));
    
            if (!isset($bookingData['event_id'], $bookingData['ticket_category_id'], $bookingData['quantity'])) {
                return $response->setJsonContent(['status' => 'error', 'message' => 'Invalid booking data']);
            }
    
            $ticketCategory = TicketCategory::findFirst($bookingData['ticket_category_id']);
            if (!$ticketCategory) {
                return $response->setJsonContent(['status' => 'error', 'message' => 'Ticket category not found']);
            }
    
            if ($ticketCategory->quantity_available < $bookingData['quantity']) {
                return $response->setJsonContent(['status' => 'error', 'message' => 'Not enough tickets available for ticket category ID: ' . $bookingData['ticket_category_id']]);
            }
    
            $event = Event::findFirst($bookingData['event_id']);
            if (!$event) {
                return $response->setJsonContent(['status' => 'error', 'message' => 'Event not found for event ID: ' . $bookingData['event_id']]);
            }
    
            if ($event->total_tickets < $bookingData['quantity']) {
                return $response->setJsonContent(['status' => 'error', 'message' => 'Not enough tickets available for event ID: ' . $bookingData['event_id']]);
            }
    
            // Create booking
            $booking = new Booking();
            $booking->customer_id = $customer->id;
            $booking->event_id = $bookingData['event_id'];  
            $booking->ticket_category_id = $bookingData['ticket_category_id'];
            $booking->quantity = $bookingData['quantity'];
            $booking->total_amount = $ticketCategory->price * $bookingData['quantity'];
            $booking->created_at = date('Y-m-d H:i:s');
    
            if (!$booking->save()) {
                $errors = $booking->getMessages();
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                error_log('Failed to save booking: ' . implode(', ', $errorMessages));
                return $response->setJsonContent(['status' => 'error', 'message' => 'Failed to save booking']);
            }
    
            $totalAmount += $booking->total_amount;
            $bookingIds[] = $booking->id;
    
            // Update ticket category with quantity and purchased tickets
            $ticketCategory->quantity_available -= $bookingData['quantity'];
            $ticketCategory->purchased_tickets += $bookingData['quantity'];
    
            if (!$ticketCategory->save()) {
                $errors = $ticketCategory->getMessages();
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                error_log('Failed to update ticket category: ' . implode(', ', $errorMessages));
                return $response->setJsonContent(['status' => 'error', 'message' => 'Failed to update ticket category', 'details' => implode(', ', $errorMessages)]);
            }
    
            // Save ticket profiles
            for ($i = 0; $i < $bookingData['quantity']; $i++) {
                $ticketProfile = new TicketProfile();
                $ticketProfile->booking_id = $booking->id;
                $ticketProfile->unique_code = $ticketProfile->generateUniqueCode(); 
                $ticketProfile->qr_code = $ticketProfile->generateQrCode($ticketProfile->unique_code); 
                $ticketProfile->valid_status = 0; 
                $ticketProfile->redeemed_ticket = 0; 
                $ticketProfile->customer_id = $customer->id; 
                $ticketProfile->category_id = $bookingData['ticket_category_id'];
    
                if (!$ticketProfile->save()) {
                    error_log('Failed to save ticket profile: ' . implode(', ', $ticketProfile->getMessages()));
                }
            }
        }
    
        // Save payment data
        $payment = new Payment();
        $payment->customer_id = $customer->id;
        $payment->total_amount = $totalAmount;
        $payment->payment_status_id = 0; 
        $payment->booking_id = $booking->id; 
        $payment->created_at = date('Y-m-d H:i:s');
    
        if (!$payment->save()) {
            $errors = $payment->getMessages();
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            error_log('Failed to save payment: ' . implode(', ', $errorMessages));
            return $response->setJsonContent(['status' => 'error', 'message' => 'Failed to save payment']);
        }
    
        // Update ticket profiles with payment_id
        foreach ($bookingIds as $bookingId) {
            $ticketProfiles = TicketProfile::findByBookingId($bookingId);
            foreach ($ticketProfiles as $ticketProfile) {
                $ticketProfile->payment_id = $payment->id; 
                if (!$ticketProfile->save()) {
                    error_log('Failed to update ticket profile with payment_id: ' . implode(', ', $ticketProfile->getMessages()));
                }
            }
        }
    
        // Initiate payment (MPESA STK Push)
        $authToken = $this->getMpesaAuthToken();
        if ($authToken === false) {
            return $response->setJsonContent(['status' => 'error', 'message' => 'Failed to authenticate with MPESA']);
        }
    
        $stkPushResponse = $this->initiateStkPush($authToken, $customer->phone, $totalAmount);
        if ($stkPushResponse === false) {
            return $response->setJsonContent(['status' => 'error', 'message' => 'Failed to initiate STK push']);
        }
    
        return $response->setJsonContent(['status' => 'success', 'message' => 'Booking successful', 'payment_response' => $stkPushResponse]);
    }
    

    private function getMpesaAuthToken()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->mpesaConfig['oauth_url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $this->mpesaConfig['consumer_key'] . ':' . $this->mpesaConfig['consumer_secret']);
        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);
        return $result['access_token'] ?? false;
    }

    private function initiateStkPush($authToken, $phoneNumber, $amount)
    {
        $payload = [
            'BusinessShortCode' => $this->mpesaConfig['shortcode'],
            'Password' => base64_encode($this->mpesaConfig['shortcode'] . $this->mpesaConfig['passkey'] . date('YmdHis')),
            'Timestamp' => date('YmdHis'),
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $phoneNumber,
            'PartyB' => $this->mpesaConfig['shortcode'],
            'PhoneNumber' => $phoneNumber,
            'CallBackURL' => 'https://example.com/mpesa/callback', 
            'AccountReference' => 'TicketBooking',
            'TransactionDesc' => 'Payment for ticket booking',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->mpesaConfig['stk_push_url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $authToken,
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }

    public function mpesaCallbackAction()
    {
        $response = new Response();
        $data = $this->request->getJsonRawBody(true);

        if (isset($data['Body']['stkCallback']['ResultCode']) && $data['Body']['stkCallback']['ResultCode'] == 0) {
            $merchantRequestId = $data['Body']['stkCallback']['MerchantRequestID'];
            $checkoutRequestId = $data['Body']['stkCallback']['CheckoutRequestID'];
            $responseCode = $data['Body']['stkCallback']['ResultCode'];
            $responseDescription = $data['Body']['stkCallback']['ResultDesc'];
            $amount = $data['Body']['stkCallback']['CallbackMetadata']['Item'][0]['Value'];
            $mpesaReceiptNumber = $data['Body']['stkCallback']['CallbackMetadata']['Item'][1]['Value'];
            $balance = $data['Body']['stkCallback']['CallbackMetadata']['Item'][2]['Value'];

            $payment = Payment::findFirstByMpesaReference($mpesaReceiptNumber);
            if ($payment) {
                $payment->payment_status_id = 1; 
                $payment->amount_paid = $amount;
                if (!$payment->save()) {
                    error_log('Failed to update payment status for MPESA reference: ' . $mpesaReceiptNumber);
                    return $response->setJsonContent(['status' => 'error', 'message' => 'Failed to update payment status']);
                }

                $bookings = Booking::findByPaymentId($payment->id);
                foreach ($bookings as $booking) {
                    $ticketProfile = TicketProfile::findFirstByBookingId($booking->id);
                    if ($ticketProfile) {
                        $ticketProfile->unique_code = $ticketProfile->generateUniqueCode();
                        $ticketProfile->qr_code = $ticketProfile->generateQrCode($ticketProfile->unique_code);
                        if (!$ticketProfile->save()) {
                            error_log('Failed to update ticket profile for booking ID: ' . $booking->id);
                        }
                    }
                }

                return $response->setJsonContent(['status' => 'success', 'message' => 'Payment processed successfully']);
            } else {
                return $response->setJsonContent(['status' => 'error', 'message' => 'Payment not found']);
            }
        } else {
            return $response->setJsonContent(['status' => 'error', 'message' => 'Payment failed']);
        }
    }
}
