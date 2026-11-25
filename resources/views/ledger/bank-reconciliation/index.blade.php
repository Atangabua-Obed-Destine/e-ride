@extends('layouts.app')
@section('title', __('ledger_periodend.bank_reconciliation'))

@section('content')
@include('ledger.partials.style')
@php use App\Ledger\Ledger; use App\Ledger\Services\Preview; @endphp
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_periodend.bank_reconciliation') <small>(@lang('ledger_periodend.rapprochement_bancaire'))</small></h1>
</section>
<section class="content">
    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">@lang('ledger_periodend.bank_reconciliation')</h3>
            <div class="box-tools">
                @can('bank-reconciliation-create')
                <a href="{{ route('ledger.bank-reconciliation.create') }}" class="btn btn-primary btn-sm"><i class="fa fa-plus"></i> @lang('ledger_periodend.new_reconciliation')</a>
                @endcan
            </div>
        </div>
        <div class="box-body">
            <p class="ap-muted" style="margin-bottom: 14px;">@lang('ledger_periodend.br_intro')</p>

            <h4 style="margin: 0 0 8px;">@lang('ledger_periodend.accounts')</h4>
            <div class="table-responsive" style="margin-bottom: 20px;">
                <table class="table table-condensed ap-table">
                    <thead>
                        <tr>
                            <th>@lang('ledger_periodend.account')</th>
                            <th class="ap-num">@lang('ledger_periodend.balance_in_books_today')</th>
                            <th>@lang('ledger_periodend.reconciled_to')</th>
                            <th class="ap-num">@lang('ledger_periodend.statement_balance_then')</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($accounts as $a)
                        @php $l = $last[$a->id]; @endphp
                        <tr>
                            <td>{{ $a->account_code }} {{ Preview::accountName($a) }}</td>
                            <td class="ap-num">{{ Ledger::money($l['book']) }}</td>
                            <td>@if($l['last']){{ $l['last']->statement_date->format('d/m/Y') }}@else<span class="ap-chip unpaid">@lang('ledger_periodend.never_reconciled')</span>@endif</td>
                            <td class="ap-num">{{ $l['last'] ? Ledger::money($l['last']->statement_closing_balance) : '—' }}</td>
                            <td class="text-right">
                                @can('bank-reconciliation-create')
                                <a href="{{ route('ledger.bank-reconciliation.create', ['account_id' => $a->id]) }}" class="btn btn-xs btn-primary">@lang('ledger_periodend.reconcile')</a>
                                @endcan
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="ap-muted">@lang('ledger_periodend.no_accounts')</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <h4 style="margin: 0 0 8px;">@lang('ledger_periodend.reconciliations')</h4>
            <form method="get" class="ap-filters" style="margin-bottom: 12px;">
                <div class="form-group">
                    <label for="account_id">@lang('ledger_periodend.account')</label>
                    <select name="account_id" id="account_id" class="form-control">
                        <option value="">@lang('ledger_periodend.all_accounts')</option>
                        @foreach($accounts as $a)
                        <option value="{{ $a->id }}" @selected(request('account_id') == $a->id)>{{ $a->account_code }} {{ Preview::accountName($a) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="status">@lang('ledger_periodend.status')</label>
                    <select name="status" id="status" class="form-control">
                        <option value="">@lang('ledger_periodend.all')</option>
                        <option value="in_progress" @selected(request('status') === 'in_progress')>@lang('ledger_periodend.in_progress')</option>
                        <option value="completed" @selected(request('status') === 'completed')>@lang('ledger_periodend.completed')</option>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-info"><i class="fa fa-filter"></i> @lang('ledger_periodend.filter')</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-striped ap-table">
                    <thead>
                        <tr>
                            <th>@lang('ledger_periodend.account')</th>
                            <th>@lang('ledger_periodend.statement_date')</th>
                            <th>@lang('ledger_periodend.reference')</th>
                            <th class="ap-num">@lang('ledger_periodend.statement_balance')</th>
                            <th class="ap-num">@lang('ledger_periodend.balance_in_books')</th>
                            <th class="ap-num">@lang('ledger_periodend.difference')</th>
                            <th>@lang('ledger_periodend.status')</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $r)
                        <tr>
                            <td>{{ $r->chartOfAccount->account_code ?? '' }} {{ $r->chartOfAccount ? Preview::accountName($r->chartOfAccount) : '' }}</td>
                            <td>{{ $r->statement_date->format('d/m/Y') }}</td>
                            <td>{{ $r->statement_reference ?: '—' }}</td>
                            <td class="ap-num">{{ Ledger::money($r->statement_closing_balance) }}</td>
                            <td class="ap-num">{{ Ledger::money($r->book_balance) }}</td>
                            <td class="ap-num">{{ Ledger::money($r->difference) }}</td>
                            <td>
                                @if(in_array($r->status, ['completed', 'approved']))
                                <span class="ap-chip paid">@lang('ledger_periodend.completed')</span>
                                @else
                                <span class="ap-chip part_paid">@lang('ledger_periodend.in_progress')</span>
                                @endif
                            </td>
                            <td class="text-right"><a href="{{ route('ledger.bank-reconciliation.show', $r->id) }}" class="btn btn-xs btn-info" title="@lang('ledger_periodend.view')"><i class="fa fa-eye"></i></a></td>
                        </tr>
                        @empty
                        <tr><td colspan="8" class="ap-muted">@lang('ledger_periodend.no_reconciliation_yet')</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div>{{ $rows->links() }}</div>
        </div>
    </div>
</section>
@endsection
