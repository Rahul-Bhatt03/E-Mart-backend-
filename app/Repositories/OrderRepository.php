<?php
// app/Repositories/OrderRepository.php
namespace App\Repositories;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Collection;

class OrderRepository
{
    protected $orderModel;
    protected $orderItemModel;

    public function __construct(Order $order, OrderItem $orderItem)
    {
        $this->orderModel = $order;
        $this->orderItemModel = $orderItem;
    }

    public function getAllOrders(array $filters = [], int $perPage = 15)
    {
        $query = $this->orderModel->with(['user', 'items.product']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['order_number'])) {
            $query->where('order_number', 'like', '%' . $filters['order_number'] . '%');
        }

        return $query->paginate($perPage);
    }

    public function getUserOrders($userId, int $perPage = 15)
    {
        return $this->orderModel->with('items.product')
                               ->where('user_id', $userId)
                               ->paginate($perPage);
    }

    public function findById($id): ?Order
    {
        return $this->orderModel->with(['user', 'items.product'])->find($id);
    }

    public function createOrder(array $orderData, array $itemsData): Order
    {
        $order = $this->orderModel->create($orderData);
        
        foreach ($itemsData as $item) {
            $this->orderItemModel->create([
                'order_id' => $order->id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'total_price' => $item['total_price']
            ]);
        }

        return $order->load('items.product');
    }

    public function updateOrderStatus($id, $status): bool
    {
        $order = $this->findById($id);
        return $order ? $order->update(['status' => $status]) : false;
    }
}