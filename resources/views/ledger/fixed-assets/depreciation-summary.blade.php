@extends('layouts.app')
@section('title', __('ledger_assets.depreciation_summary'))

{{-- paxhitestbed admin/accounting/fixed-assets/depreciation-summary: one card per category, then the grand total. --}}
@section('content')
@include('ledger.partials.style')
@include('ledger.fixed-assets.partials.style')
@php
    $money = fn ($n) => \App\Ledger\Ledger::money($n);
    $all = collect($summary);
@endphp
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_assets.depreciation_summary')</h1>
</section>
<section class="content">
    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">@lang('ledger_assets.depreciation_summary') (@lang('ledger_assets.resume_amortissement'))</h3>
            <div class="box-tools">
                <a href="{{ route('ledger.fixed-assets.index') }}" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> @lang('ledger_assets.back')</a>
                <a href="{{ route('ledger.fixed-assets.depreciation-report') }}" class="btn btn-info btn-sm"><i class="fa fa-list"></i> @lang('ledger_assets.detailed_report')</a>
            </div>
        </div>
        <div class="box-body">
            <form action="{{ route('ledger.fixed-assets.depreciation-summary') }}" method="GET">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="fiscal_year_id">@lang('ledger_assets.fiscal_year')</label>
                            <select name="fiscal_year_id" id="fiscal_year_id" class="form-control input-sm" onchange="this.form.submit()">
                                @foreach($fiscalYears as $year)
                                <option value="{{ $year->id }}" {{ ($currentFiscalYear && $currentFiscalYear->id == $year->id) ? 'selected' : '' }}>{{ $year->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </form>

            @if($currentFiscalYear)
            <div class="ap-note" style="margin-bottom: 15px;">
                <i class="fa fa-calendar"></i> @lang('ledger_assets.showing_data_for'): <strong>{{ $currentFiscalYear->name }}</strong>
                ({{ $currentFiscalYear->start_date->format('d/m/Y') }} - {{ $currentFiscalYear->end_date->format('d/m/Y') }})
            </div>
            @endif

            <div class="row">
                @forelse($summary as $item)
                @php $pct = $item['total_acquisition_cost'] > 0 ? $item['accumulated_depreciation'] / $item['total_acquisition_cost'] * 100 : 0; @endphp
                <div class="col-md-6 col-lg-4">
                    <div class="fa-panel">
                        <div class="fa-panel-head"><i class="fa fa-folder"></i> {{ $item['category_name'] }}</div>
                        <div class="fa-panel-body">
                            <table class="table table-condensed" style="margin-bottom: 0;">
                                <tr><td>@lang('ledger_assets.number_of_assets'):</td><td class="ap-num"><strong>{{ $item['asset_count'] }}</strong></td></tr>
                                <tr><td>@lang('ledger_assets.total_acquisition_cost'):</td><td class="ap-num">{{ $money($item['total_acquisition_cost']) }}</td></tr>
                                <tr><td>@lang('ledger_assets.depreciation_this_year'):</td><td class="ap-num text-warning">{{ $money($item['depreciation_this_year']) }}</td></tr>
                                <tr><td>@lang('ledger_assets.accumulated_depreciation'):</td><td class="ap-num text-danger">{{ $money($item['accumulated_depreciation']) }}</td></tr>
                                <tr class="ap-total-row"><td>@lang('ledger_assets.net_book_value'):</td><td class="ap-num text-success">{{ $money($item['net_book_value']) }}</td></tr>
                            </table>
                            <div class="progress" style="height: 10px; margin: 8px 0 2px;">
                                <div class="progress-bar progress-bar-warning" style="width: {{ min(100, $pct) }}%;" title="{{ number_format($pct, 1, ',', ' ') }}% {{ __('ledger_assets.depreciated') }}"></div>
                            </div>
                            <small class="ap-muted">{{ number_format($pct, 1, ',', ' ') }}% @lang('ledger_assets.depreciated')</small>
                        </div>
                    </div>
                </div>
                @empty
                <div class="col-xs-12"><div class="ap-note is-warn"><i class="fa fa-exclamation-triangle"></i> @lang('ledger_assets.no_data_found')</div></div>
                @endforelse
            </div>

            @if($all->count() > 0)
            <div class="fa-panel" style="margin-top: 10px;">
                <div class="fa-panel-head"><i class="fa fa-calculator"></i> @lang('ledger_assets.grand_total')</div>
                <div class="fa-panel-body">
                    <div class="row text-center">
                        <div class="col-md-3"><h4 class="text-primary">{{ $all->sum('asset_count') }}</h4><small class="ap-muted">@lang('ledger_assets.total_assets')</small></div>
                        <div class="col-md-3"><h4 class="text-info">{{ $money($all->sum('total_acquisition_cost')) }}</h4><small class="ap-muted">@lang('ledger_assets.total_acquisition_cost')</small></div>
                        <div class="col-md-3"><h4 class="text-warning">{{ $money($all->sum('depreciation_this_year')) }}</h4><small class="ap-muted">@lang('ledger_assets.depreciation_this_year')</small></div>
                        <div class="col-md-3"><h4 class="text-success">{{ $money($all->sum('net_book_value')) }}</h4><small class="ap-muted">@lang('ledger_assets.net_book_value')</small></div>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</section>
@endsection
