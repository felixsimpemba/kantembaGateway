<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    protected $fillable = [
        'merchant_id',
        'event_type',
        'payload',
        'url',
        'status_code',
        'response',
        'signature',
        'attempts',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }
}
