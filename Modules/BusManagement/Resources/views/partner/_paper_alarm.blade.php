{{-- The paper sheet alarm, on every page for counter staff: seats to pencil or erase, sheets to check,
     app sales paused, and the steps after a cut. Loud on purpose: it beeps until the sheet is right. --}}
<style>
    .pa { border: 3px solid var(--red); border-radius: 14px; background: #fff; margin-bottom: 16px; overflow: hidden; animation: paPulse 1.2s ease-in-out infinite; }
    @keyframes paPulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(224, 32, 32, .45); } 50% { box-shadow: 0 0 0 8px rgba(224, 32, 32, 0); } }
    @media (prefers-reduced-motion: reduce) { .pa { animation: none; } }
    .pa-head { background: var(--red); color: #fff; padding: 10px 14px; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; font-weight: 800; }
    .pa-head .btn { background: #fff; color: var(--red); border: 0; }
    .pa-row { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; padding: 10px 14px; border-top: 1px solid var(--line); }
    .pa-row:first-child { border-top: 0; }
    .pa-act { font-weight: 800; font-size: 15px; }
    .pa-act.erase { color: #b91c1c; }
    .pa-act.pencil { color: #9a4a06; }
    .pa-seat { display: inline-flex; min-width: 34px; height: 30px; padding: 0 6px; border-radius: 7px; align-items: center; justify-content: center; font-weight: 800; color: #fff; background: #1d2d2b; }
    .pa-seat.erase { background: #fff; color: #b91c1c; box-shadow: inset 0 0 0 2px #b91c1c; text-decoration: line-through; }
    .pause-bar { border-radius: 12px; background: #1d2d2b; color: #fff; padding: 10px 14px; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 16px; }
    .back-panel { border: 2px solid #f59e0b; border-radius: 14px; background: #fffaf0; padding: 12px 14px; margin-bottom: 16px; }
    .back-panel ol { margin: 6px 0 10px; padding-left: 20px; }
    .back-panel li { margin-bottom: 6px; }
    /* A wide screen: one compact strip each, so the screen below stays in view. */
    @media (min-width: 1024px) {
        .pa { display: flex; align-items: stretch; margin-bottom: 12px; }
        .pa-head { flex: 0 0 230px; flex-direction: column; align-items: flex-start; justify-content: center; padding: 8px 12px; font-size: 14px; }
        #paList { flex: 1; min-width: 0; display: flex; flex-wrap: wrap; align-content: flex-start; max-height: 104px; overflow-y: auto; }
        .pa-row { flex: 1 1 360px; min-width: 0; padding: 6px 12px; border-top: 0; border-bottom: 1px solid var(--line); border-inline-start: 1px solid var(--line); }
        .pa-row .b-hint { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .pa-act { font-size: 14px; }
        .pause-bar { padding: 6px 12px; margin-bottom: 12px; }
        .back-panel { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 16px; padding: 8px 12px; margin-bottom: 12px; }
        .back-panel ol { display: flex; flex-wrap: wrap; gap: 4px 18px; margin: 0; padding: 0; list-style: none; counter-reset: bp; flex: 1 1 auto; }
        .back-panel li { margin: 0; counter-increment: bp; }
        .back-panel li::before { content: counter(bp) ". "; font-weight: 800; }
    }
</style>
<div id="pauseBar" class="pause-bar" hidden role="status">
    <i class="bi bi-pause-circle-fill fs-4" aria-hidden="true"></i>
    <div class="flex-grow-1"><b>{{ translate('Sales in the app are paused') }}</b> · <span id="pauseSince"></span>
        <div class="small" style="color:#c9d1d0" id="pauseWhy"></div></div>
    <button type="button" class="btn btn-primary btn-sm" id="resumeBtn">{{ translate('Resume app sales') }}</button>
</div>
<div id="backPanel" class="back-panel" hidden role="alert">
    <b id="backTitle"></b>
    <ol>
        <li>{{ translate('Type in the seats you sold on paper') }} · <a href="{{ route('bus-partner.sell') }}">{{ translate('Quick sell') }}</a></li>
        <li>{{ translate('Check each sheet against E-Ride, seat by seat') }} · <a href="{{ route('bus-partner.paper') }}">{{ translate('Paper sheets') }}</a></li>
        <li id="backResume">{{ translate('Then resume sales in the app') }}</li>
    </ol>
    <button type="button" class="btn btn-dark btn-sm" id="backDone">{{ translate('Done') }}</button>
</div>
<div id="paperAlarm" class="pa" hidden role="alert" aria-live="assertive">
    <div class="pa-head"><i class="bi bi-pencil-square fs-5" aria-hidden="true"></i><span class="flex-grow-1" id="paTitle"></span>
        <button type="button" class="btn btn-sm" id="paSound" hidden><i class="bi bi-volume-up"></i> {{ translate('Turn the alarm sound on') }}</button></div>
    <div id="paList"></div>
</div>
<script>
(function () {
    var T = {
        title: @json(translate(key: 'Paper sheet: {n} to do now', replace: ['n' => '{n}'])),
        pencil: @json(translate('Pencil')), erase: @json(translate('Erase')), seat: @json(translate('seat')),
        donePencil: @json(translate('Done, it is pencilled')), doneErase: @json(translate('Done, it is erased')),
        check: @json(translate('Check the sheet against E-Ride before boarding')), checkBtn: @json(translate('Check it')),
        since: @json(translate(key: 'Paused at {t}', replace: ['t' => '{t}'])), auto: @json(translate('Two requests in a row got no answer, so E-Ride paused them. Passengers cannot ask for seats until you resume.')),
        manual: @json(translate('Passengers cannot ask for seats until you resume. Your counter keeps working.')),
        resumeAsk: @json(translate('Did you type in every paper sale and check the sheets against E-Ride')),
        back: @json(translate('Back after a cut or a break. Before you sell:')), newDay: @json(translate('New day. Before you sell, check the sheets for today:')), q: @json(app()->getLocale() === 'fr' ? ' ?' : '?'),
    };
    var urls = {feed: @json(route('bus-partner.paper.feed')), tick: @json(url('partner/bus-agency/paper/__/tick')), show: @json(url('partner/bus-agency/paper/__')),
        resume: @json(route('bus-partner.paper.resume'))};
    var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
    var box = document.getElementById('paperAlarm'), list = document.getElementById('paList'), soundBtn = document.getElementById('paSound');
    var state = null, known = {}, failedSince = null, busyKey = null, lastAlarm = 0, flash = false;

    // Sound: browsers only allow it after a tap on the page, so the first tap anywhere unlocks it.
    var ctx = null;
    function unlock() {
        // One sound for the whole window, even with this screen in several tabs (busDesk, _portal_js).
        ctx = busDesk.audio();
        paintSound();
    }
    function paintSound() { soundBtn.hidden = !!(ctx && ctx.state === 'running'); }
    document.addEventListener('pointerdown', unlock, {capture: true});
    document.addEventListener('keydown', unlock, {capture: true});
    soundBtn.addEventListener('click', function () { unlock(); alarm(true); });

    // Three loud beeps, repeated while something is left to do.
    function alarm(force) {
        if (!force && Date.now() - lastAlarm < 15000) return;
        // On a wide screen every tab has this alarm: only the one on screen sounds (or Home's).
        if (!busDesk.maySound(box)) return;
        lastAlarm = Date.now();
        if (navigator.vibrate) navigator.vibrate([400, 150, 400, 150, 400]);
        if (!ctx || ctx.state !== 'running') return;
        [0, 0.32, 0.64].forEach(function (at) {
            var o = ctx.createOscillator(), g = ctx.createGain(), t = ctx.currentTime + at;
            o.type = 'square'; o.frequency.setValueAtTime(1040, t); o.frequency.setValueAtTime(780, t + 0.12);
            o.connect(g); g.connect(ctx.destination);
            g.gain.setValueAtTime(0.0001, t); g.gain.exponentialRampToValueAtTime(0.9, t + 0.02); g.gain.setValueAtTime(0.9, t + 0.22); g.gain.exponentialRampToValueAtTime(0.0001, t + 0.26);
            o.start(t); o.stop(t + 0.28);
        });
    }

    function key(i) { return i.departure_id + ':' + i.seat + ':' + i.action; }
    function render() {
        var items = state.todo, checks = state.checks, n = items.length + checks.length;
        document.querySelectorAll('[data-paper-badge]').forEach(function (b) { b.textContent = n; b.style.display = n > 0 ? 'inline-flex' : 'none'; });
        document.querySelectorAll('#side-bar [data-badge="paper"]').forEach(function (a) { a.setAttribute('data-count', n > 0 ? n : ''); });
        box.hidden = !n;
        if (!n) { document.title = document.title.replace(/^✏️ /, ''); return; }
        document.getElementById('paTitle').textContent = T.title.replace('{n}', n);
        list.innerHTML = items.map(function (i) {
            var er = i.action === 'erase';
            return '<div class="pa-row" data-key="' + esc(key(i)) + '"><span class="pa-act ' + i.action + '">' + (er ? T.erase : T.pencil) + '</span>' +
                '<span class="pa-seat ' + (er ? 'erase' : '') + '">' + esc(i.seat) + '</span>' +
                '<div class="flex-grow-1" style="min-width:0"><b>' + esc(i.departure) + '</b><div class="b-hint">' + esc(i.why) + (i.name ? ' · ' + esc(i.name) : '') + '</div></div>' +
                '<button type="button" class="btn ' + (er ? 'btn-primary' : 'btn-dark') + ' btn-sm" data-tick data-dep="' + esc(i.departure_id) + '" data-seat="' + esc(i.seat) + '" data-action="' + i.action + '">' +
                (er ? T.doneErase : T.donePencil) + '</button></div>';
        }).join('') + checks.map(function (c) {
            return '<div class="pa-row"><span class="pa-act pencil"><i class="bi bi-list-check"></i></span><div class="flex-grow-1"><b>' + esc(c.departure) + '</b><div class="b-hint">' + T.check + '</div></div>' +
                '<a class="btn btn-dark btn-sm" href="' + urls.show.replace('__', c.departure_id) + '">' + T.checkBtn + '</a></div>';
        }).join('');
    }
    function renderPause() {
        var bar = document.getElementById('pauseBar'), p = state.paused;
        bar.hidden = !p;
        if (p) {
            document.getElementById('pauseSince').textContent = T.since.replace('{t}', p.at);
            document.getElementById('pauseWhy').textContent = p.auto ? T.auto : T.manual;
        }
        document.getElementById('backResume').hidden = !p;
        document.querySelectorAll('[data-pause-state]').forEach(function (el) { el.dataset.pauseState = p ? 'paused' : 'live'; });
        window.dispatchEvent(new CustomEvent('bus-paper', {detail: state}));
    }
    function showBack(title) {
        var p = document.getElementById('backPanel');
        document.getElementById('backTitle').textContent = title;
        p.hidden = false;
    }
    document.getElementById('backDone').addEventListener('click', function () { document.getElementById('backPanel').hidden = true; });
    document.getElementById('resumeBtn').addEventListener('click', function () {
        if (!confirm(T.resumeAsk + T.q)) return;
        busPost(urls.resume).then(function (d) { busToast(d.message); load(); });
    });
    window.busPaperRefresh = function () { load(); };

    function load() {
        return fetch(urls.feed, {headers: {'Accept': 'application/json'}}).then(function (r) {
            if (r.status === 401) { location.reload(); throw new Error('session'); }
            if (!r.ok) throw new Error('http');
            return r.json();
        }).then(function (d) {
            var away = failedSince && Date.now() - failedSince > 120000;
            failedSince = null;
            state = d;
            if (!d.on) { box.hidden = true; renderPause(); return; }
            // Back from a cut (the portal could not reach E-Ride), or opened again after a while.
            var last = +localStorage.getItem('busPaperSeen') || 0;
            if (away || (last && Date.now() - last > 600000)) showBack(T.back);
            else {
                var today = new Date().toDateString();
                if (localStorage.getItem('busPaperDay') !== today) showBack(T.newDay);
                localStorage.setItem('busPaperDay', today);
            }
            localStorage.setItem('busPaperSeen', String(Date.now()));
            var fresh = false, now = {};
            d.todo.forEach(function (i) { now[key(i)] = true; if (!known[key(i)]) fresh = true; });
            d.checks.forEach(function (c) { now['c' + c.departure_id] = true; if (!known['c' + c.departure_id]) fresh = true; });
            known = now;
            if (!busyKey) render();
            renderPause();
            if (d.todo.length || d.checks.length) alarm(fresh);
        }).catch(function () { failedSince = failedSince || Date.now(); });
    }
    // In a tab behind another screen this copy waits (Home's keeps watching); it asks again when it comes on screen.
    function wanted() { var t = busDesk.top(); return !t || t === window || busDesk.onScreen() === document || busDesk.isHome(); }
    (function poll() { (state === null || wanted() ? load() : Promise.resolve()).finally(function () { setTimeout(poll, 15000); }); })();
    window.addEventListener('focus', function () { if (state && wanted()) load(); });
    var flasher = setInterval(function () {
        if (!state || !state.on || !(state.todo.length || state.checks.length)) return;
        flash = !flash;
        document.title = (flash ? '✏️ ' : '') + document.title.replace(/^✏️ /, '');
        alarm(false);
    }, 1000);
    window.addEventListener('pi-desk-freed', function () { clearInterval(flasher); });

    list.addEventListener('click', function (e) {
        var b = e.target.closest('[data-tick]');
        if (!b) return;
        b.disabled = true; busyKey = true;
        busPost(urls.tick.replace('__', b.dataset.dep), {seat: b.dataset.seat, action: b.dataset.action}).then(function (d) {
            busToast(d.message);
            if (d.ok) b.closest('.pa-row').remove();
        }).catch(function () { b.disabled = false; }).finally(function () { busyKey = null; load(); });
    });
})();
</script>
