@extends('layouts.app')
@section('title', __('ledger_budget.ie_sheet'))

@section('content')
@include('ledger.partials.style')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_budget.ie_sheet')
        <small>@lang('ledger_budget.ie_sheet_intro')</small></h1>
</section>
<section class="content">
    @include('ledger.budget.partials.messages')
    <div class="row">
        <div class="col-md-8">
            <div class="box box-solid">
                <div class="box-header with-border"><h3 class="box-title">@lang('ledger_budget.sheets')</h3></div>
                <div class="box-body">
                    <div class="table-responsive">
                        <table class="table table-striped ap-table">
                            <thead>
                                <tr>
                                    <th>@lang('ledger_budget.period')</th>
                                    <th>@lang('ledger_budget.covering')</th>
                                    <th class="ap-num">@lang('ledger_budget.opening_balance')</th>
                                    <th>@lang('ledger_budget.status')</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($budgets as $row)
                                    <tr>
                                        <td>{{ $row->title }}</td>
                                        <td>{{ $row->start_date->format('d/m/Y') }} &ndash; {{ $row->end_date->format('d/m/Y') }}</td>
                                        <td class="ap-num">{{ \App\Ledger\Ledger::money($row->opening_balance) }}</td>
                                        <td>@include('ledger.budget.partials.status', ['status' => $row->status])</td>
                                        <td class="text-right" style="white-space: nowrap;">
                                            <a href="{{ route('ledger.budget-sheet.show', $row->id) }}" class="btn btn-xs btn-primary">@lang('ledger_budget.open')</a>
                                            @if($row->status === 'draft')
                                                @can('budget-delete')
                                                <form action="{{ route('ledger.budget-sheet.delete', $row->id) }}" method="post" style="display: inline;"
                                                      onsubmit="return confirm(@json(__('ledger_budget.confirm_delete_sheet')))">
                                                    @csrf
                                                    <button type="submit" class="btn btn-xs btn-danger">@lang('ledger_budget.delete')</button>
                                                </form>
                                                @endcan
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center ap-muted">@lang('ledger_budget.no_sheet_yet')</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        @canany(['budget-edit', 'budget-create'])
        <div class="col-md-4">
            <div class="box box-solid">
                <div class="box-header with-border"><h3 class="box-title">@lang('ledger_budget.new_sheet')</h3></div>
                <div class="box-body">
                    <form action="{{ route('ledger.budget-sheet.store') }}" method="post">
                        @csrf
                        <div class="form-group">
                            <label for="title">@lang('ledger_budget.period') <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="title" id="title" value="{{ old('title', date('Y')) }}" required>
                        </div>
                        <div class="form-group">
                            <label for="start_date">@lang('ledger_budget.starts') <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="start_date" id="start_date" value="{{ old('start_date', date('Y').'-01-01') }}" required>
                        </div>
                        <div class="form-group">
                            <label for="end_date">@lang('ledger_budget.ends') <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="end_date" id="end_date" value="{{ old('end_date', date('Y').'-12-31') }}" required>
                        </div>
                        <div class="form-group">
                            <label for="opening_balance">@lang('ledger_budget.opening_balance')</label>
                            <input type="number" step="0.01" class="form-control" name="opening_balance" id="opening_balance"
                                   value="{{ old('opening_balance') }}" placeholder="{{ __('ledger_budget.opening_placeholder') }}">
                            <p class="help-block">@lang('ledger_budget.opening_help')</p>
                        </div>
                        <hr>
                        <div class="form-group">
                            <label for="seed_from">@lang('ledger_budget.start_from')</label>
                            <select class="form-control" name="seed_from" id="seed_from">
                                <option value="">@lang('ledger_budget.seed_empty')</option>
                                <option value="actual" {{ old('seed_from', 'actual') === 'actual' ? 'selected' : '' }}>@lang('ledger_budget.seed_actual')</option>
                                <option value="budget" {{ old('seed_from') === 'budget' ? 'selected' : '' }}>@lang('ledger_budget.seed_budget')</option>
                            </select>
                            <p class="help-block">@lang('ledger_budget.seed_help')</p>
                        </div>
                        <div class="form-group" id="uplift_group">
                            <label for="uplift_percent">@lang('ledger_budget.adjust_by')</label>
                            <div class="input-group">
                                <input type="number" step="0.1" class="form-control" name="uplift_percent" id="uplift_percent" value="{{ old('uplift_percent', 0) }}" min="-100" max="1000">
                                <span class="input-group-addon">%</span>
                            </div>
                            <p class="help-block">@lang('ledger_budget.adjust_help')</p>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">@lang('ledger_budget.create_sheet')</button>
                    </form>
                </div>
            </div>
        </div>
        @endcanany
    </div>
</section>
@endsection
