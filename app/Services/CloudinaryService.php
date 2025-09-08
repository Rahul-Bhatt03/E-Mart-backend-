<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Cloudinary\Api\Upload\UploadApi;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CloudinaryService
{
    protected $cloudinary;
    protected $uploadApi;

    public function __construct()
    {
        // Validate Cloudinary configuration
        $cloudName = config('services.cloudinary.cloud_name');
        $apiKey = config('services.cloudinary.api_key');
        $apiSecret = config('services.cloudinary.api_secret');

        if (!$cloudName || !$apiKey || !$apiSecret) {
            throw new \Exception('Cloudinary configuration is missing. Please check your .env file.');
        }

        try {
            $this->cloudinary = new Cloudinary([
                'cloud' => [
                    'cloud_name' => $cloudName,
                    'api_key' => $apiKey,
                    'api_secret' => $apiSecret,
                ],
                'url' => [
                    'secure' => true
                ]
            ]);
            
            $this->uploadApi = $this->cloudinary->uploadApi();
            
        } catch (\Exception $e) {
            Log::error('CloudinaryService: Failed to initialize Cloudinary', [
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Failed to initialize Cloudinary service: ' . $e->getMessage());
        }
    }

    public function uploadImage(UploadedFile $file, string $folder = 'products'): array
    {
        try {
            // Validate file first
            if (!$file->isValid()) {
                throw new \Exception('Invalid file upload');
            }

            // Check file size (2MB limit)
            if ($file->getSize() > 2048000) {
                throw new \Exception('File size exceeds 2MB limit');
            }

            // Check if file is actually an image
            $allowedMimes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
            if (!in_array($file->getMimeType(), $allowedMimes)) {
                throw new \Exception('File must be a valid image (JPEG, PNG, JPG, GIF, WebP)');
            }

            // Get temporary file path
            $filePath = $file->getRealPath();
            if (!$filePath || !file_exists($filePath)) {
                throw new \Exception('File path not found');
            }

            // Generate unique public ID
            $publicId = $folder . '/' . uniqid() . '_' . $this->sanitizeFilename($file->getClientOriginalName());

            Log::info('CloudinaryService: Starting upload', [
                'original_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'folder' => $folder,
                'public_id' => $publicId
            ]);

            // Upload using the simplest method
            $uploadResult = $this->uploadApi->upload(
                $filePath,
                [
                    'public_id' => $publicId,
                    'folder' => $folder,
                    'resource_type' => 'image',
                    'timeout' => 30
                ]
            );

            if (!isset($uploadResult['secure_url']) || !isset($uploadResult['public_id'])) {
                throw new \Exception('Upload response missing required fields');
            }

            Log::info('CloudinaryService: Upload successful', [
                'public_id' => $uploadResult['public_id'],
                'url' => $uploadResult['secure_url']
            ]);

            return [
                'url' => $uploadResult['secure_url'],
                'public_id' => $uploadResult['public_id'],
                'format' => $uploadResult['format'] ?? 'unknown',
                'bytes' => $uploadResult['bytes'] ?? 0
            ];

        } catch (\Exception $e) {
            Log::error('CloudinaryService: Upload failed', [
                'error' => $e->getMessage(),
                'file_name' => $file->getClientOriginalName() ?? 'unknown',
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception('Image upload failed: ' . $e->getMessage());
        }
    }

    private function sanitizeFilename($filename)
    {
        // Remove file extension
        $name = pathinfo($filename, PATHINFO_FILENAME);
        
        // Replace problematic characters
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
        
        // Remove consecutive underscores
        $name = preg_replace('/_+/', '_', $name);
        
        // Trim underscores from start and end
        $name = trim($name, '_');
        
        // Ensure it's not empty
        if (empty($name)) {
            $name = 'image';
        }
        
        // Limit length
        return substr($name, 0, 50);
    }

    public function uploadMultipleImages(array $files, string $folder = 'products'): array
    {
        $uploadedImages = [];
        $errors = [];

        foreach ($files as $index => $file) {
            if (!($file instanceof UploadedFile)) {
                $errors[] = "File at index {$index} is not a valid upload";
                continue;
            }

            try {
                $uploadedImages[] = $this->uploadImage($file, $folder);
                
            } catch (\Exception $e) {
                $errors[] = "File '{$file->getClientOriginalName()}': {$e->getMessage()}";
                Log::error('CloudinaryService: Failed to upload file in batch', [
                    'file_name' => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                    'index' => $index
                ]);
            }
        }

        if (!empty($errors) && empty($uploadedImages)) {
            // All uploads failed
            throw new \Exception('All image uploads failed: ' . implode(', ', $errors));
        }

        if (!empty($errors)) {
            // Some uploads failed
            Log::warning('CloudinaryService: Some uploads failed in batch', [
                'successful_uploads' => count($uploadedImages),
                'failed_uploads' => count($errors),
                'errors' => $errors
            ]);
        }

        return $uploadedImages;
    }

    public function deleteImage(string $publicId): bool
    {
        try {
            if (empty($publicId)) {
                Log::warning('CloudinaryService: Attempted to delete image with empty public_id');
                return false;
            }

            Log::info('CloudinaryService: Deleting image', ['public_id' => $publicId]);

            $result = $this->uploadApi->destroy($publicId, [
                'timeout' => 30
            ]);

            $success = isset($result['result']) && $result['result'] === 'ok';

            if (!$success) {
                Log::warning('CloudinaryService: Image deletion returned non-ok result', [
                    'public_id' => $publicId,
                    'result' => $result['result'] ?? 'unknown'
                ]);
            } else {
                Log::info('CloudinaryService: Image deleted successfully', [
                    'public_id' => $publicId
                ]);
            }

            return $success;

        } catch (\Cloudinary\Api\Exception\NotFound $e) {
            Log::warning('CloudinaryService: Image not found for deletion', [
                'public_id' => $publicId,
                'error' => $e->getMessage()
            ]);
            return true; // Consider it successful if image doesn't exist

        } catch (\Exception $e) {
            Log::error('CloudinaryService: Failed to delete image', [
                'public_id' => $publicId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function deleteImages(array $publicIds): bool
    {
        if (empty($publicIds)) {
            return true;
        }

        $successCount = 0;
        $totalCount = count($publicIds);

        foreach ($publicIds as $publicId) {
            if ($this->deleteImage($publicId)) {
                $successCount++;
            }
        }

        return $successCount === $totalCount;
    }
}