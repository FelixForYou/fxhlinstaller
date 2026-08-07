(() => {
    'use strict';

    const config = window.FxhlThemeConfig || {};
    const root = document.documentElement;
    const primary = config.primaryColor || '#1769e0';
    root.style.setProperty('--fxhl-blue', primary);
    root.style.setProperty('--fxhl-bg-image', config.backgroundUrl ? `url("${String(config.backgroundUrl).replace(/"/g, '%22')}")` : 'none');
    root.style.setProperty('--fxhl-overlay', `${Number(config.backgroundOverlay ?? 18)}%`);

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;').replaceAll("'", '&#039;');

    const formatRupiah = (value) => new Intl.NumberFormat('id-ID', {
        style: 'currency', currency: 'IDR', maximumFractionDigits: 0,
    }).format(Number(value || 0));

    function showToast(message, type = 'info', duration = 4500) {
        if (!message) return;
        const existing = document.querySelector('.fxhl-toast');
        if (existing) existing.remove();
        const toast = document.createElement('div');
        toast.className = `fxhl-toast ${type}`;
        toast.textContent = message;
        document.body.appendChild(toast);
        window.setTimeout(() => {
            toast.classList.add('fxhl-leave');
            window.setTimeout(() => toast.remove(), 250);
        }, Math.max(1000, Number(duration || 4500)));
    }

    function initialPopup() {
        if (window.FxhlServerMessage) {
            showToast(window.FxhlServerMessage, 'error', 6000);
            return;
        }
        const popup = config.popup || {};
        if (!popup.enabled || !popup.message) return;
        const key = 'fxhl-popup-shown';
        if (popup.oncePerSession && sessionStorage.getItem(key)) return;
        if (popup.oncePerSession) sessionStorage.setItem(key, '1');
        showToast(popup.message, popup.type || 'info', popup.duration || 4500);
    }

    async function api(url, options = {}) {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                ...(options.headers || {}),
            },
            ...options,
        });
        let data = {};
        try { data = await response.json(); } catch (_) { /* ignore */ }
        if (!response.ok) {
            const validation = data.errors && !Array.isArray(data.errors)
                ? Object.values(data.errors).flat().join(' ')
                : null;
            throw new Error(validation || data.message || data.error || `Request gagal (${response.status}).`);
        }
        return data;
    }

    function closeModal() {
        document.querySelector('.fxhl-modal-backdrop')?.remove();
        document.body.style.overflow = '';
    }

    function openModal(title, bodyBuilder) {
        closeModal();
        const backdrop = document.createElement('div');
        backdrop.className = 'fxhl-modal-backdrop';
        backdrop.innerHTML = `
            <section class="fxhl-modal" role="dialog" aria-modal="true">
                <header class="fxhl-modal-header">
                    <h3>${escapeHtml(title)}</h3>
                    <button class="fxhl-modal-close" type="button" aria-label="Tutup">×</button>
                </header>
                <div class="fxhl-modal-body"></div>
            </section>`;
        backdrop.querySelector('.fxhl-modal-close').addEventListener('click', closeModal);
        backdrop.addEventListener('click', (event) => { if (event.target === backdrop) closeModal(); });
        document.addEventListener('keydown', function esc(event) {
            if (event.key === 'Escape') {
                closeModal();
                document.removeEventListener('keydown', esc);
            }
        });
        document.body.appendChild(backdrop);
        document.body.style.overflow = 'hidden';
        bodyBuilder(backdrop.querySelector('.fxhl-modal-body'));
    }

    function accountForm(mode) {
        const trial = mode === 'trial';
        const title = trial ? (config.trial?.buttonText || 'Coba Gratis') : (config.buy?.buttonText || 'Beli Akun');
        openModal(title, (body) => {
            if (!trial) {
                const info = document.createElement('div');
                info.className = 'fxhl-info-box';
                info.textContent = `${config.buy?.planName || 'Akun Panel'} • ${formatRupiah(config.buy?.price)} • ${Number(config.buy?.days || 0) === 0 ? 'tanpa batas waktu' : `${config.buy.days} hari`}`;
                body.appendChild(info);
            }
            const form = document.createElement('form');
            form.innerHTML = `
                <div class="fxhl-error-box" hidden></div>
                <div class="fxhl-form-grid">
                    <div class="fxhl-field"><label>Nama depan</label><input name="name_first" required maxlength="191"></div>
                    <div class="fxhl-field"><label>Nama belakang</label><input name="name_last" required maxlength="191"></div>
                    <div class="fxhl-field fxhl-field-full"><label>Email</label><input type="email" name="email" required maxlength="191"></div>
                    <div class="fxhl-field fxhl-field-full"><label>Username</label><input name="username" required minlength="3" maxlength="32" pattern="[A-Za-z0-9._-]+"></div>
                    <div class="fxhl-field"><label>Password</label><input type="password" name="password" required minlength="8"></div>
                    <div class="fxhl-field"><label>Ulangi password</label><input type="password" name="password_confirmation" required minlength="8"></div>
                </div>
                <button class="fxhl-submit" type="submit">${escapeHtml(trial ? 'Buat akun trial' : 'Lanjut ke pembayaran')}</button>`;
            body.appendChild(form);
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                const error = form.querySelector('.fxhl-error-box');
                const submit = form.querySelector('button[type="submit"]');
                error.hidden = true;
                submit.disabled = true;
                submit.textContent = 'Memproses...';
                const payload = Object.fromEntries(new FormData(form).entries());
                try {
                    const result = await api(trial ? '/auth/fxhl/trial' : '/auth/fxhl/orders', {
                        method: 'POST', body: JSON.stringify(payload),
                    });
                    if (trial) {
                        showToast(result.message || 'Akun trial berhasil dibuat.', 'success', 2500);
                        window.location.href = result.redirect || '/';
                    } else {
                        renderOrder(body, result);
                    }
                } catch (exception) {
                    error.textContent = exception.message;
                    error.hidden = false;
                    submit.disabled = false;
                    submit.textContent = trial ? 'Buat akun trial' : 'Lanjut ke pembayaran';
                }
            });
        });
    }

    function renderOrder(body, order) {
        body.innerHTML = `
            <div class="fxhl-order-view">
                <div class="fxhl-info-box">Scan QRIS dan bayar tepat sesuai nominal. Akun dibuat otomatis setelah mutasi cocok.</div>
                <strong>${escapeHtml(order.planName || 'Akun Panel')}</strong>
                <div class="fxhl-amount">${escapeHtml(formatRupiah(order.payableAmount))}</div>
                <div class="fxhl-qr" id="fxhl-qrcode"></div>
                <div class="fxhl-status">Menunggu pembayaran…</div>
            </div>`;
        const qr = body.querySelector('#fxhl-qrcode');
        if (window.QRCode && order.qrisPayload) {
            new window.QRCode(qr, { text: order.qrisPayload, width: 240, height: 240, correctLevel: window.QRCode.CorrectLevel.M });
        } else {
            qr.innerHTML = '<span>Generator QR belum termuat. Muat ulang halaman atau cek koneksi CDN.</span>';
        }
        pollOrder(body, order.code);
    }

    function pollOrder(body, code) {
        let stopped = false;
        const status = body.querySelector('.fxhl-status');
        const tick = async () => {
            if (stopped || !document.body.contains(body)) return;
            try {
                const result = await api(`/auth/fxhl/orders/${encodeURIComponent(code)}`, { method: 'GET' });
                if (result.status === 'paid') {
                    stopped = true;
                    status.textContent = 'Pembayaran berhasil. Mengarahkan ke panel…';
                    showToast('Pembayaran diterima dan akun berhasil dibuat.', 'success', 2500);
                    window.setTimeout(() => { window.location.href = result.redirect || '/'; }, 700);
                    return;
                }
                if (result.status === 'expired') {
                    stopped = true;
                    status.textContent = 'Order kedaluwarsa. Tutup lalu buat order baru.';
                    return;
                }
                status.textContent = result.gatewayError || 'Menunggu pembayaran… pengecekan otomatis aktif.';
            } catch (exception) {
                status.textContent = exception.message || 'Pengecekan tertunda. Sistem akan mencoba lagi.';
            }
            window.setTimeout(tick, 8000);
        };
        window.setTimeout(tick, 3500);
    }

    function injectLoginActions() {
        if (!/^\/auth\/login\/?$/.test(window.location.pathname)) return false;
        if (document.querySelector('.fxhl-login-actions')) return true;
        const form = document.querySelector('#app form');
        if (!form) return false;
        const actions = document.createElement('div');
        actions.className = 'fxhl-login-actions';
        if (config.trial?.enabled) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'fxhl-action-button';
            button.textContent = config.trial.buttonText || `Coba Gratis ${config.trial.days || 3} Hari`;
            button.addEventListener('click', () => accountForm('trial'));
            actions.appendChild(button);
        }
        if (config.buy?.enabled) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'fxhl-action-button fxhl-primary';
            button.textContent = config.buy.buttonText || 'Beli Akun';
            button.addEventListener('click', () => accountForm('buy'));
            actions.appendChild(button);
        }
        if (!actions.children.length) return true;
        form.insertAdjacentElement('afterend', actions);
        return true;
    }

    function applyClientLightTheme() {
        const app = document.querySelector('#app');
        if (!app || window.location.pathname.startsWith('/auth/')) return;
        const candidates = app.querySelectorAll('header, nav, aside, main, section, div');
        for (const element of candidates) {
            if (element.dataset.fxhlChecked || element.closest('.xterm, [class*="xterm"], pre, code')) continue;
            element.dataset.fxhlChecked = '1';
            const style = getComputedStyle(element);
            const bg = style.backgroundColor.replace(/\s+/g, '');
            if (['rgb(31,41,55)', 'rgb(17,24,39)', 'rgb(38,38,38)', 'rgb(24,24,27)', 'rgb(39,39,42)'].includes(bg)) {
                element.classList.add('fxhl-light-surface');
            } else if (['rgb(55,65,81)', 'rgb(63,63,70)'].includes(bg)) {
                element.classList.add('fxhl-soft-surface');
            }
        }
    }

    initialPopup();
    let attempts = 0;
    const timer = window.setInterval(() => {
        injectLoginActions();
        applyClientLightTheme();
        if (++attempts > 30) window.clearInterval(timer);
    }, 300);

    const observer = new MutationObserver(() => {
        injectLoginActions();
        window.requestAnimationFrame(applyClientLightTheme);
    });
    observer.observe(document.documentElement, { childList: true, subtree: true });
})();
