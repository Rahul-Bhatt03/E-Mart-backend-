<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Product extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'name',
        'description',
        'price',
        'stock_quantity',
        'sku',
        'status',
        'images'
    ];

    protected function price(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => (float) $value,
            set: fn ($value) => (float) $value
        );
    }

    protected function stockQuantity(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => (int) $value,
            set: fn ($value) => (int) $value
        );
    }

    protected function images(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => json_decode($value, true) ?? [],
            set: fn ($value) => is_array($value) ? json_encode($value) : $value
        );
    }

    // Remove the $casts property entirely

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function carts()
    {
        return $this->hasMany(Cart::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
}