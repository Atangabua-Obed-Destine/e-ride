@extends('layouts.app')
@section('title', __('ledger_reports.comparative_cash_flow'))

@php
    $L = fn ($k) => __('ledger_reports.'.$k);
    $pct = fn ($v) => number_format((float) $v, 1, ',', ' ').'%';
    $money = fn ($v) => round((float) $v) < 0 ? '('.\App\Ledger\Ledger::money(abs($v)).')' : \App\Ledger\Ledger::money($v);
@endphp

@section('content')
@include('ledger.partials.style')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ $L('comparative_cash_flow') }}</h1>
    <small class="text-muted"><a href="{{ route('ledger.accounting-reports.index') }}">{{ $L('reports') }}</a> › {{ $L('comparative_cash_flow') }}</small>
</section>
<section class="content">
    {{-- Filters --}}
    <div class="box box-primary">
        <div class="box-header with-border"><h3 class="box-title"><i class="fas fa-filter"></i> {{ $L('report_filters') }}</h3></div>
        <div class="box-body">
            <form method="GET" action="{{ route('ledger.accounting-reports.comparative-cash-flow') }}">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="fiscal_year_id">{{ $L('current_fiscal_year') }}</label>
                            <select class="form-control" id="fiscal_year_id" name="fiscal_year_id">
                                @foreach($fiscalYears ?? [] as $fy)
                                <option value="{{ $fy->id }}" {{ ($currentFiscalYear->id ?? 0) == $fy->id ? 'selected' : '' }}>{{ $fy->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-search"></i> {{ $L('generate_report') }}</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Comparative Statement --}}
    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">{{ $L('comparative_cash_flow_statement') }}</h3>
            <div class="box-tools pull-right">
                <button type="button" class="btn btn-success btn-sm" onclick="exportToExcel()"><i class="fas fa-file-excel"></i> {{ $L('export_excel') }}</button>
                <button type="button" class="btn btn-danger btn-sm" onclick="exportToPdf()"><i class="fas fa-file-pdf"></i> {{ $L('export_pdf') }}</button>
            </div>
        </div>
        <div class="box-body table-responsive">
            @if(isset($statement))
            <table class="table table-bordered table-hover ap-table" id="ccf_table">
                <thead>
                    <tr>
                        <th style="background:#3c8dbc !important; color:#fff !important">{{ $L('cash_flow_category') }}</th>
                        <th class="ap-num" style="background:#3c8dbc !important; color:#fff !important">{{ $statement['periods']['current']['name'] }}</th>
                        <th class="ap-num" style="background:#3c8dbc !important; color:#fff !important">{{ $statement['periods']['previous']['name'] }}</th>
                        <th class="ap-num" style="background:#3c8dbc !important; color:#fff !important">{{ $L('change') }}</th>
                        <th class="ap-num" style="background:#3c8dbc !important; color:#fff !important">{{ $L('pct_change') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(['operating' => $L('operating_activities'), 'investing' => $L('investing_activities'), 'financing' => $L('financing_activities')] as $key => $label)
                    <tr style="background:#f4f4f4; font-weight:600">
                        <td colspan="5">{{ $label }}</td>
                    </tr>
                    @forelse($statement[$key]['items'] as $item)
                    <tr>
                        <td style="padding-left: 30px;">{{ $item['description'] }}</td>
                        <td class="ap-num">{{ $money($item['current']) }}</td>
                        <td class="ap-num">{{ $money($item['previous']) }}</td>
                        <td class="ap-num {{ $item['variance'] < 0 ? 'text-danger' : 'text-success' }}">{{ $money($item['variance']) }}</td>
                        <td class="ap-num">{{ $pct($item['change_percent']) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-muted" style="padding-left: 30px;">{{ $L('no_movement') }}</td></tr>
                    @endforelse
                    <tr style="font-weight:600" data-total="{{ $key }}">
                        <td>{{ $L('net_cash_from_'.$key.'_lc') }}</td>
                        <td class="ap-num">{{ $money($statement[$key]['current_total']) }}</td>
                        <td class="ap-num">{{ $money($statement[$key]['previous_total']) }}</td>
                        <td class="ap-num {{ $statement[$key]['variance'] < 0 ? 'text-danger' : 'text-success' }}">{{ $money($statement[$key]['variance']) }}</td>
                        <td class="ap-num">{{ $pct($statement[$key]['change_percent']) }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr style="background:#6c757d; color:#fff; font-weight:600">
                        <td>{{ $L('net_change_in_cash') }}</td>
                        <td class="ap-num">{{ $money($statement['net_change']['current']) }}</td>
                        <td class="ap-num">{{ $money($statement['net_change']['previous']) }}</td>
                        <td class="ap-num">{{ $money($statement['net_change']['variance']) }}</td>
                        <td class="ap-num">{{ $pct($statement['net_change']['change_percent']) }}</td>
                    </tr>
                </tfoot>
            </table>
            @else
            <div class="text-center text-muted" style="padding:40px 0">
                <i class="fas fa-chart-line fa-3x"></i>
                <p>{{ $L('select_fiscal_year_comparative') }}</p>
            </div>
            @endif
        </div>
    </div>
</section>
@endsection

@section('javascript')
<script>
function exportToExcel() {
    window.location.href = "{{ route('ledger.accounting-reports.export-excel') }}?report=comparative-cash-flow&fiscal_year_id={{ $currentFiscalYear->id ?? '' }}";
}

function exportToPdf() {
    window.location.href = "{{ route('ledger.accounting-reports.export-pdf') }}?report=comparative-cash-flow&fiscal_year_id={{ $currentFiscalYear->id ?? '' }}";
}
</script>
@endsection
