{{-- Fapshi card body on Payment Methods. The API key and webhook secret are
     never printed back: a blank field keeps the stored value. --}}
@php
    $demo = env('APP_MODE') == 'demo';
    $hasKey = filled($values['api_key'] ?? null);
    $hasSecret = filled($values['webhook_secret'] ?? null);
    $client = new \Modules\Gateways\Services\FapshiClient($values, $mode);
    $mismatch = $client->credentialEnvironmentMismatch();
@endphp

<p class="fs-12 text-muted mb-0">
    {{ translate('Customers pay with MTN MoMo or Orange Money on a Fapshi checkout. Test mode uses sandbox.fapshi.com, Live mode uses live.fapshi.com. The credentials are under Services in your Fapshi dashboard.') }}
</p>

@if ($mismatch)
    <div class="alert alert-warning fs-12 mt-3 mb-0">
        {{ $mismatch === 'sandbox'
            ? translate('The saved key is a sandbox key but the mode is Live. Customers would see a test checkout and no money would be collected.')
            : translate('The saved key is a live key but the mode is Test.') }}
    </div>
@endif

<div class="mb-30 mt-30">
    <label class="form-label" for="fapshi-api-user">{{ translate('API User') }} *</label>
    <input type="text" class="form-control" id="fapshi-api-user" name="api_user" autocomplete="off"
           value="{{ $demo ? '' : ($values['api_user'] ?? '') }}" placeholder="{{ translate('API User') }} *">
</div>

<div class="mb-30 mt-30">
    <label class="form-label" for="fapshi-api-key">{{ translate('API Key') }} *
        @if ($hasKey)<span class="text-success fs-12 fw-normal ms-1">&#10003; {{ translate('Saved') }}</span>@endif
    </label>
    <input type="password" class="form-control" id="fapshi-api-key" name="api_key" autocomplete="new-password"
           placeholder="{{ $hasKey ? translate('Saved. Leave blank to keep it') : 'FAK_...' }}">
</div>

<div class="mb-30 mt-30">
    <label class="form-label" for="fapshi-webhook-secret">{{ translate('Webhook Secret') }}
        @if ($hasSecret)<span class="text-success fs-12 fw-normal ms-1">&#10003; {{ translate('Saved') }}</span>@endif
    </label>
    <input type="password" class="form-control" id="fapshi-webhook-secret" name="webhook_secret" autocomplete="new-password"
           placeholder="{{ $hasSecret ? translate('Saved. Leave blank to keep it') : translate('Optional, recommended') }}">
    <div class="fs-12 text-muted mt-2">
        {{ translate('In the Fapshi dashboard, set this webhook URL and the same secret:') }}
    </div>
    <div class="input-group mt-2">
        <input type="text" class="form-control fs-12" readonly id="fapshi-webhook-url" value="{{ route('fapshi.webhook') }}">
        <button type="button" class="btn btn-outline-primary fs-12" data-fapshi-copy>{{ translate('Copy') }}</button>
    </div>
</div>

<div class="row g-3 mb-30 mt-0">
    <div class="col-sm-6">
        <label class="form-label" for="fapshi-fee-percent">{{ translate('Customer fee (%)') }}</label>
        <input type="number" class="form-control" id="fapshi-fee-percent" name="fee_percent" min="0" max="20" step="0.01"
               value="{{ $values['fee_percent'] ?? 0 }}">
    </div>
    <div class="col-sm-6">
        <label class="form-label" for="fapshi-fee-fixed">{{ translate('Customer fee (fixed, FCFA)') }}</label>
        <input type="number" class="form-control" id="fapshi-fee-fixed" name="fee_fixed" min="0" max="1000" step="1"
               value="{{ $values['fee_fixed'] ?? 0 }}">
    </div>
    <div class="col-12 fs-12 text-muted mt-2">
        {{ translate('Added on top of the fare and shown to the customer before they pay, to cover the Fapshi fee. Leave both at 0 to absorb the fee. Payments are always whole francs, at least 100 FCFA.') }}
    </div>
</div>

<div class="d-flex flex-wrap align-items-center gap-3 mb-2">
    <button type="button" class="btn btn-outline-primary" data-fapshi-test
            data-url="{{ route('admin.business.configuration.third-party.payment-method.fapshi-test') }}">
        {{ translate('Test connection') }}
    </button>
    <span class="fs-12" data-fapshi-test-result></span>
</div>
<div class="fs-12 text-muted mb-30">{{ translate('Save first: the test uses the saved settings.') }}</div>

@once
    @push('script')
        <script>
            (function () {
                document.addEventListener('click', function (e) {
                    var copy = e.target.closest('[data-fapshi-copy]');
                    if (copy) {
                        var field = document.getElementById('fapshi-webhook-url');
                        field.select();
                        (navigator.clipboard ? navigator.clipboard.writeText(field.value) : Promise.reject())
                            .catch(function () { document.execCommand('copy'); });
                        copy.textContent = @json(translate('Copied'));
                        return;
                    }
                    var btn = e.target.closest('[data-fapshi-test]');
                    if (!btn) return;
                    var out = btn.parentElement.querySelector('[data-fapshi-test-result]');
                    var token = btn.closest('form').querySelector('input[name=_token]').value;
                    btn.disabled = true;
                    out.className = 'fs-12 text-muted';
                    out.textContent = @json(translate('Checking...'));
                    fetch(btn.dataset.url, {method: 'POST', headers: {'X-CSRF-TOKEN': token, 'Accept': 'application/json'}})
                        .then(function (r) { return r.json(); })
                        .then(function (d) { out.className = 'fs-12 ' + (d.ok ? 'text-success' : 'text-danger'); out.textContent = d.message; })
                        .catch(function () { out.className = 'fs-12 text-danger'; out.textContent = @json(translate('The test could not run. Reload the page and try again.')); })
                        .then(function () { btn.disabled = false; });
                });
            })();
        </script>
    @endpush
@endonce
