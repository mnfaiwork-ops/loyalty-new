// Gatcha motion spec — one continuous composition keyed to CUES.
const { useComposition, animate, clamp, Easing, CompositionStage } = window;

const MOTION = {
  enter: (from, to, start, end) => animate({ from, to, start, end, ease: Easing.easeOutCubic }),
  pop: (from, to, start, end) => animate({ from, to, start, end, ease: Easing.easeOutBack }),
  move: (from, to, start, end) => animate({ from, to, start, end, ease: Easing.easeInOutCubic }),
};

const BRANDS = {
  Amura: { name: 'Amura', L: 'A', p: '#1E6B63', b0: '#4DB5A8', b1: '#27897E', b2: '#0E4A43', st1: '#11403A', st2: '#061816', glow: 'rgba(90,200,185,.75)' },
  Reglow: { name: 'Reglow', L: 'R', p: '#A8461F', b0: '#E08450', b1: '#C45A2C', b2: '#7E3012', st1: '#4A2010', st2: '#1E0C05', glow: 'rgba(232,140,90,.75)' },
};
const GOLD = 'linear-gradient(180deg,#F5DA9A,#C99A3A)';
const FONT = "'Plus Jakarta Sans', system-ui, sans-serif";

// Screen geometry (390 × 844 app screen)
const COLS = 4, GX0 = 16, GY0 = 188, CW = 89.5, RH = 84, GS = 70;
const cellPos = i => ({ x: GX0 + (i % COLS) * CW + (CW - GS) / 2, y: GY0 + Math.floor(i / COLS) * RH });
const PICKS = [6, 13, 20];
const OPEN_S = 104, OPEN_Y = 360, OPEN_X = [24, 143, 262];
const PILL = { x: 318, y: 112 };

const SPEC = [
  { key: 'Masuk', title: 'Masuk panggung', rows: [['Latar & lantai', '0,8 s · easeOutCubic'], ['Kado jatuh', '0,45 s per kado · jeda 40 ms · easeOutBack'], ['Melayang (idle)', '±3 px · siklus 3 s · sinus'], ['Sinar latar', 'berputar 20°/s terus-menerus']], sfx: 'Whoosh masuk, lalu "tik" kecil tiap kado mendarat', hap: 'Tidak ada' },
  { key: 'Pilih', title: 'Pilih kado', rows: [['Antisipasi', 'tekan 120 ms · skala 1,1 × 0,82'], ['Lompat', '250 ms · naik 22 px · skala 1,18'], ['Mendarat', '350 ms · easeOutBack · tetap naik 6 px'], ['Nomor & slot', 'pop 300 ms · easeOutBack']], sfx: '"Pop" dengan nada naik: do – mi – sol', hap: '10 ms tiap ketuk' },
  { key: 'Buka', title: 'Buka 3 kado', rows: [['Tombol ditekan', 'turun 5 px · 100 ms, naik 150 ms'], ['Kado lain hilang', '0,5 s · mengecil ke 0,6 + pudar'], ['3 kado ke tengah', '0,7 s · easeInOutCubic'], ['Tiap kado', 'goyang 0,5 s makin kuat → tutup lepas 0,3 s']], sfx: 'Klik tombol · gemerincing saat goyang · "pop!" saat terbuka', hap: '20 ms saat tombol · 15 ms tiap kado terbuka' },
  { key: 'HadiahUtama', title: 'Hadiah utama keluar', rows: [['Tegang', 'goyang 1,2 s · layar lain meredup 60%'], ['Ledakan', 'kilat emas 0,4 s · layar bergetar 6 px'], ['Kartu hadiah', 'naik & membesar 0,5 s · easeOutBack'], ['Percikan & konfeti', '16 percikan 1 s · konfeti jatuh 2 s']], sfx: 'Drumroll → dentuman → fanfare pendek', hap: 'Pola 30 · 40 · 80 ms (kuat)' },
  { key: 'Poin', title: 'Poin masuk ke saldo', rows: [['Ketuk poin', 'token berdenyut 1,15 · 200 ms'], ['Koin terbang', '8 koin · 0,55 s tiap koin · jeda 90 ms · lintasan melengkung'], ['Saldo', 'angka bergulir 0,9 s · easeOutCubic'], ['Kapsul saldo', 'berdenyut 1,12 tiap koin tiba']], sfx: '"Ting" koin dengan nada naik · "cring" di akhir', hap: '8 ms tiap koin tiba' },
];
const EVENTS = [
  ['Masuk', 0.05, 's'], ['Masuk', 0.95, 's'], ['Masuk', 1.3, 's'],
  ['Pilih', 0.4, 'sh'], ['Pilih', 1.3, 'sh'], ['Pilih', 2.2, 'sh'],
  ['Buka', 0.12, 'sh'], ['Buka', 1.3, 'sh'], ['Buka', 2.2, 'sh'],
  ['HadiahUtama', 0.1, 's'], ['HadiahUtama', 1.2, 'sh'], ['HadiahUtama', 1.45, 's'],
  ['Poin', 0.5, 'sh'], ['Poin', 1.2, 'sh'], ['Poin', 1.5, 'sh'], ['Poin', 1.8, 'sh'],
];

