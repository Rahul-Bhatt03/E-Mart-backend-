<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;
      protected $fillable = [
        'user_id',
        'order_number',
        'total_amount',
        'status',
        'shipping_address'
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'shipping_address' => 'array'
    ];
    protected function shippingAddress():Attribute{
        return Attribute::make(
            get:fn($value)=>json_decode($value,true)??[],
            set:fn($value)=>json_encode($value)
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
protected static function boot(){
    parent::boot();
    static::creating(function($order){
        $order->order_number='ORD-'.strtoupper(uniqid());
    });
}
}
