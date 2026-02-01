<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Merchant extends Model
{
    protected $fillable = [
        'name',
        'email',
        'password',
        'business_name',
        'status',
        'is_admin',
        'webhook_url',
        'webhook_secret',
        'balance',
        'currency',
    ];

    protected $hidden = [
        'password',
        'webhook_secret',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'password' => 'hashed',
        'is_admin' => 'boolean',
    ];

    // Relationships
    public function apiKeys()
    {
        return $this->hasMany(ApiKey::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class);
    }

    public function webhooks()
    {
        return $this->hasMany(Webhook::class);
    }

    public function webhookLogs()
    {
        return $this->hasMany(WebhookLog::class);
    }
}
