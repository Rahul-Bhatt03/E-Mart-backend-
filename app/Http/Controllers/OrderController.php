<?php

namespace App\Http\Controllers;

use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Response;

class OrderController extends Controller
{
    protected $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }
    public function userOrders(Request $request)
    {
        try {
            $orders = $this->orderService->getUserOrders($request->user()->id, $request->per_page ?? 15);
            return response()->json([
                'orders' => $orders,
                'message' => 'Orders retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function userShow(Request $request, $id)
    {
        try {
            $order = $this->orderService->getOrderById($id);
            if (!$order || $order->user_id !== $request->user()->id) {
                return response()->json(['error' => 'order not found'], Response::HTTP_NOT_FOUND);
            }
            return response()->json([
                'order' => $order,
                'message' => 'Order Retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'shipping_address' => 'required|array',
            'shipping_address.street' => 'required|string',
            'shipping_address.city' => 'required|string',
            'shipping_address.state' => 'required|string',
            'shipping_address.postal_code' => 'required|string',
            'shipping_address.country' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        try {
            $order = $this->orderService->createOrderFromCart(
                $request->user()->id,
                $request->shipping_address
            );
            return response()->json([
                'order' => $order,
                'message' => 'order created successfully'
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // admin endpoints 
    public function adminIndex(Request $request){
        $this->authorize('admin-access');
        try{
            $filters=[
                 'status' => $request->status,
                'user_id' => $request->user_id,
                'order_number' => $request->order_number,
            ];
            $orders=$this->orderService->getAllOrders($filters,$request->per_page??15);
            return response()->json([
                'orders'=>$orders,
                'message'=>'Orders retrieved successfully'
            ]);
        }catch(\Exception $e){
            return response()->josn(['error'=>$e->getMessage()],Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
      public function adminShow($id)
    {
        $this->authorize('admin-access');

        try {
            $order = $this->orderService->getOrderById($id);

            if (!$order) {
                return response()->json(['error' => 'Order not found'], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'order' => $order,
                'message' => 'Order retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function updateStatus(Request $request,$id){
        $this->authorize('admin-access');
        $request->validate([
            'status'=>'required|in:pending,processing,shipped,delivered,cancelled'
        ]);
        try{
            $order=$this->orderService->updateOrderStatus($id,$request->status);
            return response()->json([
                'order'=>$order,
                'message'=>'order status updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
