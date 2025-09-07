<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    protected $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    public function index(Request $request)
    {
        try {
            $filters = [
                'search' => $request->search,
                'status' => $request->status,
                'min_price' => $request->min_price,
                'max_price' => $request->max_price,
            ];

            $products = $this->productService->getAllProducts($filters, $request->per_page ?? 15);

            return response()->json([
                'products' => $products,
                'message' => 'Products retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Product fetch error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Failed to fetch products'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show($id)
    {
        try {
            $product = $this->productService->getProductById($id);

            if (!$product) {
                return response()->json(['error' => 'Product not found'], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'product' => $product,
                'message' => 'Product retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Product show error: ' . $e->getMessage(), [
                'product_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Failed to fetch product'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Store a newly created product (without handling file uploads)
     * Images should be uploaded separately via ImageUploadController
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'sku' => 'required|string|unique:products,sku',
            'status' => 'sometimes|in:active,inactive',
            'images' => 'sometimes|array', // Array of image objects with url and public_id
            'images.*.url' => 'required_with:images|string|url',
            'images.*.public_id' => 'required_with:images|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $data = $request->all();
            $product = $this->productService->createProduct($data);

            return response()->json([
                'product' => $product,
                'message' => 'Product created successfully'
            ], Response::HTTP_CREATED);

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Database error during product creation", [
                'error' => $e->getMessage(),
                'sql_state' => $e->errorInfo[0] ?? null,
                'request_data' => $request->except(['images'])
            ]);
            
            return response()->json([
                'error' => 'Database error occurred'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);

        } catch (\Exception $e) {
            Log::error("Product creation failed", [
                'error' => $e->getMessage(), 
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['images'])
            ]);
            
            return response()->json([
                'error' => 'Failed to create product: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified product (without handling file uploads)
     * New images should be uploaded separately via ImageUploadController
     */
    public function update(Request $request, $id)
    {
        // Validate ID first
        if (!is_numeric($id)) {
            return response()->json(['error' => 'Invalid product ID'], Response::HTTP_BAD_REQUEST);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'price' => 'sometimes|numeric|min:0',
            'stock_quantity' => 'sometimes|integer|min:0',
            'sku' => 'sometimes|string|unique:products,sku,' . $id,
            'status' => 'sometimes|in:active,inactive',
            'images' => 'sometimes|array', // Complete array replacement
            'images.*.url' => 'required_with:images|string|url',
            'images.*.public_id' => 'required_with:images|string',
            'add_images' => 'sometimes|array', // Images to add to existing
            'add_images.*.url' => 'required_with:add_images|string|url',
            'add_images.*.public_id' => 'required_with:add_images|string',
            'remove_image_ids' => 'sometimes|array', // Public IDs to remove
            'remove_image_ids.*' => 'string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            // Check if product exists first
            $existingProduct = $this->productService->getProductById($id);
            if (!$existingProduct) {
                return response()->json(['error' => 'Product not found'], Response::HTTP_NOT_FOUND);
            }

            $data = $request->all();
            $product = $this->productService->updateProduct($id, $data);

            return response()->json([
                'product' => $product,
                'message' => 'Product updated successfully'
            ]);
            
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Database error during product update", [
                'product_id' => $id,
                'error' => $e->getMessage(),
                'sql_state' => $e->errorInfo[0] ?? null
            ]);
            
            return response()->json([
                'error' => 'Database error occurred'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
            
        } catch (\Exception $e) {
            Log::error('Product update error', [
                'product_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Failed to update product: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy($id)
    {
        if (!is_numeric($id)) {
            return response()->json(['error' => 'Invalid product ID'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $product = $this->productService->getProductById($id);
            if (!$product) {
                return response()->json(['error' => 'Product not found'], Response::HTTP_NOT_FOUND);
            }

            $this->productService->deleteProduct($id);

            return response()->json(['message' => 'Product deleted successfully']);
            
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("Database error during product deletion", [
                'product_id' => $id,
                'error' => $e->getMessage(),
                'sql_state' => $e->errorInfo[0] ?? null
            ]);
            
            return response()->json([
                'error' => 'Cannot delete product due to database constraints'
            ], Response::HTTP_CONFLICT);
            
        } catch (\Exception $e) {
            Log::error('Product delete error', [
                'product_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Failed to delete product: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a specific image from a product
     * This also removes it from Cloudinary
     */
    public function deleteImage($productId, $imagePublicId)
    {
        if (!is_numeric($productId)) {
            return response()->json(['error' => 'Invalid product ID'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $product = $this->productService->getProductById($productId);
            if (!$product) {
                return response()->json(['error' => 'Product not found'], Response::HTTP_NOT_FOUND);
            }

            $updatedProduct = $this->productService->deleteProductImage($productId, $imagePublicId);

            return response()->json([
                'product' => $updatedProduct,
                'message' => 'Image deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Image delete error', [
                'product_id' => $productId,
                'image_public_id' => $imagePublicId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Failed to delete image: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}