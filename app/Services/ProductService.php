<?php

namespace App\Services;

use App\Repositories\ProductRepository;
use Illuminate\Http\UploadedFile;

class ProductService
{
    protected $productReposiotry;
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
    public function createProduct(array $data)
    {
        //handle image uploads
        if (isset($data['images']) && is_array($data['images'])) {
            $uploadedImages = $this->cloudinaryService->uploadMultipleImages($data['images']);
            $data['images'] = $uploadedImages;
        }
        return $this->productRepository->create($data);
    }
     public function updateProduct($id, array $data)
    {
        $product = $this->productRepository->findById($id);

        if (!$product) {
            throw new \Exception('Product not found');
        }

        // Handle new image uploads
        if (isset($data['images']) && is_array($data['images'])) {
            $newImages = $this->cloudinaryService->uploadMultipleImages($data['images']);
            
            // Merge with existing images
            $existingImages = $product->images ?? [];
            $data['images'] = array_merge($existingImages, $newImages);
        }

        $this->productRepository->update($id, $data);

        return $this->productRepository->findById($id);
    }

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

        $product->images = array_values($images);
        $product->save();

        return $product;
    }
}
