@extends('layouts.app')
@section('title', $title)
@section('css')
@include('ledger.partials.style')
@if($print)<style>.ledger-nav, .ledger-top, .no-print { display: none !important; }</style>@endif
@endsection

{{-- Payables aging (paxhitestbed admin/supplier-bill/aging): what is owed to each supplier on a date, by how long past due, checked against the ledger. --}}
@section('content')
@php
    $money = fn ($v) => \App\Ledger\Ledger::money($v);
    $buckets = ['not_due' => __('ledger_accounts.not_yet_due'), 'd1_30' => __('ledger_accounts.d1_30'), 'd31_60' => __('ledger_accounts.d31_60'),
        'd61_90' => __('ledger_accounts.d61_90'), 'over_90' => __('ledger_accounts.over_90')];
@endphp
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="gap:10px">
        <div>
            <h5 class="mb-0">@lang('ledger_accounts.payables_aging')</h5>
            <div class="ap-muted">@lang('ledger_accounts.aging_owed_on') {{ \Carbon\Carbon::parse($report['as_of'])->format('d/m/Y') }}, @lang('ledger_accounts.aging_by_overdue')</div>
        </div>
        <form method="get" class="ap-filters no-print">
            <div class="form-group"><label for="as_of">@lang('ledger_accounts.as_of')</label><input type="date" name="as_of" id="as_of" class="form-control" value="{{ $report['as_of'] }}"></div>
            <div class="form-group">
                <button type="submit" class="btn btn-info">@lang('ledger_accounts.show')</button>
                <a href="{{ route($route.'.aging', ['as_of' => $report['as_of'], 'print' => 1]) }}" target="_blank" class="btn btn-light"><i class="fas fa-print"></i> @lang('ledger_accounts.btn_print')</a>
            </div>
        </form>
    </div>
    <div class="card-body no-print">@include('ledger.supplier-bill.nav')</div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table ap-table">
                <thead><tr><th>@lang('ledger_accounts.supplier')</th>
                    @foreach($buckets as $label)<th class="ap-num">{{ $label }}</th>@endforeach
                    <th class="ap-num">@lang('ledger_accounts.total_owed')</th></tr></thead>
                <tbody>
                    @forelse($report['suppliers'] as $s)
                    <tr>
                        <td>{{ $s['name'] }} <span class="ap-muted">({{ count($s['bills']) }} {{ trans_choice('ledger_accounts.bills_count', count($s['bills'])) }})</span></td>
                        @foreach(array_keys($buckets) as $b)<td class="ap-num">{{ $s[$b] > 0 ? $money($s[$b]) : '-' }}</td>@endforeach
                        <td class="ap-num"><strong>{{ $money($s['total']) }}</strong></td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center ap-muted">@lang('ledger_accounts.nothing_owed')</td></tr>
                    @endforelse
                    <tr class="ap-total-row"><td>@lang('ledger_accounts.total')</td>
                        @foreach(array_keys($buckets) as $b)<td class="ap-num">{{ $money($report['totals'][$b]) }}</td>@endforeach
                        <td class="ap-num">{{ $money($report['totals']['total']) }}</td></tr>
                </tbody>
            </table>
        </div>
        <dl class="ap-dl mt-3">
            <dt>@lang('ledger_accounts.owed_on_bills')</dt><dd>{{ $money($report['totals']['total']) }}</dd>
            <dt>@lang('ledger_accounts.ledger_balance_suppliers')</dt><dd>{{ $money($report['ledger_balance']) }}</dd>
            <dt>@lang('ledger_accounts.difference')</dt><dd>{{ $money($report['difference']) }}</dd>
        </dl>
        @if(abs($report['difference']) > 0.005)
        <div class="ap-note is-warn mt-2">@lang('ledger_accounts.aging_differs')</div>
        @else
        <div class="ap-note mt-2">@lang('ledger_accounts.aging_agrees')</div>
        @endif
    </div>
</div>
@if(count($report['suppliers']))
<div class="card">
    <div class="card-header"><h6 class="mb-0">@lang('ledger_accounts.bills_behind')</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table ap-table">
                <thead><tr><th>@lang('ledger_accounts.supplier')</th><th>@lang('ledger_accounts.bill')</th><th>@lang('ledger_accounts.their_invoice')</th>
                    <th>@lang('ledger_accounts.due')</th><th class="ap-num">@lang('ledger_accounts.days_overdue')</th><th class="ap-num">@lang('ledger_accounts.owed')</th></tr></thead>
                <tbody>
                    @foreach($report['suppliers'] as $s)
                        @foreach($s['bills'] as $b)
                        <tr>
                            <td>{{ $s['name'] }}</td>
                            <td><a href="{{ route($route.'.show', $b->id) }}">{{ $b->number }}</a></td>
                            <td>{{ $b->supplier_reference ?? '-' }}</td>
                            <td>{{ \Carbon\Carbon::parse($b->due_date)->format('d/m/Y') }}</td>
                            <td class="ap-num">{{ $b->days_overdue ?: '-' }}</td>
                            <td class="ap-num">{{ $money($b->owed) }}</td>
                        </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif
@endsection

@section('javascript')
@if($print)<script>window.addEventListener('load', function () { window.print(); });</script>@endif
@endsection
