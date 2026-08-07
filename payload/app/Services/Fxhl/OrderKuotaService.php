<?php

namespace Pterodactyl\Services\Fxhl;

use Throwable;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Models\FxhlAccount;
use Pterodactyl\Models\FxhlOrder;
use Pterodactyl\Models\FxhlSetting;
use Pterodactyl\Models\User;
use Pterodactyl\Services\Users\UserCreationService;
use RuntimeException;

class OrderKuotaService
{
    public function fetchMutations(): array
    {
        $endpoint = trim((string) FxhlSetting::valueOf('orderkuota_endpoint', ''));
        if ($endpoint === '') {
            throw new RuntimeException('Endpoint mutasi OrderKuota belum diatur.');
        }

        $headers = ['Accept' => 'application/json'];
        $apiKey = trim((string) FxhlSetting::secret('orderkuota_api_key', ''));
        $apiKeyHeader = trim((string) FxhlSetting::valueOf('orderkuota_api_key_header', 'apikey')) ?: 'apikey';
        if ($apiKey !== '') {
            $headers[$apiKeyHeader] = $apiKey;
        }

        $token = trim((string) FxhlSetting::secret('orderkuota_token', ''));
        if ($token !== '' && FxhlSetting::bool('orderkuota_bearer_token', true)) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $payload = [];
        $actionField = trim((string) FxhlSetting::valueOf('orderkuota_action_field', 'action'));
        $actionValue = trim((string) FxhlSetting::valueOf('orderkuota_action', 'qris_mutations'));
        if ($actionField !== '' && $actionValue !== '') {
            $payload[$actionField] = $actionValue;
        }

        $tokenField = trim((string) FxhlSetting::valueOf('orderkuota_token_field', 'auth_token'));
        if ($token !== '' && $tokenField !== '') {
            $payload[$tokenField] = $token;
        }

        $method = strtoupper((string) FxhlSetting::valueOf('orderkuota_method', 'POST'));
        $client = Http::timeout(20)->retry(1, 300)->withHeaders($headers)->acceptJson();
        if (FxhlSetting::valueOf('orderkuota_payload_type', 'form') === 'json') {
            $client = $client->asJson();
        } else {
            $client = $client->asForm();
        }

        $response = $method === 'GET' ? $client->get($endpoint, $payload) : $client->post($endpoint, $payload);
        if (!$response->successful()) {
            throw new RuntimeException('Gateway OrderKuota merespons HTTP ' . $response->status() . '.');
        }

        $json = $response->json();
        if (!is_array($json)) {
            throw new RuntimeException('Respons gateway bukan JSON yang valid.');
        }

        $configuredPath = trim((string) FxhlSetting::valueOf('orderkuota_items_path', ''));
        $source = $configuredPath !== '' ? data_get($json, $configuredPath, []) : $json;
        $records = $this->collectTransactionRecords($source);

        return array_values(array_filter(array_map(fn (array $record) => $this->normalizeMutation($record), $records)));
    }

    public function sync(FxhlOrder $order, bool $force = false): FxhlOrder
    {
        if ($order->status !== 'pending') {
            return $order;
        }

        if ($order->expires_at && $order->expires_at->isPast()) {
            $order->update(['status' => 'expired']);
            return $order->refresh();
        }

        if (!$force && $order->last_checked_at && $order->last_checked_at->gt(Carbon::now()->subSeconds(8))) {
            return $order;
        }

        $order->update(['last_checked_at' => Carbon::now()]);
        foreach ($this->fetchMutations() as $mutation) {
            if ((int) $mutation['amount'] !== (int) $order->payable_amount) {
                continue;
            }
            if (!empty($mutation['occurred_at']) && Carbon::parse($mutation['occurred_at'])->lt($order->created_at->copy()->subMinutes(10))) {
                continue;
            }
            if (FxhlOrder::query()->where('gateway_reference', $mutation['reference'])->where('id', '!=', $order->id)->exists()) {
                continue;
            }

            return $this->complete($order, $mutation['reference'], $mutation['raw']);
        }

        return $order->refresh();
    }

    public function complete(FxhlOrder $order, string $reference, array $payload = []): FxhlOrder
    {
        return DB::transaction(function () use ($order, $reference, $payload) {
            /** @var FxhlOrder $locked */
            $locked = FxhlOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->status === 'paid') {
                return $locked;
            }
            if ($locked->status !== 'pending') {
                throw new RuntimeException('Order tidak lagi aktif.');
            }

            if (User::query()->where('email', $locked->email)->exists()) {
                throw new RuntimeException('Email pada order sudah dipakai akun lain.');
            }
            if (User::query()->where('username', $locked->username)->exists()) {
                throw new RuntimeException('Username pada order sudah dipakai akun lain.');
            }

            $user = app(UserCreationService::class)->handle([
                'email' => $locked->email,
                'username' => $locked->username,
                'name_first' => $locked->name_first,
                'name_last' => $locked->name_last,
                'password' => Crypt::decryptString($locked->password_encrypted),
                'root_admin' => false,
                'language' => config('app.locale', 'en'),
            ]);

            FxhlAccount::query()->create([
                'user_id' => $user->id,
                'kind' => 'paid',
                'expires_at' => $locked->duration_days > 0 ? Carbon::now()->addDays($locked->duration_days) : null,
                'order_id' => $locked->id,
                'signup_ip' => $locked->signup_ip,
            ]);

            $locked->update([
                'status' => 'paid',
                'paid_at' => Carbon::now(),
                'gateway_reference' => substr($reference, 0, 191),
                'gateway_payload' => $payload,
            ]);

            return $locked->refresh();
        });
    }

    private function collectTransactionRecords(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $records = [];
        if ($this->looksLikeTransaction($value)) {
            $records[] = $value;
        }

        foreach ($value as $child) {
            if (is_array($child)) {
                array_push($records, ...$this->collectTransactionRecords($child));
            }
        }

        return $records;
    }

    private function looksLikeTransaction(array $record): bool
    {
        foreach (['amount', 'nominal', 'jumlah', 'kredit', 'credit', 'total', 'value'] as $key) {
            if (array_key_exists($key, $record)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeMutation(array $record): ?array
    {
        $amount = null;
        foreach (['amount', 'nominal', 'jumlah', 'kredit', 'credit', 'total', 'value'] as $key) {
            if (array_key_exists($key, $record)) {
                $amount = $this->numericAmount($record[$key]);
                break;
            }
        }
        if (!$amount) {
            return null;
        }

        $reference = null;
        foreach (['reference', 'ref', 'trxid', 'transaction_id', 'id', 'kode', 'invoice'] as $key) {
            if (!empty($record[$key])) {
                $reference = (string) $record[$key];
                break;
            }
        }
        $reference ??= sha1(json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $occurredAt = null;
        foreach (['occurred_at', 'created_at', 'date', 'tanggal', 'time', 'waktu'] as $key) {
            if (!empty($record[$key])) {
                try {
                    $occurredAt = Carbon::parse($record[$key])->toIso8601String();
                } catch (Throwable) {
                    $occurredAt = null;
                }
                break;
            }
        }

        return [
            'amount' => $amount,
            'reference' => $reference,
            'occurred_at' => $occurredAt,
            'raw' => Arr::only($record, array_keys($record)),
        ];
    }

    private function numericAmount(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int) round($value);
        }

        $digits = preg_replace('/[^0-9]/', '', (string) $value);
        return $digits === '' ? 0 : (int) $digits;
    }
}