function Gift({ x, y, size, c, lid = 0, rot = 0, sx = 1, sy = 1, lift = 0, glow = 0, opacity = 1, z = 1 }) {
  const lidT = lid > 0 ? `translateY(${-lid * 70}%) rotate(${-lid * 24}deg)` : 'none';
  return (
    <div style={{ position: 'absolute', left: x, top: y, width: size, height: size, opacity, zIndex: z }}>
      <span style={{ position: 'absolute', left: '12%', right: '12%', bottom: '2%', height: '12%', borderRadius: '50%', background: 'radial-gradient(closest-side,rgba(0,0,0,.6),transparent)', transform: `scale(${1 - lift / 60})` }} />
      {glow > 0 && <span style={{ position: 'absolute', left: '-8%', right: '-8%', bottom: '-6%', height: '30%', borderRadius: '50%', background: 'radial-gradient(closest-side,rgba(240,208,138,.95),transparent)', opacity: glow }} />}
      <div style={{ position: 'absolute', inset: 0, transformOrigin: '50% 90%', transform: `translateY(${-lift}px) rotate(${rot}deg) scale(${sx},${sy})` }}>
        <span style={{ position: 'absolute', left: '14%', right: '14%', top: '42%', bottom: '10%', borderRadius: '3px 3px 8px 8px', background: `linear-gradient(90deg,${c.b2} 0%,${c.b1} 30%,${c.b1} 60%,${c.b2} 100%)`, boxShadow: 'inset 0 -8px 12px rgba(0,0,0,.3)' }} />
        <span style={{ position: 'absolute', left: '44%', width: '12%', top: '42%', bottom: '10%', background: 'linear-gradient(90deg,#9C7020,#F0D08A,#9C7020)' }} />
        <div style={{ position: 'absolute', inset: 0, transform: lidT, transformOrigin: '30% 40%', opacity: lid > 0.85 ? clamp(1 - (lid - 0.85) * 4, 0, 1) : 1 }}>
          <span style={{ position: 'absolute', left: '10%', right: '10%', top: '31%', height: '13%', borderRadius: 3, background: `linear-gradient(180deg,${c.b0},${c.b2})`, boxShadow: '0 4px 5px rgba(0,0,0,.35)' }} />
          <span style={{ position: 'absolute', left: '43%', width: '14%', top: '31%', height: '13%', background: 'linear-gradient(90deg,#9C7020,#F5DA9A,#9C7020)' }} />
          <span style={{ position: 'absolute', left: '25%', top: '10%', width: '25%', height: '22%', border: '3.5px solid #E8C170', borderRadius: '60% 40% 30% 70%', transform: 'rotate(-22deg)', boxSizing: 'border-box' }} />
          <span style={{ position: 'absolute', right: '25%', top: '10%', width: '25%', height: '22%', border: '3.5px solid #E8C170', borderRadius: '40% 60% 70% 30%', transform: 'rotate(22deg)', boxSizing: 'border-box' }} />
          <span style={{ position: 'absolute', left: '44%', top: '22%', width: '12%', height: '10%', borderRadius: 3, background: '#F0D08A' }} />
        </div>
      </div>
    </div>
  );
}

function Rays({ cx, cy, r, color, opacity, rot, scale = 1 }) {
  if (opacity <= 0.001) return null;
  return <span style={{ position: 'absolute', left: cx - r, top: cy - r, width: r * 2, height: r * 2, borderRadius: '50%', opacity, transform: `rotate(${rot}deg) scale(${scale})`, background: `repeating-conic-gradient(${color} 0deg 7deg,transparent 7deg 22deg)`, WebkitMaskImage: 'radial-gradient(closest-side,#000 12%,transparent 100%)', maskImage: 'radial-gradient(closest-side,#000 12%,transparent 100%)' }} />;
}

function wiggle(T, start, end, maxDeg) {
  if (T < start || T > end) return 0;
  const p = (T - start) / (end - start);
  return Math.sin((T - start) * 38) * maxDeg * p;
}

