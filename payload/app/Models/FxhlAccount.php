<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class FxhlAccount extends Model
{
    protected $table = 'fxhl_accounts';
    protected $fillable = ['user_id', 'kind', 'expires_at', 'order_id', 'signup_ip'];
    protected $casts = ['expires_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(FxhlOrder::class, 'order_id');
    }

    public function expired(): bool
    {
        return !is_null($this->expires_at) && $this->expires_at->isPast();
    }

    public static function isExpiredForUser(int $userId): bool
    {
        $account = static::query()->where('user_id', $userId)->first();
        return $account ? $account->expired() : false;
    }

    public static function remainingForUser(int $userId): ?int
    {
        $expiresAt = static::query()->where('user_id', $userId)->value('expires_at');
        if (!$expiresAt) {
            return null;
        }

        return max(0, Carbon::now()->diffInSeconds(Carbon::parse($expiresAt), false));
    }
}
