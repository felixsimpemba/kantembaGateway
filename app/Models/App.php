<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class App extends Model
{
    protected $fillable = [
        'merchant_id',
        'name',
        'app_id',
        'app_secret',
        'status',
        'webhook_url',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected $hidden = [
        'app_secret',
    ];

    // Auto-generate app_id + app_secret on creation
    protected static function booted(): void
    {
        static::creating(function (App $app) {
            if (empty($app->app_id)) {
                $app->app_id = (string) Str::uuid();
            }
            if (empty($app->app_secret)) {
                $app->app_secret = Str::random(64);
            }
        });
    }

    // ── Relationships ────────────────────────────────────────────
    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function users()
    {
        return $this->hasMany(AppUser::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    // ── Scopes ───────────────────────────────────────────────────
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
