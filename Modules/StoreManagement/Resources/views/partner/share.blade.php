@extends('storemanagement::partner.layout')

@section('title', translate('Share your store'))

@section('content')
    <h1 class="fs-4 fw-bold mb-1">{{ translate('Share your store') }}</h1>
    <p class="text-muted mb-3" style="font-size:14px">{{ translate('Post your store on your WhatsApp Status and in your groups. People see your menu and prices, and order in the E-Ride app.') }}</p>

    <div class="p-card p-3 mb-3">
        <div class="d-flex gap-3 text-center">
            <div class="flex-fill"><div class="fs-3 fw-bold">{{ $brand->link_visits }}</div><div class="text-muted" style="font-size:12px">{{ translate('Opened your page') }}</div></div>
            <div class="flex-fill"><div class="fs-3 fw-bold">{{ $brand->link_installs }}</div><div class="text-muted" style="font-size:12px">{{ translate('Joined E-Ride from your link') }}</div></div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="btn-group w-100 mb-2" role="group" aria-label="{{ translate('Picture') }}" style="max-width:380px;margin:0 auto;display:flex">
                <button type="button" class="btn btn-primary" data-pick="status">{{ translate('WhatsApp Status') }}</button>
                <button type="button" class="btn btn-outline-primary" data-pick="poster">{{ translate('Poster to print') }}</button>
            </div>
            <div class="p-card p-2 mb-3" style="max-width:380px;margin:0 auto">
                <canvas id="statusCard" width="1080" height="1920" style="width:100%;height:auto;display:block;border-radius:6px;background:#E02020"
                        aria-label="{{ translate('Your WhatsApp Status picture') }}"></canvas>
                <canvas id="posterCard" width="1240" height="1754" style="width:100%;height:auto;display:none;border-radius:6px;background:#fff;border:1px solid #E8ECEB"
                        aria-label="{{ translate('Your poster to print') }}"></canvas>
                <p class="text-muted text-center mt-2 mb-0" style="font-size:12px" id="posterHint" hidden>{{ translate('Print it and put it on your counter or your door: customers scan it to see your menu and order.') }}</p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="p-card p-3 mb-3">
                <ol class="ps-3 mb-3" style="font-size:14px">
                    <li>{{ translate('Tap "Share the picture" and choose WhatsApp, then My status.') }}</li>
                    <li>{{ translate('Your link is copied: paste it in the caption so people can tap it.') }}</li>
                </ol>
                <button type="button" class="btn btn-primary w-100 mb-2" style="min-height:50px" id="sharePicture">
                    <i class="bi bi-image"></i> {{ translate('Share the picture') }}
                </button>
                <a class="btn btn-outline-success w-100 mb-2" style="min-height:46px;display:flex;align-items:center;justify-content:center;gap:6px"
                   href="https://wa.me/?text={{ rawurlencode($shareText) }}" target="_blank" rel="noopener">
                    <i class="bi bi-whatsapp"></i> {{ translate('Send the link on WhatsApp') }}
                </a>
                <div class="input-group mb-2">
                    <input type="text" class="form-control" value="{{ $url }}" readonly id="storeLink" aria-label="{{ translate('Your store link') }}">
                    <button class="btn btn-outline-secondary" type="button" id="copyLink">{{ translate('Copy') }}</button>
                </div>
                <a href="{{ $url }}" target="_blank" rel="noopener" class="d-block text-center" style="font-size:14px">{{ translate('See your page as customers see it') }}</a>
            </div>
        </div>
    </div>
@endsection

