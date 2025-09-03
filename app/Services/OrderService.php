<?php
// app/Services/OrderService.php
namespace App\Services;

use App\Repositories\OrderRepository;
use App\Repositories\CartRepository;
use App\Repositories\ProductRepository;

class OrderService
{
    protected $orderRepository;
    protected $cartRepository;
    protected $productRepository;

    public function __construct(
        OrderRepository $orderRepository,
        CartRepository $cartRepository,
        ProductRepository $productRepository
    ) {
        $this->orderRepository = $orderRepository;
        $this->cartRepository = $cartRepository;
        $this->productRepository = $productRepository;
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

    public function createOrderFromCart($userId, array $shippingAddress)
    {
        $cartItems = $this->cartRepository->getUserCart($userId);
        
        if ($cartItems->isEmpty()) {
            throw new \Exception('Cart is empty');
        }

        $totalAmount = 0;
        $orderItems = [];

        foreach ($cartItems as $cartItem) {
            $product = $cartItem->product;

            if ($product->stock_quantity < $cartItem->quantity) {
                throw new \Exception("Insufficient stock for product: {$product->name}");
            }

            $itemTotal = $product->price * $cartItem->quantity;
            $totalAmount += $itemTotal;

            $orderItems[] = [
                'product_id' => $product->id,
                'quantity' => $cartItem->quantity,
                'unit_price' => $product->price,
                'total_price' => $itemTotal
            ];

            // Update product stock
            $this->productRepository->update($product->id, [
                'stock_quantity' => $product->stock_quantity - $cartItem->quantity
            ]);
        }

        $orderData = [
            'user_id' => $userId,
            'total_amount' => $totalAmount,
            'status' => 'pending',
            'shipping_address' => $shippingAddress
        ];

        $order = $this->orderRepository->createOrder($orderData, $orderItems);

        // Clear user's cart after successful order
        $this->cartRepository->clearUserCart($userId);

        return $order;
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
}