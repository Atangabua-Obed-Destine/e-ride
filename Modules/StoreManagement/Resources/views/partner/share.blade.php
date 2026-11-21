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
            <div class="p-card p-2 mb-3" style="max-width:380px;margin:0 auto">
                <canvas id="statusCard" width="1080" height="1920" style="width:100%;height:auto;display:block;border-radius:6px;background:#E02020"
                        aria-label="{{ translate('Your WhatsApp Status picture') }}"></canvas>
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
        text(data.name, P, 800, 72, '800', '#fff', W - 2 * P);
        text(data.line, P, 868, 36, '500', 'rgba(255,255,255,0.92)', W - 2 * P);
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
        // Where to tap.
        const by = H - 230;
        ctx.fillStyle = '#fff'; rr(P, by, W - 2 * P, 160, 12); ctx.fill();
        text(data.cta, P + 36, by + 70, 42, '800', '#1D2D2B', W - 2 * P - 72);
        text(data.link, P + 36, by + 122, 32, '500', '#48615E', W - 2 * P - 72);
        try { canvas.toDataURL('image/png'); } catch (e) { tainted = true; }
    }

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
        canvas.toBlob(async blob => {
            const file = new File([blob], data.file, {type: 'image/png'});
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
})();
</script>
@endpush
