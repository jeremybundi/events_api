<?php
use Phalcon\Mvc\Controller;
use Phalcon\Http\Response;

class PaymentController extends Controller
{
    public function initialize()
    {
        $this->view->disable();
    }

    public function processPayment($userId, $bookingId, $amount, $paymentMethod, $mpesaReference = null)
    {
        $response = new Response();

        try {
            // Create a new Payment
            $payment = new Payment();
            $payment->user_id = $userId;
            $payment->booking_id = $bookingId;
            $payment->amount = $amount;
            $payment->payment_method = $paymentMethod;
            $payment->status = 'pending';
            $payment->created_at = date('Y-m-d H:i:s');
            $payment->updated_at = date('Y-m-d H:i:s');

            if ($mpesaReference) {
                $payment->mpesa_reference = $mpesaReference;
            }

            if (!$payment->save()) {
                $messages = $payment->getMessages();
                $errorMessages = [];
                foreach ($messages as $message) {
                    $errorMessages[] = $message->getMessage();
                }
                throw new \Exception('Failed to save payment: ' . implode(', ', $errorMessages));
            }

            // Simulate payment process and update status
            // Here you should integrate with the actual payment gateway
            // For simplicity, we'll assume the payment is successful
            $payment->status = 'successful';
            if (!$payment->save()) {
                $messages = $payment->getMessages();
                $errorMessages = [];
                foreach ($messages as $message) {
                    $errorMessages[] = $message->getMessage();
                }
                throw new \Exception('Failed to update payment status: ' . implode(', ', $errorMessages));
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
