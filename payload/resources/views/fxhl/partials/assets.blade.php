@php
    try {
        $fxhlThemeConfig = \Pterodactyl\Models\FxhlSetting::publicConfig();
    } catch (\Throwable $e) {
        $fxhlThemeConfig = [];
    }
@endphp
<link rel="stylesheet" href="/themes/fxhl/fxhl.css?v=1.0.0">
<script>
    window.FxhlThemeConfig = @json($fxhlThemeConfig);
    window.FxhlServerMessage = @json(session('fxhl_error'));
</script>
@if(data_get($fxhlThemeConfig, 'buy.enabled'))
    <script defer src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"
            integrity="sha512-CNgIRecGo7nphbeZ04Sc13ka07paqdeTu0WR1IM4kNcpmBAUSHSQX0FslNhTDadL4O5SAGapGt4FodqL8My0mA=="
            crossorigin="anonymous" referrerpolicy="no-referrer"></script>
@endif
<script defer src="/themes/fxhl/fxhl.js?v=1.0.0"></script>
