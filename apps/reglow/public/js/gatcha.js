// Gatcha di /play/{token}. Gerak mengikuti design/gatcha-anim.jsx (Masuk, Pilih, Buka, HadiahUtama).
// Isi kado hanya datang dari server setelah /reveal; file ini tidak pernah tahu isi kado yang belum dibuka.
(() => {
    const data = JSON.parse(document.getElementById('gatchaData').textContent);
    const $ = id => document.getElementById(id);
    const stage = $('stage');
    const cells = [...document.querySelectorAll('.cell')];
    const cellBySlot = slot => cells.find(c => Number(c.dataset.slot) === slot);
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const wait = ms => new Promise(r => setTimeout(r, reduced ? Math.min(ms, 150) : ms));
    const pad = n => String(n).padStart(2, '0');

    const EASE_BACK = 'cubic-bezier(.34, 1.56, .64, 1)';
    const EASE_OUT = 'cubic-bezier(.22, 1, .36, 1)';
    const EASE_IO = 'cubic-bezier(.65, 0, .35, 1)';

    const state = {
        chanceUsed: data.chanceUsed,
        // Kesempatan yang sedang dimainkan (penanda N/total), tetap sampai papan diacak ulang.
        current: Math.min(data.chanceUsed + 1, data.chanceTotal),
        selected: [],
        locked: false,
        busy: false,
        items: [],
        chosen: null,
        result: null,
        taken: [...data.taken],
    };
    const chanceLeft = () => Math.max(0, data.chanceTotal - state.chanceUsed);

    // ── Tingkat hadiah: warna dan kata di kartu ───────────────────────────
    const TIER = {
        poin: { cls: 'tier-ivory', chip: 'KADO POIN', token: 'Poin', v: `Poin ${data.brand}` },
        voucher: { cls: 'tier-brand', chip: 'VOUCHER', token: 'Voucher', v: 'Voucher' },
        merch: { cls: 'tier-brand', chip: 'MERCH', token: 'Merch', v: 'Merch' },
        hadiah_besar: { cls: 'tier-gold', chip: 'HADIAH UTAMA', token: 'Utama', v: 'Hadiah Utama' },
    };
    const tierOf = item => TIER[item.tier] || { cls: 'tier-ivory', chip: item.label.toUpperCase(), token: item.label, v: item.label };
    const prizeName = item => (item.tier === 'poin' && item.points ? `+${item.points} poin` : item.name || tierOf(item).v);
    const formatDate = iso => {
        if (!iso) return '';
        const d = new Date(iso + 'T00:00:00');
        return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
    };

    // ── Suara (opsional, default mati) dan getar ─────────────────────────
    const sound = {
        on: false,
        ctx: null,
        init() {
            try { this.on = localStorage.getItem('gatcha-sound') === '1'; } catch (e) { this.on = false; }
            this.paint();
            $('sound').addEventListener('click', () => {
                this.on = !this.on;
                try { localStorage.setItem('gatcha-sound', this.on ? '1' : '0'); } catch (e) { /* mode privat */ }
                this.paint();
                if (this.on) {
                    this.tone(660, .08, 'sine', .15);
                    music.start();
                } else {
                    music.stop();
                }
            });
            // Browser baru mengizinkan audio setelah ketukan pertama.
            if (this.on) document.addEventListener('pointerdown', () => music.start(), { once: true });
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) music.stop();
                else if (this.on) music.start();
            });
        },
        paint() {
            const btn = $('sound');
            btn.setAttribute('aria-pressed', String(this.on));
            btn.setAttribute('aria-label', this.on ? 'Suara dan musik nyala. Ketuk untuk mematikan' : 'Suara dan musik mati. Ketuk untuk menyalakan');
            btn.querySelector('.snd-on').hidden = !this.on;
            btn.querySelector('.snd-off').hidden = this.on;
        },
        audio() {
            if (!this.on) return null;
            try {
                this.ctx = this.ctx || new (window.AudioContext || window.webkitAudioContext)();
                if (this.ctx.state === 'suspended') this.ctx.resume();
                return this.ctx;
            } catch (e) { return null; }
        },
        tone(freq, dur, type = 'sine', gain = .2, delay = 0, slideTo = null) {
            const ctx = this.audio();
            if (!ctx) return;
            const t = ctx.currentTime + delay;
            const osc = ctx.createOscillator();
            const g = ctx.createGain();
            osc.type = type;
            osc.frequency.setValueAtTime(freq, t);
            if (slideTo) osc.frequency.exponentialRampToValueAtTime(slideTo, t + dur);
            g.gain.setValueAtTime(0.0001, t);
            g.gain.exponentialRampToValueAtTime(gain, t + .01);
            g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
            osc.connect(g).connect(ctx.destination);
            osc.start(t);
            osc.stop(t + dur + .02);
        },
        noise(dur, gain = .08, delay = 0) {
            const ctx = this.audio();
            if (!ctx) return;
            const buf = ctx.createBuffer(1, ctx.sampleRate * dur, ctx.sampleRate);
            const ch = buf.getChannelData(0);
            for (let i = 0; i < ch.length; i++) ch[i] = (Math.random() * 2 - 1) * (1 - i / ch.length);
            const src = ctx.createBufferSource();
            const g = ctx.createGain();
            g.gain.value = gain;
            src.buffer = buf;
            src.connect(g).connect(ctx.destination);
            src.start(ctx.currentTime + delay);
        },
        // "Pop" nada naik do-mi-sol untuk pilihan ke-1, 2, 3.
        pick(n) { this.tone([523, 659, 784][n] || 784, .14, 'sine', .22, 0, ([523, 659, 784][n] || 784) * 1.5); },
        unpick() { this.tone(392, .1, 'sine', .12, 0, 294); },
        click() { this.tone(180, .06, 'square', .06); },
        jingle() { [0, .07, .14].forEach((d, i) => this.tone(1568 + i * 180, .09, 'triangle', .05, d)); },
        open() { this.tone(700, .18, 'triangle', .2, 0, 1400); },
        drumroll() { for (let i = 0; i < 14; i++) this.noise(.05, .06 + i * .006, i * .085); },
        boom() { this.tone(110, .6, 'sine', .45, 0, 40); this.noise(.3, .15); },
        fanfare() { [523, 659, 784, 1047].forEach((f, i) => this.tone(f, i === 3 ? .5 : .16, 'triangle', .16, .12 * i)); },
        stamp() { this.tone(140, .08, 'square', .05); },
    };

    // Musik latar: loop music box C-Am-F-G, 100 bpm, pelan di bawah efek suara.
    // Disintesis di browser supaya tanpa file (ringan untuk sinyal lemah, tanpa urusan lisensi).
    const music = {
        LEVEL: .07,
        BEAT: .3,
        CHORDS: [
            [261.63, 329.63, 392.0, 523.25],
            [220.0, 261.63, 329.63, 440.0],
            [174.61, 220.0, 261.63, 349.23],
            [196.0, 246.94, 293.66, 392.0],
        ],
        ARP: [0, 1, 2, 3, 2, 1, 2, 3],
        bus: null,
        timer: null,
        next: 0,
        step: 0,
        ducked: false,
        start() {
            const ctx = sound.audio();
            if (!ctx || this.timer) return;
            this.bus = ctx.createGain();
            this.bus.gain.setValueAtTime(0.0001, ctx.currentTime);
            this.bus.gain.exponentialRampToValueAtTime(this.target(), ctx.currentTime + 1.2);
            this.bus.connect(ctx.destination);
            this.next = ctx.currentTime + .1;
            this.timer = setInterval(() => this.schedule(ctx), 100);
        },
        stop() {
            if (!this.timer) return;
            clearInterval(this.timer);
            this.timer = null;
            const bus = this.bus;
            const ctx = sound.ctx;
            if (bus && ctx) {
                bus.gain.setTargetAtTime(0.0001, ctx.currentTime, .15);
                setTimeout(() => bus.disconnect(), 800);
            }
            this.bus = null;
        },
        target() { return this.ducked ? this.LEVEL * .25 : this.LEVEL; },
        // Diredam saat kado dibuka supaya efek suara dan ketegangannya terdengar.
        duck(on) {
            this.ducked = on;
            if (this.bus && sound.ctx) this.bus.gain.setTargetAtTime(this.target(), sound.ctx.currentTime, .25);
        },
        schedule(ctx) {
            while (this.next < ctx.currentTime + .3) {
                const bar = Math.floor(this.step / 8) % this.CHORDS.length;
                const beat = this.step % 8;
                const chord = this.CHORDS[bar];
                this.voice(ctx, chord[this.ARP[beat]] * 2, this.next, .9, 'triangle', .5);
                if (beat === 0 || beat === 4) this.voice(ctx, chord[0] / 2, this.next, 1.4, 'sine', .9);
                if (beat === 7 && bar % 2 === 1) this.voice(ctx, chord[3] * 4, this.next, .5, 'sine', .18);
                this.next += this.BEAT;
                this.step++;
            }
        },
        voice(ctx, freq, t, dur, type, gain) {
            const osc = ctx.createOscillator();
            const g = ctx.createGain();
            osc.type = type;
            osc.frequency.setValueAtTime(freq, t);
            g.gain.setValueAtTime(0.0001, t);
            g.gain.exponentialRampToValueAtTime(gain, t + .015);
            g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
            osc.connect(g).connect(this.bus);
            osc.start(t);
            osc.stop(t + dur + .05);
        },
    };
    const buzz = pattern => { try { navigator.vibrate && navigator.vibrate(pattern); } catch (e) { /* tidak didukung */ } };
    const anim = (el, frames, opts) => (reduced || !el.animate) ? { finished: Promise.resolve() } : el.animate(frames, opts);

    // ── Server ────────────────────────────────────────────────────────────
    async function post(path, body) {
        let res;
        try {
            res = await fetch(`/play/${data.token}/${path}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': data.csrf },
                body: JSON.stringify(body || {}),
            });
        } catch (e) {
            throw new Error('Koneksi terputus. Pilihanmu aman tersimpan, coba lagi.');
        }
        const json = await res.json().catch(() => ({}));
        if (!res.ok || !json.ok) {
            const err = new Error(json.error || 'Ada gangguan di server. Coba lagi sebentar lagi.');
            err.fatal = res.status === 404 || /berakhir|habis/i.test(json.error || '');
            throw err;
        }
        return json;
    }

    function toast(message, action) {
        stage.querySelector('.toast')?.remove();
        const el = document.createElement('div');
        el.className = 'toast';
        el.setAttribute('role', 'alert');
        const text = document.createElement('span');
        text.textContent = message;
        el.appendChild(text);
        if (action) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = action.label;
            btn.addEventListener('click', () => { el.remove(); action.run(); });
            el.appendChild(btn);
        }
        stage.appendChild(el);
    }
    const fatalAction = { label: 'Kembali', run: () => { location.href = data.formUrl; } };

    // ── Tampilan ──────────────────────────────────────────────────────────
    const VIEWS = {
        pick: ['pickView', 'board', 'pickTray'],
        reveal: ['revealView', 'reveal', 'revealTray'],
        result: ['resultView', 'resultTray'],
    };
    function show(view) {
        Object.values(VIEWS).flat().forEach(id => { $(id).hidden = true; });
        VIEWS[view].forEach(id => { $(id).hidden = false; });
    }

    function paintChance() {
        document.querySelectorAll('.coin').forEach(coin => {
            coin.classList.toggle('is-used', Number(coin.dataset.coin) < state.current - 1);
        });
        $('chanceText').textContent = `${state.current}/${data.chanceTotal}`;
        $('chanceText').parentElement.setAttribute('aria-label', `Kesempatan ${state.current} dari ${data.chanceTotal}`);
    }

    // ── Pilih 3 dari 24 ──────────────────────────────────────────────────
    function paintPick() {
        const full = state.selected.length === 3;
        $('board').classList.toggle('is-full', full);
        cells.forEach(cell => {
            const slot = Number(cell.dataset.slot);
            const order = state.selected.indexOf(slot);
            const picked = order >= 0;
            cell.classList.toggle('is-picked', picked);
            cell.setAttribute('aria-pressed', String(picked));
            let badge = cell.querySelector('.badge');
            let glow = cell.querySelector('.floor-glow');
            if (picked) {
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'badge';
                    cell.appendChild(badge);
                }
                badge.textContent = order + 1;
                if (!glow) {
                    glow = document.createElement('span');
                    glow.className = 'floor-glow';
                    cell.prepend(glow);
                }
            } else {
                badge?.remove();
                glow?.remove();
            }
        });
        document.querySelectorAll('[data-tray]').forEach(slotEl => {
            const v = state.selected[Number(slotEl.dataset.tray)];
            slotEl.textContent = v ? pad(v) : '';
            slotEl.classList.toggle('is-filled', Boolean(v));
        });
        const left = 3 - state.selected.length;
        $('pickLabel').textContent = left === 0 ? 'Siap dibuka!' : `Pilih ${left} lagi`;
        $('openBtn').disabled = !full || state.busy;
    }

    function jump(cell) {
        // Antisipasi 120 ms (gepeng), lompat 250 ms naik 22 px, mendarat 350 ms tetap naik sedikit.
        anim(cell.querySelector('.gift-body'), [
            { transform: 'translateY(0) scale(1, 1)', easing: 'ease-out' },
            { transform: 'translateY(0) scale(1.1, .82)', offset: .17, easing: EASE_OUT },
            { transform: 'translateY(-22px) scale(1.18)', offset: .5, easing: EASE_BACK },
            { transform: 'translateY(-8px) scale(1.1)' },
        ], { duration: 720 });
    }

    function onCell(cell) {
        if (state.locked || state.busy || cell.disabled) return;
        const slot = Number(cell.dataset.slot);
        if (state.selected.includes(slot)) {
            state.selected = state.selected.filter(s => s !== slot);
            sound.unpick();
            buzz(10);
        } else if (state.selected.length < 3) {
            state.selected.push(slot);
            sound.pick(state.selected.length - 1);
            buzz(10);
            jump(cell);
        } else {
            return;
        }
        paintPick();
    }
    cells.forEach(cell => cell.addEventListener('click', () => onCell(cell)));

    function enterPick(first) {
        show('pick');
        paintPick();
        if (reduced) return;
        // Masuk panggung: lantai miring 0,8 s, kado jatuh 0,45 s per kado dengan jeda 40 ms.
        anim(stage.querySelector('.stage-floor'), [
            { transform: 'perspective(260px) rotateX(84deg)', opacity: 0 },
            { transform: 'perspective(260px) rotateX(58deg)', opacity: 1 },
        ], { duration: 800, easing: EASE_OUT });
        anim($('pickView'), [{ transform: 'translateY(-30px)', opacity: 0 }, { transform: 'none', opacity: 1 }],
            { duration: 500, delay: first ? 300 : 0, easing: EASE_OUT, fill: 'backwards' });
        const step = first ? 40 : 18;
        cells.forEach((cell, i) => {
            anim(cell.querySelector('.gift'), [
                { transform: 'translateY(-90px) scale(.7)', opacity: 0 },
                { transform: 'none', opacity: 1 },
            ], { duration: 450, delay: (first ? 400 : 100) + i * step, easing: EASE_BACK, fill: 'backwards' });
        });
        if (first) sound.tone(220, .5, 'sine', .08, 0, 660);
        anim($('pickTray'), [{ transform: 'translateY(160px)' }, { transform: 'none' }],
            { duration: 400, delay: first ? 1100 : 200, easing: EASE_OUT, fill: 'backwards' });
    }

    // ── Buka 3 kado ──────────────────────────────────────────────────────
    function buildCards(slots) {
        const wrap = $('reveal');
        wrap.innerHTML = '';
        return slots.map(slot => {
            const card = document.createElement('button');
            card.type = 'button';
            card.className = 'rcard';
            card.disabled = true;
            card.dataset.slot = slot;
            card.innerHTML = '<span class="rcard-stage"><span class="rcard-rays"></span>'
                + '<span class="gift"></span><span class="rcard-glow"></span><span class="token"></span></span>'
                + `<span class="rcard-label"><span class="chip">KADO #${pad(slot)}</span><span class="v">Membuka…</span></span>`;
            card.querySelector('.gift').appendChild($('giftTpl').content.cloneNode(true));
            card.addEventListener('click', () => choose(slot));
            wrap.appendChild(card);
            return card;
        });
    }
    const cardBySlot = slot => $('reveal').querySelector(`.rcard[data-slot="${slot}"]`);

    function wiggle(card, duration, maxDeg) {
        // Goyang makin kuat (kanvas: kado bergoyang sekaligus loading server).
        const frames = [];
        const n = Math.max(6, Math.round(duration / 60));
        for (let i = 0; i <= n; i++) {
            const p = i / n;
            const deg = i === n ? 0 : Math.sin(i * 1.9) * maxDeg * (.25 + .75 * p);
            frames.push({ transform: `rotate(${deg.toFixed(2)}deg) translateY(${(-Math.abs(deg) / 2).toFixed(1)}px)` });
        }
        return anim(card.querySelector('.gift-body'), frames, { duration, easing: 'linear' });
    }

    function setCardPrize(card, item) {
        const t = tierOf(item);
        card.classList.add(t.cls);
        card.querySelector('.token').textContent = item.tier === 'poin' && item.points
            ? `+${item.points}`
            : (item.tier === 'hadiah_besar' && item.name ? item.name : t.token);
        card.querySelector('.chip').textContent = t.chip;
        card.querySelector('.v').textContent = prizeName(item);
    }

    async function popOpen(card, item, instant) {
        setCardPrize(card, item);
        if (!instant) {
            await wiggle(card, 500, 7).finished;
            sound.open();
            buzz(15);
        }
        card.classList.add('is-open');
        card.querySelector('.gift').classList.add('is-open');
        if (!instant) {
            anim(card.querySelector('.gift-lid'), [
                { transform: 'none' },
                { transform: 'translateY(-62%) rotate(-26deg)', offset: .55 },
                { transform: 'translateY(-48%) rotate(-18deg)' },
            ], { duration: 700, easing: 'cubic-bezier(.2, 1.3, .4, 1)' });
            anim(card.querySelector('.rcard-label'), [
                { transform: 'perspective(600px) rotateX(80deg)', opacity: 0 },
                { transform: 'none', opacity: 1 },
            ], { duration: 400, easing: 'ease-out' });
        }
    }

    async function bigPrizeMoment(card, item) {
        // Tegang 1,2 s dengan layar meredup, kilat emas, layar bergetar 6 px, percikan dan konfeti.
        const dim = $('dim');
        const others = [...$('reveal').querySelectorAll('.rcard')].filter(c => c !== card);
        others.forEach(c => c.classList.add('is-dimmed'));
        dim.style.opacity = '.62';
        sound.drumroll();
        await wiggle(card, 1200, 11).finished;
        buzz([30, 40, 80]);
        sound.boom();
        anim($('flash'), [{ opacity: 1 }, { opacity: 0 }], { duration: 450, easing: 'ease-out' });
        anim(stage, [
            { transform: 'translate(6px, -3px)' }, { transform: 'translate(-6px, 3px)' },
            { transform: 'translate(4px, 2px)' }, { transform: 'translate(-3px, -2px)' }, { transform: 'none' },
        ], { duration: 450, easing: 'ease-out' });
        await popOpen(card, item, true);
        anim(card, [{ transform: 'scale(.9)' }, { transform: 'scale(1.08)' }], { duration: 500, easing: EASE_BACK, fill: 'forwards' });
        sound.fanfare();
        sparks(card);
        confetti();
        await wait(900);
        dim.style.opacity = '0';
        others.forEach(c => c.classList.remove('is-dimmed'));
    }

    function sparks(card) {
        if (reduced) return;
        const fx = $('fx');
        const r = card.getBoundingClientRect();
        const s = stage.getBoundingClientRect();
        const cx = r.left - s.left + r.width / 2;
        const cy = r.top - s.top + r.height * .4;
        for (let i = 0; i < 16; i++) {
            const el = document.createElement('span');
            el.className = 'spark';
            el.style.left = `${cx}px`;
            el.style.top = `${cy}px`;
            fx.appendChild(el);
            const a = (i / 16) * Math.PI * 2;
            const d = 70 + Math.random() * 60;
            el.animate([
                { transform: 'translate(-50%, -50%) scale(1)', opacity: 1 },
                { transform: `translate(calc(-50% + ${Math.cos(a) * d}px), calc(-50% + ${Math.sin(a) * d}px)) scale(.2)`, opacity: 0 },
            ], { duration: 1000, easing: EASE_OUT }).finished.then(() => el.remove());
        }
    }

    function confetti() {
        if (reduced) return;
        const fx = $('fx');
        const colors = ['#F5DA9A', '#C99A3A', getComputedStyle(document.documentElement).getPropertyValue('--b0').trim(), '#FBF3E6'];
        const w = stage.clientWidth;
        const h = stage.clientHeight;
        for (let i = 0; i < 44; i++) {
            const el = document.createElement('span');
            el.className = 'confetti';
            el.style.left = `${Math.random() * w}px`;
            el.style.background = colors[i % colors.length];
            fx.appendChild(el);
            const drift = (Math.random() - .5) * 120;
            el.animate([
                { transform: 'translate(0, 0) rotate(0deg)', opacity: 1 },
                { transform: `translate(${drift}px, ${h + 40}px) rotate(${Math.random() * 720 - 360}deg)`, opacity: .9 },
            ], { duration: 1600 + Math.random() * 700, delay: Math.random() * 300, easing: 'cubic-bezier(.3, .6, .6, 1)' })
                .finished.then(() => el.remove());
        }
    }

    async function openRound() {
        if (state.selected.length !== 3 || state.busy) return;
        state.busy = true;
        $('openBtn').disabled = true;
        sound.click();
        buzz(20);

        music.duck(true);
        const slots = [...state.selected];
        const request = (async () => {
            if (!state.locked) {
                await post('pick', { slots });
                state.locked = true;
            }
            return post('reveal');
        })();
        request.catch(() => {}); // ditangani di bawah, cegah "unhandled rejection" selama animasi

        // Kado lain mengecil ke 0,6 dan pudar 0,5 s.
        const from = new Map(slots.map(s => [s, cellBySlot(s).querySelector('.gift').getBoundingClientRect()]));
        cells.filter(c => !slots.includes(Number(c.dataset.slot))).forEach(c => {
            anim(c, [{ transform: 'none', opacity: getComputedStyle(c).opacity }, { transform: 'scale(.6)', opacity: 0 }],
                { duration: 500, easing: EASE_OUT, fill: 'forwards' });
        });
        await wait(500);

        // Tiga kado pindah ke tengah 0,7 s (FLIP dari posisi di grid).
        show('reveal');
        $('revealTitle').textContent = 'Membuka kado…';
        $('takeBtn').disabled = true;
        $('takeBtn').textContent = 'Tunggu kado terbuka';
        const cards = buildCards(slots);
        cards.forEach(card => {
            const gift = card.querySelector('.gift');
            const to = gift.getBoundingClientRect();
            const f = from.get(Number(card.dataset.slot));
            anim(gift, [
                { transform: `translate(${f.left - to.left}px, ${f.top - to.top}px) scale(${f.width / to.width})` },
                { transform: 'none' },
            ], { duration: 700, easing: EASE_IO });
        });
        cells.forEach(c => c.getAnimations?.().forEach(a => a.cancel()));
        await wait(700);

        let res;
        const waiting = [];
        const keepWiggling = () => cards.forEach(card => waiting.push(wiggle(card, 700, 3)));
        const ticker = setInterval(keepWiggling, 700);
        keepWiggling();
        try {
            res = await request;
        } catch (err) {
            clearInterval(ticker);
            state.busy = false;
            music.duck(false);
            onRoundError(err, slots);
            return;
        }
        clearInterval(ticker);
        waiting.forEach(a => a.cancel?.());
        sound.jingle();

        state.items = res.revealed;
        await playOpenings(res.revealed, false, res.auto_taken ? res : null);
    }

    function onRoundError(err, slots) {
        if (err.fatal) {
            toast(err.message, fatalAction);
            return;
        }
        // Kembali ke papan; pilihan yang sudah tercatat di server tetap terkunci.
        cells.forEach(c => c.getAnimations?.().forEach(a => a.cancel()));
        state.selected = slots;
        enterPick(false);
        toast(err.message, { label: 'Coba lagi', run: openRound });
    }

    async function playOpenings(items, instant, autoTaken) {
        // Hadiah utama dibuka paling akhir supaya ketegangannya terbangun.
        const order = [...items].sort((a, b) => (a.tier === 'hadiah_besar') - (b.tier === 'hadiah_besar'));
        for (const item of order) {
            const card = cardBySlot(item.slot);
            if (!instant && item.tier === 'hadiah_besar') {
                await bigPrizeMoment(card, item);
            } else {
                await popOpen(card, item, instant);
                if (!instant) await wait(900 - 500);
            }
        }

        if (autoTaken) {
            $('revealTitle').textContent = 'Hadiah utama untukmu!';
            await finishTake(autoTaken);
            return;
        }

        state.busy = false;
        music.duck(false);
        $('revealTitle').textContent = 'Ambil 1 hadiah';
        $('takeBtn').textContent = 'Ketuk 1 hadiah';
        $('reveal').querySelectorAll('.rcard').forEach(card => { card.disabled = false; });
        $('reveal').querySelector('.rcard')?.focus({ preventScroll: true });
    }

    function choose(slot) {
        if (state.busy) return;
        state.chosen = slot;
        buzz(10);
        sound.pick(1);
        $('reveal').querySelectorAll('.rcard').forEach(card => {
            const on = Number(card.dataset.slot) === slot;
            card.classList.toggle('is-chosen', on);
            card.setAttribute('aria-pressed', String(on));
            card.querySelector('.check')?.remove();
            if (on) {
                const check = document.createElement('span');
                check.className = 'check';
                check.textContent = '✓';
                card.appendChild(check);
            }
        });
        const item = state.items.find(i => i.slot === slot);
        $('takeBtn').disabled = false;
        $('takeBtn').textContent = `Ambil ${prizeName(item)}`;
    }

    async function take() {
        if (state.chosen == null || state.busy) return;
        state.busy = true;
        $('takeBtn').disabled = true;
        sound.click();
        buzz(30);
        try {
            const res = await post('take', { slot: state.chosen });
            await finishTake(res);
        } catch (err) {
            state.busy = false;
            $('takeBtn').disabled = false;
            toast(err.message, err.fatal ? fatalAction : { label: 'Coba lagi', run: take });
        }
    }

    async function finishTake(res) {
        state.chanceUsed = data.chanceTotal - res.chance_left;
        paintChance();
        $('takeBtn').disabled = true;
        // Kado yang tidak diambil dicap HANGUS.
        $('reveal').querySelectorAll('.rcard').forEach(card => {
            card.disabled = true;
            const slot = Number(card.dataset.slot);
            if (slot === res.chosen) {
                card.classList.add('is-chosen');
                return;
            }
            card.classList.add('is-burned');
            const stamp = document.createElement('span');
            stamp.className = 'stamp';
            stamp.textContent = 'HANGUS';
            card.appendChild(stamp);
        });
        sound.stamp();
        await wait(900);

        const chosen = res.revealed.find(i => i.slot === res.chosen);
        state.result = {
            chosen,
            burned: res.revealed.filter(i => i.slot !== res.chosen),
            code: res.claim_code,
            expires: res.claim_expires_at,
            points: res.points,
            balance: res.balance,
            chance: data.chanceTotal - res.chance_left,
        };
        state.taken.push({ ...chosen, claim_code: res.claim_code, claim_expires_at: res.claim_expires_at, points: res.points ?? chosen.points });
        res.revealed.forEach(i => markUsed(i.slot));
        state.selected = [];
        state.locked = false;
        state.chosen = null;
        state.busy = false;
        music.duck(false);
        showResult();
    }

    function markUsed(slot) {
        const cell = cellBySlot(slot);
        cell.classList.add('is-used');
        cell.classList.remove('is-picked');
        cell.disabled = true;
        cell.querySelector('.gift').classList.add('is-open');
        cell.setAttribute('aria-label', `Kado ${pad(slot)}, sudah dibuka`);
        cell.querySelector('.badge')?.remove();
        cell.querySelector('.floor-glow')?.remove();
    }

    // ── Kesempatan berikutnya: papan diisi 24 kado baru ──────────────────
    function resetCell(cell) {
        cell.classList.remove('is-used', 'is-picked');
        cell.disabled = false;
        cell.querySelector('.gift').classList.remove('is-open');
        cell.setAttribute('aria-label', `Kado ${cell.dataset.slot.padStart(2, '0')}`);
        cell.setAttribute('aria-pressed', 'false');
    }

    // Server sudah mengundi 24 kado baru saat hadiah diambil. Kado dikumpulkan
    // ke tengah, dikocok, lalu disebar lagi supaya terlihat papannya diisi ulang.
    // Waktu dan easing mengikuti desain 4h (total ±2,2 s).
    async function reshuffle() {
        const board = $('board');
        state.busy = true;
        // Penanda berubah di detik 0, supaya jelas kenapa papannya bergerak.
        state.current = state.chanceUsed + 1;
        paintChance();
        cells.forEach(resetCell);
        // Pembaca layar cukup mendengar judul dan sub sekali, bukan tiap fase.
        stage.setAttribute('aria-busy', 'true');
        $('pickTitle').textContent = 'Kado diacak ulang';
        $('pickTitle').classList.add('is-shuffling');
        $('pickLead').textContent = `Kesempatan ${state.current}: semua 24 kado diisi hadiah baru.`;
        show('pick');
        paintPick();
        $('pickLabel').textContent = 'Mengacak kado…';
        board.classList.add('is-shuffling');

        if (reduced) {
            // Tanpa gerak: papan baru langsung tampil dengan crossfade 150 ms.
            board.animate?.([{ opacity: 0 }, { opacity: 1 }], { duration: 150 });
            await new Promise(r => setTimeout(r, 150));
        } else {
            const g = $('grid').getBoundingClientRect();
            const cx = g.left + g.width / 2;
            const cy = g.top + g.height / 2;
            const gifts = cells.map(cell => {
                const r = cell.getBoundingClientRect();
                const dx = cx - (r.left + r.width / 2);
                const dy = cy - (r.top + r.height / 2);
                return { node: cell.querySelector('.gift'), dx, dy, dist: Math.hypot(dx, dy) };
            });
            const pile = ({ dx, dy }, extra = '') => `translate(${dx}px, ${dy}px) scale(.55) ${extra}`;

            // Kumpul 0 → 0,45 s: ease-in, makin cepat masuk tumpukan.
            const gather = gifts.map(gift => anim(gift.node, [
                { transform: 'none' }, { transform: pile(gift) },
            ], { duration: 450, easing: 'cubic-bezier(.5, 0, .75, 0)', fill: 'forwards' }));
            sound.noise(.3, .05);
            await Promise.all(gather.map(a => a.finished));

            // Kocok 0,45 → 1,15 s: 6 ketukan ±117 ms, miring 12 · 12 · 10 · 10 · 8 · 0 derajat.
            const BEAT = 117;
            const tilt = [0, 12, -12, 10, -10, 8, 0];
            buzz([15, 60, 15]);
            setTimeout(() => buzz([15, 60, 15]), BEAT * 5);
            for (let i = 0; i < 6; i++) sound.tone(300 + i * 70, .05, 'triangle', .07, i * BEAT / 1000);
            await Promise.all(gifts.map(gift => anim(gift.node,
                tilt.map(deg => ({ transform: pile(gift, `rotate(${deg}deg)`) })),
                { duration: BEAT * 6, easing: 'linear' }).finished));

            // Sebar 1,15 → 2,2 s: tujuan kotak tetap acak (diundi server), tapi berangkat
            // dari tengah ke luar supaya terbaca sebagai satu gelombang. Lintasan naik 34 px.
            const rank = new Map([...gifts].sort((a, b) => a.dist - b.dist).map((gift, i) => [gift, i]));
            const scatter = gifts.map(gift => anim(gift.node, [
                { transform: pile(gift) },
                { transform: `translate(${gift.dx / 2}px, ${gift.dy / 2 - 34}px) scale(.78)` },
                { transform: 'none' },
            ], { duration: 520, delay: rank.get(gift) * 22, easing: EASE_BACK, fill: 'backwards' }));
            gather.forEach(a => a.cancel?.());
            // Denting tiap 4 kado; 24 denting dalam satu detik terdengar seperti bunyi galat.
            for (let i = 0; i < gifts.length; i += 4) sound.tone(1568 + i * 20, .09, 'triangle', .05, i * .022);
            await Promise.all(scatter.map(a => a.finished));
        }

        // Selesai: nomor kotak muncul lagi (fade 180 ms), sub tetap memberi tahu kado sudah baru.
        board.classList.remove('is-shuffling');
        $('pickTitle').textContent = 'Pilih 3 kado';
        $('pickTitle').classList.remove('is-shuffling');
        stage.removeAttribute('aria-busy');
        state.busy = false;
        paintPick();
        cells[0].focus({ preventScroll: true });
    }

    // ── Hasil dan ringkasan ──────────────────────────────────────────────
    function claimNote(item, expires) {
        const until = formatDate(expires);
        if (item.tier === 'hadiah_besar') {
            return `Hadiah utama masuk Hadiahku. Redeem sebelum ${until}, lalu CS dan Finance memprosesnya.`;
        }
        return `Masuk Hadiahku. Redeem sebelum ${until} lewat WhatsApp CS ${data.brand}.`;
    }

    function el(tag, cls, text) {
        const node = document.createElement(tag);
        if (cls) node.className = cls;
        if (text != null) node.textContent = text;
        return node;
    }

    function showResult() {
        const { chosen, burned, code, expires, chance } = state.result;
        const t = tierOf(chosen);
        const view = $('resultView');
        view.innerHTML = '';
        view.className = `result ${t.cls}`;
        view.appendChild(el('div', 'eyebrow', `KESEMPATAN ${chance} · HADIAHMU`));

        const win = el('div', 'win');
        win.appendChild(el('span', 'win-rays'));
        const card = el('div', 'win-card');
        card.appendChild(el('span', 'chip', t.chip));
        card.appendChild(el('div', 'name', prizeName({ ...chosen, points: state.result.points ?? chosen.points })));
        if (chosen.tier === 'poin') {
            const saldo = el('div', 'saldo');
            saldo.append('Saldo poinmu ');
            const num = el('b', null, String(Math.max(0, (state.result.balance ?? 0) - (state.result.points ?? 0))));
            saldo.append(num, ' poin');
            card.appendChild(saldo);
            card.appendChild(el('div', 'note', 'Poin langsung masuk saldo dan bisa ditukar di Katalog.'));
            win.appendChild(card);
            view.appendChild(win);
            countUp(num, state.result.balance ?? 0);
            coinBurst(card, saldo);
        } else {
            if (code) {
                card.appendChild(el('div', 'note', 'Kode klaim'));
                card.appendChild(el('div', 'code', code));
            }
            card.appendChild(el('div', 'note', claimNote(chosen, expires)));
            if (code) {
                const link = el('a', 'voucher-link', 'Lihat voucher');
                link.href = data.rewardUrl + encodeURIComponent(code);
                card.appendChild(link);
            }
            win.appendChild(card);
            view.appendChild(win);
        }

        if (burned.length) {
            view.appendChild(el('div', 'section-label', 'KADO YANG HANGUS'));
            const grid = el('div', 'burned');
            burned.forEach(b => {
                const box = el('div', 'burned-card');
                box.appendChild(el('div', 'meta', `#${pad(b.slot)} · ${tierOf(b).token}`));
                box.appendChild(el('div', 'v', prizeName(b)));
                box.appendChild(el('span', 'stamp', 'HANGUS'));
                grid.appendChild(box);
            });
            view.appendChild(grid);
        }

        const left = chanceLeft();
        $('nextBtn').hidden = false;
        if (left > 0) {
            // Petunjuk bahwa papan akan diisi ulang, supaya acak ulang tidak mengejutkan (4h).
            const next = el('div', 'next-card');
            const icons = el('span', 'next-gifts');
            icons.setAttribute('aria-hidden', 'true');
            for (let i = 0; i < 3; i++) {
                const box = el('span', 'next-gift');
                box.appendChild(el('span', 'gift'));
                box.firstChild.innerHTML = $('giftTpl').innerHTML;
                icons.appendChild(box);
            }
            const text = el('div');
            text.appendChild(el('b', null, `Kesempatan ${chance + 1} siap`));
            text.appendChild(el('span', 'next-sub', 'Papan diisi 24 kado baru dengan hadiah yang diundi ulang.'));
            next.append(icons, text);
            view.appendChild(next);
            $('nextBtn').textContent = `Lanjut ke Kesempatan ${chance + 1} `;
            $('nextBtn').appendChild(el('span', 'btn-chip', '24 kado baru'));
        } else {
            $('nextBtn').textContent = 'Lihat ringkasan';
        }
        $('laterLink').hidden = left === 0;
        show('result');
        view.scrollTop = 0;
        if (t.cls === 'tier-gold' && !reduced) confetti();
        $('nextBtn').focus({ preventScroll: true });
    }

    // Angka saldo bergulir 0,9 s (spesifikasi Poin).
    function countUp(node, target) {
        const start = Number(node.textContent) || 0;
        if (reduced) { node.textContent = target.toLocaleString('id-ID'); return; }
        const t0 = performance.now();
        const step = now => {
            const p = Math.min(1, (now - t0) / 900);
            const eased = 1 - Math.pow(1 - p, 3);
            node.textContent = Math.round(start + (target - start) * eased).toLocaleString('id-ID');
            if (p < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
    }

    // 8 koin terbang melengkung dari kartu ke angka saldo, jeda 90 ms (spesifikasi Poin).
    function coinBurst(fromEl, toEl) {
        if (reduced) return;
        const fx = $('fx');
        const s = stage.getBoundingClientRect();
        const a = fromEl.getBoundingClientRect();
        const b = toEl.getBoundingClientRect();
        for (let i = 0; i < 8; i++) {
            const coin = el('span', 'fly-coin');
            coin.style.left = `${a.left - s.left + a.width / 2}px`;
            coin.style.top = `${a.top - s.top + a.height * .35}px`;
            fx.appendChild(coin);
            const dx = b.left - a.left - a.width / 2 + b.width / 2;
            const dy = b.top - a.top - a.height * .35 + b.height / 2;
            coin.animate([
                { transform: 'translate(-50%, -50%) scale(.6)', opacity: 0 },
                { transform: `translate(calc(-50% + ${dx * .4}px), calc(-50% + ${dy * .4 - 60}px)) scale(1)`, opacity: 1, offset: .45 },
                { transform: `translate(calc(-50% + ${dx}px), calc(-50% + ${dy}px)) scale(.5)`, opacity: 0 },
            ], { duration: 550, delay: 250 + i * 90, easing: 'cubic-bezier(.3, .7, .4, 1)', fill: 'backwards' })
                .finished.then(() => { coin.remove(); buzz(8); if (i === 0) sound.tone(1320, .08, 'triangle', .08); });
        }
    }

    function showDone() {
        state.current = data.chanceTotal;
        paintChance();
        const view = $('resultView');
        view.innerHTML = '';
        view.className = 'result';
        const head = el('div');
        head.appendChild(el('h1', null, 'Permainan selesai'));
        head.firstChild.style.cssText = 'margin:0;font-size:26px;font-weight:800';
        head.appendChild(el('p', null, `${state.taken.length} hadiah final dari Gatcha ${data.brand}.`));
        head.lastChild.style.cssText = 'margin:4px 0 0;font-size:14px;color:var(--sand)';
        view.appendChild(head);

        if (!state.taken.length) {
            view.appendChild(el('div', 'empty-note', 'Kesempatan di resi ini sudah habis dan belum ada hadiah yang tercatat. Hubungi CS kalau menurutmu ini keliru.'));
        }
        const list = el('div', 'won-list');
        state.taken.forEach((item, i) => {
            const t = tierOf(item);
            const row = el('div', `won-item ${t.cls}`);
            row.style.animationDelay = `${i * 120}ms`;
            row.appendChild(el('div', 'idx', String(i + 1)));
            const body = el('div');
            body.style.cssText = 'flex:1;min-width:0';
            body.appendChild(el('div', 'chip', t.chip));
            body.appendChild(el('div', 'v', prizeName(item)));
            if (item.tier === 'poin') {
                body.appendChild(el('div', 'sub', 'Masuk saldo poin'));
            } else if (item.claim_code) {
                const sub = el('div', 'sub', 'Kode ');
                sub.appendChild(el('b', null, item.claim_code));
                sub.appendChild(document.createTextNode(` · redeem sebelum ${formatDate(item.claim_expires_at)}`));
                body.appendChild(sub);
            }
            row.appendChild(body);
            list.appendChild(row);
        });
        view.appendChild(list);
        if (state.taken.some(i => i.tier !== 'poin')) {
            view.appendChild(el('div', 'empty-note', 'Hadiah nyata ada di Hadiahku. Tekan Redeem di sana, lalu kirim pesan ke WhatsApp CS dari nomor pemenang.'));
        }

        $('nextBtn').hidden = false;
        $('nextBtn').textContent = 'Lihat Hadiahku';
        $('nextBtn').onclick = () => { location.href = data.rewardsUrl; };
        $('laterLink').hidden = false;
        $('laterLink').textContent = 'Ke Beranda';
        show('result');
    }

    $('openBtn').addEventListener('click', openRound);
    $('takeBtn').addEventListener('click', take);
    $('nextBtn').addEventListener('click', () => {
        if ($('nextBtn').onclick) return;
        if (chanceLeft() > 0) reshuffle();
        else showDone();
    });

    // ── Mulai ────────────────────────────────────────────────────────────
    sound.init();
    paintChance();

    if (data.pendingRevealed.length) {
        // Halaman di-refresh setelah kado terbuka: tampilkan lagi, langsung bisa ambil 1.
        state.locked = true;
        state.selected = data.pendingSlots;
        state.items = data.pendingRevealed;
        show('reveal');
        buildCards(data.pendingSlots);
        playOpenings(data.pendingRevealed, true, null);
    } else if (data.pendingSlots.length === 3) {
        // Refresh setelah memilih: pilihan sudah terkunci di server, tinggal dibuka.
        state.locked = true;
        state.selected = data.pendingSlots;
        enterPick(false);
    } else if (chanceLeft() === 0) {
        showDone();
    } else {
        enterPick(true);
    }
})();
