{{-- A money account, and the agent whose cash it is when it is the counter cash. Params: $label, $idp (id prefix), $required --}}
<div class="col-sm-6" data-money-wrap>
    <label class="form-label" for="{{ $idp }}_money">{{ $label }}</label>
    <select class="form-select" id="{{ $idp }}_money" name="money_account_id" data-money-select @if($required ?? true) required @endif>
        <option value="">{{ translate('Choose') }}</option>
        @foreach($moneyAccounts as $ma)
            <option value="{{ $ma->id }}" data-role="{{ $ma->role }}">{{ $ma->label() }}</option>
        @endforeach
    </select>
</div>
<div class="col-sm-6" data-agent-wrap hidden>
    <label class="form-label" for="{{ $idp }}_agent">{{ translate('Agent whose cash it is') }}</label>
    <select class="form-select" id="{{ $idp }}_agent" name="staff_user_id">
        <option value="">{{ translate('Choose') }}</option>
        @foreach($agents as $ag)<option value="{{ $ag['id'] }}">{{ $ag['name'] }}</option>@endforeach
    </select>
</div>
@once
@push('script')
<script>
    document.addEventListener('change', function (e) {
        if (!e.target.matches('[data-money-select]')) return;
        var row = e.target.closest('.row'), wrap = row && row.querySelector('[data-agent-wrap]');
        if (!wrap) return;
        var cash = (e.target.selectedOptions[0] || {}).getAttribute && e.target.selectedOptions[0].getAttribute('data-role') === 'counter_cash';
        wrap.hidden = !cash;
        wrap.querySelector('select').required = !!cash;
    });
</script>
@endpush
@endonce
