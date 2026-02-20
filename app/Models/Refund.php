<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    protected $fillable = [
        'payment_id',
        'merchant_id',
        'reference',
        'amount',
        'status',
        'reason',
        'idempotency_key',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    // Relationships
    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }
}