function Screen({ c }) {
  const { T, CUES } = useComposition();
  const M = CUES.Masuk, P = CUES.Pilih, B = CUES.Buka, H = CUES.HadiahUtama, N = CUES.Poin;
  const END = N + 3.2;
  const fadeIn = MOTION.enter(0, 1, M, M + 0.4)(T);
  const fadeOut = MOTION.enter(1, 0, END - 0.4, END)(T);
  const vis = Math.min(fadeIn, fadeOut);
  const floorRot = MOTION.enter(84, 58, M + 0.1, M + 0.9)(T);
  const headY = MOTION.enter(-60, 0, M + 0.3, M + 0.8)(T);
  const barY = MOTION.enter(160, 0, M + 1.6, M + 2.0)(T) + MOTION.move(0, 180, B + 0.3, B + 0.7)(T);
  const dim = MOTION.enter(0, 0.62, H + 0.4, H + 1.2)(T) * MOTION.enter(1, 0, N + 0.1, N + 0.5)(T);
  const shake = T > H + 1.0 && T < H + 1.45 ? Math.sin(T * 90) * 6 * (1 - (T - H - 1.0) / 0.45) : 0;
  const flash = T >= H + 1.2 ? MOTION.enter(1, 0, H + 1.2, H + 1.65)(T) : 0;

  const picked = PICKS.map((gi, k) => P + 0.4 + k * 0.9);
  const nSel = picked.filter(tp => T >= tp + 0.1).length;
  const btnOn = MOTION.pop(0, 1, P + 2.55, P + 2.85)(T);
  const btnPress = MOTION.enter(0, 5, B + 0.1, B + 0.2)(T) - MOTION.pop(0, 5, B + 0.2, B + 0.35)(T);

  // title
  let title = 'Pilih 3 kado', sub = 'Kado mana yang menyimpan hadiah?';
  if (T >= P) { title = nSel >= 3 ? 'Siap dibuka!' : `Pilih ${3 - nSel} lagi`; sub = 'Ketuk kado untuk memilih'; }
  if (T >= B + 0.3) { title = 'Membuka kado…'; sub = 'Satu per satu'; }
  if (T >= H + 1.2) { title = 'WAAAH!'; sub = 'Kado emas terbuka'; }
  if (T >= N) { title = 'Ambil 1 hadiah'; sub = 'Contoh: kamu memilih poin'; }
  const titleKey = T >= N ? N : T >= H + 1.2 ? H + 1.2 : T >= B + 0.3 ? B + 0.3 : T >= P ? P : M + 0.6;
  const titlePop = MOTION.pop(0.85, 1, titleKey, titleKey + 0.3)(T);
  const titleOp = MOTION.enter(0, 1, titleKey, titleKey + 0.2)(T);

  const gifts = [];
  for (let i = 0; i < 24; i++) {
    const cp = cellPos(i);
    const k = PICKS.indexOf(i);
    const d0 = M + 0.5 + i * 0.04;
    let y = cp.y + MOTION.pop(-420, 0, d0, d0 + 0.45)(T);
    let x = cp.x, size = GS, rot = 0, sx = 1, sy = 1, lift = 0, glow = 0, op = T < d0 ? 0 : 1, lid = 0, z = 1;
    lift += Math.sin(T * 2.1 + i * 0.7) * 3 * MOTION.enter(0, 1, d0 + 0.4, d0 + 0.8)(T);
    if (k >= 0) {
      const tp = picked[k];
      const pre = MOTION.enter(0, 1, tp - 0.12, tp)(T) - MOTION.enter(0, 1, tp, tp + 0.08)(T);
      sx = 1 + 0.1 * pre; sy = 1 - 0.18 * pre;
      lift += MOTION.enter(0, 22, tp, tp + 0.25)(T) - MOTION.pop(0, 16, tp + 0.25, tp + 0.6)(T);
      const sc = MOTION.enter(1, 1.18, tp, tp + 0.25)(T) * (T > tp + 0.25 ? MOTION.pop(1, 1.08 / 1.18, tp + 0.25, tp + 0.6)(T) : 1);
      sx *= sc; sy *= sc;
      glow = MOTION.enter(0, 1, tp + 0.1, tp + 0.4)(T);
      // fly to reveal row
      const f = MOTION.move(0, 1, B + 0.35, B + 1.05)(T);
      x = x + (OPEN_X[k] - x) * f; y = y + (OPEN_Y - y) * f; size = GS + (OPEN_S - GS) * f;
      if (f > 0) { sx = sx + (1 - sx) * f; sy = sy + (1 - sy) * f; lift *= (1 - f); }
      z = 5;
      const to = k < 2 ? B + 1.8 + k * 0.9 : H + 1.2;
      const wStart = k < 2 ? to - 0.5 : H;
      rot = wiggle(T, wStart, to, k < 2 ? 9 : 15);
      if (k === 2) lift += (T > H && T < to ? Math.abs(Math.sin((T - H) * 19)) * 6 * ((T - H) / 1.2) : 0);
      lid = MOTION.pop(0, 1, to, to + 0.35)(T);
      if (k === 2) glow = Math.max(glow * (1 - f), MOTION.enter(0, 1, H, H + 1.2)(T) * (T < H + 1.6 ? 1 : 0));
      else if (f > 0) glow *= 1 - f;
      // Poin scene: other opened gifts fade back as base under tokens
    } else {
      const g = MOTION.enter(0, 1, B + 0.3, B + 0.8)(T);
      op *= 1 - g; sx = sy = 1 - 0.4 * g; y += 40 * g;
    }
    if (op > 0.01) gifts.push(<Gift key={i} x={x} y={y} size={size} c={c} rot={rot} sx={sx} sy={sy} lift={lift} glow={glow} opacity={op} lid={lid} z={z} />);
  }

  // tokens from opened gifts
  const TOK = [{ v: '+80', s: 'poin', tier: 'poin' }, { v: 'Serum', s: '20 ml', tier: 'item' }];
  const tokens = TOK.map((tk, k) => {
    const to = B + 1.8 + k * 0.9;
    const a = MOTION.pop(0, 1, to + 0.05, to + 0.45)(T);
    if (a <= 0.001) return null;
    const cx = OPEN_X[k] + OPEN_S / 2, cy = OPEN_Y + 20;
    const tapPulse = k === 0 ? (MOTION.enter(0, 1, N + 0.5, N + 0.6)(T) - MOTION.pop(0, 1, N + 0.6, N + 0.8)(T)) : 0;
    const drained = k === 0 ? MOTION.enter(1, 0.35, N + 0.9, N + 1.9)(T) : 1;
    const rayCol = tk.tier === 'poin' ? 'rgba(255,238,205,.7)' : c.glow;
    return (
      <React.Fragment key={k}>
        <Rays cx={cx} cy={cy + 10} r={90} color={rayCol} opacity={0.85 * a * (1 - dim)} rot={T * 40} scale={a} />
        <div style={{ position: 'absolute', left: cx - 48, top: cy - 58 * a, width: 96, height: 66, zIndex: 8, transform: `scale(${(0.4 + 0.6 * a) * (1 + 0.15 * tapPulse)})`, opacity: Math.min(1, a) * drained + (1 - drained) * 0.35, borderRadius: 16, background: tk.tier === 'poin' ? '#FBF3E6' : '#fff', color: tk.tier === 'poin' ? '#2B221D' : c.b2, boxShadow: `0 5px 0 rgba(0,0,0,.35), 0 0 0 ${k === 0 && T > N + 0.5 ? 3 : 0}px #F0D08A`, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', fontFamily: FONT }}>
          <div style={{ fontSize: 22, fontWeight: 800, lineHeight: 1 }}>{tk.v}</div>
          <div style={{ fontSize: 11, fontWeight: 700, opacity: 0.7, marginTop: 3 }}>{tk.s}</div>
        </div>
      </React.Fragment>
    );
  });

  // big prize
  const bp = MOTION.pop(0, 1, H + 1.3, H + 1.8)(T) * MOTION.enter(1, 0, N, N + 0.35)(T);
  const sparks = [];
  if (T > H + 1.2 && T < H + 2.4) {
    const p = (T - H - 1.2) / 1.2;
    for (let s = 0; s < 16; s++) {
      const ang = (s / 16) * Math.PI * 2 + 0.2;
      const d = Easing.easeOutCubic(Math.min(1, p * 1.4)) * (110 + (s % 3) * 30);
      sparks.push(<span key={s} style={{ position: 'absolute', left: 195 + Math.cos(ang) * d - 4, top: OPEN_Y + 40 + Math.sin(ang) * d - 4, width: 8, height: 8, borderRadius: s % 2 ? '50%' : 2, background: s % 3 ? '#F5DA9A' : '#fff', opacity: 1 - p, zIndex: 12 }} />);
    }
  }
  const confetti = [];
  if (T > H + 1.35 && T < N + 0.5) {
    for (let s = 0; s < 26; s++) {
      const st = H + 1.35 + (s % 7) * 0.08;
      const p = clamp((T - st) / 2.2, 0, 1);
      const x0 = (s * 53) % 390;
      confetti.push(<span key={s} style={{ position: 'absolute', left: x0 + Math.sin(T * 4 + s) * 14, top: -20 + p * 880, width: 7, height: 12, borderRadius: 2, background: ['#F5DA9A', c.b0, '#FBF3E6', '#C99A3A'][s % 4], transform: `rotate(${T * 300 + s * 40}deg)`, opacity: 1 - Math.max(0, p - 0.8) * 5, zIndex: 13 }} />);
    }
  }

  // coins
  const coins = [];
  const bal0 = 1250, bal = Math.round(bal0 + 80 * MOTION.enter(0, 1, N + 1.0, N + 1.9)(T));
  let pillPulse = 0;
  for (let s = 0; s < 8; s++) {
    const st = N + 0.9 + s * 0.09, en = st + 0.55;
    const p = clamp((T - st) / (en - st), 0, 1);
    if (T > en && T < en + 0.18) pillPulse = Math.max(pillPulse, 1 - (T - en) / 0.18);
    if (p <= 0 || p >= 1) continue;
    const e = Easing.easeInOutCubic(p);
    const sx0 = OPEN_X[0] + OPEN_S / 2 + ((s % 3) - 1) * 10, sy0 = OPEN_Y - 20;
    const x = sx0 + (PILL.x - sx0) * e, y = sy0 + (PILL.y - sy0) * e - Math.sin(p * Math.PI) * 90;
    coins.push(<span key={s} style={{ position: 'absolute', left: x - 11, top: y - 11, width: 22, height: 22, borderRadius: '50%', background: GOLD, boxShadow: 'inset 0 -2px 0 #8A6318, 0 2px 6px rgba(0,0,0,.4)', zIndex: 14, transform: `scale(${1 - 0.3 * e})` }} />);
  }
  const plus = MOTION.pop(0, 1, N + 1.9, N + 2.2)(T) * MOTION.enter(1, 0, N + 2.6, N + 2.9)(T);

  // finger
  let fx = 390 + 40, fy = 700, fOp = 0, press = 0;
  const targets = [...picked.map((tp, k) => ({ t: tp, x: cellPos(PICKS[k]).x + GS / 2, y: cellPos(PICKS[k]).y + GS / 2 })), { t: B + 0.12, x: 195, y: 790 }, { t: N + 0.55, x: OPEN_X[0] + OPEN_S / 2, y: OPEN_Y - 10 }];
  if (T > P - 0.1 && T < B + 0.6) fOp = MOTION.enter(0, 1, P - 0.1, P + 0.15)(T) * MOTION.enter(1, 0, B + 0.3, B + 0.6)(T);
  if (T > N + 0.1 && T < N + 1.1) fOp = MOTION.enter(0, 1, N + 0.1, N + 0.3)(T) * MOTION.enter(1, 0, N + 0.8, N + 1.1)(T);
  {
    let prev = { t: P - 0.3, x: 330, y: 760 };
    for (const tg of targets) {
      if (T <= tg.t) { const e = Easing.easeInOutCubic(clamp((T - Math.max(prev.t, tg.t - 0.45)) / Math.min(0.45, tg.t - prev.t), 0, 1)); fx = prev.x + (tg.x - prev.x) * e; fy = prev.y + (tg.y - prev.y) * e; break; }
      prev = tg; fx = tg.x; fy = tg.y;
      if (tg.t === targets[4].t) prev = { t: N, x: 300, y: 700 };
    }
    for (const tg of targets) if (T > tg.t - 0.1 && T < tg.t + 0.12) press = 1 - Math.abs(T - tg.t) / 0.12;
  }

  const slots = [0, 1, 2].map(k => MOTION.pop(0, 1, picked[k] + 0.2, picked[k] + 0.5)(T));

  return (
    <div style={{ position: 'absolute', inset: 0, overflow: 'hidden', background: c.st2, fontFamily: FONT, color: '#FBF3E6', transform: `translate(${shake}px, ${shake * 0.4}px)` }}>
      <div style={{ position: 'absolute', inset: 0, opacity: vis }}>
        <div style={{ position: 'absolute', inset: 0, background: `radial-gradient(130% 80% at 50% 6%,${c.st1} 0%,${c.st2} 72%)` }} />
        <Rays cx={195} cy={60} r={420} color="rgba(240,208,138,.10)" opacity={MOTION.enter(0, 1, M + 0.2, M + 1)(T)} rot={T * 20} />
        <div style={{ position: 'absolute', left: '-40%', right: '-40%', bottom: 0, height: '58%', background: 'repeating-linear-gradient(90deg,rgba(240,208,138,.10) 0 1px,transparent 1px 44px),repeating-linear-gradient(0deg,rgba(240,208,138,.10) 0 1px,transparent 1px 44px)', transform: `perspective(260px) rotateX(${floorRot}deg)`, transformOrigin: '50% 100%' }} />
        {/* status + header */}
        <div style={{ position: 'absolute', left: 0, right: 0, top: 0, height: 40, display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '0 24px', fontSize: 13, fontWeight: 700 }}><span>09.42</span><span>3G · 58%</span></div>
        <div style={{ position: 'absolute', left: 0, right: 0, top: 44 + headY, height: 52, display: 'flex', alignItems: 'center', gap: 10, padding: '0 12px' }}>
          <div style={{ width: 44, fontSize: 24, textAlign: 'center' }}>‹</div>
          <div style={{ width: 30, height: 30, borderRadius: 9, background: c.p, border: '1.5px solid #E8C170', display: 'grid', placeItems: 'center', fontFamily: "'DM Serif Display',serif", fontSize: 17, boxSizing: 'border-box' }}>{c.L}</div>
          <div style={{ flex: 1, fontSize: 16, fontWeight: 800 }}>Gatcha {c.name}</div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 6, height: 36, padding: '0 10px 0 6px', borderRadius: 999, background: 'rgba(0,0,0,.35)', border: '1px solid rgba(240,208,138,.3)', transform: `scale(${1 + 0.12 * pillPulse})` }}>
            <span style={{ width: 22, height: 22, borderRadius: '50%', background: GOLD, boxShadow: 'inset 0 -2px 0 #8A6318' }} />
            <span style={{ fontSize: 14, fontWeight: 800, fontVariantNumeric: 'tabular-nums' }}>{bal.toLocaleString('id-ID')}</span>
          </div>
        </div>
        {plus > 0.01 && <div style={{ position: 'absolute', left: PILL.x - 10, top: PILL.y + 22 - 10 * plus, fontSize: 16, fontWeight: 800, color: '#F5DA9A', opacity: plus, transform: `scale(${plus})` }}>+80</div>}
        <div style={{ position: 'absolute', left: 16, right: 16, top: 108, opacity: titleOp, transform: `scale(${titlePop})`, transformOrigin: '0 50%' }}>
          <div style={{ fontSize: 26, fontWeight: 800, color: title === 'WAAAH!' ? '#F5DA9A' : '#FBF3E6' }}>{title}</div>
          <div style={{ fontSize: 13, color: '#D9C9B6', marginTop: 3 }}>{sub}</div>
        </div>
        {PICKS.map((gi, k) => {
          const b = MOTION.pop(0, 1, picked[k] + 0.1, picked[k] + 0.4)(T) * MOTION.enter(1, 0, B + 0.3, B + 0.5)(T);
          if (b <= 0.01) return null;
          const cp = cellPos(gi);
          return <span key={k} style={{ position: 'absolute', left: cp.x + GS - 20, top: cp.y - 10, width: 24, height: 24, borderRadius: '50%', background: GOLD, color: '#2B1608', fontSize: 12, fontWeight: 800, display: 'grid', placeItems: 'center', boxShadow: '0 2px 0 #8A6318', transform: `scale(${b})`, zIndex: 6 }}>{k + 1}</span>;
        })}
        {gifts}
        <div style={{ position: 'absolute', inset: 0, background: '#000', opacity: dim, zIndex: 9 }} />
        {tokens}
        {/* big prize */}
        <Rays cx={195} cy={OPEN_Y + 40} r={330} color="rgba(240,208,138,.55)" opacity={bp} rot={T * 60} scale={0.6 + 0.4 * bp} />
        {bp > 0.01 && (
          <div style={{ position: 'absolute', left: 55, top: 250, width: 280, zIndex: 11, transform: `scale(${bp}) translateY(${(1 - bp) * 80}px)`, borderRadius: 22, background: 'linear-gradient(180deg,#3A2608,#1E1206)', border: '2px solid #F0D08A', boxShadow: '0 0 60px rgba(240,208,138,.55), 0 8px 0 #8A6318', padding: '22px 18px', display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 8, textAlign: 'center' }}>
            <div style={{ width: 96, height: 96, borderRadius: 18, background: 'repeating-linear-gradient(135deg,#5A3A12 0 6px,#6E4A18 6px 12px)', border: '1px solid rgba(240,208,138,.4)' }} />
            <div style={{ fontSize: 12, fontWeight: 800, letterSpacing: '.08em', color: '#F5DA9A' }}>HADIAH UTAMA</div>
            <div style={{ fontSize: 26, fontWeight: 800 }}>Emas 0,5 gram</div>
            <div style={{ fontSize: 12, color: '#D9C9B6' }}>Diperiksa tim 1–3 hari kerja</div>
          </div>
        )}
        {sparks}{confetti}{coins}
        {/* bottom bar */}
        <div style={{ position: 'absolute', left: 0, right: 0, bottom: 0, padding: '12px 16px 22px', background: 'rgba(0,0,0,.4)', borderTop: '1px solid rgba(240,208,138,.2)', transform: `translateY(${barY}px)`, display: 'flex', flexDirection: 'column', gap: 12, zIndex: 7 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
            <span style={{ flex: 1, fontSize: 14, fontWeight: 800 }}>{nSel >= 3 ? 'Siap dibuka!' : `Pilih ${3 - nSel} lagi`}</span>
            {slots.map((s, k) => (
              <div key={k} style={{ position: 'relative', width: 40, height: 40, borderRadius: 10, border: '1.5px dashed rgba(240,208,138,.45)', boxSizing: 'border-box' }}>
                {s > 0.01 && <div style={{ position: 'absolute', inset: -1.5, borderRadius: 10, background: GOLD, color: '#2B1608', display: 'grid', placeItems: 'center', fontSize: 12, fontWeight: 800, transform: `scale(${s})` }}>{String(PICKS[k] + 1).padStart(2, '0')}</div>}
              </div>
            ))}
          </div>
          <div style={{ position: 'relative', height: 58 }}>
            <div style={{ position: 'absolute', inset: 0, borderRadius: 16, background: 'rgba(255,255,255,.1)', boxShadow: '0 6px 0 rgba(0,0,0,.35)', display: 'grid', placeItems: 'center', fontSize: 18, fontWeight: 800, color: 'rgba(251,243,230,.5)' }}>Buka 3 kado</div>
            <div style={{ position: 'absolute', inset: 0, borderRadius: 16, background: GOLD, color: '#2B1608', boxShadow: `0 ${6 - btnPress}px 0 #8A6318, 0 14px 24px rgba(0,0,0,.45)`, display: 'grid', placeItems: 'center', fontSize: 18, fontWeight: 800, opacity: clamp(btnOn, 0, 1), transform: `translateY(${btnPress}px) scale(${0.9 + 0.1 * btnOn + (T > P + 2.85 && T < B ? Math.sin((T - P - 2.85) * 9) * 0.02 : 0)})` }}>Buka 3 kado</div>
          </div>
        </div>
        {fOp > 0.01 && <div style={{ position: 'absolute', left: fx - 22, top: fy - 22, width: 44, height: 44, borderRadius: '50%', background: 'rgba(255,255,255,.35)', border: '2px solid rgba(255,255,255,.9)', opacity: fOp, transform: `scale(${1 - 0.25 * press})`, zIndex: 20, boxShadow: `0 0 0 ${press * 14}px rgba(255,255,255,${0.25 * press})` }} />}
        <div style={{ position: 'absolute', inset: 0, background: 'radial-gradient(circle at 50% 45%,#FFF6DA,#F0D08A 60%,rgba(240,208,138,0))', opacity: flash, zIndex: 15 }} />
      </div>
    </div>
  );
}

function SpecPanel({ c }) {
  const { T, CUES } = useComposition();
  let idx = 0;
  SPEC.forEach((s, i) => { if (T >= CUES[s.key]) idx = i; });
  const s = SPEC[idx];
  const st = CUES[s.key];
  const next = idx < SPEC.length - 1 ? CUES[SPEC[idx + 1].key] : st + 3.2;
  const prog = clamp((T - st) / (next - st), 0, 1);
  const inA = MOTION.enter(0, 1, st, st + 0.35)(T);
  let sfxHit = 0, hapHit = 0;
  EVENTS.forEach(([k, off, kind]) => {
    const t0 = CUES[k] + off;
    if (T >= t0 && T < t0 + 0.35) { const v = 1 - (T - t0) / 0.35; if (kind.includes('s')) sfxHit = Math.max(sfxHit, v); if (kind.includes('h')) hapHit = Math.max(hapHit, v); }
  });
  const fmt = v => v.toFixed(1).replace('.', ',') + ' s';
  return (
    <div style={{ position: 'absolute', left: 820, top: 110, width: 960, fontFamily: FONT, color: '#FBF3E6' }}>
      <div style={{ display: 'flex', gap: 10 }}>
        {SPEC.map((x, i) => (
          <div key={i} style={{ flex: 1, display: 'flex', flexDirection: 'column', gap: 10 }}>
            <div style={{ height: 6, borderRadius: 3, background: 'rgba(255,255,255,.14)', overflow: 'hidden' }}><div style={{ height: '100%', width: `${i < idx ? 100 : i === idx ? prog * 100 : 0}%`, background: '#F0D08A' }} /></div>
            <div style={{ fontSize: 22, fontWeight: 700, color: i === idx ? '#FBF3E6' : 'rgba(251,243,230,.45)' }}>{i + 1} · {x.title}</div>
          </div>
        ))}
      </div>
      <div style={{ marginTop: 70, opacity: inA, transform: `translateY(${(1 - inA) * 16}px)` }}>
        <div style={{ fontSize: 26, fontWeight: 800, color: '#F0D08A', letterSpacing: '.06em' }}>MOMEN {idx + 1} · {fmt(next - st)}</div>
        <div style={{ fontSize: 76, fontWeight: 800, lineHeight: 1.05, marginTop: 10 }}>{s.title}</div>
        <div style={{ marginTop: 44, display: 'flex', flexDirection: 'column' }}>
          {s.rows.map((r, i) => (
            <div key={i} style={{ display: 'grid', gridTemplateColumns: '300px minmax(0,1fr)', gap: 24, padding: '18px 0', borderTop: '1px solid rgba(251,243,230,.14)', fontSize: 28 }}>
              <span style={{ fontWeight: 800 }}>{r[0]}</span><span style={{ color: '#D9C9B6' }}>{r[1]}</span>
            </div>
          ))}
        </div>
        <div style={{ marginTop: 36, display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 20 }}>
          <div style={{ borderRadius: 20, background: 'rgba(255,255,255,.06)', border: `2px solid rgba(240,208,138,${0.2 + 0.8 * sfxHit})`, padding: 24, display: 'flex', gap: 20, alignItems: 'center' }}>
            <div style={{ display: 'flex', gap: 5, alignItems: 'center', height: 52, width: 60, flex: 'none' }}>
              {[0.5, 0.9, 0.6, 1, 0.7].map((h, i) => <span key={i} style={{ width: 7, borderRadius: 4, background: '#F0D08A', height: `${(0.25 + 0.75 * h * sfxHit) * 100}%` }} />)}
            </div>
            <div><div style={{ fontSize: 20, fontWeight: 800, color: '#F0D08A', letterSpacing: '.06em' }}>SUARA</div><div style={{ fontSize: 24, marginTop: 4, lineHeight: 1.35 }}>{s.sfx}</div></div>
          </div>
          <div style={{ borderRadius: 20, background: 'rgba(255,255,255,.06)', border: `2px solid rgba(240,208,138,${0.2 + 0.8 * hapHit})`, padding: 24, display: 'flex', gap: 20, alignItems: 'center' }}>
            <div style={{ width: 60, height: 52, flex: 'none', display: 'grid', placeItems: 'center' }}>
              <span style={{ width: 28, height: 48, borderRadius: 7, border: '4px solid #F0D08A', boxSizing: 'border-box', transform: `translateX(${Math.sin(T * 120) * 4 * hapHit}px)` }} />
            </div>
            <div><div style={{ fontSize: 20, fontWeight: 800, color: '#F0D08A', letterSpacing: '.06em' }}>GETAR</div><div style={{ fontSize: 24, marginTop: 4, lineHeight: 1.35 }}>{s.hap}</div></div>
          </div>
        </div>
      </div>
    </div>
  );
}

function Piece({ brand }) {
  const { T } = useComposition();
  const c = BRANDS[brand] || BRANDS.Amura;
  const lbl = React.useRef(null);
  const sec = Math.floor(T);
  return (
    <div ref={lbl} data-screen-label={`Gatcha animasi ${sec}s`} style={{ position: 'absolute', inset: 0, background: '#15110F', fontFamily: FONT }}>
      <div style={{ position: 'absolute', left: 150, top: 40, width: 390 * 1.18, height: 844 * 1.18 }}>
        <div style={{ position: 'absolute', left: -10, top: -10, right: -10, bottom: -10, borderRadius: 56, background: '#000', boxShadow: '0 30px 80px rgba(0,0,0,.6)' }} />
        <div style={{ position: 'absolute', left: 0, top: 0, width: 390, height: 844, transform: 'scale(1.18)', transformOrigin: '0 0', borderRadius: 44, overflow: 'hidden' }}>
          <Screen c={c} />
        </div>
      </div>
      <SpecPanel c={c} />
      <div style={{ position: 'absolute', left: 820, bottom: 50, fontSize: 22, color: 'rgba(251,243,230,.5)' }}>Gatcha {c.name} · spesifikasi animasi v1</div>
    </div>
  );
}

function GatchaApp(props) {
  return (
    <CompositionStage width={1920} height={1080} scenes={window.OM_SCENES} playback={window.OM_PLAYBACK} bg="#15110F">
      <Piece brand={props.brand || 'Amura'} />
    </CompositionStage>
  );
}

window.GatchaApp = GatchaApp;
