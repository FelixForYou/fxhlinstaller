<?php

namespace Pterodactyl\Http\Controllers\Fxhl;

use Throwable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\FxhlAccount;
use Pterodactyl\Models\FxhlOrder;
use Pterodactyl\Models\FxhlSetting;
use Pterodactyl\Models\User;
use Pterodactyl\Services\Fxhl\OrderKuotaService;
use Pterodactyl\Services\Fxhl\QrisService;
use Pterodactyl\Services\Users\UserCreationService;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PortalController extends Controller
{
    public function trial(Request $request, UserCreationService $users): JsonResponse
    {
        if (!FxhlSetting::bool('trial_enabled', true)) {
            throw new HttpException(403, 'Pendaftaran trial sedang dinonaktifkan.');
        }

        $data = $request->validate($this->accountRules());
        $cooldown = max(1, FxhlSetting::int('trial_ip_cooldown_days', 30));
        if (FxhlAccount::query()
            ->where('kind', 'trial')
            ->where('signup_ip', $request->ip())
            ->where('created_at', '>=', Carbon::now()->subDays($cooldown))
            ->exists()) {
            throw new HttpException(429, "IP ini sudah pernah membuat trial dalam {$cooldown} hari terakhir.");
        }

        $user = $users->handle([
            'email' => strtolower($data['email']),
            'username' => strtolower($data['username']),
            'name_first' => $data['name_first'],
            'name_last' => $data['name_last'],
            'password' => $data['password'],
            'root_admin' => false,
            'language' => config('app.locale', 'en'),
        ]);

        $days = max(1, FxhlSetting::int('trial_days', 3));
        FxhlAccount::query()->create([
            'user_id' => $user->id,
            'kind' => 'trial',
            'expires_at' => Carbon::now()->addDays($days),
            'signup_ip' => $request->ip(),
        ]);

        Auth::guard()->login($user);
        $request->session()->regenerate();

        return response()->json([
            'success' => true,
            'message' => "Akun trial aktif selama {$days} hari.",
            'redirect' => route('index'),
        ]);
    }

    public function createOrder(Request $request, QrisService $qris): JsonResponse
    {
        if (!FxhlSetting::bool('buy_enabled', false)) {
            throw new HttpException(403, 'Pembelian akun sedang dinonaktifkan.');
        }

        $data = $request->validate($this->accountRules());
        $baseAmount = max(1, FxhlSetting::int('plan_price', 10000));
        $payableAmount = $this->uniquePayableAmount($baseAmount);
        $payload = trim((string) FxhlSetting::valueOf('qris_payload', ''));
        $dynamicPayload = $qris->withAmount($payload, $payableAmount);
        $expiresAt = Carbon::now()->addMinutes(max(5, FxhlSetting::int('order_expiry_minutes', 15)));

        do {
            $code = strtoupper(Str::random(20));
        } while (FxhlOrder::query()->where('code', $code)->exists());

        $order = FxhlOrder::query()->create([
            'code' => $code,
            'email' => strtolower($data['email']),
            'username' => strtolower($data['username']),
            'name_first' => $data['name_first'],
            'name_last' => $data['name_last'],
            'password_encrypted' => Crypt::encryptString($data['password']),
            'plan_name' => FxhlSetting::valueOf('plan_name', 'Akun Panel'),
            'base_amount' => $baseAmount,
            'payable_amount' => $payableAmount,
            'duration_days' => max(0, FxhlSetting::int('plan_days', 30)),
            'status' => 'pending',
            'qris_payload' => $dynamicPayload,
            'expires_at' => $expiresAt,
            'signup_ip' => $request->ip(),
        ]);

        return response()->json($this->orderResponse($order));
    }

    public function orderStatus(Request $request, string $code, OrderKuotaService $gateway): JsonResponse
    {
        /** @var FxhlOrder $order */
        $order = FxhlOrder::query()->where('code', $code)->firstOrFail();
        $gatewayError = null;

        try {
            $order = $gateway->sync($order);
        } catch (Throwable $exception) {
            report($exception);
            $gatewayError = 'Pengecekan pembayaran belum berhasil. Sistem akan mencoba lagi.';
        }

        if ($order->status === 'paid' && $order->account?->user) {
            Auth::guard()->login($order->account->user);
            $request->session()->regenerate();
        }

        return response()->json(array_merge($this->orderResponse($order), [
            'gatewayError' => $gatewayError,
            'redirect' => $order->status === 'paid' ? route('index') : null,
        ]));
    }

    public function callback(Request $request, OrderKuotaService $gateway): JsonResponse
    {
        $expected = (string) FxhlSetting::valueOf('orderkuota_callback_secret', '');
        $provided = (string) ($request->header('X-FXHL-Callback-Secret') ?: $request->input('secret', ''));
        if ($expected === '' || !hash_equals($expected, $provided)) {
            throw new HttpException(403, 'Callback secret tidak valid.');
        }

        $data = $request->validate([
            'amount' => ['required'],
            'reference' => ['required', 'string', 'max:191'],
            'payload' => ['sometimes', 'array'],
        ]);
        $amount = (int) preg_replace('/[^0-9]/', '', (string) $data['amount']);

        /** @var FxhlOrder|null $order */
        $order = FxhlOrder::query()
            ->where('status', 'pending')
            ->where('payable_amount', $amount)
            ->where('expires_at', '>', Carbon::now())
            ->latest('id')
            ->first();
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order aktif tidak ditemukan.'], 404);
        }

        $order = $gateway->complete($order, $data['reference'], $data['payload'] ?? $request->all());
        return response()->json(['success' => true, 'order' => $order->code]);
    }

    private function accountRules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:191', Rule::unique('users', 'email'), Rule::unique('fxhl_orders', 'email')->where(fn ($query) => $query->where('status', 'pending')->where('expires_at', '>', Carbon::now()))],
            'username' => ['required', 'string', 'min:3', 'max:32', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username'), Rule::unique('fxhl_orders', 'username')->where(fn ($query) => $query->where('status', 'pending')->where('expires_at', '>', Carbon::now()))],
            'name_first' => ['required', 'string', 'max:191'],
            'name_last' => ['required', 'string', 'max:191'],
            'password' => ['required', 'string', 'min:8', 'max:191', 'confirmed'],
        ];
    }

    private function uniquePayableAmount(int $base): int
    {
        for ($attempt = 0; $attempt < 30; ++$attempt) {
            $amount = $base + random_int(1, 499);
            if (!FxhlOrder::query()
                ->where('status', 'pending')
                ->where('expires_at', '>', Carbon::now())
                ->where('payable_amount', $amount)
                ->exists()) {
                return $amount;
            }
        }

        throw new HttpException(503, 'Nominal pembayaran unik sedang penuh. Coba lagi beberapa saat.');
    }

    private function orderResponse(FxhlOrder $order): array
    {
        return [
            'code' => $order->code,
            'status' => $order->status,
            'planName' => $order->plan_name,
            'baseAmount' => $order->base_amount,
            'payableAmount' => $order->payable_amount,
            'qrisPayload' => $order->status === 'pending' ? $order->qris_payload : null,
            'expiresAt' => optional($order->expires_at)->toIso8601String(),
            'paidAt' => optional($order->paid_at)->toIso8601String(),
        ];
    }
}
