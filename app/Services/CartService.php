<?php

namespace App\Services;

use App\Repositories\CartRepository;
use App\Repositories\ProductRepository;

class CartService
{
    protected $cartRepository;
    protected $productRepository;

    public function __construct(CartRepository $cartRepository, ProductRepository $productRepository)
    {
        $this->cartRepository = $cartRepository;
        $this->productRepository = $productRepository;
    }

    public function getUserCart($userId)
    {
        return $this->cartRepository->getUserCart($userId);
    }

    public function addToCart($userId, $productId, $quantity)
    {
        $product = $this->productRepository->findById($productId);

        if (!$product) {
            throw new \Exception('Product not found');
        }

        if ($product->status !== 'active') {
            throw new \Exception('Product is not available');
        }

        if ($product->stock_quantity < $quantity) {
            throw new \Exception('Insufficient stock');
        }

        // Check if product already in cart
        $existingCartItem = $this->cartRepository->findCartItem($userId, $productId);

        if ($existingCartItem) {
            $newQuantity = $existingCartItem->quantity + $quantity;
            
            if ($product->stock_quantity < $newQuantity) {
                throw new \Exception('Insufficient stock for the requested quantity');
            }

            return $this->cartRepository->updateCartItem($existingCartItem->id, $newQuantity);
        }

        return $this->cartRepository->addToCart($userId, $productId, $quantity);
    }

    public function updateCartItem($userId, $cartItemId, $quantity)
    {
        $cartItem = $this->cartRepository->getUserCart($userId)
            ->firstWhere('id', $cartItemId);

        if (!$cartItem) {
            throw new \Exception('Cart item not found');
        }

        $product = $cartItem->product;

        if ($product->stock_quantity < $quantity) {
            throw new \Exception('Insufficient stock');
        }

        return $this->cartRepository->updateCartItem($cartItemId, $quantity);
    }

    public function removeFromCart($userId, $cartItemId)
    {
        $cartItem = $this->cartRepository->getUserCart($userId)
            ->firstWhere('id', $cartItemId);

        if (!$cartItem) {
            throw new \Exception('Cart item not found');
        }

        return $this->cartRepository->removeFromCart($cartItemId);
    }

    public function clearCart($userId)
    {
        return $this->cartRepository->clearUserCart($userId);
    }

    public function getCartTotal($userId)
    {
        return $this->cartRepository->getCartTotal($userId);
    }

    public function getCartItemsCount($userId)
    {
        return $this->cartRepository->getCartItemsCount($userId);
    }
}