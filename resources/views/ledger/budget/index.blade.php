@extends('layouts.app')
@section('title', __('ledger_budget.text_budgets'))

@section('content')
@include('ledger.partials.style')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_budget.text_budgets')</h1>
</section>
<section class="content">
    @include('ledger.budget.partials.messages')
    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">@lang('ledger_budget.text_budgets')</h3>
            <div class="box-tools">
                @can('budget-create')
                <a href="{{ route('ledger.budget.create') }}" class="btn btn-primary btn-sm"><i class="fa fa-plus"></i> @lang('ledger_budget.text_create_budget')</a>
                @endcan
            </div>
        </div>
        <div class="box-body">
            <form method="get" action="{{ route('ledger.budget.index') }}">
                <div class="row">
                    <div class="col-md-3 form-group">
                        <label for="type">@lang('ledger_budget.field_type')</label>
                        <select class="form-control input-sm" name="type" id="type">
                            <option value="">@lang('ledger_budget.text_all')</option>
                            @foreach(['annual', 'departmental', 'project'] as $t)
                                <option value="{{ $t }}" {{ request('type') == $t ? 'selected' : '' }}>{{ __('ledger_budget.type_'.$t) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label for="status">@lang('ledger_budget.field_status')</label>
                        <select class="form-control input-sm" name="status" id="status">
                            <option value="">@lang('ledger_budget.text_all')</option>
                            @foreach(\App\Ledger\Models\Budget::STATUSES as $s)
                                <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>{{ \App\Ledger\Models\Budget::statusLabel($s) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label for="fiscal_year">@lang('ledger_budget.field_fiscal_year')</label>
                        <select class="form-control input-sm" name="fiscal_year" id="fiscal_year">
                            <option value="">@lang('ledger_budget.text_all')</option>
                            @foreach($fiscal_years as $year)
                                <option value="{{ $year }}" {{ request('fiscal_year') == $year ? 'selected' : '' }}>{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label for="location_id">@lang('ledger_budget.field_department')</label>
                        <select class="form-control input-sm" name="location_id" id="location_id">
                            <option value="">@lang('ledger_budget.text_all')</option>
                            @foreach($locations as $l)
                                <option value="{{ $l->id }}" {{ request('location_id') == $l->id ? 'selected' : '' }}>{{ $l->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-12" style="margin-bottom: 12px;">
                        <button type="submit" class="btn btn-info btn-sm"><i class="fa fa-filter"></i> @lang('ledger_budget.btn_filter')</button>
                        <a href="{{ route('ledger.budget.index') }}" class="btn btn-default btn-sm"><i class="fa fa-refresh"></i> @lang('ledger_budget.btn_reset')</a>
                    </div>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table table-striped table-hover ap-table">
                    <thead>
                        <tr>
                            <th>@lang('ledger_budget.field_id')</th>
                            <th>@lang('ledger_budget.field_budget_code')</th>
                            <th>@lang('ledger_budget.field_title')</th>
                            <th>@lang('ledger_budget.field_type')</th>
                            <th>@lang('ledger_budget.field_fiscal_year')</th>
                            <th>@lang('ledger_budget.field_department')</th>
                            <th class="ap-num">@lang('ledger_budget.field_total_amount')</th>
                            <th style="min-width: 180px;">@lang('ledger_budget.field_utilization')</th>
                            <th>@lang('ledger_budget.field_status')</th>
                            <th>@lang('ledger_budget.field_action')</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>{{ $row->id }}</td>
                            <td><strong>{{ $row->budget_code }}</strong></td>
                            <td>
                                @if($row->is_institutional)
                                    <a href="{{ route('ledger.budget-sheet.show', $row->id) }}">{{ $row->title }}</a>
                                @else
                                    {{ $row->title }}
                                @endif
                            </td>
                            <td>
                                {{-- The annual sheet is a different instrument from a departmental pot, and says so. --}}
                                <span class="ap-chip {{ $row->is_institutional ? 'closed' : 'posted' }}">
                                    {{ $row->is_institutional ? __('ledger_budget.annual_institutional') : __('ledger_budget.type_'.$row->type) }}</span>
                            </td>
                            <td>{{ $row->fiscal_year }}</td>
                            <td>{{ optional($row->location)->name ?? '-' }}</td>
                            <td class="ap-num">{{ \App\Ledger\Ledger::money($row->total_amount) }}</td>
                            <td>
                                @include('ledger.budget.partials.progress', ['pct' => $row->utilization_percentage, 'color' => $row->utilization_color])
                                <small class="ap-muted">@lang('ledger_budget.spent_remaining', ['spent' => \App\Ledger\Ledger::money($row->spent_amount), 'remaining' => \App\Ledger\Ledger::money($row->remaining_amount)])</small>
                            </td>
                            <td>@include('ledger.budget.partials.status', ['status' => $row->status])</td>
                            <td style="white-space: nowrap;">
                                <a href="{{ $row->is_institutional ? route('ledger.budget-sheet.show', $row->id) : route('ledger.budget.show', $row->id) }}" class="btn btn-info btn-xs" title="{{ __('ledger_budget.btn_view') }}"><i class="fa fa-eye"></i></a>
                                @if(! $row->is_institutional)
                                    @can('budget-edit')
                                    @if(in_array($row->status, ['draft', 'pending_approval']))
                                        <a href="{{ route('ledger.budget.edit', $row->id) }}" class="btn btn-primary btn-xs" title="{{ __('ledger_budget.btn_edit') }}"><i class="fa fa-pencil"></i></a>
                                    @endif
                                    @endcan
                                    @can('budget-delete')
                                    @if($row->status == 'draft')
                                        <form action="{{ route('ledger.budget.delete', $row->id) }}" method="POST" style="display: inline;" onsubmit="return confirm(@json(__('ledger_budget.msg_confirm_delete')))">
                                            @csrf
                                            <button type="submit" class="btn btn-danger btn-xs" title="{{ __('ledger_budget.btn_delete') }}"><i class="fa fa-trash"></i></button>
                                        </form>
                                    @endif
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center ap-muted">@lang('ledger_budget.msg_no_record_found')</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {{ $rows->links() }}
        </div>
    </div>
</section>
@endsection
