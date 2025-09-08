<?php

namespace App\Services;

use App\Repositories\ProductRepository;
use App\Services\CloudinaryService;

class ProductService
{
    protected $productRepository;
    protected $cloudinaryService;

    public function __construct(ProductRepository $productRepository, CloudinaryService $cloudinaryService)
    {
        $this->productRepository = $productRepository;
        $this->cloudinaryService = $cloudinaryService;
    }

    public function getAllProducts(array $filters = [], int $perPage = 15)
    {
        return $this->productRepository->getAll($filters, $perPage);
    }

    public function getActiveProducts(array $filters = [], int $perPage = 15)
    {
        return $this->productRepository->getActiveProducts($filters, $perPage);
    }

    public function getProductById($id)
    {
        return $this->productRepository->findById($id);
    }

    /**
     * Create product with pre-uploaded images
     * Images should already be uploaded to Cloudinary via ImageUploadController
     */
   public function createProduct(array $data)
{
    // Ensure 'images' is always an array, even if not provided
    if (!isset($data['images']) || !is_array($data['images'])) {
        $data['images'] = [];
    } else {
        // Validate image structure
        foreach ($data['images'] as $image) {
            if (!isset($image['url']) || !isset($image['public_id'])) {
                throw new \Exception('Invalid image data. Each image must have url and public_id.');
            }
        }
    }

    return $this->productRepository->create($data);
}


    /**
     * Update product with various image operations
     */
    public function updateProduct($id, array $data)
    {
        $product = $this->productRepository->findById($id);

        if (!$product) {
            throw new \Exception('Product not found');
        }

        $existingImages = $product->images ?? [];

        // Handle complete image replacement
        if (isset($data['images'])) {
            // Validate new images
            foreach ($data['images'] as $image) {
                if (!isset($image['url']) || !isset($image['public_id'])) {
                    throw new \Exception('Invalid image data. Each image must have url and public_id.');
                }
            }
            
            // Don't automatically delete old images here - let frontend handle cleanup
            // This prevents accidental deletion if user is just reordering
            $finalImages = $data['images'];
            unset($data['images']);
        } else {
            $finalImages = $existingImages;

            // Handle adding new images
            if (isset($data['add_images'])) {
                foreach ($data['add_images'] as $image) {
                    if (!isset($image['url']) || !isset($image['public_id'])) {
                        throw new \Exception('Invalid image data. Each image must have url and public_id.');
                    }
                }
                $finalImages = array_merge($existingImages, $data['add_images']);
                unset($data['add_images']);
            }

            // Handle removing specific images
            if (isset($data['remove_image_ids'])) {
                $removeIds = $data['remove_image_ids'];
                
                // Delete from Cloudinary
                foreach ($removeIds as $publicId) {
                    $this->cloudinaryService->deleteImage($publicId);
                }

                // Remove from array
                $finalImages = array_filter($finalImages, function($image) use ($removeIds) {
                    return !in_array($image['public_id'], $removeIds);
                });
                
                // Re-index array
                $finalImages = array_values($finalImages);
                unset($data['remove_image_ids']);
            }
        }

        // Set final images
        $data['images'] = $finalImages;

        $this->productRepository->update($id, $data);
        return $this->productRepository->findById($id);
    }

    /**
     * Delete product and all associated images from Cloudinary
     */
    public function deleteProduct($id)
    {
        $product = $this->productRepository->findById($id);

        if (!$product) {
            throw new \Exception('Product not found');
        }

        // Delete images from Cloudinary
        if (!empty($product->images)) {
            $publicIds = array_column($product->images, 'public_id');
            $this->cloudinaryService->deleteImages($publicIds);
        }

        return $this->productRepository->delete($id);
    }

    /**
     * Delete a specific image from product and Cloudinary
     */
    public function deleteProductImage($productId, $imagePublicId)
    {
        $product = $this->productRepository->findById($productId);

        if (!$product) {
            throw new \Exception('Product not found');
        }

        // Delete image from Cloudinary
        $this->cloudinaryService->deleteImage($imagePublicId);

        // Remove image from product images array
        $images = array_filter($product->images, function($image) use ($imagePublicId) {
            return $image['public_id'] !== $imagePublicId;
        });

        // Update product with filtered images
        $this->productRepository->update($productId, ['images' => array_values($images)]);

        return $this->productRepository->findById($productId);
    }

    /**
     * Reorder product images
     */
    public function reorderProductImages($productId, array $orderedPublicIds)
    {
        $product = $this->productRepository->findById($productId);

        if (!$product) {
            throw new \Exception('Product not found');
        }

        $existingImages = $product->images ?? [];
        
        // Create a map of public_id to image object
        $imageMap = [];
        foreach ($existingImages as $image) {
            $imageMap[$image['public_id']] = $image;
        }

        // Reorder images according to provided order
        $reorderedImages = [];
        foreach ($orderedPublicIds as $publicId) {
            if (isset($imageMap[$publicId])) {
                $reorderedImages[] = $imageMap[$publicId];
            }
        }

        // Update product with reordered images
        $this->productRepository->update($productId, ['images' => $reorderedImages]);

        return $this->productRepository->findById($productId);
    }
}