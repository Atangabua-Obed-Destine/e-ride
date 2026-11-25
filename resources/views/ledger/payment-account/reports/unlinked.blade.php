@extends('layouts.app')
@section('title', __('ledger_accounts.payment_account_report'))
@section('css')
@include('ledger.partials.style')
@endsection

{{--
  Payment account report (paxhitestbed admin/payment-account/reports/unlinked): the incomes and
  expenses recorded without a payment account, and "Link account" to put one on an account after
  the fact (once a statement shows where the money went). Linking goes through the review: the
  record's entry is re-posted from that account's ledger account.
--}}
@section('content')
@php $money = fn ($v) => \App\Ledger\Ledger::money($v); @endphp
<section class="content">
    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title mb-0" style="font-size:1.1rem">@lang('ledger_accounts.unlinked_transactions_report')</h3>
            <div class="ap-muted">@lang('ledger_accounts.link_transactions_to_payment_accounts')</div>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route($route.'.unlinked') }}">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label for="type">@lang('ledger_accounts.transaction_type')</label>
                        <select class="form-control" id="type" name="type">
                            <option value="all">@lang('ledger_accounts.all_types')</option>
                            <option value="expense" @selected($selected_type === 'expense')>@lang('ledger_accounts.expense')</option>
                            <option value="income" @selected($selected_type === 'income')>@lang('ledger_accounts.income')</option>
                        </select>
                    </div>
                    <div class="col-md-2"><label for="date_from">@lang('ledger_accounts.field_from_date')</label><input type="date" class="form-control" id="date_from" name="date_from" value="{{ $date_from }}"></div>
                    <div class="col-md-2"><label for="date_to">@lang('ledger_accounts.field_to_date')</label><input type="date" class="form-control" id="date_to" name="date_to" value="{{ $date_to }}"></div>
                    <div class="col-md-3"><label for="search">@lang('ledger_accounts.search')</label><input type="text" class="form-control" id="search" name="search" value="{{ $search }}"></div>
                    <div class="col-md-2"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter"></i> @lang('ledger_accounts.btn_filter')</button></div>
                </div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <strong>@lang('ledger_accounts.unlinked_transactions')</strong>
            <span class="ap-muted ms-2">{{ $transactions->total() }} · {{ $money($total) }} FCFA</span>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover ap-table">
                    <thead><tr><th>@lang('ledger_accounts.date')</th><th>@lang('ledger_accounts.payment_ref_no')</th><th>@lang('ledger_accounts.invoice_no')</th>
                        <th class="ap-num">@lang('ledger_accounts.amount')</th><th>@lang('ledger_accounts.payment_type')</th><th>@lang('ledger_accounts.field_payment_method')</th>
                        <th>@lang('ledger_accounts.description')</th><th>@lang('ledger_accounts.field_action')</th></tr></thead>
                    <tbody>
                        @forelse($transactions as $t)
                        <tr>
                            <td>{{ $t['date']->format('d/m/Y') }}</td>
                            <td>{{ $t['payment_ref_no'] }}</td>
                            <td>{{ $t['invoice_no'] }}</td>
                            <td class="ap-num">{{ $money($t['amount']) }}</td>
                            <td><span class="badge {{ $t['type'] === 'income' ? 'bg-success' : 'bg-danger' }}">{{ $t['payment_type'] }}</span></td>
                            <td>{{ $t['account'] }}</td>
                            <td>{{ $t['description'] }}<br><small class="ap-muted">{{ $t['category'] }}</small></td>
                            <td>
                                @can('payment-account-report-link')
                                <button type="button" class="btn btn-sm btn-primary link-account-btn" data-type="{{ $t['type'] }}" data-id="{{ $t['id'] }}" data-ref="{{ $t['payment_ref_no'] }}">
                                    <i class="fas fa-link"></i> @lang('ledger_accounts.link_account')</button>
                                @endcan
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="8" class="text-center">@lang('ledger_accounts.no_data_available')</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">{{ $transactions->links() }}</div>
    </div>
</section>

<div class="modal fade" id="linkAccountModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="{{ route($route.'.link') }}" id="linkAccountForm"
              data-review="@lang('ledger_accounts.check_link')" data-review-label="@lang('ledger_accounts.link_and_post')">
            @csrf
            <input type="hidden" name="type" id="transaction-type">
            <input type="hidden" name="id" id="transaction-id">
            <div class="modal-header">
                <h5 class="modal-title">@lang('ledger_accounts.link_account'): <span id="modal-ref-no"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="@lang('ledger_accounts.btn_close')"></button>
            </div>
            <div class="modal-body">
                <label for="payment_account_id">@lang('ledger_accounts.field_payment_account') <span class="text-danger">*</span></label>
                <select class="form-control" name="payment_account_id" id="payment_account_id" required>
                    <option value="">@lang('ledger_accounts.select_payment_account')</option>
                    @foreach($payment_accounts as $a)<option value="{{ $a->id }}">{{ $a->title }} (@lang('ledger_accounts.field_balance'): {{ $money($a->current_balance) }})</option>@endforeach
                </select>
                <div class="alert alert-info mt-3 mb-0"><i class="fas fa-info-circle"></i> @lang('ledger_accounts.link_account_help_text')</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">@lang('ledger_accounts.btn_close')</button>
                <button type="submit" class="btn btn-primary">@lang('ledger_accounts.btn_save')</button>
            </div>
        </form>
    </div></div>
</div>
@include('ledger.partials.review_modal')
@endsection

@section('javascript')
@include('ledger.partials.review_script')
<script>
$(document).on('click', '.link-account-btn', function (e) {
    e.preventDefault();
    $('#transaction-type').val($(this).data('type'));
    $('#transaction-id').val($(this).data('id'));
    $('#modal-ref-no').text($(this).data('ref'));
    $('#payment_account_id').val('');
    $('#linkAccountModal').modal('show');
});
</script>
@endsection
