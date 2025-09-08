<?php

namespace App\Services;

use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Webhook;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Exception;

class PaymentService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Create payment intent for an order
     */
    public function createPaymentIntent(Order $order)
    {
        try {
            // Convert amount to cents (Stripe expects amounts in cents)
            $amount = (int)($order->total_amount * 100);
            
            $paymentIntent = PaymentIntent::create([
                'amount' => $amount,
                'currency' => 'usd', // You might want to make this configurable
                'payment_method_types' => ['card'],
                'metadata' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'user_id' => $order->user_id,
                ],
                'description' => "Payment for order {$order->order_number}",
                'receipt_email' => $order->user->email ?? null,
            ]);

            Log::info('Payment intent created', [
                'order_id' => $order->id,
                'payment_intent_id' => $paymentIntent->id,
                'amount' => $amount
            ]);

            return $paymentIntent;

        } catch (Exception $e) {
            Log::error('Payment intent creation failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            throw new Exception('Failed to create payment intent: ' . $e->getMessage());
        }
    }

    /**
     * Retrieve payment intent
     */
    public function getPaymentIntent($paymentIntentId)
    {
        try {
            return PaymentIntent::retrieve($paymentIntentId);
        } catch (Exception $e) {
            Log::error('Failed to retrieve payment intent', [
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage()
            ]);
            throw new Exception('Failed to retrieve payment intent: ' . $e->getMessage());
        }
    }

    /**
     * Process Stripe webhook
     */
    public function processWebhook($payload, $signature)
    {
        $endpoint_secret = config('services.stripe.webhook_secret');
        
        try {
            $event = Webhook::constructEvent($payload, $signature, $endpoint_secret);
        } catch (\UnexpectedValueException $e) {
            Log::error('Invalid payload in webhook', ['error' => $e->getMessage()]);
            throw new Exception('Invalid payload');
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::error('Invalid signature in webhook', ['error' => $e->getMessage()]);
            throw new Exception('Invalid signature');
        }

        // Handle the event
        switch ($event['type']) {
            case 'payment_intent.succeeded':
                $this->handlePaymentSucceeded($event['data']['object']);
                break;
            case 'payment_intent.payment_failed':
                $this->handlePaymentFailed($event['data']['object']);
                break;
            default:
                Log::info('Received unknown event type', ['type' => $event['type']]);
        }

        return true;
    }

    /**
     * Handle successful payment
     */
    private function handlePaymentSucceeded($paymentIntent)
    {
        $orderId = $paymentIntent['metadata']['order_id'] ?? null;
        
        if (!$orderId) {
            Log::warning('Payment succeeded but no order ID in metadata', [
                'payment_intent_id' => $paymentIntent['id']
            ]);
            return;
        }

        $order = Order::find($orderId);
        if (!$order) {
            Log::warning('Payment succeeded but order not found', [
                'order_id' => $orderId,
                'payment_intent_id' => $paymentIntent['id']
            ]);
            return;
        }

        // Update order payment status
        $order->update([
            'payment_status' => 'succeeded',
            'status' => 'processing'
        ]);

        Log::info('Order payment status updated via webhook', [
            'order_id' => $orderId,
            'payment_intent_id' => $paymentIntent['id']
        ]);
    }

    /**
     * Handle failed payment
     */
    private function handlePaymentFailed($paymentIntent)
    {
        $orderId = $paymentIntent['metadata']['order_id'] ?? null;
        
        if (!$orderId) {
            Log::warning('Payment failed but no order ID in metadata', [
                'payment_intent_id' => $paymentIntent['id']
            ]);
            return;
        }

        $order = Order::find($orderId);
        if (!$order) {
            Log::warning('Payment failed but order not found', [
                'order_id' => $orderId,
                'payment_intent_id' => $paymentIntent['id']
            ]);
            return;
        }

        // Update order payment status
        $order->update([
            'payment_status' => 'failed',
            'status' => 'cancelled'
        ]);

        Log::info('Order marked as failed via webhook', [
            'order_id' => $orderId,
            'payment_intent_id' => $paymentIntent['id']
        ]);
    }
}