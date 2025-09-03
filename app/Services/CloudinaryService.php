<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Cloudinary\Api\Upload\UploadApi;
use Illuminate\Http\UploadedFile;

class CloudinaryService{
    protected $cloudinary;
    protected $uploadApi;

     public function __construct()
    {
        $this->cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => config('services.cloudinary.cloud_name'),
                'api_key' => config('services.cloudinary.api_key'),
                'api_secret' => config('services.cloudinary.api_secret'),
            ],
            'url' => [
                'secure' => true
            ]
        ]);
        $this->uploadApi=new UploadApi();
    }
       public function uploadImage(UploadedFile $file, string $folder = 'products'): array
    {
        $result = $this->uploadApi->upload($file->getRealPath(), [
            'folder' => $folder,
            'transformation' => [
                'width' => 800,
                'height' => 600,
                'crop' => 'limit',
                'quality' => 'auto'
            ]
        ]);

        return [
            'url' => $result['secure_url'],
            'public_id' => $result['public_id'],
            'format' => $result['format'],
            'bytes' => $result['bytes']
        ];
    }
      public function uploadMultipleImages(array $files, string $folder = 'products'): array
    {
        $uploadedImages = [];

        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $uploadedImages[] = $this->uploadImage($file, $folder);
            }
        }

        return $uploadedImages;
    }

    public function deleteImage(string $publicId): bool
    {
        try {
            $result = $this->uploadApi->destroy($publicId);
            return $result['result'] === 'ok';
        } catch (\Exception $e) {
            return false;
        }
    }

    public function deleteImages(array $publicIds): bool
    {
        try {
            $result = $this->uploadApi->destroy($publicIds);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

}