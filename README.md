# FXHL Pterodactyl Blue Theme

Tema Pterodactyl sederhana putih–biru dengan installer GitHub Raw mandiri. Tidak memakai efek neon, glassmorphism, atau gaya neo/neumorphism.

## Fitur

- Tema putih–biru untuk login, client panel, dan admin panel.
- Animasi hover/klik pada seluruh tombol.
- Background login/panel dapat memakai URL atau upload gambar.
- Pop-up singkat yang dapat diaktifkan, diubah teks/jenis/durasinya, lalu hilang otomatis.
- Semua pengaturan add-on hanya dapat dibuka oleh akun **Admin ID 1**.
- Pendaftaran trial; default **3 hari**, dapat diubah dari admin.
- Pembatasan trial per alamat IP.
- Akun trial atau berbayar otomatis ditolak setelah masa aktifnya habis.
- Pembelian akun memakai QRIS nominal dinamis dan nominal unik.
- Pengecekan mutasi OrderKuota/gateway otomatis selama halaman pembayaran terbuka.
- Callback pembayaran opsional.
- API key dan token gateway dienkripsi memakai `APP_KEY` Laravel sebelum disimpan ke database.
- Installer idempoten: aman dijalankan ulang tanpa menggandakan patch.
- Backup otomatis dan uninstall otomatis.
- Tidak perlu Node.js, Yarn, atau rebuild React.

> Pembelian otomatis pada versi ini membuat **akun pengguna Pterodactyl**, bukan server game. Pembuatan server tetap dilakukan admin karena membutuhkan pilihan node, egg, allocation, RAM, CPU, disk, dan variabel startup.

## Dukungan versi

Dibuat berdasarkan struktur Pterodactyl Panel **v1.15.0**. Installer berhenti jika file/anchor penting tidak ditemukan agar tidak merusak struktur panel yang berbeda.

## Cara upload ke GitHub

1. Buat repository GitHub baru, misalnya `fxhl-ptero-blue`.
2. Upload seluruh isi folder ini ke branch `main`.
3. Repository boleh public agar URL Raw dapat dibaca VPS.
4. Jangan memasukkan API key, token, payload QRIS, atau kredensial ke file GitHub. Semua itu diisi setelah instalasi melalui admin panel.

## Instalasi memakai Raw GitHub

Ganti `USERNAME` dan `REPOSITORY`:

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/USERNAME/REPOSITORY/main/install.sh)
```

Lokasi panel selain `/var/www/pterodactyl`:

```bash
PTERO_PATH=/lokasi/panel bash <(curl -fsSL https://raw.githubusercontent.com/USERNAME/REPOSITORY/main/install.sh)
```

Sesudah selesai, buka:

```text
Admin Panel -> FXHL Theme
```

Menu hanya tampil dan dapat diakses oleh user dengan ID `1`.

## Pengaturan awal

1. Atur nama brand, warna biru, dan background.
2. Atur trial; default 3 hari.
3. Atur pop-up jika diperlukan.
4. Masukkan payload QRIS statis.
5. Masukkan endpoint gateway mutasi OrderKuota, API key/token, method, action, dan data path.
6. Tekan **Tes koneksi mutasi**.
7. Aktifkan tombol **Buy** setelah hasil tes berhasil.

## Format gateway mutasi

Adapter berusaha mengenali data transaksi secara otomatis. Nama field nominal yang dikenali:

```text
amount, nominal, jumlah, kredit, credit, total, value
```

Nama field referensi yang dikenali:

```text
reference, ref, trxid, transaction_id, id, kode, invoice
```

Bila daftar transaksi berada di bagian tertentu dari JSON, isi **Data path**, misalnya:

```text
data.mutations
```

Karena OrderKuota tidak memiliki API publik resmi yang stabil, endpoint yang digunakan dapat berupa wrapper/gateway milik sendiri. Jangan mengaktifkan pembelian sebelum pengecekan mutasi berhasil.

## Callback opsional

Endpoint:

```text
POST /auth/fxhl/orderkuota/callback
```

Header:

```text
X-FXHL-Callback-Secret: SECRET_DARI_ADMIN
Content-Type: application/json
```

Body:

```json
{
  "amount": 10421,
  "reference": "TRX-UNIK-123",
  "payload": {}
}
```

## Uninstall

Menghapus kode add-on tetapi mempertahankan tabel/data:

```bash
bash /var/www/pterodactyl/.fxhl-theme/uninstall.sh
```

Menghapus kode sekaligus seluruh data add-on:

```bash
bash /var/www/pterodactyl/.fxhl-theme/uninstall.sh --purge-data
```

Untuk lokasi panel lain, tambahkan `PTERO_PATH=/lokasi/panel` di depan perintah.

## Catatan keamanan

- Simpan repository tanpa token atau kredensial.
- Pastikan `APP_KEY` panel tidak berubah; token gateway dienkripsi menggunakan key tersebut.
- Gunakan HTTPS.
- Gateway mutasi pihak ketiga berada di luar kendali tema ini. Gunakan layanan yang kamu percaya atau wrapper sendiri.
- Backup panel dan database sebelum menginstal modifikasi apa pun.
