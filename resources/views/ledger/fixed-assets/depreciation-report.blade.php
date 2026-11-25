@extends('layouts.app')
@section('title', __('ledger_assets.depreciation_report'))

{{-- paxhitestbed admin/accounting/fixed-assets/depreciation-report. --}}
@section('content')
@include('ledger.partials.style')
@include('ledger.fixed-assets.partials.style')
@php $money = fn ($n) => \App\Ledger\Ledger::money($n); @endphp
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_assets.depreciation_report')</h1>
</section>
<section class="content">
    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">@lang('ledger_assets.depreciation_report') (@lang('ledger_assets.rapport_amortissement'))</h3>
            <div class="box-tools">
                <a href="{{ route('ledger.fixed-assets.index') }}" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> @lang('ledger_assets.back')</a>
                <a href="{{ route('ledger.fixed-assets.depreciation-summary') }}" class="btn btn-info btn-sm"><i class="fa fa-chart-pie"></i> @lang('ledger_assets.summary_by_category')</a>
            </div>
        </div>
        <div class="box-body">
            <form action="{{ route('ledger.fixed-assets.depreciation-report') }}" method="GET">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="fiscal_year_id">@lang('ledger_assets.fiscal_year')</label>
                            <select name="fiscal_year_id" id="fiscal_year_id" class="form-control input-sm">
                                @foreach($fiscalYears as $year)
                                <option value="{{ $year->id }}" {{ $fiscalYearId == $year->id ? 'selected' : '' }}>{{ $year->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
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
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="status">@lang('ledger_assets.status_title')</label>
                            <select name="status" id="status" class="form-control input-sm">
                                <option value="">@lang('ledger_assets.all')</option>
                                @foreach(['pending', 'posted'] as $s)
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
                <div class="col-md-4"><div class="fa-stat is-primary"><i class="fa fa-calculator fa-stat-icon"></i><div><div class="fa-stat-label">@lang('ledger_assets.total_depreciation')</div><div class="fa-stat-value">{{ $money($totals['total_depreciation'] ?? 0) }}</div></div></div></div>
                <div class="col-md-4"><div class="fa-stat is-warning"><i class="fa fa-clock fa-stat-icon"></i><div><div class="fa-stat-label">@lang('ledger_assets.pending_entries')</div><div class="fa-stat-value">{{ $totals['pending_count'] ?? 0 }}</div></div></div></div>
                <div class="col-md-4"><div class="fa-stat is-success"><i class="fa fa-check fa-stat-icon"></i><div><div class="fa-stat-label">@lang('ledger_assets.posted_entries')</div><div class="fa-stat-value">{{ $totals['posted_count'] ?? 0 }}</div></div></div></div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-condensed table-hover ap-table">
                    <thead>
                        <tr>
                            <th>@lang('ledger_assets.asset_code')</th>
                            <th>@lang('ledger_assets.asset_name')</th>
                            <th>@lang('ledger_assets.category')</th>
                            <th>@lang('ledger_assets.period')</th>
                            <th class="ap-num">@lang('ledger_assets.depreciation_amount')</th>
                            <th class="ap-num">@lang('ledger_assets.accumulated')</th>
                            <th class="ap-num">@lang('ledger_assets.remaining_value')</th>
                            <th>@lang('ledger_assets.status_title')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($schedules as $schedule)
                        <tr>
                            <td><strong>{{ $schedule->fixedAsset->asset_code ?? '—' }}</strong></td>
                            <td>{{ $schedule->fixedAsset->name ?? '—' }}</td>
                            <td><span class="ap-chip draft">{{ $schedule->fixedAsset->category->name ?? '—' }}</span></td>
                            <td>{{ $schedule->depreciation_date->translatedFormat('M Y') }}</td>
                            <td class="ap-num">{{ $money($schedule->depreciation_amount) }}</td>
                            <td class="ap-num">{{ $money($schedule->accumulated_depreciation_after) }}</td>
                            <td class="ap-num">{{ $money($schedule->book_value_after) }}</td>
                            <td>
                                <span class="ap-chip {{ \App\Ledger\Models\DepreciationSchedule::statusChip($schedule->status) }}">{{ $schedule->getStatusLabel() }}</span>
                                @if($schedule->journalEntry)<a href="{{ route('ledger.journal-entries.show', $schedule->journal_entry_id) }}" class="ap-muted">{{ $schedule->journalEntry->entry_number }}</a>@endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="8" class="text-center ap-muted">@lang('ledger_assets.no_data_found')</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="ap-total-row">
                            <td colspan="4">@lang('ledger_assets.total')</td>
                            <td class="ap-num">{{ $money($totals['total_depreciation'] ?? 0) }}</td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</section>
@endsection
