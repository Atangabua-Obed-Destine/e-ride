@extends('layouts.app')
@section('title', __('ledger_periodend.year_end_closing_details'))

@section('content')
@include('ledger.partials.style')
@php
    use App\Ledger\Ledger;
    use App\Ledger\Services\Preview;
    $year = $closing->fiscalYear;
    $generated = in_array($closing->status, ['pending_approval', 'completed', 'reversed']);
    $net = $generated ? (float) $closing->net_income : $projection['net'];
    $items = $closing->checklistItems();
    $editing = $closing->status === 'in_progress';
    $blocking = collect($checks)->filter(fn ($c) => ! $c['ok'] && $c['blocking']);
@endphp
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_periodend.year_end_closing') <small>(@lang('ledger_periodend.cloture_exercice'))</small></h1>
</section>
<section class="content">
    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">@lang('ledger_periodend.year_end_closing'): {{ $year->name ?? 'N/A' }}
                <span class="ap-chip {{ $closing->getStatusChip() }}">{{ $closing->getStatusLabel() }}</span>
                <span class="ap-muted">{{ $closing->closing_reference }}</span></h3>
            <div class="box-tools">
                <a href="{{ route('ledger.year-end-closing.index') }}" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> @lang('ledger_periodend.back')</a>
            </div>
        </div>
        <div class="box-body">
            {{-- Summary --}}
            <div class="row">
                <div class="col-md-3 col-sm-6">
                    <div class="info-box"><span class="info-box-icon bg-aqua"><i class="fa fa-calendar"></i></span>
                        <div class="info-box-content"><span class="info-box-text">@lang('ledger_periodend.fiscal_year')</span><span class="info-box-number">{{ $year->name ?? 'N/A' }}</span></div></div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="info-box"><span class="info-box-icon bg-blue"><i class="fa fa-calendar-check"></i></span>
                        <div class="info-box-content"><span class="info-box-text">@lang('ledger_periodend.closing_date')</span><span class="info-box-number">{{ $closing->closing_date->format('d/m/Y') }}</span></div></div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="info-box"><span class="info-box-icon {{ $net >= 0 ? 'bg-green' : 'bg-red' }}"><i class="fa fa-coins"></i></span>
                        <div class="info-box-content"><span class="info-box-text">@lang('ledger_periodend.net_income'){{ $generated ? '' : ' ('.__('ledger_periodend.projected').')' }}</span><span class="info-box-number">{{ Ledger::money($net) }}</span></div></div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="info-box"><span class="info-box-icon bg-yellow"><i class="fa fa-user"></i></span>
                        <div class="info-box-content"><span class="info-box-text">@lang('ledger_periodend.prepared_by')</span><span class="info-box-number" style="font-size: 15px;">{{ trim(optional($closing->creator)->user_full_name ?? '') ?: 'N/A' }}</span></div></div>
                </div>
            </div>

            {{-- The system's checks --}}
            @if($editing)
            <h4 style="margin-top: 6px;"><i class="fa fa-clipboard-check"></i> @lang('ledger_periodend.system_checks')</h4>
            <div class="table-responsive">
                <table class="table table-condensed ap-table">
                    <thead><tr><th>@lang('ledger_periodend.task')</th><th width="12%">@lang('ledger_periodend.status')</th><th>@lang('ledger_periodend.detail')</th></tr></thead>
                    <tbody>
                        @foreach($checks as $c)
                        <tr>
                            <td>{{ $c['label'] }}</td>
                            <td>@if($c['ok'])<span class="ap-chip paid">@lang('ledger_periodend.chk_state_ok')</span>@elseif($c['blocking'])<span class="ap-chip unpaid">@lang('ledger_periodend.chk_state_todo')</span>@else<span class="ap-chip part_paid">@lang('ledger_periodend.chk_state_check')</span>@endif</td>
                            <td>{{ $c['detail'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            {{-- Checklist --}}
            <h4 style="margin-top: 16px;"><i class="fa fa-tasks"></i> @lang('ledger_periodend.closing_checklist') <small class="ap-muted">{{ $closing->getChecklistCompletedCount() }}/{{ count($items) }}</small></h4>
            <form action="{{ route('ledger.year-end-closing.update-checklist', $closing->id) }}" method="POST">
                @csrf
                <table class="table table-bordered ap-table">
                    <thead>
                        <tr>
                            <th width="6%" class="text-center">@lang('ledger_periodend.done')</th>
                            <th>@lang('ledger_periodend.task')</th>
                            <th width="18%">@lang('ledger_periodend.completed_by')</th>
                            <th width="15%">@lang('ledger_periodend.completed_at')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $key => $item)
                        <tr class="{{ ! empty($item['completed']) ? 'success' : '' }}">
                            <td class="text-center">
                                @if($editing)
                                @can('year-end-closing-edit')
                                <input type="checkbox" name="checklist[{{ $key }}][completed]" value="1" @checked(! empty($item['completed'])) aria-label="@lang('ledger_periodend.ci_'.$key)">
                                @else
                                <i class="fa fa-{{ ! empty($item['completed']) ? 'check-circle text-success' : 'circle text-muted' }}"></i>
                                @endcan
                                @else
                                <i class="fa fa-{{ ! empty($item['completed']) ? 'check-circle text-success' : 'circle text-muted' }}"></i>
                                @endif
                            </td>
                            <td>@lang('ledger_periodend.ci_'.$key)</td>
                            <td>{{ $item['completed_by'] ?? '-' }}</td>
                            <td>{{ ! empty($item['completed_at']) ? \Carbon\Carbon::parse($item['completed_at'])->format('d/m/Y H:i') : '-' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @if($editing)
                @can('year-end-closing-edit')
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> @lang('ledger_periodend.save_checklist')</button>
                @endcan
                @endif
            </form>

            {{-- Stock of goods --}}
            <h4 style="margin-top: 20px;"><i class="fa fa-boxes"></i> @lang('ledger_periodend.stock_of_goods')</h4>
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-condensed ap-table">
                        <tr><td>@lang('ledger_periodend.opening_stock_on_books')</td><td class="ap-num">{{ Ledger::money($generated ? $closing->opening_stock_value : $projection['opening_stock']) }}</td></tr>
                        <tr><td>@lang('ledger_periodend.stock_valuation_at', ['date' => $closing->closing_date->format('d/m/Y')])</td><td class="ap-num">{{ Ledger::money($valuation) }}</td></tr>
                        <tr class="ap-total-row"><td>@lang('ledger_periodend.closing_stock_counted')</td><td class="ap-num">{{ $closing->closing_stock_value === null ? '—' : Ledger::money($closing->closing_stock_value) }}</td></tr>
                        <tr><td>@lang('ledger_periodend.stock_change')</td><td class="ap-num">{{ $projection['stock_change'] === null ? '—' : Ledger::money($projection['stock_change']) }}</td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    @if(in_array($closing->status, ['draft', 'in_progress']))
                    @can('year-end-closing-edit')
                    <div class="ap-note" style="margin-bottom: 10px;">@lang('ledger_periodend.stock_note')</div>
                    <form action="{{ route('ledger.year-end-closing.stock', $closing->id) }}" method="POST" class="ap-filters">
                        @csrf
                        <div class="form-group">
                            <label for="closing_stock_value">@lang('ledger_periodend.closing_stock_counted')</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="closing_stock_value" name="closing_stock_value" required
                                   value="{{ old('closing_stock_value', $closing->closing_stock_value !== null ? (float) $closing->closing_stock_value : $valuation) }}">
                        </div>
                        <div class="form-group"><button type="submit" class="btn btn-primary">@lang('ledger_periodend.save_stock')</button></div>
                    </form>
                    @endcan
                    @endif
                </div>
            </div>

            {{-- The result --}}
            <h4 style="margin-top: 16px;"><i class="fa fa-balance-scale"></i> @lang('ledger_periodend.result_of_the_year')</h4>
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-condensed ap-table">
                        <tr><td>@lang('ledger_periodend.total_revenue')</td><td class="ap-num">{{ Ledger::money($generated ? $closing->total_revenue : $projection['revenue']) }}</td></tr>
                        <tr><td>@lang('ledger_periodend.total_expenses_before_stock')</td><td class="ap-num">{{ Ledger::money($projection['expenses_before_stock']) }}</td></tr>
                        <tr><td>@lang('ledger_periodend.stock_change')</td><td class="ap-num">{{ $projection['stock_change'] === null ? '—' : Ledger::money($projection['stock_change']) }}</td></tr>
                        <tr><td>@lang('ledger_periodend.total_expenses')</td><td class="ap-num">{{ Ledger::money($generated ? $closing->total_expenses : $projection['expenses']) }}</td></tr>
                        <tr class="ap-total-row"><td>@lang('ledger_periodend.net_income')</td><td class="ap-num">{{ Ledger::money($net) }}</td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <dl class="ap-dl">
                        <dt>@lang('ledger_periodend.income_summary_account')</dt><dd>{{ $closing->incomeSummaryAccount ? $closing->incomeSummaryAccount->account_code.' '.Preview::accountName($closing->incomeSummaryAccount) : '-' }}</dd>
                        <dt>@lang('ledger_periodend.retained_earnings_account')</dt><dd>{{ $closing->retainedEarningsAccount ? $closing->retainedEarningsAccount->account_code.' '.Preview::accountName($closing->retainedEarningsAccount) : '-' }}</dd>
                        @if($closing->approved_at)
                        <dt>@lang('ledger_periodend.approved_by')</dt><dd>{{ trim(optional($closing->approver)->user_full_name ?? '') ?: '-' }} · {{ $closing->approved_at->format('d/m/Y H:i') }}</dd>
                        @endif
                    </dl>
                </div>
            </div>

            {{-- Closing entries --}}
            @if($closing->closing_journal_entry_id || $closing->stock_journal_entry_id)
            <h4 style="margin-top: 16px;"><i class="fa fa-file-invoice"></i> @lang('ledger_periodend.closing_entries')</h4>
            <div class="table-responsive">
                <table class="table table-condensed ap-table">
                    <thead><tr><th>@lang('ledger_periodend.entry')</th><th>@lang('ledger_periodend.description')</th><th class="ap-num">@lang('ledger_periodend.amount')</th><th>@lang('ledger_periodend.status')</th><th></th></tr></thead>
                    <tbody>
                        @foreach([$closing->stockJournalEntry, $closing->closingJournalEntry] as $je)
                        @if($je)
                        <tr>
                            <td>{{ $je->entry_number }}</td>
                            <td>{{ $je->description }}</td>
                            <td class="ap-num">{{ Ledger::money($je->total_debit) }}</td>
                            <td>@if($je->is_reversed)<span class="ap-chip void">@lang('ledger_periodend.reversed')</span>
                                @if($je->reversingEntry) <a href="{{ route('ledger.journal-entries.show', $je->reversingEntry->id) }}">{{ $je->reversingEntry->entry_number }}</a>@endif
                                @else<span class="ap-chip posted">@lang('ledger_periodend.posted')</span>@endif</td>
                            <td class="text-right"><a href="{{ route('ledger.journal-entries.show', $je->id) }}" class="btn btn-xs btn-info"><i class="fa fa-eye"></i> @lang('ledger_periodend.view_entry')</a></td>
                        </tr>
                        @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            {{-- Notes --}}
            @if($closing->notes)
            <h4 style="margin-top: 16px;"><i class="fa fa-sticky-note"></i> @lang('ledger_periodend.notes')</h4>
            <div class="ap-note" style="white-space: pre-wrap;">{{ $closing->notes }}</div>
            @endif

            {{-- Actions --}}
            <div class="ap-toolbar" style="margin-top: 20px;">
                @if($closing->status == 'draft')
                @can('year-end-closing-edit')
                <form action="{{ route('ledger.year-end-closing.start', $closing->id) }}" method="POST" style="display: inline-block;">
                    @csrf
                    <button type="submit" class="btn btn-warning"><i class="fa fa-play"></i> @lang('ledger_periodend.start_closing')</button>
                </form>
                @endcan
                @can('year-end-closing-delete')
                <form action="{{ route('ledger.year-end-closing.destroy', $closing->id) }}" method="POST" style="display: inline-block;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger" onclick="return confirm({{ json_encode(__('ledger_periodend.are_you_sure')) }})"><i class="fa fa-trash"></i> @lang('ledger_periodend.delete')</button>
                </form>
                @endcan
                @endif

                @if($editing)
                @if($closing->isChecklistComplete() && $blocking->isEmpty())
                @can('year-end-closing-edit')
                <form data-review="@lang('ledger_periodend.generate_check')" data-review-label="@lang('ledger_periodend.generate_the_entries')" action="{{ route('ledger.year-end-closing.generate-entries', $closing->id) }}" method="POST" style="display: inline-block;">
                    @csrf
                    <button type="submit" class="btn btn-success"><i class="fa fa-cogs"></i> @lang('ledger_periodend.generate_closing_entries')</button>
                </form>
                @endcan
                @else
                <div class="ap-note is-warn"><i class="fa fa-exclamation-triangle"></i> @lang('ledger_periodend.complete_checklist_first')</div>
                @endif
                @endif

                @if($closing->status == 'pending_approval')
                @can('year-end-closing-approve')
                <form data-review="@lang('ledger_periodend.approve_check')" data-review-label="@lang('ledger_periodend.approve_the_closing')" data-review-class="btn-danger" action="{{ route('ledger.year-end-closing.approve', $closing->id) }}" method="POST" style="display: inline-block;">
                    @csrf
                    <button type="submit" class="btn btn-success"><i class="fa fa-check"></i> @lang('ledger_periodend.approve_closing')</button>
                </form>
                @endcan
                @endif

                @if($closing->status == 'completed')
                @can('year-end-closing-approve')
                <form method="post" action="{{ route('ledger.year-end-closing.reverse', $closing->id) }}" id="reverse-closing" class="hidden">@csrf</form>
                <button type="button" class="btn btn-danger"
                        data-review-url="{{ route('ledger.year-end-closing.preview-reverse', $closing->id) }}"
                        data-confirm-form="reverse-closing" data-confirm-label="@lang('ledger_periodend.reverse_the_closing')" data-confirm-class="btn-danger"
                        data-needs-reason="1" data-confirm-check="@lang('ledger_periodend.reverse_check')">
                    <i class="fa fa-undo"></i> @lang('ledger_periodend.reverse_closing')</button>
                @endcan
                @endif
            </div>
        </div>
    </div>
    @include('ledger.partials.review_modal')
</section>
@endsection

@section('javascript')
@include('ledger.partials.review_script')
@endsection
