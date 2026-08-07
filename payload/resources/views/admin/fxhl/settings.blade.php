@extends('layouts.admin')

@section('title')
    FXHL Theme
@endsection

@section('content-header')
    <h1>FXHL Theme <small>Tema, trial, pembelian, background, dan notifikasi.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">FXHL Theme</li>
    </ol>
@endsection

@section('content')
    @if(session('fxhl_success'))
        <div class="alert alert-success">{{ session('fxhl_success') }}</div>
    @endif
    @if(session('fxhl_error'))
        <div class="alert alert-danger">{{ session('fxhl_error') }}</div>
    @endif

    <form action="{{ route('admin.fxhl.update') }}" method="POST" enctype="multipart/form-data">
        {!! csrf_field() !!}
        <input type="hidden" name="_method" value="PATCH">

        <div class="row">
            <div class="col-md-6">
                <div class="box box-primary">
                    <div class="box-header with-border"><h3 class="box-title">Tampilan putih–biru</h3></div>
                    <div class="box-body">
                        <div class="form-group">
                            <label>Nama brand</label>
                            <input class="form-control" name="brand_name" value="{{ old('brand_name', $settings['brand_name']) }}" required>
                        </div>
                        <div class="form-group">
                            <label>Warna biru utama</label>
                            <input class="form-control" type="color" name="primary_color" value="{{ old('primary_color', $settings['primary_color']) }}" required>
                        </div>
                        <div class="form-group">
                            <label>URL background</label>
                            <input class="form-control" name="background_url" value="{{ old('background_url', $settings['background_url']) }}" placeholder="https://... atau /themes/fxhl/uploads/...">
                        </div>
                        <div class="form-group">
                            <label>Upload background (maks. 4 MB)</label>
                            <input class="form-control" type="file" name="background_file" accept="image/*">
                        </div>
                        <div class="form-group">
                            <label>Gelap overlay background: <span id="overlayValue">{{ old('background_overlay', $settings['background_overlay']) }}</span>%</label>
                            <input class="form-control" type="range" min="0" max="90" name="background_overlay" value="{{ old('background_overlay', $settings['background_overlay']) }}" oninput="document.getElementById('overlayValue').textContent=this.value">
                        </div>
                    </div>
                </div>

                <div class="box box-primary">
                    <div class="box-header with-border"><h3 class="box-title">Notifikasi pop-up</h3></div>
                    <div class="box-body">
                        <div class="checkbox">
                            <label><input type="checkbox" name="popup_enabled" value="1" {{ old('popup_enabled', $settings['popup_enabled']) == '1' ? 'checked' : '' }}> Aktifkan pop-up</label>
                        </div>
                        <div class="checkbox">
                            <label><input type="checkbox" name="popup_once_session" value="1" {{ old('popup_once_session', $settings['popup_once_session']) == '1' ? 'checked' : '' }}> Muncul sekali per sesi browser</label>
                        </div>
                        <div class="form-group">
                            <label>Pesan</label>
                            <textarea class="form-control" name="popup_message" rows="3">{{ old('popup_message', $settings['popup_message']) }}</textarea>
                        </div>
                        <div class="row">
                            <div class="col-sm-6 form-group">
                                <label>Jenis</label>
                                <select class="form-control" name="popup_type">
                                    @foreach(['info' => 'Info', 'success' => 'Sukses', 'warning' => 'Peringatan', 'error' => 'Error'] as $value => $label)
                                        <option value="{{ $value }}" {{ old('popup_type', $settings['popup_type']) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-sm-6 form-group">
                                <label>Durasi (ms)</label>
                                <input class="form-control" type="number" min="1000" max="30000" name="popup_duration" value="{{ old('popup_duration', $settings['popup_duration']) }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="box box-primary">
                    <div class="box-header with-border"><h3 class="box-title">Trial account</h3></div>
                    <div class="box-body">
                        <div class="checkbox">
                            <label><input type="checkbox" name="trial_enabled" value="1" {{ old('trial_enabled', $settings['trial_enabled']) == '1' ? 'checked' : '' }}> Aktifkan trial pada halaman login</label>
                        </div>
                        <div class="row">
                            <div class="col-sm-6 form-group">
                                <label>Masa trial (hari)</label>
                                <input class="form-control" type="number" min="1" max="365" name="trial_days" value="{{ old('trial_days', $settings['trial_days']) }}">
                            </div>
                            <div class="col-sm-6 form-group">
                                <label>Cooldown per IP (hari)</label>
                                <input class="form-control" type="number" min="1" max="3650" name="trial_ip_cooldown_days" value="{{ old('trial_ip_cooldown_days', $settings['trial_ip_cooldown_days']) }}">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Teks tombol trial</label>
                            <input class="form-control" name="trial_button_text" value="{{ old('trial_button_text', $settings['trial_button_text']) }}">
                        </div>
                    </div>
                </div>

                <div class="box box-primary">
                    <div class="box-header with-border"><h3 class="box-title">Pembelian akun otomatis</h3></div>
                    <div class="box-body">
                        <div class="checkbox">
                            <label><input type="checkbox" name="buy_enabled" value="1" {{ old('buy_enabled', $settings['buy_enabled']) == '1' ? 'checked' : '' }}> Aktifkan tombol beli</label>
                        </div>
                        <div class="form-group">
                            <label>Teks tombol beli</label>
                            <input class="form-control" name="buy_button_text" value="{{ old('buy_button_text', $settings['buy_button_text']) }}">
                        </div>
                        <div class="row">
                            <div class="col-sm-5 form-group">
                                <label>Nama paket</label>
                                <input class="form-control" name="plan_name" value="{{ old('plan_name', $settings['plan_name']) }}">
                            </div>
                            <div class="col-sm-4 form-group">
                                <label>Harga (Rp)</label>
                                <input class="form-control" type="number" min="1" name="plan_price" value="{{ old('plan_price', $settings['plan_price']) }}">
                            </div>
                            <div class="col-sm-3 form-group">
                                <label>Hari</label>
                                <input class="form-control" type="number" min="0" name="plan_days" value="{{ old('plan_days', $settings['plan_days']) }}">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Order kedaluwarsa (menit)</label>
                            <input class="form-control" type="number" min="5" max="120" name="order_expiry_minutes" value="{{ old('order_expiry_minutes', $settings['order_expiry_minutes']) }}">
                            <p class="help-block">Isi hari dengan 0 untuk akun tanpa tanggal kedaluwarsa.</p>
                        </div>
                        <div class="form-group">
                            <label>Payload QRIS statis</label>
                            <textarea class="form-control" rows="4" name="qris_payload" placeholder="000201010211...">{{ old('qris_payload', $settings['qris_payload']) }}</textarea>
                            <p class="help-block">Installer mengubahnya menjadi QRIS nominal dinamis memakai nominal unik.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">Adapter mutasi OrderKuota</h3></div>
            <div class="box-body">
                <div class="alert alert-warning">
                    OrderKuota tidak menyediakan API publik resmi yang stabil. Adapter ini dibuat fleksibel untuk gateway/wrapper milikmu. Jangan menaruh token di GitHub; isi hanya dari halaman ini.
                </div>
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>Endpoint mutasi</label>
                        <input class="form-control" type="url" name="orderkuota_endpoint" value="{{ old('orderkuota_endpoint', $settings['orderkuota_endpoint']) }}" placeholder="https://gateway-kamu.example/api.php">
                    </div>
                    <div class="col-md-2 form-group">
                        <label>Method</label>
                        <select class="form-control" name="orderkuota_method">
                            @foreach(['POST', 'GET'] as $value)
                                <option {{ old('orderkuota_method', $settings['orderkuota_method']) === $value ? 'selected' : '' }}>{{ $value }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 form-group">
                        <label>Body</label>
                        <select class="form-control" name="orderkuota_payload_type">
                            <option value="form" {{ old('orderkuota_payload_type', $settings['orderkuota_payload_type']) === 'form' ? 'selected' : '' }}>Form</option>
                            <option value="json" {{ old('orderkuota_payload_type', $settings['orderkuota_payload_type']) === 'json' ? 'selected' : '' }}>JSON</option>
                        </select>
                    </div>
                    <div class="col-md-2 form-group">
                        <label>Data path</label>
                        <input class="form-control" name="orderkuota_items_path" value="{{ old('orderkuota_items_path', $settings['orderkuota_items_path']) }}" placeholder="data.mutations">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3 form-group">
                        <label>Header API key</label>
                        <input class="form-control" name="orderkuota_api_key_header" value="{{ old('orderkuota_api_key_header', $settings['orderkuota_api_key_header']) }}">
                    </div>
                    <div class="col-md-3 form-group">
                        <label>API key (kosong = jangan ubah)</label>
                        <input class="form-control" type="password" name="orderkuota_api_key" autocomplete="new-password">
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Token (kosong = jangan ubah)</label>
                        <input class="form-control" type="password" name="orderkuota_token" autocomplete="new-password">
                    </div>
                    <div class="col-md-3 form-group">
                        <label>&nbsp;</label>
                        <div class="checkbox"><label><input type="checkbox" name="orderkuota_bearer_token" value="1" {{ old('orderkuota_bearer_token', $settings['orderkuota_bearer_token']) == '1' ? 'checked' : '' }}> Kirim Bearer token</label></div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3 form-group">
                        <label>Field action</label>
                        <input class="form-control" name="orderkuota_action_field" value="{{ old('orderkuota_action_field', $settings['orderkuota_action_field']) }}">
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Nilai action</label>
                        <input class="form-control" name="orderkuota_action" value="{{ old('orderkuota_action', $settings['orderkuota_action']) }}">
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Field token dalam body</label>
                        <input class="form-control" name="orderkuota_token_field" value="{{ old('orderkuota_token_field', $settings['orderkuota_token_field']) }}">
                    </div>
                    <div class="col-md-3 form-group">
                        <label>Callback secret</label>
                        <input class="form-control" name="orderkuota_callback_secret" value="{{ old('orderkuota_callback_secret', $settings['orderkuota_callback_secret']) }}">
                    </div>
                </div>
                <p class="help-block"><strong>Callback opsional:</strong> POST ke <code>{{ url('/auth/fxhl/orderkuota/callback') }}</code>, header <code>X-FXHL-Callback-Secret</code>, body JSON <code>{"amount":10421,"reference":"TRX123"}</code>.</p>
            </div>
            <div class="box-footer">
                <button class="btn btn-primary pull-right" type="submit">Simpan semua pengaturan</button>
            </div>
        </div>
    </form>

    <form action="{{ route('admin.fxhl.test') }}" method="POST" style="margin-bottom:20px">
        {!! csrf_field() !!}
        <button class="btn btn-default" type="submit"><i class="fa fa-plug"></i> Tes koneksi mutasi</button>
    </form>

    <div class="box">
        <div class="box-header with-border"><h3 class="box-title">20 order terakhir</h3></div>
        <div class="box-body table-responsive no-padding">
            <table class="table table-hover">
                <thead><tr><th>Kode</th><th>Email</th><th>Nominal</th><th>Status</th><th>Dibuat</th></tr></thead>
                <tbody>
                @forelse($orders as $order)
                    <tr>
                        <td><code>{{ $order->code }}</code></td>
                        <td>{{ $order->email }}</td>
                        <td>Rp{{ number_format($order->payable_amount, 0, ',', '.') }}</td>
                        <td><span class="label label-{{ $order->status === 'paid' ? 'success' : ($order->status === 'pending' ? 'warning' : 'default') }}">{{ strtoupper($order->status) }}</span></td>
                        <td>{{ $order->created_at }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted">Belum ada order.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
