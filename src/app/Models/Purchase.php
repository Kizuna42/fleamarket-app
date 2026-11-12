<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasFactory;

    // 支払い方法（フロント・サーバー共通の値）
    public const PAYMENT_CONVENIENCE_STORE = 'convenience_store';
    public const PAYMENT_CREDIT_CARD = 'credit_card';

    public const PAYMENT_METHODS = [
        self::PAYMENT_CONVENIENCE_STORE => 'コンビニ払い',
        self::PAYMENT_CREDIT_CARD => 'クレジットカード',
    ];

    protected $fillable = [
        'user_id',
        'item_id',
        'price',
        'status',
        'payment_method',
        'stripe_session_id',
        'postal_code',
        'prefecture',
        'city',
        'address',
        'building_name',
        'tracking_number',
    ];

    protected $casts = [
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    // 購入者
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // 購入された商品
    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    // 配送先の完全な住所を取得
    public function getFullAddressAttribute()
    {
        return sprintf(
            '〒%s %s',
            $this->postal_code,
            $this->address
        );
    }

    // 取引状態を確認するためのスコープ
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeShipped($query)
    {
        return $query->where('status', 'shipped');
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    public function scopeCanceled($query)
    {
        return $query->where('status', 'canceled');
    }
}
