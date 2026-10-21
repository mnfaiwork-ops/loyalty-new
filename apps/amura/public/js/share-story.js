// Gambar share story 1080x1920 untuk hadiah Kejutan Kado (permintaan Fai 2026-10-21).
// Digambar di HP pelanggan dengan canvas, lalu dibagikan lewat menu share bawaan HP
// (Instagram Story, WhatsApp Status, dll). Tidak memuat nama, nomor HP, atau kode klaim.
// Kalau HP tidak bisa share file, sheet cadangan menawarkan simpan gambar dan WhatsApp.
(() => {
    const W = 1080;
    const H = 1920;
    const css = name => getComputedStyle(document.documentElement).getPropertyValue(name).trim();

    function loadImage(src) {
        return new Promise(resolve => {
            if (!src) return resolve(null);
            const img = new Image();
            img.onload = () => resolve(img);
            img.onerror = () => resolve(null);
            img.src = src;
        });
    }

    function roundRect(ctx, x, y, w, h, r) {
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.arcTo(x + w, y, x + w, y + h, r);
        ctx.arcTo(x + w, y + h, x, y + h, r);
        ctx.arcTo(x, y + h, x, y, r);
        ctx.arcTo(x, y, x + w, y, r);
        ctx.closePath();
    }

    // Pecah teks jadi baris yang muat di lebar maxWidth.
    function lines(ctx, text, maxWidth) {
        const out = [];
        let line = '';
        text.split(/\s+/).forEach(word => {
            const next = line ? `${line} ${word}` : word;
            if (line && ctx.measureText(next).width > maxWidth) {
                out.push(line);
                line = word;
            } else {
                line = next;
            }
        });
        if (line) out.push(line);
        return out;
    }

    // Kotak kado terbuka untuk hadiah tanpa foto (voucher, hadiah utama tanpa foto).
    function drawGift(ctx, cx, top, brand) {
        const gold = ctx.createLinearGradient(0, top, 0, top + 360);
        gold.addColorStop(0, '#F5DA9A');
        gold.addColorStop(1, '#C99A3A');
        ctx.fillStyle = gold;
        roundRect(ctx, cx - 170, top + 120, 340, 260, 28);
        ctx.fill();
        ctx.save();
        ctx.translate(cx - 30, top + 70);
        ctx.rotate(-0.18);
        roundRect(ctx, -170, 0, 380, 80, 22);
        ctx.fill();
        ctx.restore();
        ctx.fillStyle = brand;
        ctx.fillRect(cx - 28, top + 120, 56, 260);
        ctx.beginPath();
        ctx.ellipse(cx - 60, top + 40, 70, 34, -0.5, 0, Math.PI * 2);
        ctx.ellipse(cx + 40, top + 22, 70, 34, 0.4, 0, Math.PI * 2);
        ctx.fill();
    }

    async function render(o) {
        await Promise.all([
            document.fonts?.load('400 72px "DM Serif Display"'),
            document.fonts?.load('800 40px "Plus Jakarta Sans"'),
            document.fonts?.load('500 40px "Plus Jakarta Sans"'),
        ].filter(Boolean)).catch(() => {});
        const [logo, photo] = await Promise.all([loadImage(o.logo), loadImage(o.image)]);

        const canvas = document.createElement('canvas');
        canvas.width = W;
        canvas.height = H;
        const ctx = canvas.getContext('2d');
        const brand = css('--p') || '#1E6B63';
        const goldLight = css('--gold-light') || '#F0D08A';
        const cream = css('--cream') || '#FBF3E6';
        const ink = css('--ink') || '#2B221D';

        const bg = ctx.createLinearGradient(0, 0, 0, H);
        bg.addColorStop(0, css('--st1') || '#11403A');
        bg.addColorStop(1, css('--st2') || '#061816');
        ctx.fillStyle = bg;
        ctx.fillRect(0, 0, W, H);

        // Sinar dan cahaya di belakang kartu, seperti panggung Gatcha.
        ctx.save();
        ctx.translate(W / 2, 1000);
        ctx.fillStyle = 'rgba(240, 208, 138, 0.06)';
        for (let i = 0; i < 18; i++) {
            ctx.rotate((Math.PI * 2) / 18);
            ctx.beginPath();
            ctx.moveTo(0, 0);
            ctx.lineTo(-70, -1300);
            ctx.lineTo(70, -1300);
            ctx.closePath();
            ctx.fill();
        }
        ctx.restore();
        const glow = ctx.createRadialGradient(W / 2, 1000, 40, W / 2, 1000, 640);
        glow.addColorStop(0, 'rgba(240, 208, 138, 0.28)');
        glow.addColorStop(1, 'rgba(240, 208, 138, 0)');
        ctx.fillStyle = glow;
        ctx.fillRect(0, 0, W, H);

        ctx.textAlign = 'center';
        ctx.textBaseline = 'alphabetic';

        if (logo) {
            const h = 96 * (o.logoScale || 1);
            const w = h * (logo.naturalWidth / logo.naturalHeight);
            ctx.drawImage(logo, (W - w) / 2, 290 - h / 2, w, h);
        }

        ctx.fillStyle = goldLight;
        ctx.font = '800 34px "Plus Jakarta Sans", system-ui, sans-serif';
        if ('letterSpacing' in ctx) ctx.letterSpacing = '6px';
        ctx.fillText(`KEJUTAN KADO ${o.brand.toUpperCase()}`, W / 2, 440);
        if ('letterSpacing' in ctx) ctx.letterSpacing = '0px';

        ctx.fillStyle = cream;
        ctx.font = '700 58px "Plus Jakarta Sans", system-ui, sans-serif';
        ctx.fillText('Aku dapat hadiah!', W / 2, 540);

        // Kartu hadiah.
        const card = { x: 150, y: 610, w: 780, h: 860 };
        ctx.save();
        ctx.shadowColor = 'rgba(0, 0, 0, 0.35)';
        ctx.shadowBlur = 60;
        ctx.shadowOffsetY = 24;
        ctx.fillStyle = '#FFFFFF';
        roundRect(ctx, card.x, card.y, card.w, card.h, 48);
        ctx.fill();
        ctx.restore();
        if (o.isMain) {
            ctx.strokeStyle = '#C99A3A';
            ctx.lineWidth = 8;
            roundRect(ctx, card.x + 4, card.y + 4, card.w - 8, card.h - 8, 46);
            ctx.stroke();
        }

        // Chip jenis hadiah.
        ctx.font = '800 28px "Plus Jakarta Sans", system-ui, sans-serif';
        const chip = o.tierLabel.toUpperCase();
        const chipW = ctx.measureText(chip).width + 56;
        ctx.fillStyle = o.isMain ? '#F5DA9A' : css('--t') || '#DCEDE9';
        roundRect(ctx, (W - chipW) / 2, card.y + 40, chipW, 56, 28);
        ctx.fill();
        ctx.fillStyle = o.isMain ? '#6B4A10' : brand;
        ctx.fillText(chip, W / 2, card.y + 78);

        const area = { x: card.x + 110, y: card.y + 130, w: card.w - 220, h: 470 };
        if (o.isPoin) {
            ctx.fillStyle = brand;
            ctx.font = '400 210px "DM Serif Display", Georgia, serif';
            ctx.fillText(`+${o.points}`, W / 2, area.y + 390);
            ctx.font = '700 64px "Plus Jakarta Sans", system-ui, sans-serif';
            ctx.fillText('poin', W / 2, area.y + 490);
        } else if (photo) {
            const scale = Math.min(area.w / photo.naturalWidth, area.h / photo.naturalHeight);
            const w = photo.naturalWidth * scale;
            const h = photo.naturalHeight * scale;
            ctx.drawImage(photo, area.x + (area.w - w) / 2, area.y + (area.h - h) / 2, w, h);
        } else {
            drawGift(ctx, W / 2, area.y + 40, brand);
        }

        // Nama hadiah, maksimal 3 baris; font mengecil untuk nama panjang.
        if (!o.isPoin) {
            let size = 66;
            let rows;
            do {
                ctx.font = `400 ${size}px "DM Serif Display", Georgia, serif`;
                rows = lines(ctx, o.prizeName, card.w - 120);
                size -= 4;
            } while (rows.length > 3 && size > 40);
            ctx.fillStyle = ink;
            const lh = size + 14;
            const top = card.y + card.h - 60 - lh * (rows.length - 1);
            rows.slice(0, 3).forEach((row, i) => ctx.fillText(row, W / 2, top + i * lh));
        }

        ctx.fillStyle = cream;
        ctx.font = '500 40px "Plus Jakarta Sans", system-ui, sans-serif';
        ctx.fillText(`Tiap paket ${o.brand} bawa 2 Kejutan Kado.`, W / 2, 1560);
        ctx.fillStyle = goldLight;
        ctx.font = '800 40px "Plus Jakarta Sans", system-ui, sans-serif';
        ctx.fillText(o.label, W / 2, 1630);

        return new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
    }

    function message(o) {
        const what = o.isPoin ? `+${o.points} poin` : o.prizeName;
        return `Aku dapat ${what} dari Kejutan Kado ${o.brand}! Cara mainnya: ${o.url}`;
    }

    // Sheet cadangan: pratinjau, simpan gambar, kirim ke WhatsApp.
    function openSheet(blob, o) {
        const sheet = document.getElementById('shareSheet');
        if (!sheet) return;
        const url = URL.createObjectURL(blob);
        sheet.querySelector('img').src = url;
        const save = sheet.querySelector('[data-share-save]');
        save.href = url;
        save.download = 'kejutan-kado.png';
        sheet.querySelector('[data-share-wa]').href = `https://wa.me/?text=${encodeURIComponent(message(o))}`;
        sheet.addEventListener('close', () => URL.revokeObjectURL(url), { once: true });
        sheet.showModal();
    }

    async function share(pending, o) {
        const blob = await pending;
        if (!blob) return;
        const file = new File([blob], 'kejutan-kado.png', { type: 'image/png' });
        if (navigator.canShare && navigator.canShare({ files: [file] })) {
            try {
                await navigator.share({ files: [file], text: message(o) });
                return;
            } catch (err) {
                if (err && err.name === 'AbortError') return;
            }
        }
        openSheet(blob, o);
    }

    window.KadoShare = { render, share };
})();
