#!/usr/bin/env python3
from __future__ import annotations

import re
import sys
from pathlib import Path

START = 'FXHL_THEME'


def read(path: Path) -> str:
    if not path.is_file():
        raise RuntimeError(f'File wajib tidak ditemukan: {path}')
    return path.read_text(encoding='utf-8')


def write(path: Path, content: str) -> None:
    path.write_text(content, encoding='utf-8')


def insert_once(content: str, marker: str, anchor: str, block: str, position: str = 'before') -> str:
    if marker in content:
        return content
    if anchor not in content:
        raise RuntimeError(f'Anchor tidak ditemukan untuk marker {marker}: {anchor[:100]}')
    if position == 'after':
        return content.replace(anchor, anchor + block, 1)
    return content.replace(anchor, block + anchor, 1)


def remove_block(content: str, start: str, end: str) -> str:
    pattern = re.compile(rf'\n?[ \t]*{re.escape(start)}.*?{re.escape(end)}[ \t]*\n?', re.S)
    return pattern.sub('\n', content)


def install(root: Path) -> None:
    # Public/client wrapper assets.
    path = root / 'resources/views/templates/wrapper.blade.php'
    content = read(path)
    block = "\n        {{-- FXHL_THEME_ASSETS_START --}}\n        @includeIf('fxhl.partials.assets')\n        {{-- FXHL_THEME_ASSETS_END --}}\n"
    content = insert_once(content, 'FXHL_THEME_ASSETS_START', "        @yield('assets')\n", block, 'after')
    write(path, content)

    # Admin assets and menu, visible only to account ID 1.
    path = root / 'resources/views/layouts/admin.blade.php'
    content = read(path)
    assets = "\n            {{-- FXHL_THEME_ADMIN_ASSETS_START --}}\n            @includeIf('fxhl.partials.assets')\n            {{-- FXHL_THEME_ADMIN_ASSETS_END --}}\n"
    content = insert_once(content, 'FXHL_THEME_ADMIN_ASSETS_START', "            {!! Theme::css('css/pterodactyl.css?t={cache-version}') !!}\n", assets, 'after')
    menu = """                        {{-- FXHL_THEME_ADMIN_MENU_START --}}
                        @if(Auth::id() === 1)
                            <li class=\"{{ ! starts_with(Route::currentRouteName(), 'admin.fxhl') ?: 'active' }}\">
                                <a href=\"{{ route('admin.fxhl.index') }}\">
                                    <i class=\"fa fa-paint-brush\"></i> <span>FXHL Theme</span>
                                </a>
                            </li>
                        @endif
                        {{-- FXHL_THEME_ADMIN_MENU_END --}}
"""
    content = insert_once(content, 'FXHL_THEME_ADMIN_MENU_START', '                        <li class="header">MANAGEMENT</li>\n', menu, 'before')
    write(path, content)

    # Guest routes for trial, order polling, and normalized callback.
    path = root / 'routes/auth.php'
    content = read(path)
    routes = """/* FXHL_THEME_AUTH_ROUTES_START */
Route::middleware(['throttle:5,1'])->group(function () {
    Route::post('/fxhl/trial', [\\Pterodactyl\\Http\\Controllers\\Fxhl\\PortalController::class, 'trial'])->name('fxhl.trial');
    Route::post('/fxhl/orders', [\\Pterodactyl\\Http\\Controllers\\Fxhl\\PortalController::class, 'createOrder'])->name('fxhl.orders.create');
    Route::get('/fxhl/orders/{code}', [\\Pterodactyl\\Http\\Controllers\\Fxhl\\PortalController::class, 'orderStatus'])->name('fxhl.orders.status');
});
Route::post('/fxhl/orderkuota/callback', [\\Pterodactyl\\Http\\Controllers\\Fxhl\\PortalController::class, 'callback'])->name('fxhl.orderkuota.callback');
/* FXHL_THEME_AUTH_ROUTES_END */

"""
    content = insert_once(content, 'FXHL_THEME_AUTH_ROUTES_START', '// Catch any other combinations of routes and pass them off to the React component.\n', routes, 'before')
    write(path, content)

    # Admin routes, already protected by Pterodactyl admin middleware; controller enforces ID 1.
    path = root / 'routes/admin.php'
    content = read(path)
    admin_routes = """
/* FXHL_THEME_ADMIN_ROUTES_START */
Route::prefix('fxhl-theme')->name('admin.fxhl.')->group(function () {
    Route::get('/', [Admin\\Fxhl\\SettingsController::class, 'index'])->name('index');
    Route::patch('/', [Admin\\Fxhl\\SettingsController::class, 'update'])->name('update');
    Route::post('/test', [Admin\\Fxhl\\SettingsController::class, 'test'])->name('test');
});
/* FXHL_THEME_ADMIN_ROUTES_END */
"""
    content = insert_once(content, 'FXHL_THEME_ADMIN_ROUTES_START', "Route::get('/', [Admin\\BaseController::class, 'index'])->name('admin.index');\n", admin_routes, 'after')
    write(path, content)

    # Expiration middleware for web panel and API access.
    path = root / 'app/Providers/RouteServiceProvider.php'
    content = read(path)
    import_block = "\n/* FXHL_THEME_IMPORT_START */\nuse Pterodactyl\\Http\\Middleware\\Fxhl\\EnsureAccountActive;\n/* FXHL_THEME_IMPORT_END */"
    content = insert_once(content, 'FXHL_THEME_IMPORT_START', 'use Pterodactyl\\Http\\Middleware\\RequireTwoFactorAuthentication;', import_block, 'after')
    original = "Route::middleware(['auth.session', RequireTwoFactorAuthentication::class])"
    replacement = "Route::middleware(['auth.session', RequireTwoFactorAuthentication::class, /* FXHL_THEME_BASE_MIDDLEWARE */ EnsureAccountActive::class])"
    if 'FXHL_THEME_BASE_MIDDLEWARE' not in content:
        if original not in content:
            raise RuntimeError('Anchor middleware web Pterodactyl tidak ditemukan.')
        content = content.replace(original, replacement, 1)
    original_api = "Route::middleware(['api', RequireTwoFactorAuthentication::class])->group(function () {"
    replacement_api = "Route::middleware(['api', RequireTwoFactorAuthentication::class, /* FXHL_THEME_API_MIDDLEWARE */ EnsureAccountActive::class])->group(function () {"
    if 'FXHL_THEME_API_MIDDLEWARE' not in content:
        if original_api not in content:
            raise RuntimeError('Anchor middleware API Pterodactyl tidak ditemukan.')
        content = content.replace(original_api, replacement_api, 1)
    write(path, content)

    # Reject expired accounts before 2FA/login completes.
    path = root / 'app/Http/Controllers/Auth/LoginController.php'
    content = read(path)
    guard = """        /* FXHL_THEME_LOGIN_GUARD_START */
        try {
            if (\\Pterodactyl\\Models\\FxhlAccount::isExpiredForUser($user->id)) {
                throw \\Illuminate\\Validation\\ValidationException::withMessages([
                    'user' => 'Masa aktif akun telah berakhir. Silakan membeli atau memperpanjang akun.',
                ]);
            }
        } catch (\\Illuminate\\Database\\QueryException) {
            // Tabel add-on belum tersedia saat proses instalasi/migrasi berlangsung.
        }
        /* FXHL_THEME_LOGIN_GUARD_END */
"""
    content = insert_once(content, 'FXHL_THEME_LOGIN_GUARD_START', '        if (!$user->use_totp) {\n', guard, 'before')
    write(path, content)


