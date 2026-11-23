{{--
  The share studio of a partner portal (Food & Shops, bus agencies): the WhatsApp Status picture,
  their own designed picture, the poster to print, and their look (colour, layout, own picture).
  Needs: $card (what the pictures show, with 'look'), $look (ShareLooks::present(..., true)),
  $canEdit, $urls [look, design, remove], $url (the public link), $shareText, $pickItems (for "One item, big").
--}}
@php
    $layoutNames = [
        'photos' => translate('Four items'),
        'list' => translate('Price list'),
        'one' => translate('One item, big'),
        'brand' => translate('Logo and a few words'),
        'routes' => translate('Destinations and fares'),
        'times' => translate('Departure times'),
    ];
    $editor = $canEdit && ($look['options']['on'] ?? false);
@endphp
<style>
    .sl-card { background: #fff; border: 1px solid #E8ECEB; border-radius: 6px; }
    .sl-sw { width: 38px; height: 38px; border-radius: 6px; border: 2px solid transparent; box-shadow: inset 0 0 0 1px rgba(0,0,0,.12); cursor: pointer; padding: 0; position: relative; }
    .sl-sw[aria-pressed="true"] { outline: 3px solid #1D2D2B; outline-offset: 2px; }
    .sl-own { display: inline-flex; align-items: center; gap: 6px; height: 38px; border: 1px dashed #B9C2C1; border-radius: 6px; padding: 0 8px; font-size: 13px; font-weight: 600; cursor: pointer; }
    .sl-own input { width: 26px; height: 26px; border: 0; padding: 0; background: none; cursor: pointer; }
    .sl-lay { border: 1px solid #D6DCDB; background: #fff; border-radius: 6px; padding: 6px 12px; font-size: 13px; font-weight: 600; }
    .sl-lay[aria-pressed="true"] { background: #1D2D2B; color: #fff; border-color: #1D2D2B; }
    .sl-label { font-size: 13px; font-weight: 700; margin-bottom: 6px; }
    .sl-pick { display: flex; gap: 0; max-width: 380px; margin: 0 auto 8px; }
    .sl-pick button { flex: 1; border: 1px solid #D6DCDB; background: #fff; padding: 8px 6px; font-size: 13px; font-weight: 700; }
    .sl-pick button:first-child { border-radius: 6px 0 0 6px; }
    .sl-pick button:last-child { border-radius: 0 6px 6px 0; }
    .sl-pick button + button { border-left: 0; }
    .sl-pick button[aria-pressed="true"] { background: #1D2D2B; color: #fff; border-color: #1D2D2B; }
    .sl-thumb { width: 72px; height: 96px; object-fit: cover; border-radius: 6px; background: #F4F6F6; border: 1px solid #E8ECEB; }
</style>

<div class="row g-3">
    <div class="col-md-6">
        <div class="sl-pick" role="group" aria-label="{{ translate('Picture') }}">
            <button type="button" data-pick="status" aria-pressed="true">{{ translate('Status picture') }}</button>
            <button type="button" data-pick="design" aria-pressed="false" hidden>{{ translate('My picture') }}</button>
            <button type="button" data-pick="poster" aria-pressed="false">{{ translate('Poster to print') }}</button>
        </div>
        <div class="sl-card p-2 mb-3" style="max-width:380px;margin:0 auto">
            <canvas id="statusCard" width="1080" height="1920" style="width:100%;height:auto;display:block;border-radius:6px"
                    aria-label="{{ translate('Your WhatsApp Status picture') }}"></canvas>
            <canvas id="posterCard" width="1240" height="1754" style="width:100%;height:auto;display:none;border-radius:6px;background:#fff;border:1px solid #E8ECEB"
                    aria-label="{{ translate('Your poster to print') }}"></canvas>
            <p class="text-muted text-center mt-2 mb-0" style="font-size:12px" id="posterHint" hidden>{{ $card['poster_hint'] ?? translate('Print it and put it on your counter or your door: customers scan it.') }}</p>
        </div>
    </div>
    <div class="col-md-6">
        <div class="sl-card p-3 mb-3">
            <ol class="ps-3 mb-3" style="font-size:14px">
                <li>{{ translate('Tap "Share the picture" and choose WhatsApp, then My status.') }}</li>
                <li>{{ translate('Your link is copied: paste it in the caption so people can tap it.') }}</li>
            </ol>
            <button type="button" class="btn btn-primary w-100 mb-2" style="min-height:50px;border-radius:6px" id="sharePicture">
                <i class="bi bi-image"></i> <span>{{ translate('Share the picture') }}</span>
            </button>
            <a class="btn btn-outline-success w-100 mb-2" style="min-height:46px;display:flex;align-items:center;justify-content:center;gap:6px;border-radius:6px"
               href="https://wa.me/?text={{ rawurlencode($shareText) }}" target="_blank" rel="noopener">
                <i class="bi bi-whatsapp"></i> {{ translate('Send the link on WhatsApp') }}
            </a>
            <div class="input-group mb-2">
                <input type="text" class="form-control" value="{{ $url }}" readonly id="storeLink" aria-label="{{ translate('Your link') }}">
                <button class="btn btn-outline-secondary" type="button" id="copyLink">{{ translate('Copy') }}</button>
            </div>
            <a href="{{ $url }}" target="_blank" rel="noopener" class="d-block text-center" style="font-size:14px">{{ translate('See your page as customers see it') }}</a>
        </div>

        @if($editor)
            <div class="sl-card p-3 mb-3" id="lookEditor">
                <h2 class="fs-6 fw-bold mb-1">{{ translate('Your look') }}</h2>
                <p class="text-muted mb-3" style="font-size:13px">{{ translate('Your colour and layout are used on your pictures and poster. Text turns white or dark by itself so people can read it.') }}</p>
                @if(!empty($logoLink))
                    <p class="mb-3" style="font-size:13px"><i class="bi bi-image"></i> {{ translate('Your logo and cover photo are on your pictures too.') }} <a href="{{ $logoLink }}">{{ translate('Change them') }}</a></p>
                @endif
                @if($look['removed'])
                    <div class="alert alert-warning py-2" style="font-size:13px;border-radius:6px" id="removedNote">
                        {{ translate('E-Ride took your picture down:') }} <strong>{{ $look['removed']['reason'] }}</strong>. {{ translate('You can share another one.') }}
                    </div>
                @endif

                <div class="sl-label">{{ translate('Colour') }}</div>
                <div class="d-flex flex-wrap gap-2 align-items-center mb-3" id="swatches">
                    @foreach(array_values(array_unique(array_merge([\App\Lib\ShareLooks::RED], $look['options']['palette']))) as $c)
                        <button type="button" class="sl-sw" style="background:{{ $c }}" data-color="{{ $c }}" aria-label="{{ $c === \App\Lib\ShareLooks::RED ? translate('E-Ride red') : $c }}" aria-pressed="false"></button>
                    @endforeach
                    @if($look['options']['any_color'])
                        <label class="sl-own"><input type="color" id="ownColor" value="{{ $look['color'] }}" aria-label="{{ translate('Your own colour') }}"> {{ translate('Your colour') }}</label>
                    @endif
                </div>

                @if(count($look['options']['layouts']) > 1)
                    <div class="sl-label">{{ translate('Layout of the Status picture') }}</div>
                    <div class="d-flex flex-wrap gap-2 mb-2" id="layouts">
                        @foreach($look['options']['layouts'] as $l)
                            <button type="button" class="sl-lay" data-layout="{{ $l }}" aria-pressed="false">{{ $layoutNames[$l] ?? $l }}</button>
                        @endforeach
                    </div>
                @endif
                @if(in_array('one', $look['options']['layouts'], true) && count($pickItems))
                    <div id="bigPick" class="mb-2" hidden>
                        <label class="form-label mb-1" for="bigItem" style="font-size:13px">{{ translate('The item shown big') }}</label>
                        <select id="bigItem" class="form-select form-select-sm">
                            <option value="">{{ translate('Choose for me') }}</option>
                            @foreach($pickItems as $p)<option value="{{ $p['id'] }}">{{ $p['name'] }}</option>@endforeach
                        </select>
                    </div>
                @endif

                @if($look['options']['designs'])
                    <div class="sl-label mt-3">{{ translate('Your own picture') }}</div>
                    <p class="text-muted mb-2" style="font-size:13px">{{ translate('A flyer you made in Canva or a designer made for you. Tall like a Status, or square. E-Ride adds a strip with your QR code and link so people can open your page.') }}</p>
                    <div class="d-flex gap-3 align-items-center">
                        <img id="designThumb" class="sl-thumb" alt="{{ translate('Your picture') }}" hidden>
                        <div class="d-flex flex-column gap-2">
                            <label class="btn btn-outline-primary btn-sm mb-0" style="border-radius:6px">
                                <i class="bi bi-upload"></i> <span id="designPickText">{{ translate('Upload a picture') }}</span>
                                <input type="file" id="designFile" accept="image/jpeg,image/png,image/webp" hidden>
                            </label>
                            <button type="button" class="btn btn-outline-danger btn-sm" id="designRemove" style="border-radius:6px" hidden>{{ translate('Remove my picture') }}</button>
                        </div>
                    </div>
                    <div class="text-muted mt-1" style="font-size:12px">{{ translate(key: 'JPG, PNG or WEBP, up to {mb} MB.', replace: ['mb' => $look['options']['max_mb']]) }}</div>
                    @unless($look['options']['strip_required'])
                        <div class="form-check mt-2" id="stripRow" hidden>
                            <input class="form-check-input" type="checkbox" id="stripOn">
                            <label class="form-check-label" for="stripOn" style="font-size:13px">{{ translate('Add the E-Ride strip (QR code and link) under my picture') }}</label>
                            <div class="text-muted" style="font-size:12px">{{ translate('Without it, people cannot open your page from the picture, and visits are not counted.') }}</div>
                        </div>
                    @endunless
                @endif
                <div class="mt-2" style="font-size:12px;min-height:18px" id="lookSaved" aria-live="polite"></div>
            </div>
        @endif
    </div>
</div>

<script>
(function () {
    const data = @json($card);
    let look = data.look;
    const urls = @json($urls ?? null);
    const words = {
        saved: @json(translate('Saved')),
        saving: @json(translate('Saving…')),
        failed: @json(translate('Could not save. Try again.')),
        uploading: @json(translate('Sending your picture…')),
        replace: @json(translate('Replace my picture')),
        upload: @json(translate('Upload a picture')),
        removeAsk: @json(translate('Remove your picture? Your Status picture goes back to the one E-Ride draws.')),
        linkCopied: @json(translate('Link copied')),
        savedPicture: @json(translate('Picture saved. Post it on your WhatsApp Status from your gallery.')),
        sharePicture: @json(translate('Share the picture')),
        savePoster: @json(translate('Save or share the poster')),
    };
    const DARK = '#1D2D2B';
    const status = document.getElementById('statusCard');
    const ctx = status.getContext('2d');
    const poster = document.getElementById('posterCard');
    const pc = poster.getContext('2d');
    const W = 1080, P = 64;
    let mode = look.design ? 'design' : 'status';
    let tainted = false;

    const images = {};
    function load(src) {
        if (!src) return Promise.resolve(null);
        if (!images[src]) {
            images[src] = new Promise(resolve => {
                const img = new Image();
                img.crossOrigin = 'anonymous';
                img.onload = () => resolve(img);
                img.onerror = () => resolve(null);
                img.src = src;
            });
        }
        return images[src];
    }
    /** The colour on white (prices, "scan"): their colour, or dark when it is too light to read. */
    const accent = () => look.ink === '#FFFFFF' ? look.color : DARK;
    const soft = () => look.ink === '#FFFFFF' ? 'rgba(255,255,255,0.92)' : 'rgba(29,45,43,0.85)';
    const veil = () => look.ink === '#FFFFFF' ? 'rgba(255,255,255,0.18)' : 'rgba(29,45,43,0.10)';

    function rr(c, x, y, w, h, r) {
        c.beginPath();
        c.moveTo(x + r, y); c.arcTo(x + w, y, x + w, y + h, r); c.arcTo(x + w, y + h, x, y + h, r);
        c.arcTo(x, y + h, x, y, r); c.arcTo(x, y, x + w, y, r); c.closePath();
    }
    function cover(img, x, y, w, h, r) {
        ctx.save(); rr(ctx, x, y, w, h, r); ctx.clip();
        if (img) {
            const s = Math.max(w / img.width, h / img.height);
            ctx.drawImage(img, x + (w - img.width * s) / 2, y + (h - img.height * s) / 2, img.width * s, img.height * s);
        } else {
            ctx.fillStyle = veil(); ctx.fillRect(x, y, w, h);
        }
        ctx.restore();
    }
    function contain(img, x, y, w, h) {
        const s = Math.min(w / img.width, h / img.height);
        ctx.drawImage(img, x + (w - img.width * s) / 2, y + (h - img.height * s) / 2, img.width * s, img.height * s);
    }
    function font(c, size, weight) { c.font = weight + ' ' + size + 'px system-ui, -apple-system, Roboto, sans-serif'; }
    function text(t, x, y, size, weight, color, maxW, c) {
        c = c || ctx;
        font(c, size, weight);
        c.fillStyle = color;
        let s = String(t || '');
        if (maxW) { while (s.length > 1 && c.measureText(s).width > maxW) s = s.slice(0, -2) + '…'; }
        c.fillText(s, x, y);
    }
    /** The words of t in at most max lines of width w; the last one ends with … when cut. */
    function lines(t, size, weight, w, max, c) {
        c = c || ctx;
        font(c, size, weight);
        const out = [''];
        let full = false;
        String(t || '').split(/\s+/).filter(Boolean).forEach(word => {
            if (full) return;
            const tryLine = (out[out.length - 1] + ' ' + word).trim();
            if (c.measureText(tryLine).width <= w || out[out.length - 1] === '') { out[out.length - 1] = tryLine; return; }
            if (out.length >= max) { full = true; out[out.length - 1] += '…'; return; }
            out.push(word);
        });
        return out.filter(l => l !== '');
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
    /** Where to tap, and the QR code to scan. */
    function tapBox(by, bh) {
        ctx.fillStyle = '#fff'; rr(ctx, P, by, W - 2 * P, bh, 12); ctx.fill();
        const q = bh - 40;
        qr(ctx, W - P - 20 - q, by + 20, q);
        const tw = W - 2 * P - q - 90;
        text(data.cta, P + 36, by + bh / 2 - 30, fit(data.cta, 44, '800', tw, 30), '800', DARK, tw);
        text(data.scan_short, P + 36, by + bh / 2 + 25, 32, '600', accent(), tw);
        text(data.link, P + 36, by + bh / 2 + 78, 26, '500', '#48615E', tw);
    }
    /** The largest size (down to min) at which t fits in w. */
    function fit(t, size, weight, w, min, c) {
        c = c || ctx;
        for (; size > min; size -= 2) { font(c, size, weight); if (c.measureText(String(t || '')).width <= w) break; }
        return size;
    }
    /** Rows of name and price (or city and fare, time and class) on a white card. */
    function rowsCard(rows, y, maxH) {
        // Rows get closer (down to 84) so more of them fit.
        const rowH = Math.max(84, Math.min(112, Math.floor((maxH - 40) / Math.max(1, rows.length))));
        const n = Math.max(1, Math.min(rows.length, Math.floor((maxH - 40) / rowH))), h = n * rowH + 40;
        const base = rowH >= 100 ? 40 : 36;
        const inner = W - 2 * P - 72;
        ctx.fillStyle = '#fff'; rr(ctx, P, y, W - 2 * P, h, 12); ctx.fill();
        rows.slice(0, n).forEach((it, k) => {
            const ry = y + 20 + k * rowH;
            if (k) { ctx.fillStyle = '#EEF0F0'; ctx.fillRect(P + 36, ry, inner, 2); }
            const rs = fit(it.r, base, '800', inner * 0.55, 28);
            font(ctx, rs, '800');
            const rw = ctx.measureText(String(it.r || '')).width;
            text(it.l, P + 36, ry + rowH / 2 + 14, base, '600', DARK, inner - rw - 30);
            ctx.textAlign = 'right';
            text(it.r, W - P - 36, ry + rowH / 2 + 14, rs, '800', accent(), inner * 0.55);
            ctx.textAlign = 'left';
        });
    }

    let drawToken = 0, posterToken = 0;
    async function drawDesign() {
        const token = ++drawToken;
        const img = await load(look.design);
        if (token !== drawToken) return;
        if (!img) { return drawStatus(true); }
        const h = Math.round(W * img.height / img.width), strip = look.strip ? 300 : 0;
        status.width = W; status.height = h + strip;
        ctx.drawImage(img, 0, 0, W, h);
        if (strip) {
            ctx.fillStyle = look.color; ctx.fillRect(0, h, W, strip);
            tapBox(h + 25, 250);
        }
        try { status.toDataURL('image/png'); tainted = false; } catch (e) { tainted = true; }
    }

    async function drawStatus(forceDrawn) {
        if (!forceDrawn && mode === 'design' && look.design) return drawDesign();
        const token = ++drawToken;
        const layout = look.layout;
        // Every picture first, then the drawing in one go (a newer drawing wins).
        const [coverImg, logoImg, bigImg, itemImgs] = await Promise.all([
            load(data.cover), load(data.logo),
            layout === 'one' && data.big ? load(data.big.image) : null,
            layout === 'photos' ? Promise.all((data.items || []).slice(0, 4).map(i => load(i.image))) : [],
        ]);
        if (token !== drawToken) return;
        const H = 1920;
        status.width = W; status.height = H;
        ctx.fillStyle = look.color; ctx.fillRect(0, 0, W, H);
        text('E-RIDE', P, P + 40, 44, '900', look.ink);
        font(ctx, 44, '900');
        text(data.kind, P + ctx.measureText('E-RIDE ').width + 6, P + 40, 44, '500', soft());
        const brandOnly = layout === 'brand';
        const heroH = brandOnly ? 820 : 520;
        // The cover with the logo on it, or the logo whole on white.
        if (coverImg) {
            cover(coverImg, P, 150, W - 2 * P, heroH, 12);
            if (logoImg) {
                ctx.fillStyle = '#fff'; rr(ctx, P + 30, 150 + heroH - 110, 170, 170, 12); ctx.fill();
                cover(logoImg, P + 38, 150 + heroH - 102, 154, 154, 8);
            }
        } else {
            ctx.fillStyle = '#fff'; rr(ctx, P, 150, W - 2 * P, heroH, 12); ctx.fill();
            if (logoImg) {
                ctx.save(); rr(ctx, P, 150, W - 2 * P, heroH, 12); ctx.clip();
                contain(logoImg, P + 80, 190, W - 2 * P - 160, heroH - 80);
                ctx.restore();
            } else {
                ctx.textAlign = 'center';
                text(String(data.name).charAt(0).toUpperCase(), W / 2, 150 + heroH / 2 + 70, 200, '900', accent());
                ctx.textAlign = 'left';
            }
        }
        // The name on up to two lines, then the line under it.
        const nameLines = lines(data.name, 68, '800', W - 2 * P, 2);
        let y = 150 + heroH + (coverImg && logoImg ? 140 : 100);
        nameLines.forEach((l, k) => text(l, P, y + k * 74, 68, '800', look.ink, W - 2 * P));
        y += (nameLines.length - 1) * 74 + 60;
        text(data.line, P, y, 36, '500', soft(), W - 2 * P);
        y += 50;
        const bh = 250, by = H - bh - 50, room = by - 30 - y;

        if (brandOnly) {
            // A few words about them.
            const about = lines(data.about || '', 40, '500', W - 2 * P, Math.max(1, Math.floor(room / 56)));
            about.forEach((l, k) => text(l, P, y + 40 + k * 56, 40, '500', look.ink, W - 2 * P));
        } else if (layout === 'one' && data.big) {
            const img = bigImg;
            ctx.fillStyle = '#fff'; rr(ctx, P, y, W - 2 * P, room, 12); ctx.fill();
            if (img) {
                const ih = room - 200;
                cover(img, P + 16, y + 16, W - 2 * P - 32, ih, 8);
                text(data.big.name, P + 36, y + ih + 90, 52, '800', DARK, W - 2 * P - 72);
                text(data.big.price, P + 36, y + ih + 160, 56, '900', accent(), W - 2 * P - 72);
            } else {
                // No photo: the name, the price and a few words, big, in the middle.
                const nl = lines(data.big.name, 76, '800', W - 2 * P - 120, 3);
                const ab = data.big.about ? lines(data.big.about, 38, '500', W - 2 * P - 120, 3) : [];
                const blockH = nl.length * 86 + 110 + ab.length * 52;
                let ty = y + Math.max(90, (room - blockH) / 2 + 60);
                ctx.textAlign = 'center';
                nl.forEach(l => { text(l, W / 2, ty, 76, '800', DARK); ty += 86; });
                text(data.big.price, W / 2, ty + 30, 84, '900', accent()); ty += 110;
                ab.forEach(l => { text(l, W / 2, ty + 10, 38, '500', '#48615E'); ty += 52; });
                ctx.textAlign = 'left';
            }
        } else if (layout === 'photos' && (data.items || []).some(i => i.image)) {
            const items = data.items.slice(0, 4).map((i, k) => ({...i, img: itemImgs[k]}));
            const gw = (W - 2 * P - 28) / 2, gh = Math.min(400, (room - 28) / 2);
            items.forEach((it, k) => {
                const x = P + (k % 2) * (gw + 28), iy = y + Math.floor(k / 2) * (gh + 28);
                ctx.fillStyle = '#fff'; rr(ctx, x, iy, gw, gh, 12); ctx.fill();
                if (it.img) cover(it.img, x + 14, iy + 14, gw - 28, gh - 130, 8);
                else { ctx.fillStyle = '#F4F6F6'; rr(ctx, x + 14, iy + 14, gw - 28, gh - 130, 8); ctx.fill(); }
                text(it.name, x + 22, iy + gh - 72, 32, '600', DARK, gw - 44);
                text(it.price, x + 22, iy + gh - 26, 38, '800', accent(), gw - 44);
            });
        } else {
            const rows = (data.rows && data.rows[layout]) || (data.rows && data.rows.list) || [];
            if (rows.length) rowsCard(rows, y, room);
        }
        tapBox(by, bh);
        try { status.toDataURL('image/png'); tainted = false; } catch (e) { tainted = true; }
    }

    // The poster to print: white, their colour on top, the name, a big QR code.
    async function drawPoster() {
        const PW = 1240, PH = 1754;
        const token = ++posterToken;
        const logo = await load(data.logo);
        if (token !== posterToken) return;
        pc.clearRect(0, 0, PW, PH);
        pc.fillStyle = '#fff'; pc.fillRect(0, 0, PW, PH);
        pc.fillStyle = look.color; pc.fillRect(0, 0, PW, 150);
        text('E-RIDE', 80, 100, 64, '900', look.ink, null, pc);
        font(pc, 64, '900');
        text(data.kind, 80 + pc.measureText('E-RIDE ').width + 8, 100, 64, '500', look.ink, null, pc);
        let y = 260;
        if (logo) {
            const s = Math.min(180 / logo.width, 180 / logo.height);
            pc.drawImage(logo, (PW - logo.width * s) / 2, y, logo.width * s, logo.height * s);
            y += 220;
        }
        pc.textAlign = 'center';
        const pl = lines(data.name, 80, '800', PW - 160, 2, pc);
        pl.forEach((l, k) => text(l, PW / 2, y + 40 + k * 88, 80, '800', DARK, null, pc));
        y += (pl.length - 1) * 88;
        text(data.poster_line || '', PW / 2, y + 110, 40, '500', '#6B7675', PW - 160, pc);
        text(data.scan, PW / 2, y + 230, fit(data.scan, 64, '800', PW - 140, 44, pc), '800', accent(), PW - 120, pc);
        qr(pc, (PW - 720) / 2, y + 290, 720);
        text(data.link, PW / 2, y + 1070, 38, '600', DARK, PW - 160, pc);
        text(data.no_app, PW / 2, y + 1125, 34, '500', '#6B7675', PW - 160, pc);
        pc.textAlign = 'left';
    }

    function redraw() { drawStatus(); drawPoster(); }

    // Which picture is shown and shared.
    const picks = document.querySelectorAll('[data-pick]');
    function showPick() {
        const designBtn = document.querySelector('[data-pick="design"]');
        designBtn.hidden = !look.design;
        if (mode === 'design' && !look.design) mode = 'status';
        picks.forEach(b => b.setAttribute('aria-pressed', b.dataset.pick === mode ? 'true' : 'false'));
        const posterOn = mode === 'poster';
        status.style.display = posterOn ? 'none' : 'block';
        poster.style.display = posterOn ? 'block' : 'none';
        document.getElementById('posterHint').hidden = !posterOn;
        document.querySelector('#sharePicture span').textContent = posterOn ? words.savePoster : words.sharePicture;
    }
    picks.forEach(b => b.addEventListener('click', () => { mode = b.dataset.pick; showPick(); if (mode !== 'poster') drawStatus(); }));

    // ---- The look editor ----
    const editor = document.getElementById('lookEditor');
    const said = document.getElementById('lookSaved');
    function say(t, bad) { if (said) { said.textContent = t || ''; said.style.color = bad ? '#C0262D' : '#12A150'; } }
    function markEditor() {
        if (!editor) return;
        let matched = false;
        editor.querySelectorAll('[data-color]').forEach(b => {
            const on = b.dataset.color.toUpperCase() === look.color.toUpperCase();
            matched = matched || on;
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        const own = document.getElementById('ownColor');
        if (own) { own.value = look.color.toLowerCase(); own.parentElement.style.borderStyle = matched ? 'dashed' : 'solid'; own.parentElement.style.borderColor = matched ? '#B9C2C1' : DARK; }
        editor.querySelectorAll('[data-layout]').forEach(b => b.setAttribute('aria-pressed', b.dataset.layout === look.layout ? 'true' : 'false'));
        const bigPick = document.getElementById('bigPick');
        if (bigPick) { bigPick.hidden = look.layout !== 'one'; document.getElementById('bigItem').value = look.item || ''; }
        const thumb = document.getElementById('designThumb');
        if (thumb) {
            thumb.hidden = !look.design; if (look.design) thumb.src = look.design;
            document.getElementById('designRemove').hidden = !look.design;
            document.getElementById('designPickText').textContent = look.design ? words.replace : words.upload;
        }
        const stripRow = document.getElementById('stripRow');
        if (stripRow) { stripRow.hidden = !look.design; document.getElementById('stripOn').checked = !!look.strip; }
    }
    function adopt(json) {
        const next = Object.assign({}, json.look || {});
        delete next.options; delete next.removed;
        look = Object.assign({}, look, next);
        data.look = look;
        if (data.items_by_id) data.big = data.items_by_id[look.item] || data.big_default;
    }
    async function send(url, body, isForm) {
        const headers = {'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json'};
        if (!isForm) headers['Content-Type'] = 'application/json';
        const res = await fetch(url, {method: 'POST', headers, body: isForm ? body : JSON.stringify(body || {})});
        const json = await res.json().catch(() => ({}));
        if (!res.ok) {
            const first = json.errors ? Object.values(json.errors)[0] : null;
            throw new Error((Array.isArray(first) ? first[0] : first) || json.message || words.failed);
        }
        return json;
    }
    let saveTimer = null;
    function saveLook(change) {
        Object.assign(look, change);
        if (change.color) look.ink = inkFor(change.color);
        if (change.item !== undefined && data.items_by_id) data.big = data.items_by_id[change.item] || data.big_default;
        markEditor(); redraw();
        clearTimeout(saveTimer);
        say(words.saving);
        saveTimer = setTimeout(async () => {
            try {
                const json = await send(urls.look, {color: look.color, layout: look.layout, item: look.item || null, strip: !!look.strip});
                adopt(json); markEditor(); redraw(); say(words.saved);
            } catch (e) { say(e.message, true); }
        }, 500);
    }
    /** White or dark text on a colour (the same rule as the server). */
    function inkFor(hex) {
        const lum = h => {
            const c = [1, 3, 5].map(i => parseInt(h.substr(i, 2), 16) / 255).map(v => v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4));
            return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
        };
        const l = lum(hex);
        return 1.05 / (l + 0.05) >= (l + 0.05) / (lum(DARK) + 0.05) ? '#FFFFFF' : DARK;
    }
    if (editor) {
        editor.querySelectorAll('[data-color]').forEach(b => b.addEventListener('click', () => saveLook({color: b.dataset.color.toUpperCase()})));
        const own = document.getElementById('ownColor');
        if (own) own.addEventListener('input', () => saveLook({color: own.value.toUpperCase()}));
        editor.querySelectorAll('[data-layout]').forEach(b => b.addEventListener('click', () => { if (mode === 'design') { mode = 'status'; showPick(); } saveLook({layout: b.dataset.layout}); }));
        const bigItem = document.getElementById('bigItem');
        if (bigItem) bigItem.addEventListener('change', () => saveLook({item: bigItem.value || null}));
        const stripOn = document.getElementById('stripOn');
        if (stripOn) stripOn.addEventListener('change', () => saveLook({strip: stripOn.checked}));
        const file = document.getElementById('designFile');
        if (file) file.addEventListener('change', async () => {
            if (!file.files.length) return;
            const form = new FormData(); form.append('design', file.files[0]);
            say(words.uploading);
            try {
                const json = await send(urls.design, form, true);
                adopt(json); mode = 'design';
                const note = document.getElementById('removedNote'); if (note) note.remove();
                markEditor(); showPick(); redraw(); say(words.saved);
            } catch (e) { say(e.message, true); }
            file.value = '';
        });
        const remove = document.getElementById('designRemove');
        if (remove) remove.addEventListener('click', async () => {
            if (!confirm(words.removeAsk)) return;
            try {
                const json = await send(urls.remove, {});
                adopt(json); look.design = null; markEditor(); showPick(); redraw(); say(words.saved);
            } catch (e) { say(e.message, true); }
        });
    }

    // ---- Sharing ----
    async function copyLink() {
        const link = document.getElementById('storeLink').value;
        try { await navigator.clipboard.writeText(link); } catch (e) {
            const input = document.getElementById('storeLink'); input.select(); document.execCommand('copy');
        }
    }
    document.getElementById('copyLink').addEventListener('click', async () => {
        await copyLink();
        if (window.toastr) toastr.success(words.linkCopied); else say(words.linkCopied);
    });
    document.getElementById('sharePicture').addEventListener('click', async () => {
        await copyLink();
        if (tainted && mode !== 'poster') { window.open(data.page, '_blank'); return; }
        const active = mode === 'poster' ? poster : status;
        active.toBlob(async blob => {
            const file = new File([blob], (mode === 'poster' ? 'poster-' : '') + data.file, {type: 'image/png'});
            if (navigator.canShare && navigator.canShare({files: [file]})) {
                try { await navigator.share({files: [file], text: data.share_text}); } catch (e) {}
            } else {
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob); a.download = file.name; a.click();
                if (window.toastr) toastr.info(words.savedPicture); else say(words.savedPicture);
            }
        }, 'image/png');
    });

    markEditor();
    showPick();
    redraw();
})();
</script>
