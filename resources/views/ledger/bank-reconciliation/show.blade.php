@extends('layouts.app')
@section('title', __('ledger_periodend.bank_reconciliation'))

@section('content')
@include('ledger.partials.style')
@php
    use App\Ledger\Ledger;
    use App\Ledger\Services\Preview;
    use App\Ledger\Services\BankReconciliationService;
    $account = $rec->chartOfAccount;
    $done = ! $editable;
@endphp
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_periodend.bank_reconciliation') <small>(@lang('ledger_periodend.rapprochement_bancaire'))</small></h1>
</section>
<section class="content">
    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">@lang('ledger_periodend.bank_reconciliation') — {{ $account->account_code ?? '' }} {{ $account ? Preview::accountName($account) : '' }}
                · {{ $rec->statement_date->format('d/m/Y') }}
                @if($done)<span class="ap-chip paid">@lang('ledger_periodend.completed')</span>@else<span class="ap-chip part_paid">@lang('ledger_periodend.in_progress')</span>@endif
            </h3>
            <div class="box-tools ap-toolbar">
                <a href="{{ route('ledger.bank-reconciliation.index') }}" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> @lang('ledger_periodend.back')</a>
                @if($editable)
                    @can('bank-reconciliation-complete')
                    <form method="post" action="{{ route('ledger.bank-reconciliation.complete', $rec->id) }}" id="complete-form" class="hidden">@csrf</form>
                    <button type="button" class="btn btn-success btn-sm"
                            data-review-url="{{ route('ledger.bank-reconciliation.preview-complete', $rec->id) }}"
                            data-confirm-form="complete-form" data-confirm-label="@lang('ledger_periodend.complete_reconciliation')"
                            data-confirm-check="@lang('ledger_periodend.br_complete_check')">
                        <i class="fa fa-check"></i> @lang('ledger_periodend.review_and_complete')</button>
                    @endcan
                    @can('bank-reconciliation-delete')
                    <form method="post" action="{{ route('ledger.bank-reconciliation.destroy', $rec->id) }}" style="display: inline-block;" data-confirm="@lang('ledger_periodend.br_delete_confirm')" onsubmit="return confirm(this.dataset.confirm)">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm"><i class="fa fa-trash"></i> @lang('ledger_periodend.delete')</button>
                    </form>
                    @endcan
                @endif
            </div>
        </div>

        <div class="box-body">
            <div class="row">
                <div class="col-lg-6">
                    <table class="table table-condensed ap-table" id="bank-position" style="margin-bottom: 14px;">
                        <tbody>
                            <tr><td>@lang('ledger_periodend.closing_balance_on_statement')</td><td class="ap-num" data-v="statement">{{ Ledger::money($position['statement']) }}</td></tr>
                            <tr><td>+ @lang('ledger_periodend.deposits_in_transit_long')</td><td class="ap-num" data-v="in_transit">{{ Ledger::money($position['in_transit']) }}</td></tr>
                            <tr><td>− @lang('ledger_periodend.outstanding_payments_long')</td><td class="ap-num" data-v="outstanding">{{ Ledger::money($position['outstanding']) }}</td></tr>
                            <tr class="ap-total-row"><td>= @lang('ledger_periodend.adjusted_bank_balance')</td><td class="ap-num" data-v="adjusted">{{ Ledger::money($position['adjusted']) }}</td></tr>
                            <tr><td>@lang('ledger_periodend.balance_in_books_at_date')</td><td class="ap-num" data-v="book">{{ Ledger::money($position['book']) }}</td></tr>
                            <tr class="ap-total-row"><td>@lang('ledger_periodend.difference')</td>
                                <td class="ap-num"><span data-v="difference">{{ Ledger::money($position['difference']) }}</span>
                                    <span id="diff-chip" class="ap-chip {{ abs($position['difference']) < 0.01 ? 'paid' : 'unpaid' }}">{{ abs($position['difference']) < 0.01 ? __('ledger_periodend.agrees') : __('ledger_periodend.does_not_agree') }}</span></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="col-lg-6">
                    @if($editable)
                    <div class="ap-note" style="margin-bottom: 12px;">@lang('ledger_periodend.br_show_note')</div>
                    @can('bank-reconciliation-edit')
                    <form method="post" action="{{ route('ledger.bank-reconciliation.update-balance', $rec->id) }}" class="ap-filters">
                        @csrf
                        <div class="form-group">
                            <label for="statement_closing_balance">@lang('ledger_periodend.statement_balance')</label>
                            <input type="number" step="0.01" class="form-control" name="statement_closing_balance" id="statement_closing_balance" value="{{ (float) $rec->statement_closing_balance }}">
                        </div>
                        <div class="form-group"><button class="btn btn-primary" type="submit">@lang('ledger_periodend.update')</button></div>
                    </form>
                    @endcan
                    @else
                    <div class="ap-note" style="margin-bottom: 12px;">@lang('ledger_periodend.completed_by_on', ['date' => optional($rec->reconciled_at)->format('d/m/Y'), 'name' => trim(optional($rec->reconciledBy)->user_full_name ?? '')])</div>
                    @endif
                    @if($rec->statement_reference || $rec->notes)
                    <dl class="ap-dl" style="margin-top: 10px;">
                        @if($rec->statement_reference)<dt>@lang('ledger_periodend.statement_reference')</dt><dd>{{ $rec->statement_reference }}</dd>@endif
                        @if($rec->notes)<dt>@lang('ledger_periodend.notes')</dt><dd>{{ $rec->notes }}</dd>@endif
                    </dl>
                    @endif
                </div>
            </div>

            <form method="post" action="{{ route('ledger.bank-reconciliation.ticks', $rec->id) }}" id="ticks-form">
                @csrf
                <div style="display: flex; justify-content: space-between; align-items: center; margin: 8px 0;">
                    <h4 style="margin: 0;">@lang('ledger_periodend.movements_in_books') <small class="ap-muted">({{ $position['ticked'] }} @lang('ledger_periodend.ticked'), {{ $position['unticked'] }} @lang('ledger_periodend.not_on_statement'))</small></h4>
                    @if($editable)
                    @can('bank-reconciliation-edit')
                    <div class="ap-toolbar">
                        <button type="button" class="btn btn-default btn-sm" id="tick-all">@lang('ledger_periodend.tick_all')</button>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-save"></i> @lang('ledger_periodend.save_ticks')</button>
                    </div>
                    @endcan
                    @endif
                </div>
                <div class="table-responsive">
                    <table class="table table-condensed table-hover ap-table">
                        <thead>
                            <tr>
                                <th style="width: 40px;">@lang('ledger_periodend.on_statement')</th>
                                <th>@lang('ledger_periodend.date')</th>
                                <th>@lang('ledger_periodend.entry')</th>
                                <th>@lang('ledger_periodend.description')</th>
                                <th class="ap-num">@lang('ledger_periodend.money_in')</th>
                                <th class="ap-num">@lang('ledger_periodend.money_out')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rec->items as $item)
                            <tr>
                                <td class="text-center">
                                    <input type="checkbox" name="cleared[]" value="{{ $item->id }}" class="tick" data-effect="{{ $item->effect }}" data-amount="{{ (float) $item->amount }}"
                                           aria-label="@lang('ledger_periodend.on_statement')" @checked($item->is_cleared) @disabled(! $editable)>
                                </td>
                                <td class="text-nowrap">{{ $item->transaction_date->format('d/m/Y') }}</td>
                                <td class="text-nowrap">
                                    @if($item->journal_entry_id)<a href="{{ route('ledger.journal-entries.show', $item->journal_entry_id) }}">{{ $item->reference_number }}</a>@else{{ $item->reference_number }}@endif
                                    @if($item->isAdjustment())<span class="ap-chip posted">{{ $item->item_type === 'bank_charge' ? __('ledger_periodend.charge') : __('ledger_periodend.interest') }}</span>@endif
                                </td>
                                <td>{{ $item->description }}</td>
                                <td class="ap-num">{{ $item->effect === 'add' ? Ledger::money($item->amount) : '' }}</td>
                                <td class="ap-num">{{ $item->effect === 'subtract' ? Ledger::money($item->amount) : '' }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="ap-muted">@lang('ledger_periodend.no_movement')</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </form>

            @if($editable)
            @can('bank-reconciliation-edit')
            <h4 style="margin: 20px 0 8px;">@lang('ledger_periodend.post_charge_or_interest')</h4>
            <form method="post" action="{{ route('ledger.bank-reconciliation.add-adjustment', $rec->id) }}" id="adj-form" class="ap-filters">
                @csrf
                <div class="form-group">
                    <label for="adj-kind">@lang('ledger_periodend.what')</label>
                    <select name="kind" id="adj-kind" class="form-control" required>
                        <option value="bank_charge">@lang('ledger_periodend.bank_charges')</option>
                        <option value="bank_interest">@lang('ledger_periodend.bank_interest')</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="adj-amount">@lang('ledger_periodend.amount')</label>
                    <input type="number" step="0.01" min="0.01" name="amount" id="adj-amount" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="adj-date">@lang('ledger_periodend.date')</label>
                    <input type="date" name="date" id="adj-date" class="form-control" required value="{{ $rec->statement_date->toDateString() }}" max="{{ $rec->statement_date->toDateString() }}">
                </div>
                <div class="form-group" style="min-width: 260px;">
                    <label for="adj-account">@lang('ledger_periodend.account')</label>
                    <select name="account_id" id="adj-account" class="form-control" required>
                        @foreach(['bank_charge' => 'accounts_for_charges', 'bank_interest' => 'accounts_for_interest'] as $kind => $groupLabel)
                        <optgroup label="@lang('ledger_periodend.'.$groupLabel)" data-kind="{{ $kind }}">
                            @foreach($adjustmentAccounts[$kind] as $a)
                            <option value="{{ $a->id }}" data-kind="{{ $kind }}" @selected($a->account_code === BankReconciliationService::defaultAdjustmentCode($kind))>{{ $a->account_code }} {{ Preview::accountName($a) }}</option>
                            @endforeach
                        </optgroup>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="adj-desc">@lang('ledger_periodend.note')</label>
                    <input type="text" name="description" id="adj-desc" class="form-control" maxlength="150">
                </div>
                <div class="form-group">
                    <button type="button" class="btn btn-success"
                            data-review-url="{{ route('ledger.bank-reconciliation.preview-adjustment', $rec->id) }}"
                            data-review-form="adj-form" data-confirm-form="adj-form" data-confirm-label="@lang('ledger_periodend.post_and_tick')"
                            data-confirm-check="@lang('ledger_periodend.br_adjust_check')">
                        <i class="fa fa-search"></i> @lang('ledger_periodend.review_and_post')</button>
                </div>
            </form>
            @endcan
            @endif
        </div>
    </div>
    @include('ledger.partials.review_modal')
</section>
@endsection

@section('javascript')
@include('ledger.partials.review_script')
<script>
(function () {
    var ticks = document.querySelectorAll('.tick');
    var pos = {!! json_encode(['book' => (float) $position['book'], 'statement' => (float) $position['statement']]) !!};
    var fmt = function (v) { return (Math.round(v * 100) / 100).toLocaleString('fr-FR', {minimumFractionDigits: 0, maximumFractionDigits: 2}); };
    var agrees = {!! json_encode(__('ledger_periodend.agrees')) !!};
    var differs = {!! json_encode(__('ledger_periodend.does_not_agree')) !!};

    // Recompute the position as boxes are ticked, so the accountant sees the
    // difference close before saving. The server recomputes on save.
    function refresh() {
        var inTransit = 0, outstanding = 0;
        ticks.forEach(function (t) {
            if (t.checked) { return; }
            var a = parseFloat(t.getAttribute('data-amount')) || 0;
            if (t.getAttribute('data-effect') === 'add') { inTransit += a; } else { outstanding += a; }
        });
        var adjusted = pos.statement + inTransit - outstanding;
        var diff = pos.book - adjusted;
        var set = function (k, v) { var el = document.querySelector('[data-v="' + k + '"]'); if (el) { el.textContent = fmt(v); } };
        set('in_transit', inTransit); set('outstanding', outstanding); set('adjusted', adjusted); set('difference', diff);
        var chip = document.getElementById('diff-chip');
        var ok = Math.abs(diff) < 0.01;
        chip.className = 'ap-chip ' + (ok ? 'paid' : 'unpaid');
        chip.textContent = ok ? agrees : differs;
    }
    ticks.forEach(function (t) { t.addEventListener('change', refresh); });
    var all = document.getElementById('tick-all');
    if (all) { all.addEventListener('click', function () { ticks.forEach(function (t) { if (!t.disabled) { t.checked = true; } }); refresh(); }); }

    // Offer only the accounts that suit the kind chosen.
    var kind = document.getElementById('adj-kind');
    var account = document.getElementById('adj-account');
    function filterAccounts() {
        if (!kind || !account) { return; }
        account.querySelectorAll('optgroup').forEach(function (g) {
            var show = g.getAttribute('data-kind') === kind.value;
            g.hidden = !show;
            g.style.display = show ? '' : 'none';
            g.querySelectorAll('option').forEach(function (o) { o.disabled = !show; });
        });
        var sel = account.options[account.selectedIndex];
        if (!sel || sel.disabled) {
            var group = account.querySelector('optgroup[data-kind="' + kind.value + '"]');
            var pick = group && (group.querySelector('option[selected]') || group.querySelector('option'));
            if (pick) { pick.selected = true; }
        }
    }
    if (kind) { kind.addEventListener('change', filterAccounts); filterAccounts(); }
})();
</script>
@endsection
