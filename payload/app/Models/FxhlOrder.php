<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;

class FxhlOrder extends Model
{
    protected $table = 'fxhl_orders';
    protected $fillable = [
        'code', 'email', 'username', 'name_first', 'name_last', 'password_encrypted',
        'plan_name', 'base_amount', 'payable_amount', 'duration_days', 'status',
        'gateway_reference', 'gateway_payload', 'qris_payload', 'last_checked_at',
        'expires_at', 'paid_at', 'signup_ip',
    ];
    protected $hidden = ['password_encrypted'];
    protected $casts = [
        'gateway_payload' => 'array',
        'last_checked_at' => 'datetime',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function account(): HasOne
    {
        return $this->hasOne(FxhlAccount::class, 'order_id');
    }
}
