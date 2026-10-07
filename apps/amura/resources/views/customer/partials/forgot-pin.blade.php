@if ($forgotUrl)
    <a class="entry-link" href="{{ $forgotUrl }}" target="_blank" rel="noopener">Lupa PIN? Chat CS {{ $theme['name'] }}</a>
@else
    {{-- WhatsApp CS belum diatur (4g): teks biasa supaya tidak terlihat bisa diketuk. --}}
    <p class="entry-note">Lupa PIN? Hubungi CS {{ $theme['name'] }} dari nomor HP akunmu.</p>
@endif
