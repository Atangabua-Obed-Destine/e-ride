{{--
    "Book on WhatsApp", automated: a short booking sheet that turns the
    visitor's choices into one clear WhatsApp message to the business.

    Opened by any element with [data-er-book]; those keep a plain wa.me href
    as the fallback if this script does not run. Place search, current
    location and fares come from routes/web.php -> WhatsAppBookingController.
    Settings: Landing Page Setup > WhatsApp Booking.

    @param array $booking  WhatsAppBookingController::settings()
--}}
@php
    $erbText = [
        'kicker' => translate('Book on WhatsApp'),
        'me' => translate('Me'),
        'someoneElse' => translate('Someone else'),
        'pickupMine' => translate('Pickup'),
        'pickupTheirs' => translate('Their pickup'),
        'locating' => translate('Finding you...'),
        'locationDenied' => translate('Location is off for this site. Search for your pickup instead.'),
        'locationFailed' => translate('We could not get your location. Search for your pickup instead.'),
        'theyShare' => translate('They will share their location on WhatsApp'),
        'noResults' => translate('No places found'),
        'searching' => translate('Searching...'),
        'outOfZone' => translate('This trip is outside our service area right now. Send it anyway and we will tell you what is possible.'),
        'quoteFailed' => translate('We could not price this trip. You can still send it.'),
        'approx' => translate('approx.'),
        'min' => 'min',
        'now' => translate('Now'),
        'needPickup' => translate('Add a pickup'),
        'needDrop' => translate('Add a destination'),
        'needOther' => translate('Add the rider name and phone'),
        'needTime' => translate('Choose a time'),
        'send' => translate('Send booking on WhatsApp'),
        'sent' => translate('Opening WhatsApp...'),
        // message
        'msgTitle' => translate('New ride booking'),
        'msgRider' => translate('Rider'),
        'msgBookedBy' => translate('Booked by'),
        'msgPickup' => translate('Pickup'),
        'msgDrop' => translate('Drop-off'),
        'msgWhen' => translate('When'),
        'msgRide' => translate('Ride'),
        'msgFare' => translate('Estimated fare'),
        'msgNote' => translate('Note'),
        'msgPayment' => translate('Payment'),
        'msgCash' => translate('Cash'),
        'msgMe' => translate('me'),
        'msgShare' => translate('Rider will share their location here'),
        'msgFooter' => translate('Sent from the E-Ride website'),
    ];
@endphp

