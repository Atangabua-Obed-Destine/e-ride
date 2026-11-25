@extends('layouts.app')
@section('title', __('ledger_assets.asset_register'))

{{-- paxhitestbed admin/accounting/fixed-assets/register (the Asset Report). PosInnova has no departments: the second filter is the location. --}}
@section('content')
@include('ledger.partials.style')
@include('ledger.fixed-assets.partials.style')
@php $money = fn ($n) => \App\Ledger\Ledger::money($n); @endphp
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_assets.asset_register')</h1>
</section>
<section class="content">
    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">@lang('ledger_assets.asset_register') (@lang('ledger_assets.registre_immobilisations'))</h3>
            <div class="box-tools">
                <a href="{{ route('ledger.fixed-assets.index') }}" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> @lang('ledger_assets.back')</a>
                <a href="{{ route('ledger.fixed-assets.register', array_merge(request()->query(), ['export' => 'excel'])) }}" class="btn btn-success btn-sm" id="export_excel">
                    <i class="fa fa-file-excel"></i> @lang('ledger_assets.export_excel')
                </a>
            </div>
        </div>
        <div class="box-body">
            <form action="{{ route('ledger.fixed-assets.register') }}" method="GET">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="category_id">@lang('ledger_assets.category')</label>
                            <select name="category_id" id="category_id" class="form-control input-sm">
                                <option value="">@lang('ledger_assets.all_categories')</option>
                                @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="location">@lang('ledger_assets.location')</label>
                            <input type="text" name="location" id="location" class="form-control input-sm" value="{{ request('location') }}" placeholder="{{ __('ledger_assets.all_locations') }}">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="status">@lang('ledger_assets.status_title')</label>
                            <select name="status" id="status" class="form-control input-sm">
                                <option value="">@lang('ledger_assets.all')</option>
                                @foreach(['active', 'disposed'] as $s)
                                <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>@lang('ledger_assets.'.$s)</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary btn-sm btn-block"><i class="fa fa-filter"></i> @lang('ledger_assets.filter')</button>
                        </div>
                    </div>
                </div>
            </form>

            <div class="row">
                <div class="col-md-4"><div class="fa-stat is-info"><i class="fa fa-building fa-stat-icon"></i><div><div class="fa-stat-label">@lang('ledger_assets.total_acquisition_cost')</div><div class="fa-stat-value">{{ $money($totals['acquisition_cost'] ?? 0) }}</div></div></div></div>
                <div class="col-md-4"><div class="fa-stat is-warning"><i class="fa fa-chart-line fa-stat-icon"></i><div><div class="fa-stat-label">@lang('ledger_assets.accumulated_depreciation')</div><div class="fa-stat-value">{{ $money($totals['accumulated_depreciation'] ?? 0) }}</div></div></div></div>
                <div class="col-md-4"><div class="fa-stat is-success"><i class="fa fa-balance-scale fa-stat-icon"></i><div><div class="fa-stat-label">@lang('ledger_assets.net_book_value')</div><div class="fa-stat-value">{{ $money($totals['book_value'] ?? 0) }}</div></div></div></div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-condensed table-hover ap-table" id="assetRegisterTable">
                    <thead>
                        <tr>
                            <th>@lang('ledger_assets.asset_code')</th>
                            <th>@lang('ledger_assets.asset_name')</th>
                            <th>@lang('ledger_assets.category')</th>
                            <th>@lang('ledger_assets.acquisition_date')</th>
                            <th class="ap-num">@lang('ledger_assets.acquisition_cost')</th>
                            <th class="ap-num">@lang('ledger_assets.accumulated_depreciation')</th>
                            <th class="ap-num">@lang('ledger_assets.net_book_value')</th>
                            <th>@lang('ledger_assets.status_title')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($assets as $asset)
                        <tr>
                            <td><strong><a href="{{ route('ledger.fixed-assets.show', $asset->id) }}">{{ $asset->asset_code }}</a></strong></td>
                            <td>{{ $asset->name }}@if($asset->serial_number)<br><small class="ap-muted">S/N: {{ $asset->serial_number }}</small>@endif
                                @if($asset->bus_label)<br><small class="ap-muted"><i class="fa fa-bus"></i> {{ $asset->bus_label }}</small>@endif{{-- E-Ride: the bus --}}</td>
                            <td><span class="ap-chip draft">{{ $asset->category->name ?? '—' }}</span></td>
                            <td>{{ $asset->acquisition_date->format('d/m/Y') }}</td>
                            <td class="ap-num">{{ $money($asset->acquisition_cost) }}</td>
                            <td class="ap-num">{{ $money($asset->accumulated_depreciation) }}</td>
                            <td class="ap-num"><strong>{{ $money($asset->book_value) }}</strong></td>
                            <td><span class="ap-chip {{ \App\Ledger\Models\FixedAsset::statusChip($asset->status) }}">{{ $asset->getStatusLabel() }}</span></td>
                        </tr>
                        @empty
                        <tr><td colspan="8" class="text-center ap-muted">@lang('ledger_assets.no_data_found')</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="ap-total-row">
                            <td colspan="4">@lang('ledger_assets.total')</td>
                            <td class="ap-num">{{ $money($totals['acquisition_cost'] ?? 0) }}</td>
                            <td class="ap-num">{{ $money($totals['accumulated_depreciation'] ?? 0) }}</td>
                            <td class="ap-num">{{ $money($totals['book_value'] ?? 0) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</section>
@endsection
