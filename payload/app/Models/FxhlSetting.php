<?php

namespace Pterodactyl\Models;

use Throwable;
use Illuminate\Support\Facades\Crypt;

class FxhlSetting extends Model
{
    protected $table = 'fxhl_settings';
    protected $primaryKey = 'key';
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value'];

    public static function valueOf(string $key, mixed $default = null): mixed
    {
        try {
            $value = static::query()->where('key', $key)->value('value');
        } catch (Throwable) {
            return $default;
        }

        return is_null($value) ? $default : $value;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        return filter_var(static::valueOf($key, $default ? '1' : '0'), FILTER_VALIDATE_BOOLEAN);
    }

    public static function int(string $key, int $default = 0): int
    {
        return (int) static::valueOf($key, (string) $default);
    }


    public static function secret(string $key, string $default = ''): string
    {
        $value = (string) static::valueOf($key, '');
        if ($value === '') {
            return $default;
        }

        try {
            return Crypt::decryptString($value);
        } catch (Throwable) {
            // Backward compatibility if an older install stored this value unencrypted.
            return $value;
        }
    }

    public static function encryptSecret(string $value): string
    {
        return Crypt::encryptString($value);
    }

    public static function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            static::query()->updateOrCreate(['key' => $key], ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value]);
        }
    }

    public static function publicConfig(): array
    {
        return [
            'brand' => static::valueOf('brand_name', config('app.name', 'Pterodactyl')),
            'primaryColor' => static::valueOf('primary_color', '#1769e0'),
            'backgroundUrl' => static::valueOf('background_url', ''),
            'backgroundOverlay' => max(0, min(90, static::int('background_overlay', 18))),
            'trial' => [
                'enabled' => static::bool('trial_enabled', true),
                'days' => max(1, static::int('trial_days', 3)),
                'buttonText' => static::valueOf('trial_button_text', 'Coba Gratis 3 Hari'),
            ],
            'buy' => [
                'enabled' => static::bool('buy_enabled', false),
                'buttonText' => static::valueOf('buy_button_text', 'Beli Akun'),
                'planName' => static::valueOf('plan_name', 'Akun Panel'),
                'price' => max(0, static::int('plan_price', 10000)),
                'days' => max(0, static::int('plan_days', 30)),
            ],
            'popup' => [
                'enabled' => static::bool('popup_enabled', false),
                'message' => static::valueOf('popup_message', 'Selamat datang di panel.'),
                'type' => static::valueOf('popup_type', 'info'),
                'duration' => max(1000, min(30000, static::int('popup_duration', 4500))),
                'oncePerSession' => static::bool('popup_once_session', true),
            ],
        ];
    }
}