@push('script')
<script>
(function () {
    const data = @json($card);
    const canvas = document.getElementById('statusCard');
    const ctx = canvas.getContext('2d');
    const W = 1080, H = 1920, P = 64;
    let tainted = false;

    function load(src) {
        return new Promise(resolve => {
            if (!src) return resolve(null);
            const img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = () => resolve(img);
            img.onerror = () => resolve(null);
            img.src = src;
        });
    }
    function rr(x, y, w, h, r) {
        ctx.beginPath();
        ctx.moveTo(x + r, y); ctx.arcTo(x + w, y, x + w, y + h, r); ctx.arcTo(x + w, y + h, x, y + h, r);
        ctx.arcTo(x, y + h, x, y, r); ctx.arcTo(x, y, x + w, y, r); ctx.closePath();
    }
    function cover(img, x, y, w, h, r) {
        ctx.save(); rr(x, y, w, h, r); ctx.clip();
        if (img) {
            const s = Math.max(w / img.width, h / img.height);
            const iw = img.width * s, ih = img.height * s;
            ctx.drawImage(img, x + (w - iw) / 2, y + (h - ih) / 2, iw, ih);
        } else {
            ctx.fillStyle = 'rgba(255,255,255,0.18)'; ctx.fillRect(x, y, w, h);
        }
        ctx.restore();
    }
    function text(t, x, y, size, weight, color, maxW) {
        ctx.font = weight + ' ' + size + 'px system-ui, -apple-system, Roboto, sans-serif';
        ctx.fillStyle = color;
        let s = String(t || '');
        if (maxW) { while (s.length > 1 && ctx.measureText(s).width > maxW) s = s.slice(0, -2) + '…'; }
        ctx.fillText(s, x, y);
    }

    async function draw() {
        ctx.fillStyle = '#E02020'; ctx.fillRect(0, 0, W, H);
        text('E-RIDE', P, P + 40, 44, '900', '#fff');
        text(data.kind, P + 190, P + 40, 44, '500', 'rgba(255,255,255,0.9)');
        // The store: cover, logo, name, open, ways.
        const [coverImg, logoImg] = await Promise.all([load(data.cover), load(data.logo)]);
        if (coverImg) {
            cover(coverImg, P, 150, W - 2 * P, 520, 12);
            if (logoImg) {
                ctx.fillStyle = '#fff'; rr(P + 30, 560, 170, 170, 12); ctx.fill();
                cover(logoImg, P + 38, 568, 154, 154, 8);
            }
        } else {
            // Only a logo: whole, on white, never cropped.
            ctx.fillStyle = '#fff'; rr(P, 150, W - 2 * P, 520, 12); ctx.fill();
            if (logoImg) {
                const s = Math.min(420 / logoImg.width, 420 / logoImg.height);
                ctx.drawImage(logoImg, (W - logoImg.width * s) / 2, 150 + (520 - logoImg.height * s) / 2, logoImg.width * s, logoImg.height * s);
            } else {
                text(data.name.charAt(0).toUpperCase(), W / 2 - 50, 470, 200, '900', '#E02020');
            }
        }
        // The name on up to two lines, then the hours and ways.
        ctx.font = '800 68px system-ui, -apple-system, Roboto, sans-serif';
        const words = String(data.name).split(' '), lines = [''];
        words.forEach(w => {
            const tryLine = (lines[lines.length - 1] + ' ' + w).trim();
            if (ctx.measureText(tryLine).width > W - 2 * P && lines[lines.length - 1] !== '' && lines.length < 2) lines.push(w);
            else lines[lines.length - 1] = tryLine;
        });
        const nameY = lines.length > 1 ? 770 : 800;
        lines.forEach((l, k) => text(l, P, nameY + k * 72, 68, '800', '#fff', W - 2 * P));
        text(data.line, P, nameY + (lines.length - 1) * 72 + 62, 36, '500', 'rgba(255,255,255,0.92)', W - 2 * P);
        // Four things with prices.
        const items = await Promise.all(data.items.slice(0, 4).map(async i => ({...i, img: await load(i.image)})));
        const gy = 920;
        if (items.some(i => i.img)) {
            // Photos: a 2 x 2 grid.
            const gw = (W - 2 * P - 28) / 2, gh = 400;
            items.forEach((it, k) => {
                const x = P + (k % 2) * (gw + 28), y = gy + Math.floor(k / 2) * (gh + 28);
                ctx.fillStyle = '#fff'; rr(x, y, gw, gh, 12); ctx.fill();
                if (it.img) { cover(it.img, x + 14, y + 14, gw - 28, gh - 130, 8); }
                else { ctx.fillStyle = '#F4F6F6'; rr(x + 14, y + 14, gw - 28, gh - 130, 8); ctx.fill(); }
                text(it.name, x + 22, y + gh - 72, 32, '600', '#1D2D2B', gw - 44);
                text(it.price, x + 22, y + gh - 26, 38, '800', '#E02020', gw - 44);
            });
        } else if (items.length) {
            // No photos: a clean price list.
            const rowH = 118, h = items.length * rowH + 40;
            ctx.fillStyle = '#fff'; rr(P, gy, W - 2 * P, h, 12); ctx.fill();
            items.forEach((it, k) => {
                const y = gy + 20 + k * rowH;
                if (k) { ctx.fillStyle = '#EEF0F0'; ctx.fillRect(P + 36, y, W - 2 * P - 72, 2); }
                text(it.name, P + 36, y + 72, 40, '600', '#1D2D2B', W - 2 * P - 380);
                ctx.textAlign = 'right';
                text(it.price, W - P - 36, y + 72, 44, '800', '#E02020');
                ctx.textAlign = 'left';
            });
        }
        // Where to tap, and the QR code to scan.
        const bh = 250, by = H - bh - 50;
        ctx.fillStyle = '#fff'; rr(P, by, W - 2 * P, bh, 12); ctx.fill();
        qr(ctx, W - P - 24 - 210, by + 20, 210);
        text(data.cta, P + 36, by + 90, 44, '800', '#1D2D2B', W - 2 * P - 330);
        text(data.scan_short, P + 36, by + 145, 32, '600', '#E02020', W - 2 * P - 330);
        text(data.link, P + 36, by + 200, 26, '500', '#48615E', W - 2 * P - 330);
        try { canvas.toDataURL('image/png'); } catch (e) { tainted = true; }
    }

    /** The link's QR code, dark squares on white, at (x, y), size s. */
    function qr(c, x, y, s) {
        const n = data.qr.length, m = s / (n + 2);
        c.fillStyle = '#fff'; c.fillRect(x, y, s, s);
        c.fillStyle = '#111';
        data.qr.forEach((row, r) => {
            for (let k = 0; k < row.length; k++) {
                if (row[k] === '1') c.fillRect(Math.floor(x + (k + 1) * m), Math.floor(y + (r + 1) * m), Math.ceil(m), Math.ceil(m));
            }
        });
    }

    // The poster to print: white, the name, a big QR code, how to order.
    const poster = document.getElementById('posterCard');
    const pc = poster.getContext('2d');
    async function drawPoster() {
        const PW = 1240, PH = 1754;
        pc.fillStyle = '#fff'; pc.fillRect(0, 0, PW, PH);
        pc.fillStyle = '#E02020'; pc.fillRect(0, 0, PW, 150);
        pc.font = '900 64px system-ui, -apple-system, Roboto, sans-serif'; pc.fillStyle = '#fff'; pc.fillText('E-RIDE', 80, 100);
        pc.font = '500 64px system-ui, -apple-system, Roboto, sans-serif'; pc.fillText(data.kind, 330, 100);
        const logo = await load(data.logo);
        let y = 260;
        if (logo) {
            const s = Math.min(180 / logo.width, 180 / logo.height);
            pc.drawImage(logo, (PW - logo.width * s) / 2, y, logo.width * s, logo.height * s);
            y += 220;
        }
        pc.textAlign = 'center';
        pc.fillStyle = '#1D2D2B'; pc.font = '800 84px system-ui, -apple-system, Roboto, sans-serif';
        let name = String(data.name);
        while (name.length > 1 && pc.measureText(name).width > PW - 160) name = name.slice(0, -2) + '…';
        pc.fillText(name, PW / 2, y + 40);
        pc.fillStyle = '#6B7675'; pc.font = '500 40px system-ui, -apple-system, Roboto, sans-serif';
        pc.fillText(data.type + '  ·  ' + data.ways.join(', '), PW / 2, y + 110);
        pc.fillStyle = '#E02020'; pc.font = '800 64px system-ui, -apple-system, Roboto, sans-serif';
        pc.fillText(data.scan, PW / 2, y + 230);
        qr(pc, (PW - 720) / 2, y + 290, 720);
        pc.fillStyle = '#1D2D2B'; pc.font = '600 38px system-ui, -apple-system, Roboto, sans-serif';
        pc.fillText(data.link, PW / 2, y + 1070);
        pc.fillStyle = '#6B7675'; pc.font = '500 34px system-ui, -apple-system, Roboto, sans-serif';
        pc.fillText(data.no_app, PW / 2, y + 1125);
        pc.textAlign = 'left';
    }

    let active = canvas;
    document.querySelectorAll('[data-pick]').forEach(b => b.addEventListener('click', () => {
        const posterOn = b.dataset.pick === 'poster';
        active = posterOn ? poster : canvas;
        canvas.style.display = posterOn ? 'none' : 'block';
        poster.style.display = posterOn ? 'block' : 'none';
        document.getElementById('posterHint').hidden = !posterOn;
        document.querySelectorAll('[data-pick]').forEach(x => x.className = 'btn ' + (x === b ? 'btn-primary' : 'btn-outline-primary'));
        document.getElementById('sharePicture').lastChild.textContent = ' ' + (posterOn ? @json(translate('Save or share the poster')) : @json(translate('Share the picture')));
    }));

    async function copyLink() {
        const link = document.getElementById('storeLink').value;
        try { await navigator.clipboard.writeText(link); } catch (e) {
            const input = document.getElementById('storeLink'); input.select(); document.execCommand('copy');
        }
    }

    document.getElementById('copyLink').addEventListener('click', async () => {
        await copyLink();
        if (window.toastr) toastr.success(@json(translate('Link copied')));
    });

    document.getElementById('sharePicture').addEventListener('click', async () => {
        await copyLink();
        if (tainted) { window.open(data.page, '_blank'); return; }
        active.toBlob(async blob => {
            const file = new File([blob], (active === poster ? 'poster-' : '') + data.file, {type: 'image/png'});
            if (navigator.canShare && navigator.canShare({files: [file]})) {
                try { await navigator.share({files: [file], text: data.share_text}); } catch (e) {}
            } else {
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob); a.download = data.file; a.click();
                if (window.toastr) toastr.info(@json(translate('Picture saved. Post it on your WhatsApp Status from your gallery.')));
            }
        }, 'image/png');
    });

    draw();
    drawPoster();
})();
</script>
@endpush
