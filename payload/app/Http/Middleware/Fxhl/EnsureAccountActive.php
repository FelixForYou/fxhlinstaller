<?php

namespace Pterodactyl\Http\Middleware\Fxhl;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Pterodactyl\Models\FxhlAccount;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user || $user->id === 1 || !$this->expired($user->id)) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'errors' => [[
                    'code' => 'AccountExpired',
                    'status' => '403',
                    'detail' => 'Masa aktif akun telah berakhir. Silakan membeli atau memperpanjang akun.',
                ]],
            ], 403);
        }

        Auth::guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('auth.login')->with('fxhl_error', 'Masa aktif akun telah berakhir.');
    }

    private function expired(int $userId): bool
    {
        try {
            return FxhlAccount::isExpiredForUser($userId);
        } catch (\Throwable) {
            return false;
        }
    }
}
