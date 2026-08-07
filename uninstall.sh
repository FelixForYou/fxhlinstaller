#!/usr/bin/env bash
set -Eeuo pipefail

PANEL_PATH="${PTERO_PATH:-/var/www/pterodactyl}"
META_DIR="$PANEL_PATH/.fxhl-theme"
PURGE_DATA=0
[[ "${1:-}" == "--purge-data" ]] && PURGE_DATA=1

if [[ $EUID -ne 0 ]]; then
  echo "Jalankan sebagai root." >&2
  exit 1
fi
if [[ ! -f "$PANEL_PATH/artisan" ]]; then
  echo "Panel tidak ditemukan di $PANEL_PATH. Gunakan: PTERO_PATH=/lokasi/panel bash uninstall.sh" >&2
  exit 1
fi
if [[ ! -f "$META_DIR/patch.py" ]]; then
  echo "Metadata installer tidak ditemukan: $META_DIR/patch.py" >&2
  exit 1
fi

python3 "$META_DIR/patch.py" uninstall "$PANEL_PATH"

if [[ -f "$META_DIR/files.list" ]]; then
  while IFS= read -r file; do
    [[ -z "$file" ]] && continue
    rm -f "$PANEL_PATH/$file"
  done < "$META_DIR/files.list"
fi
rm -rf "$PANEL_PATH/app/Http/Controllers/Fxhl" \
       "$PANEL_PATH/app/Http/Controllers/Admin/Fxhl" \
       "$PANEL_PATH/app/Http/Middleware/Fxhl" \
       "$PANEL_PATH/app/Services/Fxhl" \
       "$PANEL_PATH/resources/views/admin/fxhl" \
       "$PANEL_PATH/resources/views/fxhl" \
       "$PANEL_PATH/public/themes/fxhl"

if [[ $PURGE_DATA -eq 1 ]]; then
  cd "$PANEL_PATH"
  php -r '
    require "vendor/autoload.php";
    $app = require "bootstrap/app.php";
    $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
    Illuminate\\Support\\Facades\\Schema::dropIfExists("fxhl_accounts");
    Illuminate\\Support\\Facades\\Schema::dropIfExists("fxhl_orders");
    Illuminate\\Support\\Facades\\Schema::dropIfExists("fxhl_settings");
    Illuminate\\Support\\Facades\\DB::table("migrations")->where("migration", "2026_08_06_000001_create_fxhl_theme_tables")->delete();
  '
fi

cd "$PANEL_PATH"
php artisan optimize:clear || true
rm -rf "$META_DIR"
echo "FXHL Theme berhasil dihapus.$([[ $PURGE_DATA -eq 1 ]] && echo ' Data add-on juga dihapus.')"
