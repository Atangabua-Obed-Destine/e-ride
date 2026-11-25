@extends('layouts.app')
@section('title', __('ledger_fy.fiscal_years'))

@section('content')
@include('ledger.partials.style')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_fy.fiscal_years') <small>(@lang('ledger_fy.exercices_comptables'))</small></h1>
</section>
<section class="content">
    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">@lang('ledger_fy.fiscal_years')</h3>
            <div class="box-tools">
                @can('accounting-period-create')
                <a href="{{ route('ledger.fiscal-years.create') }}" class="btn btn-primary btn-sm"><i class="fa fa-plus"></i> @lang('ledger_fy.add_fiscal_year')</a>
                @endcan
            </div>
        </div>
        <div class="box-body">
            <div class="table-responsive">
                <table class="table table-bordered ap-table">
                    <thead>
                        <tr>
                            <th width="5%">@lang('ledger_fy.sl')</th>
                            <th>@lang('ledger_fy.fiscal_year_name')</th>
                            <th>@lang('ledger_fy.start_date')</th>
                            <th>@lang('ledger_fy.end_date')</th>
                            <th>@lang('ledger_fy.duration')</th>
                            <th width="10%" class="text-center">@lang('ledger_fy.periods')</th>
                            <th width="10%">@lang('ledger_fy.status')</th>
                            <th width="20%">@lang('ledger_fy.action')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($fiscalYears as $key => $year)
                        <tr>
                            <td>{{ $key + 1 }}</td>
                            <td>
                                <strong>{{ $year->name }}</strong>
                                @if($year->is_active)
                                <span class="ap-chip open" style="margin-left: 6px;">@lang('ledger_fy.active')</span>
                                @endif
                                @if($year->is_closed)
                                <span class="ap-chip closed" style="margin-left: 6px;">@lang('ledger_fy.closed')</span>
                                @endif
                                @if($year->shape_warning)
                                <br><small class="text-warning"><i class="fa fa-exclamation-triangle"></i> @lang('ledger_fy.shape_title'): {{ $year->shape_warning }}</small>
                                @endif
                            </td>
                            <td>{{ $year->start_date->format('d/m/Y') }}</td>
                            <td>{{ $year->end_date->format('d/m/Y') }}</td>
                            <td>{{ $year->duration_in_months }} @lang('ledger_fy.months')</td>
                            <td class="text-center"><span class="label label-info">{{ $year->accounting_periods_count }}</span></td>
                            <td>
                                @if($year->is_closed)
                                <span class="ap-chip closed">@lang('ledger_fy.closed')</span>
                                @elseif($year->is_active)
                                <span class="ap-chip open">@lang('ledger_fy.active')</span>
                                @else
                                <span class="ap-chip draft">@lang('ledger_fy.inactive')</span>
                                @endif
                            </td>
                            <td class="text-nowrap">
                                @can('accounting-period-view')
                                <a href="{{ route('ledger.fiscal-years.show', $year->id) }}" class="btn btn-info btn-xs" title="@lang('ledger_fy.view')"><i class="fa fa-eye"></i></a>
                                @endcan

                                @can('accounting-period-edit')
                                @if(! $year->is_closed)
                                <a href="{{ route('ledger.fiscal-years.edit', $year->id) }}" class="btn btn-primary btn-xs" title="@lang('ledger_fy.edit')"><i class="fa fa-edit"></i></a>
                                @endif
                                @endcan

                                @can('accounting-period-create')
                                @if($year->accounting_periods_count == 0)
                                <form action="{{ route('ledger.fiscal-years.generate-periods', $year->id) }}" method="POST" style="display: inline-block;">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-xs" title="@lang('ledger_fy.generate_periods')" onclick="return confirm({{ json_encode(__('ledger_fy.generate_12_monthly_periods').'?') }})"><i class="fa fa-calendar-plus"></i></button>
                                </form>
                                @endif
                                @endcan

                                @can('accounting-period-edit')
                                @if(! $year->is_active && ! $year->is_closed)
                                <form action="{{ route('ledger.fiscal-years.set-active', $year->id) }}" method="POST" style="display: inline-block;">
                                    @csrf
                                    <button type="submit" class="btn btn-warning btn-xs" title="@lang('ledger_fy.set_active')" onclick="return confirm({{ json_encode(__('ledger_fy.set_as_active_year').'?') }})"><i class="fa fa-check-circle"></i></button>
                                </form>
                                @endif
                                @endcan

                                @can('accounting-period-close')
                                @if(! $year->is_closed && $year->accounting_periods_count > 0)
                                <form action="{{ route('ledger.fiscal-years.close', $year->id) }}" method="POST" data-review="{{ __('ledger_fy.confirm_close_fiscal_year') }}" data-review-label="{{ __('ledger_fy.close_year') }}" data-review-class="btn-danger" style="display: inline-block;">
                                    @csrf
                                    <button type="submit" class="btn btn-danger btn-xs" title="@lang('ledger_fy.close_year')"><i class="fa fa-lock"></i></button>
                                </form>
                                @endif
                                @endcan

                                @can('accounting-period-reopen')
                                @if($year->is_closed)
                                <form action="{{ route('ledger.fiscal-years.reopen', $year->id) }}" method="POST" data-review="{{ __('ledger_fy.confirm_reopen_fiscal_year') }}" data-review-label="{{ __('ledger_fy.reopen_year') }}" data-review-class="btn-success" style="display: inline-block;">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-xs" title="@lang('ledger_fy.reopen_year')"><i class="fa fa-unlock"></i></button>
                                </form>
                                @endif
                                @endcan

                                @can('accounting-period-delete')
                                @if(! $year->is_closed && $year->journal_entries_count == 0)
                                <form action="{{ route('ledger.fiscal-years.destroy', $year->id) }}" method="POST" style="display: inline-block;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-xs" title="@lang('ledger_fy.delete')" onclick="return confirm({{ json_encode(__('ledger_fy.are_you_sure')) }})"><i class="fa fa-trash"></i></button>
                                </form>
                                @endif
                                @endcan
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="8" class="text-center">@lang('ledger_fy.no_data_found')</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
@include('ledger.partials.review_modal')
@endsection

@section('javascript')
@include('ledger.partials.review_script')
@endsection
