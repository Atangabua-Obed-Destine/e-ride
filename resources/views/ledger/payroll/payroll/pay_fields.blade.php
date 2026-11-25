{{-- The pay fields (paxhitestbed admin/payroll/pay): pay date, payment method, the payment account it is paid from (E-Ride), note. $form: the form they belong to (for the bulk modal). --}}
@php $f = isset($form) ? 'form='.$form : ''; $sfx = $suffix ?? ''; @endphp
<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label" for="pay_date{{ $sfx }}">@lang('ledger_payroll.field_pay_date') <span class="text-danger">*</span></label>
        <input type="date" class="form-control" name="pay_date" id="pay_date{{ $sfx }}" {!! $f !!} value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" required>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label" for="payment_method{{ $sfx }}">@lang('ledger_payroll.field_payment_method') <span class="text-danger">*</span></label>
        <select class="form-select" name="payment_method" id="payment_method{{ $sfx }}" {!! $f !!} required>
            @foreach($methods as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
        </select>
    </div>
    <div class="col-md-12 mb-3">
        <label class="form-label" for="payment_account_id{{ $sfx }}">@lang('ledger_payroll.field_paid_from') <span class="text-danger">*</span></label>
        <select class="form-select" name="payment_account_id" id="payment_account_id{{ $sfx }}" {!! $f !!} required>
            <option value="">@lang('ledger_payroll.select')</option>
            @foreach($payment_accounts as $a)<option value="{{ $a->id }}">{{ $a->title }} ({{ \App\Ledger\Ledger::money($a->current_balance) }})</option>@endforeach
        </select>
        @if($payment_accounts->isEmpty())
        <small class="text-danger">@lang('ledger_payroll.no_payment_accounts') @can('payment-account-create')<a href="{{ route('ledger.payment-account.create') }}">@lang('ledger_payroll.add_payment_account')</a>@endcan</small>
        @endif
    </div>
    <div class="col-md-12 mb-1">
        <label class="form-label" for="note{{ $sfx }}">@lang('ledger_payroll.field_note')</label>
        <input type="text" class="form-control" name="note" id="note{{ $sfx }}" {!! $f !!} maxlength="255">
    </div>
</div>
