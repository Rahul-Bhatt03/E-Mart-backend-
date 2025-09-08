<?php

namespace App\Http\Controllers;

use App\Services\PaymentService;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    protected $paymentService;
    protected $orderService;

    public function __construct(PaymentService $paymentService, OrderService $orderService)
    {
        $this->paymentService = $paymentService;
        $this->orderService = $orderService;
    }

    /**
     * Create payment intent for an order
     */
    public function createPaymentIntent(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id'
        ]);

        try {
            $paymentIntent = $this->orderService->createPaymentIntent($request->order_id);

            return response()->json([
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id,
                'message' => 'Payment intent created successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Payment intent creation error: ' . $e->getMessage());
            return response()->json([
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get payment intent status
     */
    public function getPaymentStatus(Request $request, $paymentIntentId)
    {
        try {
            $paymentIntent = $this->paymentService->getPaymentIntent($paymentIntentId);

            return response()->json([
                'status' => $paymentIntent->status,
                'amount' => $paymentIntent->amount,
                'currency' => $paymentIntent->currency,
                'payment_intent' => $paymentIntent
            ]);
        } catch (\Exception $e) {
            Log::error('Payment status error: ' . $e->getMessage());
            return response()->json([
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Confirm order after successful payment
     */
    public function confirmOrder(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id'
        ]);

        try {
            $order = $this->orderService->confirmOrder($request->order_id);

            return response()->json([
                'order' => $order,
                'message' => 'Order confirmed successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Order confirmation error: ' . $e->getMessage());
            return response()->json([
                'error' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Handle Stripe webhooks
     */
    public function webhook(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        try {
            $this->paymentService->processWebhook($payload, $signature);

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('Stripe webhook error: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Webhook error'
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get Stripe public key for frontend
     */
    public function getPublicKey()
    {
        return response()->json([
            'public_key' => config('services.stripe.public')
        ]);
    }
}