<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ImageUploadController extends Controller
{
    protected $cloudinaryService;

    public function __construct(CloudinaryService $cloudinaryService)
    {
        $this->cloudinaryService = $cloudinaryService;
    }

    /**
     * Upload single image to Cloudinary
     */
    public function uploadSingle(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|file|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'folder' => 'sometimes|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $folder = $request->input('folder', 'products');
            $uploadedImage = $this->cloudinaryService->uploadImage($request->file('image'), $folder);

            return response()->json([
                'success' => true,
                'message' => 'Image uploaded successfully',
                'data' => $uploadedImage
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            Log::error('Image upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to upload image',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Upload multiple images to Cloudinary
     */
   public function uploadMultiple(Request $request)
{
    $validator = Validator::make($request->all(), [
        'images' => 'required|array|max:10',
        'images.*' => 'file|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        'folder' => 'sometimes|string|max:50'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'error' => 'Validation failed',
            'errors' => $validator->errors()
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    try {
        $folder = $request->input('folder', 'products');
        $images = $request->file('images');
        
        $uploadedImages = $this->cloudinaryService->uploadMultipleImages($images, $folder);

        return response()->json([
            'success' => true,
            'message' => count($uploadedImages) . ' images uploaded successfully',
            'data' => $uploadedImages
        ], Response::HTTP_CREATED);

    } catch (\Exception $e) {
        Log::error('Multiple image upload failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'file_count' => count($request->file('images') ?? [])
        ]);

        // Check if it's a Cloudinary configuration issue
        if (strpos($e->getMessage(), 'configuration') !== false) {
            return response()->json([
                'error' => 'Cloudinary configuration error',
                'message' => 'Please check your Cloudinary credentials in the .env file'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'error' => 'Failed to upload images',
            'message' => $e->getMessage()
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}

    /**
     * Delete image from Cloudinary
     */
    public function deleteImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'public_id' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $publicId = $request->input('public_id');
            $deleted = $this->cloudinaryService->deleteImage($publicId);

            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'Image deleted successfully'
                ]);
            } else {
                return response()->json([
                    'error' => 'Failed to delete image'
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

        } catch (\Exception $e) {
            Log::error('Image deletion failed', [
                'public_id' => $request->input('public_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to delete image',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete multiple images from Cloudinary
     */
    public function deleteMultiple(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'public_ids' => 'required|array',
            'public_ids.*' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $publicIds = $request->input('public_ids');
            $deleted = $this->cloudinaryService->deleteImages($publicIds);

            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => count($publicIds) . ' images deleted successfully'
                ]);
            } else {
                return response()->json([
                    'error' => 'Some images failed to delete'
                ], Response::HTTP_PARTIAL_CONTENT);
            }

        } catch (\Exception $e) {
            Log::error('Multiple image deletion failed', [
                'public_ids' => $request->input('public_ids'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to delete images',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}