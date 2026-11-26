@extends('layouts.app')
@section('title', __('ledger_profit.title'))

{{-- Profit by bus, trip or route (E-Ride, bus books): the posted income and costs of a period, per bus record's tags. --}}
@section('content')
@include('ledger.partials.style')
@php
    $money = fn ($n) => \App\Ledger\Ledger::money($n);
    $neg = fn ($n) => $n < 0 ? 'text-danger' : '';
    $q = ['from' => $from, 'to' => $to];
@endphp
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_profit.title')
        <small>@lang('ledger_profit.subtitle')</small></h1>
</section>
<section class="content">
    <div class="box box-solid no-print">
        <div class="box-body">
            <form method="GET" action="{{ route('ledger.bus-profit.index') }}" class="row" style="row-gap: 10px;">
                <div class="col-md-3 col-sm-6">
                    <label>@lang('ledger_profit.by')</label>
                    <div class="btn-group btn-group-justified" role="group" style="display:flex;">
                        @foreach(['bus', 'trip', 'route'] as $b)
                        <a href="{{ route('ledger.bus-profit.index', $q + ['by' => $b]) }}" class="btn btn-sm {{ $by === $b ? 'btn-primary' : 'btn-default' }}" style="flex:1">@lang('ledger_profit.by_'.$b)</a>
                        @endforeach
                    </div>
                </div>
                <input type="hidden" name="by" value="{{ $by }}">
                <div class="col-md-3 col-sm-6">
                    <label for="from">@lang('ledger_profit.from')</label>
                    <input type="date" class="form-control" id="from" name="from" value="{{ $from }}">
                </div>
                <div class="col-md-3 col-sm-6">
                    <label for="to">@lang('ledger_profit.to')</label>
                    <input type="date" class="form-control" id="to" name="to" value="{{ $to }}">
                </div>
                <div class="col-md-3 col-sm-6" style="display:flex; align-items:flex-end; gap:8px;">
                    <button type="submit" class="btn btn-primary"><i class="fa fa-filter"></i> @lang('ledger_profit.show')</button>
                    <a href="{{ route('ledger.bus-profit.index', $q + ['by' => $by, 'export' => 'csv']) }}" class="btn btn-default"><i class="fa fa-file-csv"></i> CSV</a>
                </div>
            </form>
        </div>
    </div>

    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">@lang('ledger_profit.heading_'.$by, ['from' => \Carbon\Carbon::parse($from)->format('d/m/Y'), 'to' => \Carbon\Carbon::parse($to)->format('d/m/Y')])</h3>
        </div>
        <div class="box-body">
            <p class="ap-muted">@lang('ledger_profit.intro_'.$by)</p>
            <div class="table-responsive">
                <table class="table table-condensed ap-table">
                    <thead><tr>
                        <th>@lang('ledger_profit.col_'.$by)</th>
                        @if($by !== 'trip')<th class="ap-num">@lang('ledger_profit.col_trips')</th>@endif
                        <th class="ap-num">@lang('ledger_profit.col_sales')</th>
                        <th class="ap-num">@lang('ledger_profit.col_other_income')</th>
                        <th class="ap-num">@lang('ledger_profit.col_fees')</th>
                        <th class="ap-num">@lang('ledger_profit.col_running')</th>
                        <th class="ap-num">@lang('ledger_profit.col_wear')</th>
                        <th class="ap-num">@lang('ledger_profit.col_result')</th>
                        <th class="ap-num">@lang('ledger_profit.col_margin')</th>
                    </tr></thead>
                    <tbody>
                        @forelse($rows as $r)
                        <tr>
                            <td>{{ $r['label'] }}</td>
                            @if($by !== 'trip')<td class="ap-num">{{ $r['trips'] }}</td>@endif
                            <td class="ap-num">{{ $money($r['sales']) }}</td>
                            <td class="ap-num">{{ $money($r['other_income']) }}</td>
                            <td class="ap-num">{{ $money($r['fees']) }}</td>
                            <td class="ap-num">{{ $money($r['running']) }}</td>
                            <td class="ap-num">{{ $money($r['wear']) }}</td>
                            <td class="ap-num {{ $neg($r['result']) }}"><strong>{{ $money($r['result']) }}</strong></td>
                            <td class="ap-num">{{ $r['margin'] === null ? '-' : number_format($r['margin'], 1, ',', ' ').' %' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="9" class="ap-muted">@lang('ledger_profit.nothing')</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        @if($shared)
                        <tr>
                            <td><em>@lang('ledger_profit.shared_'.$by)</em><br><small class="ap-muted">@lang('ledger_profit.shared_help')</small></td>
                            @if($by !== 'trip')<td></td>@endif
                            <td class="ap-num">{{ $money($shared['sales']) }}</td>
                            <td class="ap-num">{{ $money($shared['other_income']) }}</td>
                            <td class="ap-num">{{ $money($shared['fees']) }}</td>
                            <td class="ap-num">{{ $money($shared['running']) }}</td>
                            <td class="ap-num">{{ $money($shared['wear']) }}</td>
                            <td class="ap-num {{ $neg($shared['result']) }}"><strong>{{ $money($shared['result']) }}</strong></td>
                            <td></td>
                        </tr>
                        @endif
                        <tr style="border-top: 2px solid #cbd5e1;">
                            <td><strong>@lang('ledger_profit.total')</strong></td>
                            @if($by !== 'trip')<td class="ap-num"><strong>{{ $total['trips'] }}</strong></td>@endif
                            <td class="ap-num"><strong>{{ $money($total['sales']) }}</strong></td>
                            <td class="ap-num"><strong>{{ $money($total['other_income']) }}</strong></td>
                            <td class="ap-num"><strong>{{ $money($total['fees']) }}</strong></td>
                            <td class="ap-num"><strong>{{ $money($total['running']) }}</strong></td>
                            <td class="ap-num"><strong>{{ $money($total['wear']) }}</strong></td>
                            <td class="ap-num {{ $neg($total['result']) }}"><strong>{{ $money($total['result']) }}</strong></td>
                            <td class="ap-num"><strong>{{ $total['margin'] === null ? '-' : number_format($total['margin'], 1, ',', ' ').' %' }}</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <p class="ap-muted" style="margin-top:10px;">@lang('ledger_profit.footnote')</p>
        </div>
    </div>
</section>
@endsection
