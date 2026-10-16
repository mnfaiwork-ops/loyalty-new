@php
    // Pop up promo dari /admin/tampilan, hanya di halaman yang dipilih Game Admin.
    $popupPage = match (true) {
        request()->routeIs('play.form') => 'play',
        request()->routeIs('home') => 'home',
        request()->routeIs('customer.login') => 'masuk',
        default => null,
    };
    $popup = $popupPage ? \LoyaltyNew\Core\Content\SiteContent::popupFor($popupPage) : null;
    $external = $popup && $popup['link'] && parse_url($popup['link'], PHP_URL_HOST) !== request()->getHost();
@endphp
@if ($popup)
    <dialog class="promo" id="promo" aria-label="{{ $popup['alt'] ?: 'Promo' }}"
            data-key="promo:{{ config('app.brand') }}" data-version="{{ $popup['version'] }}">
        <button type="button" class="promo-close" data-close autofocus aria-label="Tutup pop up">
            <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>
        </button>
        @if ($popup['link'])
            <a class="promo-media" href="{{ $popup['link'] }}" data-seen @if ($external) target="_blank" rel="noopener" @endif>
                <img src="{{ asset('storage/'.$popup['image']) }}" alt="{{ $popup['alt'] }}">
            </a>
        @else
            <button type="button" class="promo-media" data-close>
                <img src="{{ asset('storage/'.$popup['image']) }}" alt="{{ $popup['alt'] }}">
            </button>
        @endif
    </dialog>
    <script>
        (() => {
            const dialog = document.getElementById('promo');
            if (!dialog || typeof dialog.showModal !== 'function') return;
            // Sekali per hari per perangkat; versi baru (pop up disimpan ulang) muncul lagi.
            const today = new Date().toLocaleDateString('sv-SE');
            const stamp = `${dialog.dataset.version}:${today}`;
            const read = () => { try { return localStorage.getItem(dialog.dataset.key); } catch (e) { return null; } };
            const remember = () => { try { localStorage.setItem(dialog.dataset.key, stamp); } catch (e) { /* mode privat */ } };
            if (read() === stamp) return;

            dialog.addEventListener('close', remember);
            dialog.querySelectorAll('[data-close]').forEach(el => el.addEventListener('click', () => dialog.close()));
            dialog.querySelectorAll('[data-seen]').forEach(el => el.addEventListener('click', () => { remember(); dialog.close(); }));
            // Klik di luar gambar menutup pop up.
            dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
            dialog.showModal();
        })();
    </script>
@endif
