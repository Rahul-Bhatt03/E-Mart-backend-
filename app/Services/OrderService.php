<?php

namespace App\Services;

use App\Repositories\OrderRepository;
use App\Repositories\CartRepository;
use App\Repositories\ProductRepository;
use App\Services\PaymentService;

class OrderService
{
    protected $orderRepository;
    protected $cartRepository;
    protected $productRepository;
    protected $paymentService;

    public function __construct(
        OrderRepository $orderRepository,
        CartRepository $cartRepository,
        ProductRepository $productRepository,
        PaymentService $paymentService
    ) {
        $this->orderRepository = $orderRepository;
        $this->cartRepository = $cartRepository;
        $this->productRepository = $productRepository;
        $this->paymentService = $paymentService;
    }

    public function getAllOrders(array $filters = [], int $perPage = 15)
    {
        return $this->orderRepository->getAllOrders($filters, $perPage);
    }

    public function getUserOrders($userId, int $perPage = 15)
    {
        return $this->orderRepository->getUserOrders($userId, $perPage);
    }

    public function getOrderById($id)
    {
        return $this->orderRepository->findById($id);
    }

    /**
     * Create order from cart with payment processing
     */
    public function createOrderFromCart($userId, array $shippingAddress, $taxRate = 0.1, $shippingAmount = 0)
    {
        $cartItems = $this->cartRepository->getUserCart($userId);
        
        if ($cartItems->isEmpty()) {
            throw new \Exception('Cart is empty');
        }

        $subtotalAmount = 0;
        $orderItems = [];

        // Validate stock and calculate totals
        foreach ($cartItems as $cartItem) {
            $product = $cartItem->product;

            if ($product->stock_quantity < $cartItem->quantity) {
                throw new \Exception("Insufficient stock for product: {$product->name}");
            }

            $itemTotal = $product->price * $cartItem->quantity;
            $subtotalAmount += $itemTotal;

            $orderItems[] = [
                'product_id' => $product->id,
                'quantity' => $cartItem->quantity,
                'unit_price' => $product->price,
                'total_price' => $itemTotal
            ];
        }

        $taxAmount = $subtotalAmount * $taxRate;
        $totalAmount = $subtotalAmount + $taxAmount + $shippingAmount;

        // Generate order number
        $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(uniqid());

        // Create order with payment status as pending
        $orderData = [
            'user_id' => $userId,
            'order_number' => $orderNumber,
            'total_amount' => $totalAmount, // This is the final total
            'tax_amount' => $taxAmount,
            'shipping_amount' => $shippingAmount,
            'status' => 'pending',
            'payment_status' => 'pending',
            'shipping_address' => $shippingAddress
        ];

        $order = $this->orderRepository->createOrder($orderData, $orderItems);

        return $order;
    }

    /**
     * Create payment intent for an order
     */
    public function createPaymentIntent($orderId)
    {
        $order = $this->orderRepository->findById($orderId);
        
        if (!$order) {
            throw new \Exception('Order not found');
        }

        if ($order->payment_status !== 'pending') {
            throw new \Exception('Order payment already processed');
        }

        $paymentIntent = $this->paymentService->createPaymentIntent($order);
        
        // Update order with payment intent ID
        $this->orderRepository->updatePaymentStatus($orderId, 'processing', $paymentIntent->id);

        return $paymentIntent;
    }

    /**
     * Confirm order after successful payment
     */
    public function confirmOrder($orderId)
    {
        $order = $this->orderRepository->findById($orderId);
        
        if (!$order) {
            throw new \Exception('Order not found');
        }

        // Verify payment was successful
        if ($order->stripe_payment_intent_id) {
            $paymentIntent = $this->paymentService->getPaymentIntent($order->stripe_payment_intent_id);
            if ($paymentIntent->status !== 'succeeded') {
                throw new \Exception('Payment not completed for this order');
            }
        }

        // Update product stock only after successful payment
        foreach ($order->items as $orderItem) {
            $product = $orderItem->product;
            $this->productRepository->update($product->id, [
                'stock_quantity' => $product->stock_quantity - $orderItem->quantity
            ]);
        }

        // Clear user's cart after successful payment confirmation
        $this->cartRepository->clearUserCart($order->user_id);

        // Update order status and payment status
        $this->orderRepository->updateOrderStatus($orderId, 'processing');
        $this->orderRepository->updatePaymentStatus($orderId, 'succeeded');

        return $order->fresh(['items.product']);
    }

    public function updateOrderStatus($id, $status)
    {
        $order = $this->orderRepository->findById($id);

        if (!$order) {
            throw new \Exception('Order not found');
        }

        $this->orderRepository->updateOrderStatus($id, $status);

        return $this->orderRepository->findById($id);
    }

    /**
     * Cancel order and restore stock if payment not completed
     */
    public function cancelOrder($orderId)
    {
        $order = $this->orderRepository->findById($orderId);
        
        if (!$order) {
            throw new \Exception('Order not found');
        }

        if ($order->payment_status === 'succeeded') {
            throw new \Exception('Cannot cancel order with successful payment');
        }

        $this->orderRepository->updateOrderStatus($orderId, 'cancelled');
        $this->orderRepository->updatePaymentStatus($orderId, 'canceled');

        return $order->fresh();
    }
}