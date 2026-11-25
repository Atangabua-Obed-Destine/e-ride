@extends('layouts.app')
@section('title', __('ledger_fy.fiscal_year_details'))

@section('content')
@include('ledger.partials.style')
@php $periods = $fiscalYear->accountingPeriods->sortBy('period_number'); @endphp
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_fy.fiscal_year_details') <small>{{ $fiscalYear->name }}</small></h1>
</section>
<section class="content">
    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">@lang('ledger_fy.fiscal_year_details')</h3>
            <div class="box-tools">
                @can('accounting-period-edit')
                @if(! $fiscalYear->is_closed)
                <a href="{{ route('ledger.fiscal-years.edit', $fiscalYear->id) }}" class="btn btn-primary btn-sm"><i class="fa fa-edit"></i> @lang('ledger_fy.edit')</a>
                @endif
                @endcan
                <a href="{{ route('ledger.fiscal-years.index') }}" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> @lang('ledger_fy.back')</a>
            </div>
        </div>
        <div class="box-body">
            @if($shapeWarning)
            <div class="ap-note is-warn" style="margin-bottom: 14px;"><strong>@lang('ledger_fy.shape_title'):</strong> {{ $shapeWarning }}</div>
            @endif

            <div class="row">
                {{-- Fiscal year information --}}
                <div class="col-md-6">
                    <table class="table table-bordered ap-table">
                        <tr><th width="40%">@lang('ledger_fy.fiscal_year_name')</th><td><strong>{{ $fiscalYear->name }}</strong></td></tr>
                        <tr><th>@lang('ledger_fy.start_date')</th><td>{{ $fiscalYear->start_date->format('d/m/Y') }}</td></tr>
                        <tr><th>@lang('ledger_fy.end_date')</th><td>{{ $fiscalYear->end_date->format('d/m/Y') }}</td></tr>
                        <tr><th>@lang('ledger_fy.duration')</th><td>{{ $fiscalYear->duration_in_months }} @lang('ledger_fy.months')</td></tr>
                        <tr>
                            <th>@lang('ledger_fy.status')</th>
                            <td>
                                @if($fiscalYear->is_closed)
                                <span class="ap-chip closed">@lang('ledger_fy.closed')</span>
                                @elseif($fiscalYear->is_active)
                                <span class="ap-chip open">@lang('ledger_fy.active')</span>
                                @else
                                <span class="ap-chip draft">@lang('ledger_fy.inactive')</span>
                                @endif
                            </td>
                        </tr>
                        @if($fiscalYear->is_closed)
                        <tr><th>@lang('ledger_fy.closed_by')</th><td>{{ optional($fiscalYear->closedBy)->user_full_name ?? '-' }}</td></tr>
                        <tr><th>@lang('ledger_fy.closed_at')</th><td>{{ $fiscalYear->closed_at ? $fiscalYear->closed_at->format('d/m/Y H:i') : '-' }}</td></tr>
                        @endif
                    </table>
                </div>

                {{-- Statistics --}}
                <div class="col-md-6">
                    <table class="table table-bordered ap-table">
                        <tr><th width="40%">@lang('ledger_fy.total_periods')</th><td><strong>{{ $periods->count() }}</strong></td></tr>
                        <tr><th>@lang('ledger_fy.closed_periods')</th><td>{{ $periods->where('is_closed', true)->count() }}</td></tr>
                        <tr><th>@lang('ledger_fy.total_journal_entries')</th><td>{{ $entryCount }}</td></tr>
                        <tr><th>@lang('ledger_fy.posted_entries')</th><td>{{ $postedCount }}</td></tr>
                        <tr><th>@lang('ledger_fy.created_at')</th><td>{{ optional($fiscalYear->created_at)->format('d/m/Y H:i') }}</td></tr>
                        <tr><th>@lang('ledger_fy.updated_at')</th><td>{{ optional($fiscalYear->updated_at)->format('d/m/Y H:i') }}</td></tr>
                    </table>
                </div>
            </div>

            {{-- Accounting periods --}}
            <div class="row" style="margin-top: 16px;">
                <div class="col-md-12">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <h4 style="margin: 0;">@lang('ledger_fy.accounting_periods') ({{ $periods->count() }})</h4>
                        @can('accounting-period-create')
                        @if($periods->count() == 0 && ! $fiscalYear->is_closed)
                        <form action="{{ route('ledger.fiscal-years.generate-periods', $fiscalYear->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-success btn-sm" onclick="return confirm({{ json_encode(__('ledger_fy.generate_12_monthly_periods').'?') }})"><i class="fa fa-calendar-plus"></i> @lang('ledger_fy.generate_periods')</button>
                        </form>
                        @endif
                        @endcan
                    </div>

                    @if($periods->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-bordered table-condensed ap-table">
                            <thead>
                                <tr>
                                    <th width="8%" class="text-center">@lang('ledger_fy.period_number')</th>
                                    <th>@lang('ledger_fy.period_name')</th>
                                    <th>@lang('ledger_fy.start_date')</th>
                                    <th>@lang('ledger_fy.end_date')</th>
                                    <th width="10%" class="text-center">@lang('ledger_fy.entries')</th>
                                    <th width="10%">@lang('ledger_fy.status')</th>
                                    <th width="14%"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($periods as $period)
                                @php
                                    $label = \App\Ledger\Models\FiscalYear::periodLabel($period);
                                    $other = $label === $period->name ? $period->french_name : $period->name;
                                @endphp
                                <tr>
                                    <td class="text-center"><strong>{{ $period->period_number }}</strong></td>
                                    <td>
                                        <strong>{{ $label }}</strong>
                                        @if($other && $other != $label)
                                        <br><small class="text-muted">{{ $other }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $period->start_date->format('d/m/Y') }}</td>
                                    <td>{{ $period->end_date->format('d/m/Y') }}</td>
                                    <td class="text-center"><span class="label label-info">{{ $period->journal_entries_count }}</span></td>
                                    <td>
                                        @if($period->is_closed)
                                        <span class="ap-chip closed">@lang('ledger_fy.closed')</span>
                                        @else
                                        <span class="ap-chip open">@lang('ledger_fy.open')</span>
                                        @endif
                                    </td>
                                    <td class="text-right text-nowrap">
                                        @if(! $fiscalYear->is_closed)
                                        @if(! $period->is_closed)
                                        @can('accounting-period-close')
                                        <form method="post" action="{{ route('ledger.accounting-periods.close', $period->id) }}" id="close-period-{{ $period->id }}" class="hidden">@csrf</form>
                                        <button type="button" class="btn btn-xs btn-danger"
                                                data-review-url="{{ route('ledger.accounting-periods.preview-close', $period->id) }}"
                                                data-confirm-form="close-period-{{ $period->id }}" data-confirm-label="@lang('ledger_fy.close_month')" data-confirm-class="btn-danger"
                                                data-confirm-check="@lang('ledger_fy.close_check')">
                                            <i class="fa fa-lock"></i> @lang('ledger_fy.close')</button>
                                        @endcan
                                        @else
                                        @can('accounting-period-reopen')
                                        <form method="post" action="{{ route('ledger.accounting-periods.reopen', $period->id) }}" id="reopen-period-{{ $period->id }}" class="hidden">@csrf</form>
                                        <button type="button" class="btn btn-xs btn-default"
                                                data-review-url="{{ route('ledger.accounting-periods.preview-reopen', $period->id) }}"
                                                data-confirm-form="reopen-period-{{ $period->id }}" data-confirm-label="@lang('ledger_fy.reopen_month')" data-needs-reason="1"
                                                data-confirm-check="@lang('ledger_fy.reopen_check')">
                                            <i class="fa fa-lock-open"></i> @lang('ledger_fy.reopen')</button>
                                        @endcan
                                        @endif
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <p class="text-muted">@lang('ledger_fy.no_periods_generated')</p>
                    @endif
                </div>
            </div>

            {{-- Quick actions --}}
            <div class="row" style="margin-top: 16px;">
                <div class="col-md-12">
                    <h4>@lang('ledger_fy.quick_actions')</h4>
                    <div class="ap-toolbar">
                        @can('accounting-period-edit')
                        @if(! $fiscalYear->is_active && ! $fiscalYear->is_closed)
                        <form action="{{ route('ledger.fiscal-years.set-active', $fiscalYear->id) }}" method="POST" style="display: inline-block;">
                            @csrf
                            <button type="submit" class="btn btn-warning" onclick="return confirm({{ json_encode(__('ledger_fy.set_as_active_year').'?') }})"><i class="fa fa-check-circle"></i> @lang('ledger_fy.set_active')</button>
                        </form>
                        @endif
                        @endcan

                        @can('accounting-period-close')
                        @if(! $fiscalYear->is_closed && $periods->count() > 0)
                        <form action="{{ route('ledger.fiscal-years.close', $fiscalYear->id) }}" method="POST" data-review="{{ __('ledger_fy.confirm_close_fiscal_year') }}" data-review-label="{{ __('ledger_fy.close_year') }}" data-review-class="btn-danger" style="display: inline-block;">
                            @csrf
                            <button type="submit" class="btn btn-danger"><i class="fa fa-lock"></i> @lang('ledger_fy.close_year')</button>
                        </form>
                        @endif
                        @endcan

                        @can('accounting-period-reopen')
                        @if($fiscalYear->is_closed)
                        <form action="{{ route('ledger.fiscal-years.reopen', $fiscalYear->id) }}" method="POST" data-review="{{ __('ledger_fy.confirm_reopen_fiscal_year') }}" data-review-label="{{ __('ledger_fy.reopen_year') }}" data-review-class="btn-success" style="display: inline-block;">
                            @csrf
                            <button type="submit" class="btn btn-success"><i class="fa fa-unlock"></i> @lang('ledger_fy.reopen_year')</button>
                        </form>
                        @endif
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    </div>
    @include('ledger.partials.review_modal')
</section>
@endsection

@section('javascript')
@include('ledger.partials.review_script')
@endsection
