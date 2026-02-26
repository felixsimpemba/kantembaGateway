<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppUser extends Model
{
    protected $fillable = [
        'app_id',
        'external_user_id',
        'name',
        'email',
        'phone',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    // ── Relationships ────────────────────────────────────────────
    public function app()
    {
        return $this->belongsTo(App::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    // ── Helpers ──────────────────────────────────────────────────

    /**
     * Find or create an AppUser for a given app by external_user_id.
     * Updates name/email/phone if provided.
     */
    public static function upsertForApp(int $appId, array $data): self
    {
        $user = static::firstOrNew([
            'app_id'           => $appId,
            'external_user_id' => $data['external_user_id'],
        ]);

        if (!empty($data['name']))  $user->name  = $data['name'];
        if (!empty($data['email'])) $user->email = $data['email'];
        if (!empty($data['phone'])) $user->phone = $data['phone'];
        if (!empty($data['metadata'])) $user->metadata = $data['metadata'];

        $user->save();

        return $user;
    }
}
