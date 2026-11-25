@extends('layouts.app')
@section('title', __('ledger_reports.cash_flow_statement'))

@php
    $L = fn ($k) => __('ledger_reports.'.$k);
    $cur = session('currency')['symbol'] ?? 'FCFA';
    $m = fn ($v) => \App\Ledger\Ledger::money($v);
    // paxhitestbed's format_amount($v, true): a negative amount in brackets
    $mb = fn ($v) => round((float) $v) < 0 ? '('.\App\Ledger\Ledger::money(abs($v)).')' : \App\Ledger\Ledger::money($v);
    $d = fn ($x) => \Carbon\Carbon::parse($x)->format('d/m/Y');
    $sections = [
        'operating_activities' => ['cash_flows_from_operating_activities', 'net_cash_from_operating_activities', 'box-primary', 'bg-light-blue'],
        'investing_activities' => ['cash_flows_from_investing_activities', 'net_cash_from_investing_activities', 'box-info', 'bg-aqua'],
        'financing_activities' => ['cash_flows_from_financing_activities', 'net_cash_from_financing_activities', 'box-warning', 'bg-yellow'],
    ];
@endphp

@section('content')
@include('ledger.partials.style')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ $L('cash_flow_statement') }}</h1>
    <small class="text-muted"><a href="{{ route('ledger.accounting-reports.index') }}">{{ $L('reports') }}</a> › {{ $L('cash_flow_statement') }}</small>
</section>
<section class="content">
    {{-- Filters --}}
    <div class="box box-success">
        <div class="box-header with-border"><h3 class="box-title"><i class="fas fa-filter"></i> {{ $L('report_filters') }}</h3></div>
        <div class="box-body">
            <form method="GET" action="{{ route('ledger.accounting-reports.cash-flow-statement') }}">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="fiscal_year_id">{{ $L('fiscal_year') }}</label>
                            <select class="form-control" id="fiscal_year_id" name="fiscal_year_id">
                                @foreach($fiscalYears ?? [] as $fy)
                                <option value="{{ $fy->id }}" {{ ($fiscalYear->id ?? 0) == $fy->id ? 'selected' : '' }}>{{ $fy->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="start_date">{{ $L('start_date') }}</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="{{ isset($statement) ? $statement['start_date'] : '' }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="end_date">{{ $L('end_date') }}</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="{{ isset($statement) ? $statement['end_date'] : '' }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-success btn-block"><i class="fas fa-search"></i> {{ $L('generate_report') }}</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Cash Flow Statement --}}
    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">{{ $L('cash_flow_statement') }}@if(isset($fiscalYear)) - {{ $fiscalYear->name }}@endif</h3>
            <div class="box-tools pull-right">
                <button type="button" class="btn btn-success btn-sm" onclick="exportToExcel()"><i class="fas fa-file-excel"></i> {{ $L('export_excel') }}</button>
                <button type="button" class="btn btn-danger btn-sm" onclick="exportToPdf()"><i class="fas fa-file-pdf"></i> {{ $L('export_pdf') }}</button>
            </div>
        </div>
        <div class="box-body">
            @if(isset($statement))
            <div class="row">
                <div class="col-md-8 col-md-offset-2">
                    <div class="text-center" style="margin-bottom:20px">
                        <h4>{{ session('business.name') }}</h4>
                        <h5>{{ $L('cash_flow_statement') }}</h5>
                        <p class="text-muted">{{ $L('for_the_period') }} {{ $d($statement['start_date']) }} {{ $L('to') }} {{ $d($statement['end_date']) }}</p>
                    </div>

                    @foreach($sections as $key => [$title, $net, $box, $head])
                    <div class="box {{ $box }}" id="cf_{{ $key }}">
                        <div class="box-header with-border {{ $head }}"><h3 class="box-title">{{ $L($title) }}</h3></div>
                        <div class="box-body">
                            <table class="table table-condensed ap-table">
                                <tbody>
                                    @foreach($statement[$key]['items'] ?? [] as $item)
                                    <tr data-line="{{ $item['type'] }}">
                                        <td style="padding-left: 20px;">{{ $item['description'] }}</td>
                                        <td class="ap-num {{ $item['amount'] < 0 ? 'text-danger' : '' }}">{{ $mb($item['amount']) }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="ap-total-row">
                                        <td>{{ $L($net) }}</td>
                                        <td class="ap-num {{ ($statement[$key]['total'] ?? 0) < 0 ? 'text-danger' : 'text-success' }}">{{ $mb($statement[$key]['total'] ?? 0) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    @endforeach

                    @unless($statement['is_balanced'] ?? true)
                    <div class="alert alert-danger">{{ $L('movements_do_not_explain_change_in_cash_by') }} {{ $m(abs($statement['difference'])) }} {{ $cur }}. {{ $L('check_the_trial_balance') }}</div>
                    @endunless

                    {{-- Summary --}}
                    <div class="ap-card" style="background:#f8fafc">
                        <table class="table table-borderless ap-table" style="margin-bottom:0" id="cf_summary">
                            <tr style="font-weight:600">
                                <td>{{ $L('net_increase_decrease_in_cash') }}</td>
                                <td class="ap-num {{ ($statement['net_change_in_cash'] ?? 0) < 0 ? 'text-danger' : 'text-success' }}">{{ $m($statement['net_change_in_cash'] ?? 0) }} {{ $cur }}</td>
                            </tr>
                            <tr>
                                <td>{{ $L('cash_at_beginning_of_period') }}</td>
                                <td class="ap-num">{{ $m($statement['beginning_cash_balance'] ?? 0) }} {{ $cur }}</td>
                            </tr>
                            <tr style="font-weight:600; font-size:1.1em; border-top:2px solid #cbd5e1">
                                <td>{{ $L('cash_at_end_of_period') }}</td>
                                <td class="ap-num text-primary">{{ $m($statement['ending_cash_balance'] ?? 0) }} {{ $cur }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            @else
            <div class="text-center text-muted" style="padding:40px 0">
                <i class="fas fa-chart-line fa-3x"></i>
                <p>{{ $L('select_fiscal_year_and_range') }}</p>
            </div>
            @endif
        </div>
    </div>
</section>
@endsection

@section('javascript')
<script>
function exportToExcel() {
    window.location.href = "{{ route('ledger.accounting-reports.export-excel') }}?report=cash-flow&fiscal_year_id={{ $fiscalYear->id ?? '' }}&start_date={{ $statement['start_date'] ?? '' }}&end_date={{ $statement['end_date'] ?? '' }}";
}

function exportToPdf() {
    window.location.href = "{{ route('ledger.accounting-reports.export-pdf') }}?report=cash-flow&fiscal_year_id={{ $fiscalYear->id ?? '' }}&start_date={{ $statement['start_date'] ?? '' }}&end_date={{ $statement['end_date'] ?? '' }}";
}
</script>
@endsection