<div class="erb" data-erb hidden>
    <div class="erb-backdrop" data-erb-close></div>

    <div class="erb-panel" role="dialog" aria-modal="true" aria-labelledby="erb-title">
        <header class="erb-head">
            <div>
                <span class="erb-kicker">
                    @include('landing-page.partials._whatsapp-icon', ['size' => 14])
                    {{ translate('Book on WhatsApp') }}
                </span>
                <h2 id="erb-title" class="erb-title">{{ translate('Where are we going') }}?</h2>
            </div>
            <button type="button" class="erb-x" data-erb-close aria-label="{{ translate('Close') }}">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </header>

        <div class="erb-body">

            {{-- who is riding --}}
            <div class="erb-block">
                <div class="erb-label">{{ translate('Who is riding') }}?</div>
                <div class="erb-seg" role="radiogroup" aria-label="{{ translate('Who is riding') }}?">
                    <button type="button" role="radio" aria-checked="true" class="is-on" data-erb-who="me">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg>
                        {{ translate('Me') }}
                    </button>
                    <button type="button" role="radio" aria-checked="false" data-erb-who="other">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.5 3-5.5 6.5-5.5s6.5 2 6.5 5.5"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18.5 14.8c2 .7 3 2.4 3 5.2"/></svg>
                        {{ translate('Someone else') }}
                    </button>
                </div>

                <div class="erb-grid2" data-erb-other hidden>
                    <label class="erb-field">
                        <span>{{ translate('Rider name') }}</span>
                        <input type="text" maxlength="60" autocomplete="off" data-erb-other-name placeholder="{{ translate('e.g. Mama Grace') }}">
                    </label>
                    <label class="erb-field">
                        <span>{{ translate('Rider phone') }}</span>
                        <input type="tel" maxlength="20" autocomplete="off" inputmode="tel" data-erb-other-phone placeholder="+237 6XX XXX XXX">
                    </label>
                </div>
            </div>

            {{-- route --}}
            <div class="erb-block">
                <div class="erb-route">
                    <div class="erb-stop" data-erb-stop="pickup">
                        <span class="erb-dot erb-dot--pickup"></span>
                        <div class="erb-stop-main">
                            <span class="erb-stop-label" data-erb-pickup-label>{{ translate('Pickup') }}</span>
                            <input type="text" autocomplete="off" spellcheck="false" data-erb-input="pickup"
                                   placeholder="{{ translate('Search a place or use your location') }}"
                                   aria-label="{{ translate('Pickup') }}">
                        </div>
                        <button type="button" class="erb-locate" data-erb-locate title="{{ translate('Use my location') }}">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="3.5"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/><circle cx="12" cy="12" r="8"/></svg>
                            <span>{{ translate('My location') }}</span>
                        </button>
                    </div>
                    <div class="erb-connector"></div>
                    <div class="erb-stop" data-erb-stop="drop">
                        <span class="erb-dot erb-dot--drop">
                            <svg width="12" height="14" viewBox="0 0 24 28" fill="currentColor"><path d="M12 0C5.4 0 0 5.2 0 11.7 0 20.3 12 28 12 28s12-7.7 12-16.3C24 5.2 18.6 0 12 0Zm0 16a4.5 4.5 0 1 1 0-9 4.5 4.5 0 0 1 0 9Z"/></svg>
                        </span>
                        <div class="erb-stop-main">
                            <span class="erb-stop-label">{{ translate('Destination') }}</span>
                            <input type="text" autocomplete="off" spellcheck="false" data-erb-input="drop"
                                   placeholder="{{ translate('Where to') }}?" aria-label="{{ translate('Destination') }}">
                        </div>
                        <button type="button" class="erb-swap" data-erb-swap title="{{ translate('Swap pickup and destination') }}" aria-label="{{ translate('Swap pickup and destination') }}">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M7 4v16M7 20l-3-3M7 20l3-3M17 20V4M17 4l-3 3M17 4l3 3"/></svg>
                        </button>
                    </div>
                    <ul class="erb-suggest" data-erb-suggest role="listbox" hidden></ul>
                </div>

                <label class="erb-check" data-erb-share-wrap hidden>
                    <input type="checkbox" data-erb-share>
                    <span>{{ translate('They will share their location on WhatsApp') }}</span>
                </label>
                <p class="erb-hint" data-erb-hint hidden></p>
            </div>

            {{-- ride options + fare --}}
            <div class="erb-block" data-erb-quote hidden>
                <div class="erb-label erb-label--row">
                    <span>{{ translate('Choose your ride') }}</span>
                    <span class="erb-trip" data-erb-trip></span>
                </div>
                <div class="erb-options" data-erb-options role="radiogroup" aria-label="{{ translate('Choose your ride') }}"></div>
                <p class="erb-note" data-erb-quote-note hidden></p>
            </div>

            {{-- when --}}
            <div class="erb-block">
                <div class="erb-label">{{ translate('When') }}?</div>
                <div class="erb-seg erb-seg--sm" role="radiogroup" aria-label="{{ translate('When') }}?">
                    <button type="button" role="radio" aria-checked="true" class="is-on" data-erb-when="now">{{ translate('Now') }}</button>
                    <button type="button" role="radio" aria-checked="false" data-erb-when="later">{{ translate('Schedule') }}</button>
                </div>
                <input type="datetime-local" class="erb-datetime" data-erb-datetime hidden aria-label="{{ translate('Pickup time') }}">
            </div>

            {{-- details --}}
            <div class="erb-block">
                <div class="erb-grid2">
                    <label class="erb-field">
                        <span data-erb-myname-label>{{ translate('Your name') }}</span>
                        <input type="text" maxlength="60" autocomplete="name" data-erb-myname placeholder="{{ translate('Optional') }}">
                    </label>
                    <label class="erb-field">
                        <span>{{ translate('Note for the driver') }}</span>
                        <input type="text" maxlength="140" autocomplete="off" data-erb-note placeholder="{{ translate('e.g. 2 bags, gate is blue') }}">
                    </label>
                </div>
            </div>
        </div>

        <footer class="erb-foot">
            <p class="erb-missing" data-erb-missing aria-live="polite"></p>
            <button type="button" class="erb-send" data-erb-send disabled>
                @include('landing-page.partials._whatsapp-icon', ['size' => 20])
                <span data-erb-send-label>{{ translate('Send booking on WhatsApp') }}</span>
            </button>
            <p class="erb-foot-hint">{{ translate('WhatsApp opens with your booking written out. Just press send and we confirm your driver there.') }}</p>
        </footer>
    </div>
