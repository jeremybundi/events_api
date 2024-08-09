<?php

use Phalcon\Mvc\Controller;
use Phalcon\Http\Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class TransactionController extends Controller
{
    const MPESA_CONSUMER_KEY = 'FRgqLoVwjGEGomglkNJfspqlkPX7uyk9TwtZt9508xPMOoqF';
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

        switch ($paymentMethod) {
            case 'mpesa':
                $phoneNumber = isset($data->phoneNumber) ? $data->phoneNumber : null;
                if (!$phoneNumber) {
                    return $this->sendErrorResponse('Phone number is required');
                }
                return $this->processMpesaPayment($amount, $phoneNumber, $paymentMethod, $paymentId, $userId);
            case 'card':
                $barcodeData = isset($data->barcodeData) ? $data->barcodeData : null;
                if (!$barcodeData) {
                    return $this->sendErrorResponse('Barcode data is required');
                }
                return $this->processBarcodePayment($amount, $userId, $paymentMethod, $paymentId, $barcodeData);
            default:
                return $this->sendErrorResponse('Invalid payment method');
        }
    }

    private function processMpesaPayment($amount, $phoneNumber, $paymentMethod, $paymentId, $userId)
    {
        if ($this->isInvalidAmount($amount) || $this->isInvalidPhoneNumber($phoneNumber)) {
            return $this->sendErrorResponse('Invalid amount or phone number');
        }

        $callbackUrl = 'https://your-ngrok-url/transaction/callback'; 

        $mpesaResponse = $this->initiateMpesaStkPush($amount, $phoneNumber, $callbackUrl, $paymentId);

        if ($mpesaResponse['status'] === 'success') {
            return $this->sendSuccessResponse('M-Pesa payment initiated', $amount, $userId, $paymentMethod);
        } else {
            return $this->sendErrorResponse('Failed to initiate M-Pesa payment: ' . $mpesaResponse['message']);
        }
    }

    private function processBarcodePayment($amount, $userId, $paymentMethod, $paymentId, $barcodeData)
    {
        if ($this->isInvalidAmount($amount) || $this->isInvalidUserId($userId)) {
            return $this->sendErrorResponse('Invalid amount or user ID');
        }

        // Simulate barcode scan logic here, including validation of the barcode data
        $isValidBarcode = $this->validateBarcodeData($barcodeData, $amount);

        if (!$isValidBarcode) {
            return $this->sendErrorResponse('Insufficient funds or invalid barcode');
        }

        // If barcode is valid, proceed to process the payment
        $payment = Payment::findFirstById($paymentId);
        if ($payment) {
            $payment->payment_status_id = 1; // Mark payment as successful
            if ($payment->save()) {
                return $this->sendSuccessResponse('Barcode payment processed successfully', $amount, $userId, $paymentMethod);
            } else {
                return $this->sendErrorResponse('Failed to update payment record');
            }
        } else {
            return $this->sendErrorResponse('Payment record not found');
        }
    }

    private function validateBarcodeData($barcodeData, $amount)
    {
        // Implement logic to validate the barcode data and check if there are sufficient funds
        // For now, we'll simulate this with a simple check
        if ($barcodeData === 'VALID_BARCODE' && $amount <= 1000) { // Example condition
            return true;
        }
        return false;
    }

    private function initiateMpesaStkPush($amount, $phoneNumber, $callbackUrl, $paymentId)
    {
        $accessToken = $this->generateMpesaAccessToken(self::MPESA_CONSUMER_KEY, self::MPESA_CONSUMER_SECRET);

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

        $logDir = __DIR__ . '/../logs';
        $logFilePath = $logDir . '/callback_logs.txt';

        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        if (!is_writable($logDir)) {
            chmod($logDir, 0777);
        }

        $logData = print_r($request, true);
        if (file_put_contents($logFilePath, $logData, FILE_APPEND) === false) {
            return $this->sendErrorResponse('Failed to write to log file');
        }

        $this->sendSuccessResponse('Callback received successfully');
    }

    private function isInvalidAmount($amount)
    {
        return !is_numeric($amount) || $amount <= 0;
    }

    private function isInvalidPhoneNumber($phoneNumber)
    {
        return !preg_match('/^\d{10,12}$/', $phoneNumber);
    }

    private function isInvalidUserId($userId)
    {
        return !is_numeric($userId) || $userId <= 0;
    }

    private function sendSuccessResponse($message, $amount = null, $userId = null, $paymentMethod = null)
    {
        $responseContent = ['status' => 'success', 'message' => $message];
        if ($amount !== null) {
            $responseContent['amount'] = $amount;
        }
        if ($userId !== null) {
            $responseContent['user_id'] = $userId;
        }
        if ($paymentMethod !== null) {
            $responseContent['payment_method'] = $paymentMethod;
        }
        return $this->response->setStatusCode(200)->setJsonContent($responseContent)->send();
    }

    private function sendErrorResponse($message)
    {
        return $this->response->setStatusCode(400)->setJsonContent(['status' => 'error', 'message' => $message])->send();
    }
}
