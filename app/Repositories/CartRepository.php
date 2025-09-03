<?php
// app/Repositories/CartRepository.php
namespace App\Repositories;

use App\Models\Cart;
use Illuminate\Database\Eloquent\Collection;

class CartRepository
{
    protected $model;

    public function __construct(Cart $cart)
    {
        $this->model = $cart;
    }

    public function getUserCart($userId): Collection
    {
        return $this->model->with('product')->where('user_id', $userId)->get();
    }

    public function findCartItem($userId, $productId): ?Cart
    {
        return $this->model->where('user_id', $userId)
                          ->where('product_id', $productId)
                          ->first();
    }

    public function addToCart($userId, $productId, $quantity): Cart
    {
        return $this->model->create([
            'user_id' => $userId,
            'product_id' => $productId,
            'quantity' => $quantity
        ]);
    }

    public function updateCartItem($cartItemId, $quantity): bool
    {
        $cartItem = $this->model->find($cartItemId);
        return $cartItem ? $cartItem->update(['quantity' => $quantity]) : false;
    }

    public function removeFromCart($cartItemId): bool
    {
        $cartItem = $this->model->find($cartItemId);
        return $cartItem ? $cartItem->delete() : false;
    }

    public function clearUserCart($userId): bool
    {
        return $this->model->where('user_id', $userId)->delete();
    }

    public function getCartTotal($userId): float
    {
        return $this->model->where('user_id', $userId)
                          ->join('products', 'carts.product_id', '=', 'products.id')
                          ->selectRaw('SUM(carts.quantity * products.price) as total')
                          ->value('total') ?? 0;
    }

    public function getCartItemsCount($userId): int
    {
        return $this->model->where('user_id', $userId)->count();
    }
}