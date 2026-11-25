{{-- The fields of a deposit, a withdrawal or its correction ($t: the movement being corrected, or null). --}}
@php $v = fn ($f, $d = null) => old($f, $t->{$f} ?? $d); @endphp
<div class="row">
    <div class="col-md-6 mb-3">
        <label for="amount">@lang('ledger_accounts.amount') (FCFA) <span class="text-danger">*</span></label>
        <input type="number" step="1" min="1" class="form-control" id="amount" name="amount" value="{{ old('amount', $t ? (int) round($t->amount) : '') }}" required>
    </div>
    <div class="col-md-6 mb-3">
        <label for="transaction_date">@lang('ledger_accounts.transaction_date') <span class="text-danger">*</span></label>
        <input type="date" class="form-control" id="transaction_date" name="transaction_date" max="{{ now()->toDateString() }}"
               value="{{ old('transaction_date', $t ? $t->transaction_date->format('Y-m-d') : date('Y-m-d')) }}" required>
    </div>
    <div class="col-md-12 mb-3">
        <label for="title">@lang('ledger_accounts.field_title') <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="title" name="title" value="{{ $v('title') }}" required maxlength="255">
    </div>
    <div class="col-md-6 mb-3">
        <label for="payment_method">@lang('ledger_accounts.field_payment_method')</label>
        <input type="text" class="form-control" id="payment_method" name="payment_method" value="{{ $v('payment_method') }}" placeholder="@lang('ledger_accounts.method_placeholder')" maxlength="255">
    </div>
    <div class="col-md-6 mb-3">
        <label for="payment_reference">@lang('ledger_accounts.field_reference')</label>
        <input type="text" class="form-control" id="payment_reference" name="payment_reference" value="{{ $v('payment_reference') }}" maxlength="255">
    </div>
    <div class="col-md-12 mb-3">
        <label for="contra_account_id">@lang('ledger_accounts.other_side')</label>
        <select class="form-control" id="contra_account_id" name="contra_account_id">
            <option value="">@lang('ledger_accounts.not_posted_option')</option>
            @foreach($contra_accounts as $c)
            <option value="{{ $c->id }}" @selected((string) $v('contra_account_id') === (string) $c->id)>{{ \App\Ledger\Services\PaymentBook::accountLabel($c) }}</option>
            @endforeach
        </select>
        <small class="text-muted">@lang('ledger_accounts.other_side_help')</small>
    </div>
    <div class="col-md-12 mb-3">
        <label for="description">@lang('ledger_accounts.description')</label>
        <textarea class="form-control" id="description" name="description" rows="2">{{ $v('description') }}</textarea>
    </div>
    <div class="col-md-12 mb-3">
        <label for="attach">@lang('ledger_accounts.attachment')</label>
        <input type="file" class="form-control" id="attach" name="attach" accept=".jpg,.jpeg,.png,.pdf">
        <small class="text-muted">@lang('ledger_accounts.allowed_files')</small>
    </div>
</div>
