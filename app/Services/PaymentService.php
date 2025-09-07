<?php

namespace App\Services;

use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Exception\ApiErrorException;
use App\Models\Order;

class PaymentService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Create a payment intent for the order
     */
    public function createPaymentIntent(Order $order)
    {
        try {
            $paymentIntent = PaymentIntent::create([
                'amount' => $this->convertToStripeAmount($order->total_amount),
                'currency' => 'usd',
                'payment_method_types' => ['card'],
                'metadata' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'user_id' => $order->user_id
                ],
                'description' => "Payment for Order #{$order->order_number}",
                'receipt_email' => $order->user->email ?? null,
            ]);

            return $paymentIntent;
        } catch (ApiErrorException $e) {
            throw new \Exception('Failed to create payment intent: ' . $e->getMessage());
        }
    }

    /**
     * Confirm a payment intent
     */
    public function confirmPaymentIntent($paymentIntentId, $paymentMethodId)
    {
        try {
            return PaymentIntent::retrieve($paymentIntentId)->confirm([
                'payment_method' => $paymentMethodId
            ]);
        } catch (ApiErrorException $e) {
            throw new \Exception('Failed to confirm payment: ' . $e->getMessage());
        }
    }

    /**
     * Retrieve payment intent
     */
    public function getPaymentIntent($paymentIntentId)
    {
        try {
            return PaymentIntent::retrieve($paymentIntentId);
        } catch (ApiErrorException $e) {
            throw new \Exception('Failed to retrieve payment intent: ' . $e->getMessage());
        }
    }

    /**
     * Process webhook from Stripe
     */
    public function processWebhook($payload, $signature)
    {
        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $signature,
                config('services.stripe.webhook_secret')
            );

            switch ($event['type']) {
                case 'payment_intent.succeeded':
                    $this->handlePaymentSucceeded($event['data']['object']);
                    break;
                case 'payment_intent.payment_failed':
                    $this->handlePaymentFailed($event['data']['object']);
                    break;
                case 'payment_intent.canceled':
                    $this->handlePaymentCanceled($event['data']['object']);
                    break;
            }

            return true;
        } catch (\Exception $e) {
            throw new \Exception('Webhook error: ' . $e->getMessage());
        }
    }

    /**
     * Handle successful payment
     */
    private function handlePaymentSucceeded($paymentIntent)
    {
        $order = Order::where('stripe_payment_intent_id', $paymentIntent['id'])->first();
        
        if ($order) {
            $order->update([
                'payment_status' => 'succeeded',
                'status' => 'processing',
                'payment_metadata' => json_encode([
                    'payment_method' => $paymentIntent['payment_method'],
                    'amount_received' => $paymentIntent['amount_received'],
                    'charges' => $paymentIntent['charges']['data'] ?? []
                ])
            ]);
        }
    }

    /**
     * Handle failed payment
     */
    private function handlePaymentFailed($paymentIntent)
    {
        $order = Order::where('stripe_payment_intent_id', $paymentIntent['id'])->first();
        
        if ($order) {
            $order->update([
                'payment_status' => 'failed',
                'status' => 'cancelled',
                'payment_metadata' => json_encode([
                    'failure_reason' => $paymentIntent['last_payment_error']['message'] ?? 'Payment failed'
                ])
            ]);
        }
    }

    /**
     * Handle canceled payment
     */
    private function handlePaymentCanceled($paymentIntent)
    {
        $order = Order::where('stripe_payment_intent_id', $paymentIntent['id'])->first();
        
        if ($order) {
            $order->update([
                'payment_status' => 'canceled',
                'status' => 'cancelled'
            ]);
        }
    }

    /**
     * Convert amount to Stripe format (cents)
     */
    private function convertToStripeAmount($amount)
    {
        return (int) round($amount * 100); // Convert to cents
    }

    /**
     * Convert from Stripe amount format
     */
    private function convertFromStripeAmount($amount)
    {
        return $amount / 100; // Convert from cents
    }
}