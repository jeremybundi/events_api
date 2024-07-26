<?php
use Phalcon\Mvc\Controller;
use Phalcon\Http\Response;

class PaymentController extends Controller
{
    public function initialize()
    {
        $this->view->disable();
    }

    public function processPayment($userId, $totalAmount, $paymentMethod, $bookingId)
    {
        $response = new Response();

        try {
            $payment = new Payment();
            $payment->user_id = $userId;
            $payment->total_amount = $totalAmount;
            $payment->payment_method = $paymentMethod;
            $payment->payment_status_id = 0; 
            $payment->created_at = date('Y-m-d H:i:s');
            $payment->updated_at = date('Y-m-d H:i:s');
            $payment->booking_id = $bookingId;


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
   