</div>

@push('script')
    <script>
        "use strict";
        (function () {
            var root = document.querySelector('[data-erb]');
            if (!root) { return; }

            var T = @json($erbText);
            var NUMBER = @json($booking['number']);
            var GREETING = @json($booking['greeting']);
            var URLS = {
                places: @json(route('whatsapp-booking.places')),
                place: @json(route('whatsapp-booking.place')),
                reverse: @json(route('whatsapp-booking.reverse')),
                quote: @json(route('whatsapp-booking.quote'))
            };

            var $ = function (sel) { return root.querySelector(sel); };
            var $$ = function (sel) { return Array.prototype.slice.call(root.querySelectorAll(sel)); };
            var store = {
                get: function (k) { try { return localStorage.getItem('erb_' + k) || ''; } catch (e) { return ''; } },
                set: function (k, v) { try { localStorage.setItem('erb_' + k, v); } catch (e) {} }
            };

            var state = { who: 'me', when: 'now', pickup: null, drop: null, share: false, option: null, quote: null };
            var lastFocus = null;

            /* ------------------------------------------------ open / close */
            function open(e) {
                if (e) { e.preventDefault(); }
                lastFocus = document.activeElement;
                root.hidden = false;
                document.documentElement.classList.add('erb-lock');
                requestAnimationFrame(function () { root.classList.add('is-open'); });
                $('[data-erb-myname]').value = $('[data-erb-myname]').value || store.get('name');
                setTimeout(function () { (state.pickup ? $('[data-erb-input="drop"]') : $('[data-erb-input="pickup"]')).focus(); }, 260);
                refresh();
            }
            function close() {
                root.classList.remove('is-open');
                document.documentElement.classList.remove('erb-lock');
                setTimeout(function () { root.hidden = true; }, 220);
                if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
            }
            document.querySelectorAll('[data-er-book]').forEach(function (el) { el.addEventListener('click', open); });
            $$('[data-erb-close]').forEach(function (el) { el.addEventListener('click', close); });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && !root.hidden) { close(); }
            });
            if (location.hash === '#book') { open(); }

            /* ------------------------------------------------ who / when */
            function choose(group, value) {
                $$('[data-erb-' + group + ']').forEach(function (b) {
                    var on = b.getAttribute('data-erb-' + group) === value;
                    b.classList.toggle('is-on', on);
                    b.setAttribute('aria-checked', on ? 'true' : 'false');
                });
            }
            $$('[data-erb-who]').forEach(function (b) {
                b.addEventListener('click', function () {
                    state.who = b.getAttribute('data-erb-who');
                    choose('who', state.who);
                    var other = state.who === 'other';
                    $('[data-erb-other]').hidden = !other;
                    $('[data-erb-share-wrap]').hidden = !other;
                    $('[data-erb-pickup-label]').textContent = other ? T.pickupTheirs : T.pickupMine;
                    if (!other) { setShare(false); }
                    if (other) { $('[data-erb-other-name]').focus(); }
                    refresh();
                });
            });
            $$('[data-erb-when]').forEach(function (b) {
                b.addEventListener('click', function () {
                    state.when = b.getAttribute('data-erb-when');
                    choose('when', state.when);
                    var dt = $('[data-erb-datetime]');
                    dt.hidden = state.when !== 'later';
                    if (state.when === 'later') {
                        var d = new Date(Date.now() + 60 * 60 * 1000);
                        d.setMinutes(Math.ceil(d.getMinutes() / 15) * 15, 0, 0);
                        var pad = function (n) { return String(n).padStart(2, '0'); };
                        var local = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
                        dt.min = local.slice(0, 16);
                        if (!dt.value) { dt.value = local; }
                        dt.focus();
                    }
                    quote();
                });
            });
            $('[data-erb-datetime]').addEventListener('change', refresh);

            /* ------------------------------------------------ places */
            var suggest = $('[data-erb-suggest]');
            var activeField = null, timer = null, seq = 0;

            function near() {
                var p = state.pickup || state.drop || state.here;
                return p ? '&lat=' + p.lat + '&lng=' + p.lng : '';
            }
            function setPlace(field, place) {
                state[field] = place;
                var input = $('[data-erb-input="' + field + '"]');
                input.value = place ? place.address : '';
                input.closest('.erb-stop').classList.toggle('is-set', !!place);
                hideSuggest();
                quote();
            }
            function hideSuggest() { suggest.hidden = true; suggest.innerHTML = ''; }
            function showMessage(text) {
                suggest.innerHTML = '<li class="erb-suggest-msg">' + text + '</li>';
                suggest.hidden = false;
            }
            function search(field, q) {
                var mine = ++seq;
                showMessage(T.searching);
                fetch(URLS.places + '?q=' + encodeURIComponent(q) + near(), { headers: { Accept: 'application/json' } })
                    .then(function (r) { return r.ok ? r.json() : { places: [] }; })
                    .then(function (data) {
                        if (mine !== seq || activeField !== field) { return; }
                        var places = data.places || [];
                        if (!places.length) { showMessage(T.noResults); return; }
                        suggest.innerHTML = '';
                        places.forEach(function (p) {
                            var li = document.createElement('li');
                            li.setAttribute('role', 'option');
                            li.tabIndex = 0;
                            li.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/></svg><span><strong></strong><small></small></span>';
                            li.querySelector('strong').textContent = p.main;
                            li.querySelector('small').textContent = p.secondary;
                            var pick = function () { pickSuggestion(field, p); };
                            li.addEventListener('mousedown', function (e) { e.preventDefault(); pick(); });
                            li.addEventListener('keydown', function (e) { if (e.key === 'Enter') { pick(); } });
                            suggest.appendChild(li);
                        });
                        suggest.hidden = false;
                    })
                    .catch(function () { if (mine === seq) { hideSuggest(); } });
            }
            function pickSuggestion(field, p) {
                hideSuggest();
                var input = $('[data-erb-input="' + field + '"]');
                input.value = p.main;
                fetch(URLS.place + '?id=' + encodeURIComponent(p.id), { headers: { Accept: 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (d.lat === undefined) { return; }
                        setPlace(field, { lat: d.lat, lng: d.lng, address: p.secondary ? p.main + ', ' + p.secondary : (d.address || p.main) });
                        if (field === 'pickup' && !state.drop) { $('[data-erb-input="drop"]').focus(); }
                    });
            }
            $$('[data-erb-input]').forEach(function (input) {
                var field = input.getAttribute('data-erb-input');
                input.addEventListener('focus', function () { activeField = field; });
                input.addEventListener('input', function () {
                    activeField = field;
                    if (state[field]) { state[field] = null; input.closest('.erb-stop').classList.remove('is-set'); quote(); }
                    clearTimeout(timer);
                    var q = input.value.trim();
                    if (q.length < 2) { hideSuggest(); return; }
                    timer = setTimeout(function () { search(field, q); }, 280);
                });
                input.addEventListener('blur', function () { setTimeout(function () { if (activeField === field) { hideSuggest(); } }, 150); });
            });

            /* my location */
            var locateBtn = $('[data-erb-locate]');
            function hint(text) {
                var h = $('[data-erb-hint]');
                h.textContent = text || '';
                h.hidden = !text;
            }
            locateBtn.addEventListener('click', function () {
                if (!navigator.geolocation) { hint(T.locationFailed); return; }
                hint('');
                setShare(false);
                locateBtn.classList.add('is-busy');
                $('[data-erb-input="pickup"]').value = T.locating;
                navigator.geolocation.getCurrentPosition(function (pos) {
                    var lat = pos.coords.latitude, lng = pos.coords.longitude;
                    state.here = { lat: lat, lng: lng };
                    fetch(URLS.reverse + '?lat=' + lat + '&lng=' + lng, { headers: { Accept: 'application/json' } })
                        .then(function (r) { return r.json(); })
                        .then(function (d) { setPlace('pickup', { lat: lat, lng: lng, address: d.address, mine: true }); })
                        .catch(function () { setPlace('pickup', { lat: lat, lng: lng, address: T.pickupMine, mine: true }); })
                        .then(function () { locateBtn.classList.remove('is-busy'); if (!state.drop) { $('[data-erb-input="drop"]').focus(); } });
                }, function (err) {
                    locateBtn.classList.remove('is-busy');
                    $('[data-erb-input="pickup"]').value = '';
                    hint(err && err.code === 1 ? T.locationDenied : T.locationFailed);
                }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 });
            });

            /* booking for someone who will send their own location */
            function setShare(on) {
                state.share = on;
                $('[data-erb-share]').checked = on;
                var stop = $('[data-erb-stop="pickup"]');
                stop.classList.toggle('is-shared', on);
                var input = $('[data-erb-input="pickup"]');
                input.disabled = on;
                if (on) { state.pickup = null; input.value = T.theyShare; stop.classList.add('is-set'); }
                else if (input.value === T.theyShare) { input.value = ''; stop.classList.remove('is-set'); }
                quote();
            }
            $('[data-erb-share]').addEventListener('change', function (e) { setShare(e.target.checked); });

            $('[data-erb-swap]').addEventListener('click', function () {
                if (state.share) { return; }
                var a = state.pickup, b = state.drop;
                setPlace('pickup', b);
                setPlace('drop', a);
            });

            /* ------------------------------------------------ fare quote */
            var quoteSeq = 0;
            function quote() {
                var box = $('[data-erb-quote]');
                if (!state.pickup || !state.drop) { state.quote = null; box.hidden = true; refresh(); return; }
                var mine = ++quoteSeq;
                box.hidden = false;
                box.classList.add('is-loading');
                $('[data-erb-options]').innerHTML = '<div class="erb-skel"></div><div class="erb-skel"></div>';
                $('[data-erb-quote-note]').hidden = true;
                $('[data-erb-trip]').textContent = '';
                var q = '?plat=' + state.pickup.lat + '&plng=' + state.pickup.lng + '&dlat=' + state.drop.lat + '&dlng=' + state.drop.lng + (state.when === 'later' ? '&scheduled=1' : '');
                fetch(URLS.quote + q, { headers: { Accept: 'application/json' } })
                    .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
                    .then(function (d) {
                        if (mine !== quoteSeq) { return; }
                        state.quote = d;
                        box.classList.remove('is-loading');
                        $('[data-erb-trip]').textContent = d.distance_km + ' km · ~' + d.duration_min + ' ' + T.min;
                        renderOptions(d.options || []);
                        var note = $('[data-erb-quote-note]');
                        if (!d.in_zone) { note.textContent = T.outOfZone; note.hidden = false; }
                        refresh();
                    })
                    .catch(function () {
                        if (mine !== quoteSeq) { return; }
                        state.quote = null;
                        box.classList.remove('is-loading');
                        $('[data-erb-options]').innerHTML = '';
                        var note = $('[data-erb-quote-note]');
                        note.textContent = T.quoteFailed;
                        note.hidden = false;
                        refresh();
                    });
            }
            function renderOptions(options) {
                var wrap = $('[data-erb-options]');
                wrap.innerHTML = '';
                var keep = state.option && options.some(function (o) { return o.id === state.option.id; });
                if (!keep) { state.option = options[0] || null; }
                options.forEach(function (o) {
                    var b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'erb-option';
                    b.setAttribute('role', 'radio');
                    var on = state.option && state.option.id === o.id;
                    b.classList.toggle('is-on', !!on);
                    b.setAttribute('aria-checked', on ? 'true' : 'false');
                    b.innerHTML = '<span class="erb-option-img"></span><span class="erb-option-text"><strong></strong><small></small></span><span class="erb-option-fare"><strong></strong><small></small></span>';
                    var img = b.querySelector('.erb-option-img');
                    if (o.image) { var i = new Image(); i.alt = ''; i.src = o.image; img.appendChild(i); }
                    else { img.textContent = o.type === 'car' ? '🚗' : '🏍️'; }
                    b.querySelector('.erb-option-text strong').textContent = o.name;
                    b.querySelector('.erb-option-text small').textContent = '~' + o.minutes + ' ' + T.min;
                    b.querySelector('.erb-option-fare strong').textContent = o.fare_text;
                    b.querySelector('.erb-option-fare small').textContent = T.approx;
                    b.addEventListener('click', function () { state.option = o; renderOptions(options); refresh(); });
                    wrap.appendChild(b);
                });
            }

            /* ------------------------------------------------ validation + message */
            function missing() {
                if (state.who === 'other' && (!$('[data-erb-other-name]').value.trim() || !$('[data-erb-other-phone]').value.trim())) { return T.needOther; }
                if (!state.pickup && !state.share) { return T.needPickup; }
                if (!state.drop) { return T.needDrop; }
                if (state.when === 'later' && !$('[data-erb-datetime]').value) { return T.needTime; }
                return '';
            }
            function refresh() {
                var m = missing();
                $('[data-erb-missing]').textContent = m;
                $('[data-erb-send]').disabled = !!m;
            }
            ['[data-erb-other-name]', '[data-erb-other-phone]'].forEach(function (s) { $(s).addEventListener('input', refresh); });

            function mapLink(p) { return 'https://maps.google.com/?q=' + p.lat.toFixed(6) + ',' + p.lng.toFixed(6); }
            function reference() {
                var a = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789', r = '';
                for (var i = 0; i < 4; i++) { r += a[Math.floor(Math.random() * a.length)]; }
                return 'ER-' + r;
            }
            function whenText() {
                if (state.when !== 'later') { return T.now; }
                var d = new Date($('[data-erb-datetime]').value);
                try {
                    return d.toLocaleString(undefined, { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
                } catch (e) { return $('[data-erb-datetime]').value.replace('T', ' '); }
            }
            function message(ref) {
                var my = $('[data-erb-myname]').value.trim();
                var lines = [];
                if (GREETING) { lines.push(GREETING, ''); }
                lines.push('🚖 *' + T.msgTitle + '* · ' + ref, '');
                if (state.who === 'other') {
                    lines.push('👤 *' + T.msgRider + ':* ' + $('[data-erb-other-name]').value.trim() + ' · ' + $('[data-erb-other-phone]').value.trim());
                    if (my) { lines.push('🙋 *' + T.msgBookedBy + ':* ' + my); }
                } else {
                    lines.push('👤 *' + T.msgRider + ':* ' + (my ? my + ' (' + T.msgMe + ')' : T.msgMe));
                }
                lines.push('');
                if (state.share) {
                    lines.push('📍 *' + T.msgPickup + ':* ' + T.msgShare);
                } else {
                    lines.push('📍 *' + T.msgPickup + ':* ' + state.pickup.address, mapLink(state.pickup));
                }
                lines.push('🏁 *' + T.msgDrop + ':* ' + state.drop.address, mapLink(state.drop), '');
                lines.push('🕒 *' + T.msgWhen + ':* ' + whenText());
                var q = state.quote;
                if (state.option) {
                    lines.push('🚗 *' + T.msgRide + ':* ' + state.option.name + (q ? ' · ' + q.distance_km + ' km · ~' + state.option.minutes + ' ' + T.min : ''));
                    lines.push('💵 *' + T.msgFare + ':* ~' + state.option.fare_text + ' · ' + T.msgCash);
                } else if (q) {
                    lines.push('🚗 *' + T.msgRide + ':* ' + q.distance_km + ' km · ~' + q.duration_min + ' ' + T.min);
                }
                var note = $('[data-erb-note]').value.trim();
                if (note) { lines.push('📝 *' + T.msgNote + ':* ' + note); }
                lines.push('', '_' + T.msgFooter + '_');
                return lines.join('\n');
            }

            $('[data-erb-send]').addEventListener('click', function () {
                if (missing()) { refresh(); return; }
                var my = $('[data-erb-myname]').value.trim();
                if (my) { store.set('name', my); }
                var url = 'https://wa.me/' + NUMBER + '?text=' + encodeURIComponent(message(reference()));
                var label = $('[data-erb-send-label]');
                label.textContent = T.sent;
                setTimeout(function () { label.textContent = T.send; }, 2500);
                var w = window.open(url, '_blank');
                if (w) { w.opener = null; } else { location.href = url; }
            });
        })();
    </script>
@endpush