def uninstall(root: Path) -> None:
    targets = {
        'resources/views/templates/wrapper.blade.php': [
            ('{{-- FXHL_THEME_ASSETS_START --}}', '{{-- FXHL_THEME_ASSETS_END --}}'),
        ],
        'resources/views/layouts/admin.blade.php': [
            ('{{-- FXHL_THEME_ADMIN_ASSETS_START --}}', '{{-- FXHL_THEME_ADMIN_ASSETS_END --}}'),
            ('{{-- FXHL_THEME_ADMIN_MENU_START --}}', '{{-- FXHL_THEME_ADMIN_MENU_END --}}'),
        ],
        'routes/auth.php': [
            ('/* FXHL_THEME_AUTH_ROUTES_START */', '/* FXHL_THEME_AUTH_ROUTES_END */'),
        ],
        'routes/admin.php': [
            ('/* FXHL_THEME_ADMIN_ROUTES_START */', '/* FXHL_THEME_ADMIN_ROUTES_END */'),
        ],
        'app/Http/Controllers/Auth/LoginController.php': [
            ('/* FXHL_THEME_LOGIN_GUARD_START */', '/* FXHL_THEME_LOGIN_GUARD_END */'),
        ],
        'app/Providers/RouteServiceProvider.php': [
            ('/* FXHL_THEME_IMPORT_START */', '/* FXHL_THEME_IMPORT_END */'),
        ],
    }
    for relative, blocks in targets.items():
        path = root / relative
        if not path.is_file():
            continue
        content = read(path)
        for start, end in blocks:
            content = remove_block(content, start, end)
        content = content.replace(', /* FXHL_THEME_BASE_MIDDLEWARE */ EnsureAccountActive::class', '')
        content = content.replace(', /* FXHL_THEME_API_MIDDLEWARE */ EnsureAccountActive::class', '')
        write(path, content)


def main() -> int:
    if len(sys.argv) != 3 or sys.argv[1] not in {'install', 'uninstall'}:
        print('Usage: patch.py <install|uninstall> <panel_path>', file=sys.stderr)
        return 2
    root = Path(sys.argv[2]).resolve()
    try:
        install(root) if sys.argv[1] == 'install' else uninstall(root)
    except Exception as exc:
        print(f'ERROR: {exc}', file=sys.stderr)
        return 1
    print(f'Patch {sys.argv[1]} selesai: {root}')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
