<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class TransactionController extends Controller
{
    const MPESA_CONSUMER_KEY = 'FRgqLoVwjGEGomglkNJfspqlgPX7uyk9TwtZt9508xPMOoqF';
    const MPESA_CONSUMER_SECRET = 'a8tM9QuyTGb2MXLmlWKc9pczBAfd4RHEZxZkjIHGeyKbzdCA1ask2qOj9ymFaR98';
    const MPESA_SHORT_CODE = '174379';
    const MPESA_PASSKEY = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919';
    const MPESA_STK_PUSH_URL = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
    const MPESA_OAUTH_URL = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

    public function initialize()
    {
        $this->response = new Response();
    }

    private function getUserIdFromToken()
    {
        $authHeader = $this->request->getHeader('Authorization');
        if (!$authHeader) {
            throw new \Exception('No authorization header provided');
        }

        $token = str_replace('Bearer ', '', $authHeader);
        $config = $this->di->getConfig();
        $secretKey = $config->jwt->secret_key;

        try {
            $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));
            return $decoded->data->userId;
        } catch (\Exception $e) {
            throw new \Exception('Invalid or expired token');
        }
    }

    private function requireAuth()
    {
        try {
            return $this->getUserIdFromToken();
        } catch (\Exception $e) {
            return $this->response->setStatusCode(401, 'Unauthorized')
                                  ->setJsonContent(['status' => 'error', 'message' => $e->getMessage()])
                                  ->send();
        }
    }

    public function payAction($id)
    {
        $this->requireAuth();

        $payment = Payment::findFirstById($id);

        if (!$payment) {
            return $this->sendErrorResponse('Payment not found');
        }

        $paymentId = $payment->id; 
        $paymentMethod = $payment->payment_method;
        $amount = $payment->total_amount;
        $userId = $payment->user_id;

        if (is_null($amount)) {
            return $this->sendErrorResponse('Total amount not found or is null');
        }

        $data = $this->request->getJsonRawBody();
        $phoneNumber = isset($data->phoneNumber) ? $data->phoneNumber : null;

        if (!$phoneNumber) {
            return $this->sendErrorResponse('Phone number is required');
        }

        switch ($paymentMethod) {
            case 'mpesa':
                return $this->processMpesaPayment($amount, $phoneNumber, $paymentMethod, $paymentId, $userId);
            case 'card':
                return $this->processCardPayment($amount, $userId, $paymentMethod);
            default:
                return $this->sendErrorResponse('Invalid payment method');
        }
    }

    private function processMpesaPayment($amount, $phoneNumber, $paymentMethod, $paymentId, $userId)
    {
        // Validate amount and phoneNumber
        if ($this->isInvalidAmount($amount) || $this->isInvalidPhoneNumber($phoneNumber)) {
            return $this->sendErrorResponse('Invalid amount or phone number');
        }

        // M-Pesa STK push logic
        $callbackUrl = 'https://9cfa-196-216-68-178.ngrok-free.app/transaction/callback'; 

        $mpesaResponse = $this->initiateMpesaStkPush($amount, $phoneNumber, $callbackUrl, $paymentId);

        if ($mpesaResponse['status'] === 'success') {
            return $this->sendSuccessResponse('M-Pesa payment initiated', $amount, $userId, $paymentMethod);
        } else {
            return $this->sendErrorResponse('Failed to initiate M-Pesa payment: ' . $mpesaResponse['message']);
        }
    }

    private function processCardPayment($amount, $userId, $paymentMethod)
    {
        // Validate amount and userId
        if ($this->isInvalidAmount($amount) || $this->isInvalidUserId($userId)) {
            return $this->sendErrorResponse('Invalid amount or user ID');
        }

        return $this->sendSuccessResponse('Card payment processed', $amount, $userId, $paymentMethod);
    }

    private function initiateMpesaStkPush($amount, $phoneNumber, $callbackUrl, $paymentId)
    {
        $accessToken = $this->generateMpesaAccessToken(self::MPESA_CONSUMER_KEY, self::MPESA_CONSUMER_SECRET);

        // STK push request payload
        $timestamp = date('YmdHis');
        $password = base64_encode(self::MPESA_SHORT_CODE . self::MPESA_PASSKEY . $timestamp);

        $stkPushData = [
            'BusinessShortCode' => self::MPESA_SHORT_CODE,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $phoneNumber, 
            'PartyB' => self::MPESA_SHORT_CODE,
            'PhoneNumber' => $phoneNumber, 
            'CallBackURL' => $callbackUrl,
            'AccountReference' => $paymentId, 
            'TransactionDesc' => 'Payment for order'
        ];

        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ];

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, self::MPESA_STK_PUSH_URL);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($stkPushData));

        $response = curl_exec($curl);
        curl_close($curl);

        $response = json_decode($response, true);

        if (isset($response['ResponseCode']) && $response['ResponseCode'] === '0') {
            return ['status' => 'success'];
        } else {
            $message = isset($response['errorMessage']) ? $response['errorMessage'] : 'Unknown error';
            return ['status' => 'error', 'message' => $message];
        }
    }

    private function generateMpesaAccessToken($consumerKey, $consumerSecret)
    {
        $credentials = base64_encode($consumerKey . ':' . $consumerSecret);

        $headers = [
            'Authorization: Basic ' . $credentials
        ];

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, self::MPESA_OAUTH_URL);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($curl);
        curl_close($curl);

        $response = json_decode($response, true);

        return $response['access_token'];
    }

    public function callbackAction()
    {
        $request = $this->request->getJsonRawBody(true);

        // Use absolute path for logs directory
        $logDir = __DIR__ . '/../logs';
        $logFilePath = $logDir . '/callback_logs.txt';
        $errorLogFilePath = $logDir . '/error_logs.txt';

        // Ensure the directory exists and is writable
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        if (!is_writable($logDir)) {
            chmod($logDir, 0777);
        }

        // Log the entire callback request for debugging purposes
        $logData = print_r($request, true);
        if (file_put_contents($logFilePath, $logData, FILE_APPEND) === false) {
            file_put_contents($errorLogFilePath, "Failed to write to callback_logs.txt\n", FILE_APPEND);
            return $this->sendErrorResponse('Failed to write to log file');
        }

        if (isset($request['Body']['stkCallback'])) {
            $callback = $request['Body']['stkCallback'];

            $resultCode = $callback['ResultCode'];
            $resultDesc = $callback['ResultDesc'];

            if ($resultCode == 0) {
                // Transaction was successful
                $items = $callback['CallbackMetadata']['Item'];
                $mpesaReceiptNumber = $this->getCallbackItemValue($items, 'MpesaReceiptNumber');
                $paymentId = $this->getCallbackItemValue($items, 'AccountReference');

                // Log successful callback details
                $logData = "Payment ID: $paymentId, Receipt Number: $mpesaReceiptNumber\n";
                if (file_put_contents($logFilePath, $logData, FILE_APPEND) === false) {
                    file_put_contents($errorLogFilePath, "Failed to write successful transaction to callback_logs.txt\n", FILE_APPEND);
                    return $this->sendErrorResponse('Failed to write to log file');
                }

                // Update payment record
                $payment = Payment::findFirstById($paymentId);

                if ($payment) {
                    $payment->mpesa_reference = $mpesaReceiptNumber;
                    $payment->payment_status_id = 1;

                    if ($payment->save()) {
                        return $this->sendSuccessResponse('Payment successful', $payment->total_amount, $payment->user_id, 'mpesa');
                    } else {
                        // Log errors if save fails
                        $messages = $payment->getMessages();
                        foreach ($messages as $message) {
                            file_put_contents($errorLogFilePath, $message->getMessage() . "\n", FILE_APPEND);
                        }
                        return $this->sendErrorResponse('Failed to update payment record: ' . implode(', ', $messages));
                    }
                } else {
                    return $this->sendErrorResponse('Payment record not found');
                }
            } else {
                return $this->sendErrorResponse('Payment failed: ' . $resultDesc);
            }
        } else {
            file_put_contents($errorLogFilePath, "Invalid callback data\n", FILE_APPEND);
            return $this->sendErrorResponse('Invalid callback data');
        }
    }

    private function getCallbackItemValue($items, $key)
    {
        foreach ($items as $item) {
            if ($item['Name'] === $key) {
                return $item['Value'];
            }
        }
        return null;
    }

    private function sendErrorResponse($message)
    {
        return $this->response->setJsonContent([
            'status' => 'error',
            'message' => $message
        ])->send();
    }

    private function sendSuccessResponse($message, $amount = null, $userId = null, $paymentMethod = null)
    {
        return $this->response->setJsonContent([
            'status' => 'success',
            'message' => $message,
            'data' => [
                'amount' => $amount,
                'user_id' => $userId, 
                'payment_method' => $paymentMethod
            ]
        ])->send();
    }

    private function isInvalidAmount($amount)
    {
        return !is_numeric($amount) || $amount <= 0;       
    }

    private function isInvalidPhoneNumber($phoneNumber)
    {
        return empty($phoneNumber) || !is_numeric($phoneNumber) || strlen($phoneNumber) != 12;
    }

    private function isInvalidUserId($userId)
    {
        return empty($userId) || !is_numeric($userId);
    }
}
