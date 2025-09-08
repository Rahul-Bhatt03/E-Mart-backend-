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
        'payment_metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
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
    
    // Scopes
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByPaymentStatus($query, $paymentStatus)
    {
        return $query->where('payment_status', $paymentStatus);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Accessors
    public function getFormattedTotalAttribute()
    {
        return number_format($this->total_amount, 2);
    }

    public function getFormattedTaxAttribute()
    {
        return number_format($this->tax_amount, 2);
    }

    public function getFormattedShippingAttribute()
    {
        return number_format($this->shipping_amount, 2);
    }

    // Methods
    public function canBeCancelled()
    {
        return in_array($this->status, ['pending', 'processing']) && 
               in_array($this->payment_status, ['pending', 'processing']);
    }

    public function isPaid()
    {
        return $this->payment_status === 'succeeded';
    }

    public function isCompleted()
    {
        return $this->status === 'delivered';
    }
}