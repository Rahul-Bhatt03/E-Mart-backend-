<?php

namespace App\Http\Controllers;

use App\Services\CartService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Response;

class CartController extends Controller{
    protected $cartService;

    public function __construct(CartService $cartService){
        $this->cartService=$cartService;
    }

    public function index(Request $request){
        try{
            $cartItems=$this->cartService->getUserCart($request->user()->id);
            $total=$this->cartService->getCartTotal($request->user()->id);
            $itemsCount=$this->cartService->getCartItemsCount($request->user()->id);
        
        return response()->json([
            'cart_items'=>$cartItems,
            'total'=>$total,
            'items_count'=>$itemsCount,
            'message'=>'Cart retrieved succesfully'
        ]);
    }catch(\Exception $e){
        return response()->json(['error'=>$e->getMessage()],Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}

public function store(Request $request){
    $validator=Validator::make($request->all(),[
           'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1'
    ]);
    if($validator->fails()){
        return response()->json([
            'errors'=>$validator->errors()
        ],Response::HTTP_UNPROCESSABLE_ENTITY);
    }
    try{
$cartItem=$this->cartService->addToCart(
    $request->user()->id,
    $request->product_id,
    $request->quantity
);
return response()->json([
    'cart_item'=>$cartItem,
    'message'=>'Item added to the cart successfully'
],Response::HTTP_CREATED);
    }catch(Exception $e){
         return response()->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}

  public function update(Request $request, $cartItemId)
    {
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $cartItem = $this->cartService->updateCartItem(
                $request->user()->id,
                $cartItemId,
                $request->quantity
            );

            return response()->json([
                'cart_item' => $cartItem,
                'message' => 'Cart item updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

 public function destroy(Request $request, $cartItemId)
    {
        try {
            $this->cartService->removeFromCart($request->user()->id, $cartItemId);

            return response()->json(['message' => 'Item removed from cart successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function clear(Request $request)
    {
        try {
            $this->cartService->clearCart($request->user()->id);

            return response()->json(['message' => 'Cart cleared successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}