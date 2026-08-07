<?php

namespace Pterodactyl\Http\Controllers\Admin\Fxhl;

use Throwable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\FxhlOrder;
use Pterodactyl\Models\FxhlSetting;
use Pterodactyl\Services\Fxhl\OrderKuotaService;

class SettingsController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeOwner($request);

        return view('admin.fxhl.settings', [
            'settings' => $this->settings(),
            'orders' => FxhlOrder::query()->latest('id')->limit(20)->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeOwner($request);
        $data = $request->validate([
            'brand_name' => ['required', 'string', 'max:80'],
            'primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'background_url' => ['nullable', 'string', 'max:500'],
            'background_overlay' => ['required', 'integer', 'min:0', 'max:90'],
            'background_file' => ['nullable', 'image', 'max:4096'],
            'trial_days' => ['required', 'integer', 'min:1', 'max:365'],
            'trial_ip_cooldown_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'trial_button_text' => ['required', 'string', 'max:60'],
            'buy_button_text' => ['required', 'string', 'max:60'],
            'plan_name' => ['required', 'string', 'max:80'],
            'plan_price' => ['required', 'integer', 'min:1'],
            'plan_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'order_expiry_minutes' => ['required', 'integer', 'min:5', 'max:120'],
            'qris_payload' => ['nullable', 'string', 'max:1000'],
            'popup_message' => ['nullable', 'string', 'max:500'],
            'popup_type' => ['required', 'in:info,success,warning,error'],
            'popup_duration' => ['required', 'integer', 'min:1000', 'max:30000'],
            'orderkuota_endpoint' => ['nullable', 'url', 'max:500'],
            'orderkuota_method' => ['required', 'in:GET,POST'],
            'orderkuota_payload_type' => ['required', 'in:form,json'],
            'orderkuota_api_key_header' => ['nullable', 'string', 'max:80'],
            'orderkuota_action_field' => ['nullable', 'string', 'max:80'],
            'orderkuota_action' => ['nullable', 'string', 'max:191'],
            'orderkuota_token_field' => ['nullable', 'string', 'max:80'],
            'orderkuota_items_path' => ['nullable', 'string', 'max:191'],
            'orderkuota_api_key' => ['nullable', 'string', 'max:1000'],
            'orderkuota_token' => ['nullable', 'string', 'max:3000'],
            'orderkuota_callback_secret' => ['nullable', 'string', 'min:16', 'max:191'],
        ]);

        if ($request->hasFile('background_file')) {
            $file = $request->file('background_file');
            $extension = match ($file->getMimeType()) {
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
                default => 'jpg',
            };
            $name = 'background-' . time() . '.' . $extension;
            $directory = public_path('themes/fxhl/uploads');
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
            $file->move($directory, $name);
            $data['background_url'] = '/themes/fxhl/uploads/' . $name;
        }

        foreach (['trial_enabled', 'buy_enabled', 'popup_enabled', 'popup_once_session', 'orderkuota_bearer_token'] as $checkbox) {
            $data[$checkbox] = $request->boolean($checkbox);
        }

        foreach (['orderkuota_api_key', 'orderkuota_token'] as $secret) {
            if (empty($data[$secret])) {
                unset($data[$secret]);
            } else {
                $data[$secret] = FxhlSetting::encryptSecret($data[$secret]);
            }
        }

        if (empty($data['orderkuota_callback_secret'])) {
            $data['orderkuota_callback_secret'] = FxhlSetting::valueOf('orderkuota_callback_secret', Str::random(48));
        }

        unset($data['background_file']);
        FxhlSetting::setMany($data);

        return redirect()->route('admin.fxhl.index')->with('fxhl_success', 'Pengaturan tema dan akun berhasil disimpan.');
    }

    public function test(Request $request, OrderKuotaService $gateway): RedirectResponse
    {
        $this->authorizeOwner($request);
        try {
            $count = count($gateway->fetchMutations());
            return redirect()->route('admin.fxhl.index')->with('fxhl_success', "Koneksi berhasil. {$count} catatan mutasi dikenali.");
        } catch (Throwable $exception) {
            return redirect()->route('admin.fxhl.index')->with('fxhl_error', $exception->getMessage());
        }
    }

    private function authorizeOwner(Request $request): void
    {
        abort_unless((int) $request->user()?->id === 1, 403, 'Menu ini khusus Admin ID 1.');
    }

    private function settings(): array
    {
        $defaults = [
            'brand_name' => config('app.name', 'Pterodactyl'),
            'primary_color' => '#1769e0',
            'background_url' => '',
            'background_overlay' => '18',
            'trial_enabled' => '1',
            'trial_days' => '3',
            'trial_ip_cooldown_days' => '30',
            'trial_button_text' => 'Coba Gratis 3 Hari',
            'buy_enabled' => '0',
            'buy_button_text' => 'Beli Akun',
            'plan_name' => 'Akun Panel',
            'plan_price' => '10000',
            'plan_days' => '30',
            'order_expiry_minutes' => '15',
            'qris_payload' => '',
            'popup_enabled' => '0',
            'popup_message' => 'Selamat datang di panel.',
            'popup_type' => 'info',
            'popup_duration' => '4500',
            'popup_once_session' => '1',
            'orderkuota_endpoint' => '',
            'orderkuota_method' => 'POST',
            'orderkuota_payload_type' => 'form',
            'orderkuota_api_key_header' => 'apikey',
            'orderkuota_action_field' => 'action',
            'orderkuota_action' => 'qris_mutations',
            'orderkuota_token_field' => 'auth_token',
            'orderkuota_items_path' => '',
            'orderkuota_bearer_token' => '1',
            'orderkuota_callback_secret' => Str::random(48),
        ];

        foreach ($defaults as $key => $default) {
            $defaults[$key] = FxhlSetting::valueOf($key, $default);
        }

        return $defaults;
    }
}
