<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'merchant_id',
        'reference',
        'amount',
        'currency',
        'fee',
        'net_amount',
        'status',
        'payment_method',
        'card_last4',
        'card_brand',
        'customer_email',
        'customer_name',
        'metadata',
        'idempotency_key',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    // Relationships
    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class);
    }
}
