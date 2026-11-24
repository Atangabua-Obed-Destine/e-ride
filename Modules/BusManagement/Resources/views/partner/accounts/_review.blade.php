{{--
    The review dialog of every posting action. A form with data-acc-action="expense" is not sent
    directly: it is first previewed (the real action, rolled back), shown here, and only confirmed
    once the preview is clean and the box is ticked. The server refuses it without reviewed=1.
--}}
<div class="modal fade" id="rvModal" tabindex="-1" aria-labelledby="rvTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5 fw-bold" id="rvTitle">{{ translate('Check before it is posted') }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ translate('Close') }}"></button>
            </div>
            <div class="modal-body" id="rvBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ translate('Change something') }}</button>
                <button type="button" class="btn btn-primary" id="rvConfirm" disabled>{{ translate('Confirm and post') }}</button>
            </div>
        </div>
    </div>
</div>
@push('script')
<script>
(function () {
    var T = {
        problems: @json(translate('Fix this first')),
        warnings: @json(translate('Worth a second look')),
        summary: @json(translate('What you entered')),
        entries: @json(translate('What will be posted')),
        account: @json(translate('Account')),
        debit: @json(translate('Debit')),
        credit: @json(translate('Credit')),
        checking: @json(translate('Checking')),
        failed: @json(translate('Could not check it. Try again.')),
        none: @json(translate('Nothing is posted: it is only recorded.'))
    };
    var base = {preview: {!! json_encode(route('bus-partner.accounts.preview', ['action' => '__A__'])) !!}, act: {!! json_encode(route('bus-partner.accounts.do', ['action' => '__A__'])) !!}};
    var modalEl = document.getElementById('rvModal'), modal = new bootstrap.Modal(modalEl), body = document.getElementById('rvBody'), btn = document.getElementById('rvConfirm');
    var current = null;
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]; }); }
    function send(url, fd) {
        return fetch(url, {method: 'POST', headers: {'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json'}, body: fd})
            .then(function (r) {
                if (r.status === 401 || r.status === 419) { window.location.reload(); throw new Error('session'); }
                return r.json().then(function (d) { d._status = r.status; return d; });
            });
    }
    function table(cols, rows, numeric) {
        numeric = numeric || [];
        return '<div class="acc-scroll"><table class="acc-t"><thead><tr>' + cols.map(function (c, i) { return '<th' + (numeric.indexOf(i) >= 0 ? ' class="n"' : '') + '>' + esc(c) + '</th>'; }).join('') +
            '</tr></thead><tbody>' + rows.map(function (r) { return '<tr>' + r.map(function (v, i) { return '<td' + (numeric.indexOf(i) >= 0 ? ' class="n"' : '') + '>' + esc(v) + '</td>'; }).join('') + '</tr>'; }).join('') + '</tbody></table></div>';
    }
    function render(d) {
        var h = '';
        if (d.problems && d.problems.length) h += '<div class="rv-block"><h3>' + esc(T.problems) + '</h3>' + d.problems.map(function (p) { return '<div class="rv-problem">' + esc(p) + '</div>'; }).join('') + '</div>';
        if (d.warnings && d.warnings.length) h += '<div class="rv-block"><h3>' + esc(T.warnings) + '</h3>' + d.warnings.map(function (p) { return '<div class="rv-warn">' + esc(p) + '</div>'; }).join('') + '</div>';
        if (d.summary && d.summary.length) h += '<div class="rv-block"><h3>' + esc(T.summary) + '</h3>' + table(['', ''], d.summary) + '</div>';
        if (d.ok) {
            h += '<div class="rv-block"><h3>' + esc(T.entries) + '</h3>';
            if (!d.entries || !d.entries.length) h += '<div class="rv-note">' + esc(T.none) + '</div>';
            (d.entries || []).forEach(function (e) {
                h += '<div class="rv-entry"><div class="d-flex justify-content-between gap-2 mb-1"><b>' + esc(e.memo) + '</b><span class="acc-tag text-nowrap">' + esc(e.date) + '</span></div>' +
                    '<div class="acc-scroll"><table class="acc-t"><thead><tr><th>' + esc(T.account) + '</th><th class="n">' + esc(T.debit) + '</th><th class="n">' + esc(T.credit) + '</th></tr></thead><tbody>' +
                    e.lines.map(function (l) { return '<tr><td>' + esc(l.account) + (l.tags ? '<div class="acc-tag">' + esc(l.tags) + '</div>' : '') + '</td><td class="n">' + esc(l.debit) + '</td><td class="n">' + esc(l.credit) + '</td></tr>'; }).join('') +
                    '</tbody></table></div></div>';
            });
            h += '</div>';
            (d.effects || []).forEach(function (ef) { h += '<div class="rv-block"><h3>' + esc(ef.title) + '</h3>' + table(ef.columns, ef.rows, ef.numeric) + '</div>'; });
        }
        (d.notes || []).forEach(function (n) { h += '<div class="rv-note">' + esc(n) + '</div>'; });
        h += '<label class="rv-tick" id="rvTickBox"><input type="checkbox" class="form-check-input" id="rvTick"' + (d.ok ? '' : ' disabled') + '><span>' + esc(d.confirm || '') + '</span></label>';
        body.innerHTML = h;
        var tick = document.getElementById('rvTick');
        tick.addEventListener('change', function () { btn.disabled = !(d.ok && tick.checked); document.getElementById('rvTickBox').classList.toggle('on', tick.checked); });
        btn.disabled = true;
    }
    window.accReview = function (form) {
        var action = form.getAttribute('data-acc-action');
        current = {form: form, action: action};
        document.getElementById('rvTitle').textContent = form.getAttribute('data-acc-title') || T.checking;
        body.innerHTML = '<div class="py-4 text-center b-hint">' + esc(T.checking) + '…</div>';
        btn.disabled = true;
        modal.show();
        send(base.preview.replace('__A__', action), new FormData(form)).then(function (d) {
            if (d._status >= 400 && !d.problems) { d = {ok: false, problems: [d.message || T.failed], confirm: ''}; }
            render(d);
        }).catch(function (e) { if (e.message !== 'session') body.innerHTML = '<div class="rv-problem">' + esc(T.failed) + '</div>'; });
    };
    btn.addEventListener('click', function () {
        if (!current) return;
        btn.disabled = true;
        var fd = new FormData(current.form);
        fd.append('reviewed', '1');
        send(base.act.replace('__A__', current.action), fd).then(function (d) {
            if (d.ok) {
                busToast(d.message);
                setTimeout(function () { if (d.redirect) window.location.href = d.redirect; else window.location.reload(); }, 500);
            } else {
                body.insertAdjacentHTML('afterbegin', '<div class="rv-problem">' + esc(d.message || T.failed) + '</div>');
                body.scrollTop = 0;
            }
        }).catch(function (e) { if (e.message !== 'session') busToast(T.failed); });
    });
    document.addEventListener('submit', function (e) {
        var f = e.target.closest('form[data-acc-action]');
        if (!f) return;
        e.preventDefault();
        if (!f.reportValidity()) return;
        accReview(f);
    });
})();
</script>
@endpush
