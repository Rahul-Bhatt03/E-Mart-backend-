<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_number',
        'total_amount',
        'tax_amount',
        'shipping_amount',
        'status',
        'payment_status',
        'shipping_address',
        'stripe_payment_intent_id',
        'payment_metadata'
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'shipping_address' => 'array',
        'payment_metadata' => 'array'
    ];

    protected function shippingAddress(): Attribute
    {
        return Attribute::make(
            get: fn($value) => json_decode($value, true) ?? [],
            set: fn($value) => json_encode($value)
        );
    }

    protected function paymentMetadata(): Attribute
    {
        return Attribute::make(
            get: fn($value) => json_decode($value, true) ?? [],
            set: fn($value) => json_encode($value)
        );
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($order) {
            $order->order_number = 'ORD-' . strtoupper(uniqid());
        });
    }

    // Helper method to calculate total with tax and shipping
    public function getFinalAmountAttribute()
    {
        return $this->total_amount + $this->tax_amount + $this->shipping_amount;
    }

    // Helper method to check if payment is successful
    public function isPaymentSucceeded()
    {
        return $this->payment_status === 'succeeded';
    }
}