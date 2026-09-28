@extends('layouts.play')

@section('title', 'Reglow — Papan Hadiah')

@section('content')
    <div class="brand">Reglow</div>
    <h1 class="headline">Pilih 3 Kotak</h1>
    <p class="sub">Ketuk 3 kotak pilihanmu, lalu buka hadiahnya.</p>

    <div class="chance">Kesempatan: {{ $session->chance_total - $session->chance_used }}/{{ $session->chance_total }}</div>

    <div class="grid" id="grid">
        @foreach ($prizes as $prize)
            @php
                $state = $prize->opened ? 'opened' : ($prize->picked ? 'selected' : '');
            @endphp
            <button
                type="button"
                class="slot {{ $state }} {{ $prize->taken ? 'chosen' : '' }}"
                data-slot="{{ $prize->slot }}"
                @disabled($prize->opened || $prize->taken)
            >{{ $prize->slot }}</button>
        @endforeach
    </div>

    <button type="button" class="btn" id="open" disabled>Buka 3 Kado</button>

    <div id="reveal" class="reveal" hidden>
        <h2 class="headline">Pilih 1 Hadiah</h2>
        <p class="sub">Ketuk hadiah yang ingin kamu simpan.</p>
        <div class="reveal-grid" id="revealGrid"></div>
        <p class="note" id="revealNote"></p>
    </div>

    <div id="result" class="result" hidden>
        <h2 class="headline">Hadiahmu tersimpan!</h2>
        <p class="sub">Kode klaim:</p>
        <div class="claim-code" id="claimCode"></div>
        <p class="note">Klaim sebelum <b id="claimExpiry"></b>. Kirim kode ini ke WhatsApp CS kami, lalu CS akan mencocokkan dan memproses hadiahmu.</p>
    </div>
@endsection

@push('scripts')
<script>
    const token = @json($session->session_token);
    const grid = document.getElementById('grid');
    const slots = document.querySelectorAll('.slot');
    const openBtn = document.getElementById('open');
    const revealBox = document.getElementById('reveal');
    const revealGrid = document.getElementById('revealGrid');
    const revealNote = document.getElementById('revealNote');
    const pendingSlots = @json($pendingSlots);
    const pendingRevealed = @json($pendingRevealed);
    let selected = [...pendingSlots];
    // Ronde yang sudah tercatat di server tidak boleh diubah dari browser.
    let roundLocked = pendingSlots.length === 3;

    slots.forEach(el => el.addEventListener('click', () => {
        if (el.disabled || roundLocked) return;
        const slot = Number(el.dataset.slot);
        if (selected.includes(slot)) {
            selected = selected.filter(s => s !== slot);
            el.classList.remove('selected');
        } else if (selected.length < 3) {
            selected.push(slot);
            el.classList.add('selected');
        }
        openBtn.disabled = selected.length !== 3;
    }));

    async function post(url, body) {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': @json(csrf_token()),
            },
            body: JSON.stringify(body),
        });
        return res.json();
    }

    function showReveal(items) {
        revealGrid.innerHTML = '';
        items.forEach(item => {
            const card = document.createElement('button');
            card.type = 'button';
            card.className = 'reveal-card';
            card.dataset.slot = item.slot;
            card.innerHTML = `<span class="reveal-slot">${item.slot}</span><span class="reveal-tier">${item.label}</span>`;
            card.addEventListener('click', () => keep(item.slot));
            revealGrid.appendChild(card);
        });
        revealBox.hidden = false;
    }

    async function keep(slot) {
        const take = await post(`/play/${token}/take`, { slot });
        if (!take.ok) { alert(take.error || 'Gagal'); return; }

        document.querySelectorAll('.reveal-card').forEach(card => {
            card.disabled = true;
            card.classList.toggle('chosen', Number(card.dataset.slot) === take.chosen);
        });
        revealNote.textContent = 'Hadiahmu tersimpan.';

        slots.forEach(el => {
            const s = Number(el.dataset.slot);
            if (take.revealed.some(r => r.slot === s)) {
                el.classList.remove('selected');
                el.classList.add('opened');
                if (s === take.chosen) el.classList.add('chosen');
                el.disabled = true;
            }
        });
        selected = [];
        roundLocked = false;
        openBtn.disabled = true;

        if (take.claim_code) {
            document.getElementById('claimCode').textContent = take.claim_code;
            document.getElementById('claimExpiry').textContent = take.claim_expires_at;
            document.getElementById('result').hidden = false;
        }
    }

    openBtn.addEventListener('click', async () => {
        openBtn.disabled = true;
        if (!roundLocked) {
            const pick = await post(`/play/${token}/pick`, { slots: selected });
            if (!pick.ok) { alert(pick.error || 'Gagal'); openBtn.disabled = false; return; }
            roundLocked = true;
        }

        const reveal = await post(`/play/${token}/reveal`);
        if (!reveal.ok) { alert(reveal.error || 'Gagal'); openBtn.disabled = false; return; }

        showReveal(reveal.revealed);
    });

    // Lanjutkan ronde yang tertunda setelah halaman di-refresh.
    if (pendingRevealed.length) {
        showReveal(pendingRevealed);
    } else if (roundLocked) {
        openBtn.disabled = false;
    }
</script>
@endpush
