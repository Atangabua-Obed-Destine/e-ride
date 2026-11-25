@extends('layouts.app')
@section('title', __('ledger_accounts.account_book'))
@section('css')
@include('ledger.partials.style')
@endsection

{{-- A payment account's book (paxhitestbed admin/payment-account/account-book): every movement with its running balance. --}}
@section('content')
@php $money = fn ($v) => \App\Ledger\Ledger::money($v); @endphp
<section class="content">
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0" style="font-size:1.1rem">@lang('ledger_accounts.account_book'): {{ $payment_account->title }}</h3>
            <a href="{{ route('ledger.payment-account.index') }}" class="btn btn-light btn-sm"><i class="fas fa-arrow-left"></i> @lang('ledger_accounts.btn_back')</a>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <p><strong>@lang('ledger_accounts.account_type'):</strong> {{ $payment_account->accountType?->label() }}</p>
                    <p><strong>@lang('ledger_accounts.account_number'):</strong> {{ $payment_account->account_number ?? '-' }}</p>
                    <p><strong>@lang('ledger_accounts.ledger_account'):</strong> {{ \App\Ledger\Services\PaymentBook::accountLabel($payment_account->ledgerAccount) }}</p>
                </div>
                <div class="col-md-3">
                    <p><strong>@lang('ledger_accounts.opening_balance'):</strong> {{ $money($payment_account->opening_balance) }}</p>
                    <p><strong>@lang('ledger_accounts.current_balance'):</strong>
                        <span class="badge {{ $payment_account->current_balance < 0 ? 'bg-danger' : 'bg-success' }}" style="font-size:1rem">{{ $money($payment_account->current_balance) }}</span></p>
                </div>
                <div class="col-md-5 text-end">
                    @can('payment-account-deposit')
                    <a href="{{ route('ledger.payment-account.deposit', $payment_account->id) }}" class="btn btn-success"><i class="fas fa-plus-circle"></i> @lang('ledger_accounts.deposit')</a>
                    @endcan
                    @can('payment-account-withdraw')
                    <a href="{{ route('ledger.payment-account.withdraw', $payment_account->id) }}" class="btn btn-warning"><i class="fas fa-minus-circle"></i> @lang('ledger_accounts.withdraw')</a>
                    @endcan
                    @can('payment-account-report-statement')
                    <a href="{{ route('ledger.payment-account-report.statement', $payment_account->id) }}" class="btn btn-outline-primary"><i class="fas fa-file-alt"></i> @lang('ledger_accounts.account_statement')</a>
                    @endcan
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h3 class="card-title mb-0" style="font-size:1rem">@lang('ledger_accounts.filter_transactions')</h3></div>
        <div class="card-body">
            <form action="{{ route('ledger.payment-account.account-book', $payment_account->id) }}" method="GET">
                <div class="row align-items-end">
                    <div class="col-md-3"><label for="date_from">@lang('ledger_accounts.field_from_date')</label><input type="date" class="form-control" id="date_from" name="date_from" value="{{ request('date_from') }}"></div>
                    <div class="col-md-3"><label for="date_to">@lang('ledger_accounts.field_to_date')</label><input type="date" class="form-control" id="date_to" name="date_to" value="{{ request('date_to') }}"></div>
                    <div class="col-md-3">
                        <label for="transaction_type">@lang('ledger_accounts.transaction_type')</label>
                        <select class="form-control" id="transaction_type" name="transaction_type">
                            <option value="">@lang('ledger_accounts.all')</option>
                            <option value="credit" @selected(request('transaction_type') === 'credit')>@lang('ledger_accounts.credit_in')</option>
                            <option value="debit" @selected(request('transaction_type') === 'debit')>@lang('ledger_accounts.debit_out')</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> @lang('ledger_accounts.btn_filter')</button>
                        <a href="{{ route('ledger.payment-account.account-book', $payment_account->id) }}" class="btn btn-secondary"><i class="fas fa-redo"></i> @lang('ledger_accounts.reset')</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title mb-0" style="font-size:1rem">@lang('ledger_accounts.transactions')</h3></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped ap-table">
                    <thead>
                        <tr>
                            <th width="5%">@lang('ledger_accounts.sl')</th>
                            <th width="10%">@lang('ledger_accounts.date')</th>
                            <th>@lang('ledger_accounts.field_title')</th>
                            <th>@lang('ledger_accounts.description')</th>
                            <th width="8%">@lang('ledger_accounts.type')</th>
                            <th width="10%" class="ap-num">@lang('ledger_accounts.debit_out')</th>
                            <th width="10%" class="ap-num">@lang('ledger_accounts.credit_in')</th>
                            <th width="12%" class="ap-num">@lang('ledger_accounts.field_balance')</th>
                            <th width="10%">@lang('ledger_accounts.field_action')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transactions as $key => $t)
                        @php $st = $states[(string) $t->id] ?? null; @endphp
                        <tr>
                            <td>{{ $transactions->firstItem() + $key }}</td>
                            <td>{{ $t->transaction_date->format('d/m/Y') }}</td>
                            <td>{{ $t->title }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($t->description, 50) }}
                                <br><small class="badge bg-secondary">{{ __('ledger_accounts.ref_'.($t->reference_type ?: 'manual')) }}</small>
                                @if($t->isManual())
                                    @if($t->contra_account_id && $st && $st->status === 'posted')<small class="ap-chip posted">@lang('ledger_accounts.posted')</small>
                                    @elseif($st && $st->status === 'failed')<small class="ap-chip failed" title="{{ $st->error }}">@lang('ledger_accounts.not_posted')</small>
                                    @elseif(! $t->contra_account_id)<small class="ap-muted">@lang('ledger_accounts.book_only')</small>@endif
                                @endif
                            </td>
                            <td>@if($t->transaction_type === 'debit')<span class="badge bg-danger">@lang('ledger_accounts.debit_out')</span>@else<span class="badge bg-success">@lang('ledger_accounts.credit_in')</span>@endif</td>
                            <td class="ap-num">@if($t->transaction_type === 'debit')<span class="text-danger">{{ $money($t->amount) }}</span>@else - @endif</td>
                            <td class="ap-num">@if($t->transaction_type === 'credit')<span class="text-success">{{ $money($t->amount) }}</span>@else - @endif</td>
                            <td class="ap-num"><strong>{{ $money($t->balance_after) }}</strong></td>
                            <td class="text-nowrap">
                                @if($t->attach)<a href="{{ \App\Http\Controllers\Ledger\IncomeExpenseController::fileUrl($t->attach) }}" target="_blank" class="btn btn-sm btn-info" title="@lang('ledger_accounts.view_attachment')"><i class="fas fa-paperclip"></i></a>@endif
                                @if($t->reference_type === 'transfer')
                                    <a href="{{ route('ledger.payment-account-transfer.index') }}" class="btn btn-sm btn-secondary" title="@lang('ledger_accounts.view_transfer')"><i class="fas fa-exchange-alt"></i></a>
                                @elseif($t->isManual())
                                    @can('payment-account-transaction-edit')
                                    <a href="{{ route('ledger.payment-account.transaction.edit', $t->id) }}" class="btn btn-sm btn-primary" title="@lang('ledger_accounts.btn_edit')"><i class="fas fa-edit"></i></a>
                                    @endcan
                                    @can('payment-account-transaction-delete')
                                    <form action="{{ route('ledger.payment-account.transaction.delete', $t->id) }}" method="POST" class="d-inline"
                                          data-review="@lang('ledger_accounts.check_delete_transaction')" data-review-label="@lang('ledger_accounts.delete_and_reverse')" data-review-class="btn-danger">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger" title="@lang('ledger_accounts.btn_delete')"><i class="fas fa-trash"></i></button>
                                    </form>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="9" class="text-center">@lang('ledger_accounts.no_transactions_found')</td></tr>
                        @endforelse
                    </tbody>
                    @if($transactions->count() > 0)
                    <tfoot class="bg-light">
                        <tr>
                            <th colspan="5" class="text-end">@lang('ledger_accounts.total'):</th>
                            <th class="ap-num text-danger">{{ $money(collect($transactions->items())->where('transaction_type', 'debit')->sum('amount')) }}</th>
                            <th class="ap-num text-success">{{ $money(collect($transactions->items())->where('transaction_type', 'credit')->sum('amount')) }}</th>
                            <th colspan="2"></th>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>
        <div class="card-footer">{{ $transactions->links() }}</div>
    </div>
</section>
@include('ledger.partials.review_modal')
@endsection

@section('javascript')
@include('ledger.partials.review_script')
@endsection
