<?php
use Phalcon\Mvc\Controller;
use Phalcon\Http\Response;

class PaymentController extends Controller
{
    public function initialize()
    {
        $this->view->disable();
    }

    public function processPayment($userId, $totalAmount, $paymentMethod)
    {
        $response = new Response();

        try {
            // Create a new Payment
            $payment = new Payment();
            $payment->user_id = $userId;
            $payment->total_amount = $totalAmount;
            $payment->payment_method = $paymentMethod;
            $payment->payment_status_id = 0; 
            $payment->created_at = date('Y-m-d H:i:s');
            $payment->updated_at = date('Y-m-d H:i:s');

            if (!$payment->save()) {
                throw new \Exception('Failed to save payment');
            }

            return [
                'status' => 'success',
                'message' => 'Payment processed successfully',
                'payment_id' => $payment->id,
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }
}
    /*public function initiateMpesaPaymentAction()
    {
        $response = new Response();

        try {
            // Find the payment record
            $payment = Payment::findFirst($paymentId);
            if (!$payment) {
                throw new \Exception('Payment record not found');
            }

            // Check if the payment method is M-Pesa
            if ($payment->payment_method !== 'mpesa') {
                throw new \Exception('Payment method is not M-Pesa');
            }

            // Retrieve user's phone number from the Users table
            $user = Users::findFirst($payment->user_id);
            if (!$user) {
                throw new \Exception('User not found');
            }
            $phoneNumber = $user->phone;

            // Process M-Pesa payment
            $paymentResult = $this->processMpesaPayment($payment, $phoneNumber, $payment->total_amount);

            if ($paymentResult['status'] !== 'success') {
                throw new \Exception($paymentResult['message']);
            }

            return $response->setJsonContent([
                'status' => 'success',
                'message' => 'M-Pesa payment initiated successfully',
                'payment_id' => $payment->id,
            ]);

        } catch (\Exception $e) {
            return $response->setJsonContent([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function initiateCardPaymentAction()
    {
        $response = new Response();

        try {
            // Find the payment record
            $payment = Payment::findFirst($paymentId);
            if (!$payment) {
                throw new \Exception('Payment record not found');
            }

            // Check if the payment method is card
            if ($payment->payment_method !== 'card') {
                throw new \Exception('Payment method is not card');
            }

            // Assuming card ID is stored in the payment table
            $cardId = $payment->card_id ?? null;
            if (!$cardId) {
                throw new \Exception('Card ID not found in payment record');
            }

            // Process card payment
            $paymentResult = $this->processCardPayment($cardId, $payment->total_amount);

            if ($paymentResult['status'] !== 'success') {
                throw new \Exception($paymentResult['message']);
            }

            return $response->setJsonContent([
                'status' => 'success',
                'message' => 'Card payment processed successfully',
                'payment_id' => $payment->id,
            ]);

        } catch (\Exception $e) {
            return $response->setJsonContent([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function processMpesaPayment($payment, $phoneNumber, $totalAmount)
    {
        $accessToken = $this->generateMpesaToken();
        $shortcode = 'YOUR_SHORTCODE';
        $lipaNaMpesaOnlinePasskey = 'YOUR_LIPA_NA_MPESA_ONLINE_PASSKEY';
        $timestamp = date('YmdHis');
        $password = base64_encode($shortcode . $lipaNaMpesaOnlinePasskey . $timestamp);

        $curl_post_data = [
            'BusinessShortCode' => $shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $totalAmount,
            'PartyA' => $phoneNumber,
            'PartyB' => $shortcode,
            'PhoneNumber' => $phoneNumber,
            'CallBackURL' => 'https://YOUR_NGROK_URL.ngrok-free.app/mpesa-callback',
            'AccountReference' => 'YOUR_ACCOUNT_REFERENCE',
            'TransactionDesc' => 'Payment for order'
        ];

        $data_string = json_encode($curl_post_data);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Authorization: Bearer ' . $accessToken));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response);

        if ($result->ResponseCode == '0') {
            // Save the transaction code to the payment record
            $payment->mpesa_reference = $result->CheckoutRequestID;
            $payment->payment_status_id = 1; // Assuming 1 is for success
            if (!$payment->save()) {
                throw new \Exception('Failed to update payment status and M-Pesa reference');
            }

            return [
                'status' => 'success',
                'message' => 'M-Pesa payment initiated successfully',
                'transactionCode' => $result->CheckoutRequestID,
                'MerchantRequestID' => $result->MerchantRequestID,
                'CheckoutRequestID' => $result->CheckoutRequestID
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Failed to initiate M-Pesa payment: ' . $result->errorMessage
            ];
        }
    }

    private function generateMpesaToken()
    {
        $consumerKey = 'YOUR_CONSUMER_KEY';
        $consumerSecret = 'YOUR_CONSUMER_SECRET';
        $credentials = base64_encode($consumerKey . ':' . $consumerSecret);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $credentials));
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response);
        return $result->access_token;
    }

    private function processCardPayment($cardId, $totalAmount)
    {
        // Implement card payment logic here
        // Simulating card payment response
        return [
            'status' => 'success',
            'message' => 'Card payment processed successfully',
        ];
    }

    public function mpesaCallbackAction()
    {
        // Callback URL handler
        if ($this->request->isPost()) {
            $callbackData = file_get_contents('php://input');
            $callbackJson = json_decode($callbackData, true);
            
            // Process the callback data
            // For example, you might want to log it or update your database
            file_put_contents('callback_log.txt', print_r($callbackJson, true), FILE_APPEND);

            // Respond to Mpesa to acknowledge receipt of the callback
            $response = new Response();
            $response->setJsonContent(['ResultCode' => 0, 'ResultDesc' => 'Success']);
            return $response;
        }
    }
}
