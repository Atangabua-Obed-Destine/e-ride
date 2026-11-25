{{--
    The review dialog's script (paxhitestbed supplier-bill/partials/review-modal). Two triggers:

    1. A button with data-review-url (and data-review-form, data-confirm-form, data-confirm-label,
       data-confirm-check, data-confirm-class, data-needs-reason): its own preview route.
    2. Any form marked data-review="<tick-box wording>" (optional data-review-label,
       data-review-class, data-review-reason="1"): the form's real request is replayed and rolled
       back by Ledger\ReviewController::dryRun, and the dialog shows what it would write. Its route
       carries the ledger.review middleware, so it is refused without reviewed=1.
--}}
<script>
(function () {
    var modal = $('#reviewModal');
    var body = document.getElementById('review-body');
    var confirmBtn = document.getElementById('review-confirm');
    var reasonBox = document.getElementById('review-reason');
    var reasonInput = document.getElementById('review-reason-input');
    var check = document.getElementById('review-check');
    var checkBox = document.getElementById('review-check-box');
    var checkLabel = document.getElementById('review-check-label');
    var T = {!! json_encode(['default' => __('ledger.review_check_default'), 'checking' => __('ledger.checking'), 'confirm' => __('ledger.confirm'), 'confirm_post' => __('ledger.confirm_and_post'), 'failed' => __('ledger.check_failed'), 'forbidden' => __('ledger.not_allowed'), 'working' => __('ledger.working')], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};
    var dryRunUrl = {!! json_encode(route('ledger.review.dry-run')) !!};
    var current = null; // { confirmForm, needsReason }

    function previewOk() {
        var p = body.querySelector('.ap-preview');
        return !!p && p.getAttribute('data-ok') === '1';
    }
    function refresh() {
        var ok = previewOk();
        var needsReason = current && current.needsReason;
        check.disabled = !ok;
        if (!ok) { check.checked = false; }
        checkBox.classList.toggle('is-ready', ok);
        confirmBtn.disabled = !ok || !check.checked || (needsReason && reasonInput.value.trim() === '');
    }
    function fail(xhr) {
        var msg = T.failed;
        if (xhr && xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
            msg = Object.values(xhr.responseJSON.errors).map(function (m) { return m[0]; }).join(' ');
        } else if (xhr && xhr.status === 403) {
            msg = T.forbidden;
        }
        body.innerHTML = '<div class="ap-preview" data-ok="0"><div class="ap-note is-bad">' + $('<div>').text(msg).html() + '</div></div>';
        refresh();
    }
    function open(cfg) {
        current = cfg;
        body.innerHTML = '<div class="ap-muted"></div>';
        body.firstChild.textContent = T.checking;
        confirmBtn.textContent = cfg.label || T.confirm;
        confirmBtn.className = 'btn ' + (cfg.cssClass || 'btn-success');
        checkLabel.textContent = cfg.check || T['default'];
        check.checked = false;
        reasonInput.value = '';
        reasonBox.style.display = cfg.needsReason ? '' : 'none';
        refresh();
        modal.modal('show');
        cfg.request().done(function (html) { body.innerHTML = html; refresh(); }).fail(fail);
    }
    window.LedgerReview = { open: open };

    // 1. Trigger buttons with their own preview route.
    $(document).on('click', '[data-review-url]', function (e) {
        e.preventDefault();
        var el = this;
        var formId = el.getAttribute('data-review-form');
        if (formId) {
            var f = document.getElementById(formId);
            if (f.reportValidity && !f.reportValidity()) { return; }
        }
        open({
            request: function () {
                var url = el.getAttribute('data-review-url');
                return formId ? $.post(url, $('#' + formId).serialize()) : $.get(url);
            },
            confirmForm: document.getElementById(el.getAttribute('data-confirm-form')),
            label: el.getAttribute('data-confirm-label'),
            cssClass: el.getAttribute('data-confirm-class'),
            check: el.getAttribute('data-confirm-check'),
            needsReason: el.getAttribute('data-needs-reason') === '1'
        });
    });

    // 2. Any form marked data-review: its own request, replayed and rolled back.
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.matches || !form.matches('form[data-review]') || form.getAttribute('data-reviewed') === '1') { return; }
        e.preventDefault();
        e.stopImmediatePropagation();
        if (form.reportValidity && !form.reportValidity()) { return; }
        var methodInput = form.querySelector('input[name="_method"]');
        var payload = $(form).serialize()
            + '&_review_target=' + encodeURIComponent(form.getAttribute('action'))
            + '&_review_method=' + encodeURIComponent((methodInput && methodInput.value) || form.getAttribute('method') || 'POST');
        $(form).closest('.modal').modal('hide');
        open({
            request: function () { return $.post(dryRunUrl, payload); },
            confirmForm: form,
            label: form.getAttribute('data-review-label') || T.confirm_post,
            cssClass: form.getAttribute('data-review-class'),
            check: form.getAttribute('data-review'),
            needsReason: form.getAttribute('data-review-reason') === '1'
        });
    }, true);

    reasonInput.addEventListener('input', refresh);
    check.addEventListener('change', refresh);

    confirmBtn.addEventListener('click', function () {
        if (confirmBtn.disabled || !current || !check.checked || !current.confirmForm) { return; }
        var form = current.confirmForm;
        if (current.needsReason) {
            var r = form.querySelector('[name="reason"], [name="void_reason"]');
            if (!r) { r = document.createElement('input'); r.type = 'hidden'; r.name = 'reason'; form.appendChild(r); }
            r.value = reasonInput.value.trim();
        }
        var flag = form.querySelector('input[name="reviewed"]');
        if (!flag) {
            flag = document.createElement('input');
            flag.type = 'hidden';
            flag.name = 'reviewed';
            form.appendChild(flag);
        }
        flag.value = '1';
        form.setAttribute('data-reviewed', '1');
        confirmBtn.disabled = true;
        confirmBtn.textContent = T.working;
        HTMLFormElement.prototype.submit.call(form);
    });
})();
</script>
