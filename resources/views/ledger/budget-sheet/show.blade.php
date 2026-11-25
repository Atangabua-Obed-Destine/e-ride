@extends('layouts.app')
@section('title', __('ledger_budget.ie').' — '.$budget->title)

@section('css')
<style>
    /* paxhitestbed's sheet: a wide numeric table read down a column and across a row at once. */
    .sheet-scroll { overflow-x: auto; }
    .sheet-table { border-collapse: separate; border-spacing: 0; min-width: 860px; }
    .sheet-table td, .sheet-table th { vertical-align: middle !important; border-bottom: 1px solid #edf0f2; padding: 5px 8px !important; }
    .sheet-table thead th { position: sticky; top: 0; z-index: 3; background: #f7f9fa !important; border-bottom: 2px solid #cfd8dc;
        font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .07em; color: #55636b !important; white-space: nowrap; }
    .sheet-table tbody tr:hover td { background-color: #f4f9f8; }
    .sheet-table .code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; color: #78868d; width: 62px; font-size: 12px; }
    .sheet-section td { background: #34495e !important; color: #fff; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; font-size: 12px; }
    .sheet-header td { background: #eef1f6; font-weight: 600; }
    .sheet-child .name { padding-left: 24px !important; }
    .sheet-subtotal td { background: #f7f9fb; font-weight: 600; border-top: 1px solid #c8d3e0; border-bottom: 1px solid #c8d3e0; }
    .sheet-subtotal .subtotal-name { padding-left: 24px !important; font-style: italic; }
    .sheet-total td { border-top: 2px solid #34495e; border-bottom: none; font-weight: 700; background: #fbfcfd; }
    .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .sheet-table input.figure { text-align: right; width: 100%; max-width: 148px; margin-left: auto; display: block; font-variant-numeric: tabular-nums; }
    .budget-col { background: #fdfefe; }
    .variance-good { color: #1e8449; }
    .variance-bad { color: #c0392b; font-weight: 600; }
    .prior-col { color: #6c757d; background: #fbfbfc; font-size: 12px; }
    .account-ref { display: block; font-size: 11px; line-height: 1.35; color: #8a969c; margin-top: 1px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
    .account-missing { color: #b9770e; }
    .forecast-basis { display: block; font-size: 11px; line-height: 1.35; color: #1c5cab; margin-top: 2px; }
    .un-saved { color: #1e7e34; font-style: italic; }
    .forecast-btn { background: transparent; border: 0; padding: 0; margin-top: 2px; font-size: 11px; color: #1c5cab; cursor: pointer; text-decoration: underline dotted; }
    .fc-result { background: #f2f7ff; border-left: 3px solid #2a78d6; padding: 10px 14px; margin-top: 12px; }
    .fc-result .value { font-size: 22px; font-weight: 800; color: #0a2540; font-variant-numeric: tabular-nums; }
    .fc-warn { background: #fdf6ea; border-left: 3px solid #c98b2e; padding: 8px 12px; margin-top: 10px; font-size: 12px; color: #7a5312; }
    .local-flag { font-size: 10px; color: #b9770e; background: #fdf3e3; border: 1px solid #f0d9ac; border-radius: 3px; padding: 0 4px; margin-left: 6px; text-transform: uppercase; }
    .sheet-stage { display: inline-block; font-size: 11px; padding: 4px 8px; border-radius: 3px; border: 1px solid #ddd; color: #888; background: #f8f8f8; }
    .sheet-stage.here { background: #3c8dbc; border-color: #3c8dbc; color: #fff; }
    .sheet-stage.done { background: #00a65a; border-color: #00a65a; color: #fff; }
    @media print {
        .sheet-scroll { overflow: visible; }
        .sheet-table { min-width: 0; }
        .sheet-table thead th { position: static; }
        .no-print, .main-sidebar, .main-header { display: none !important; }
    }
</style>
@endsection

@section('content')
@include('ledger.partials.style')
@php
    $money = fn ($v) => $v ? \App\Ledger\Ledger::money($v) : '—';
    $M = fn ($v) => \App\Ledger\Ledger::money($v);
    // Only the one step that is available is offered.
    $stage = [
        'draft' => ['submit', __('ledger_budget.submit_for_approval'), 'btn-primary', 'budget-edit'],
        'pending_approval' => ['approve', __('ledger_budget.approve'), 'btn-success', 'budget-approve'],
        'approved' => ['activate', __('ledger_budget.activate_for_year'), 'btn-success', 'budget-activate'],
        'active' => ['close', __('ledger_budget.close_the_year'), 'btn-warning', 'budget-close'],
    ][$budget->status] ?? null;
@endphp
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_budget.ie') — {{ $budget->title }}
        <small>{{ $budget->start_date->format('d/m/Y') }} &ndash; {{ $budget->end_date->format('d/m/Y') }}</small></h1>
</section>
<section class="content">
    @include('ledger.budget.partials.messages')

    <div class="ap-toolbar no-print" style="margin-bottom: 12px; align-items: center;">
        @include('ledger.budget.partials.status', ['status' => $budget->status])
        @if($stage && auth()->user()->can($stage[3]))
            @if($stage[0] === 'approve')
                <form action="{{ route('ledger.budget-sheet.approve', $budget->id) }}" method="post" style="display: inline;"
                      data-review="{{ __('ledger_budget.review_approve_sheet') }}" data-review-label="{{ __('ledger_budget.approve') }}" data-review-class="btn-success">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-success">{{ $stage[1] }}</button>
                </form>
            @else
                <form action="{{ route('ledger.budget-sheet.'.$stage[0], $budget->id) }}" method="post" style="display: inline;"
                      onsubmit="return confirm(@json(__('ledger_budget.continue')))">
                    @csrf
                    <button type="submit" class="btn btn-sm {{ $stage[2] }}">{{ $stage[1] }}</button>
                </form>
            @endif
        @endif
        <a href="{{ route('ledger.budget-sheet.index') }}" class="btn btn-default btn-sm">@lang('ledger_budget.all_sheets')</a>
        <button type="button" class="btn btn-default btn-sm" onclick="window.print()"><i class="fa fa-print"></i> @lang('ledger_budget.print')</button>
        <a href="{{ route('ledger.budget-sheet.pdf', $budget->id) }}" class="btn btn-default btn-sm"><i class="fa fa-file-pdf-o"></i> @lang('ledger_budget.pdf')</a>
        <a href="{{ route('ledger.budget-sheet.excel', $budget->id) }}" class="btn btn-default btn-sm"><i class="fa fa-file-excel-o"></i> @lang('ledger_budget.excel')</a>
    </div>

    {{-- How this sheet works, and the period its actuals cover. --}}
    <div class="callout callout-info no-print">
        <strong>@lang('ledger_budget.how_sheet_works')</strong>
        <p style="margin: 4px 0;">@lang('ledger_budget.how_sheet_works_text')</p>
        <p style="margin: 0;">
            @lang('ledger_budget.actuals_cover') <strong>{{ $budget->start_date->format('d/m/Y') }}</strong>
            @lang('ledger_budget.to') <strong>{{ $budget->end_date->format('d/m/Y') }}</strong>.
            @if($editable)
                @can('budget-edit')<a href="#correct-period" data-toggle="collapse" role="button" style="color: #fff; text-decoration: underline;">@lang('ledger_budget.correct_period')</a>@endcan
            @endif
        </p>
        @if($editable)
        <div class="collapse" id="correct-period" style="margin-top: 10px;">
            <form action="{{ route('ledger.budget-sheet.period', $budget->id) }}" method="post" class="form-inline">
                @csrf
                <input type="text" name="title" class="form-control input-sm" value="{{ $budget->title }}" required aria-label="{{ __('ledger_budget.period_name') }}">
                <input type="date" name="start_date" class="form-control input-sm" value="{{ $budget->start_date->format('Y-m-d') }}" required>
                <input type="date" name="end_date" class="form-control input-sm" value="{{ $budget->end_date->format('Y-m-d') }}" required>
                <button type="submit" class="btn btn-sm btn-default">@lang('ledger_budget.update_period')</button>
            </form>
        </div>
        @endif
    </div>

    {{-- Where this sheet is in its life, and what happens next. --}}
    @php
        $order = ['draft', 'pending_approval', 'approved', 'active', 'closed'];
        $currentIndex = array_search($budget->status, $order, true);
    @endphp
    <div class="ap-card no-print">
        <div style="display: flex; flex-wrap: wrap; gap: 6px; align-items: center; margin-bottom: 8px;">
            @foreach($order as $i => $key)
                <span class="sheet-stage {{ $budget->status === $key ? 'here' : (($currentIndex !== false && $i < $currentIndex) ? 'done' : '') }}">{{ $i + 1 }}. {{ __('ledger_budget.stage_'.$key) }}</span>
                @if($i < count($order) - 1)<span class="ap-muted">&rarr;</span>@endif
            @endforeach
        </div>
        <p style="margin: 0;"><strong>{{ __('ledger_budget.stage_'.$budget->status) }}.</strong> {{ __('ledger_budget.stage_'.$budget->status.'_text') }}</p>
    </div>

    @if(count($unallocated))
        <div class="ap-note is-warn" style="margin-bottom: 16px;">
            <strong>@lang('ledger_budget.nowhere_to_go')</strong>
            <p style="margin: 4px 0;">@lang('ledger_budget.nowhere_to_go_text')</p>
            <ul style="margin: 0;">
                @foreach($unallocated as $section => $amount)
                    <li>{{ \App\Ledger\Models\BudgetLine::sectionLabel($section) }}: <strong>{{ $M($amount) }}</strong></li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Does this sheet tie back to the accounts? --}}
    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">@lang('ledger_budget.agreement_with_accounts')</h3>
            <div class="box-tools">
                @if($reconciliation['agrees'])
                    <span class="ap-chip paid">@lang('ledger_budget.reconciled')</span>
                @else
                    <span class="ap-chip unpaid">@lang('ledger_budget.needs_attention')</span>
                @endif
            </div>
        </div>
        <div class="box-body">
            <div class="table-responsive">
                <table class="table table-condensed ap-table" style="margin-bottom: 0;">
                    <thead>
                        <tr>
                            <th>@lang('ledger_budget.section')</th>
                            <th class="ap-num">@lang('ledger_budget.this_sheet')</th>
                            <th class="ap-num">@lang('ledger_budget.ledger')</th>
                            <th class="ap-num">@lang('ledger_budget.difference')</th>
                            <th>@lang('ledger_budget.accounts')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reconciliation['sections'] as $s)
                            <tr>
                                <td>{{ $s['label'] }}</td>
                                <td class="ap-num">{{ $M($s['sheet']) }}</td>
                                <td class="ap-num">{{ $M($s['ledger']) }}</td>
                                <td class="ap-num {{ ! $s['agrees'] ? 'text-danger' : 'text-success' }}" style="{{ ! $s['agrees'] ? 'font-weight:600' : '' }}">
                                    {{ ! $s['agrees'] ? $M($s['difference']) : '—' }}
                                </td>
                                <td class="ap-muted">
                                    {{ __('ledger_budget.syscohada_class', ['class' => $s['class']]) }}
                                    @if($s['section'] === 'capital')
                                        <span title="{{ __('ledger_budget.capitalised_title') }}">— @lang('ledger_budget.capitalised')</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if(! empty($reconciliation['known_differences']))
                <div class="ap-note is-warn" style="margin-top: 12px;">
                    <strong>@lang('ledger_budget.known_difference')</strong>
                    <ul style="margin: 4px 0 0;">
                        @foreach($reconciliation['known_differences'] as $known)
                            <li>{{ $known['detail'] }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if(count($reconciliation['issues']))
                <div class="ap-note is-bad" style="margin-top: 12px;">
                    <strong>@lang('ledger_budget.cannot_tie')</strong>
                    <ul style="margin: 4px 0 0;">
                        @foreach($reconciliation['issues'] as $issue)
                            <li>{{ $issue['detail'] }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>

    @if($lines->isEmpty())
        <div class="ap-note is-warn" style="margin-bottom: 16px;">
            @lang('ledger_budget.no_lines_yet')
            @can('budget-line-view') <a href="{{ route('ledger.budget-line.index') }}">@lang('ledger_budget.budget_lines') &rarr;</a>@endcan
        </div>
    @endif

    <form action="{{ route('ledger.budget-sheet.figures', $budget->id) }}" method="post" id="figuresForm">
        @csrf
        <div class="box box-solid">
            <div class="box-body sheet-scroll">
                <table class="table table-condensed sheet-table">
                    <thead>
                        <tr>
                            <th class="code">@lang('ledger_budget.code')</th>
                            <th>@lang('ledger_budget.description')</th>
                            <th class="num prior-col" style="width:130px">{{ $previous ? $previous->title : __('ledger_budget.last_year') }}</th>
                            <th class="num" style="width:180px">@lang('ledger_budget.budget')</th>
                            <th class="num" style="width:140px">@lang('ledger_budget.actual')</th>
                            <th class="num" style="width:140px">@lang('ledger_budget.variance')</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="sheet-total">
                            <td></td>
                            <td>@lang('ledger_budget.opening_balance_caps')</td>
                            <td class="num prior-col">{{ $previous ? $money($priorTotals['opening']) : '—' }}</td>
                            <td class="num">
                                @if($editable)
                                    <input type="number" step="0.01" name="opening_balance" class="form-control input-sm figure" value="{{ old('opening_balance', (float) $budget->opening_balance) }}">
                                @else
                                    {{ $money($budget->opening_balance) }}
                                @endif
                            </td>
                            <td class="num">{{ $money($totals['actual']['opening']) }}</td>
                            <td class="num">—</td>
                        </tr>

                        @foreach(['income', 'expenditure', 'capital'] as $currentSection)
                        <tr class="sheet-section"><td colspan="6">{{ __('ledger_budget.sheet_section_'.$currentSection) }}</td></tr>
                        @foreach($lines->where('section', $currentSection) as $line)
                            @php
                                $b = $budgeted[$line->id] ?? 0;
                                $a = $actual[$line->id] ?? 0;
                                $v = $a - $b;
                                // Beating an income target is favourable; beating a spending one is an overspend.
                                $favourable = $line->section === 'income' ? $v >= 0 : $v <= 0;
                                $varianceClass = $b ? ($v == 0 ? '' : ($favourable ? 'variance-good' : 'variance-bad')) : '';
                                $fcPayload = ['line_id' => $line->id, 'code' => $line->code, 'name' => $line->display_name];
                            @endphp
                            <tr class="{{ $line->is_header ? 'sheet-header' : 'sheet-child' }}">
                                <td class="code">{{ $line->code }}</td>
                                <td class="name">
                                    {{ $line->display_name }}
                                    @if($line->is_local)
                                        <span class="local-flag" title="{{ __('ledger_budget.local_title') }}">@lang('ledger_budget.local')</span>
                                    @endif
                                    @if(! $line->is_header)
                                        @if(! empty($accountsByLine[$line->id]))
                                            <span class="account-ref">{{ implode(' · ', $accountsByLine[$line->id]) }}</span>
                                        @else
                                            <span class="account-ref account-missing">@lang('ledger_budget.no_account_stays_empty')</span>
                                        @endif
                                    @endif
                                </td>
                                <td class="num prior-col">@php $p = $priorActual[$line->id] ?? 0; @endphp{{ $p ? $money($p) : '—' }}</td>
                                <td class="num budget-col">
                                    @if($line->is_header)
                                        {{ $money($b) }}
                                    @else
                                        @if($editable)
                                            <input type="number" step="0.01" min="0" class="form-control input-sm figure" name="amounts[{{ $line->id }}]" value="{{ $b ?: '' }}">
                                            @php $fc = $forecasts[$line->id] ?? null; $unit = $fc && $fc->isUnitBasis() ? $fc : null; @endphp
                                            <input type="hidden" name="workings[{{ $line->id }}][quantity]" id="un_q_{{ $line->id }}" value="{{ $unit ? rtrim(rtrim(number_format((float) $unit->quantity, 2, '.', ''), '0'), '.') : '' }}">
                                            <input type="hidden" name="workings[{{ $line->id }}][rate]" id="un_r_{{ $line->id }}" value="{{ $unit ? rtrim(rtrim(number_format((float) $unit->rate, 2, '.', ''), '0'), '.') : '' }}">
                                            <input type="hidden" name="workings[{{ $line->id }}][note]" id="un_n_{{ $line->id }}" value="{{ $unit ? $unit->note : '' }}">
                                            <button type="button" class="forecast-btn no-print" id="un_btn_{{ $line->id }}" data-toggle="modal" data-target="#unitModal"
                                                    onclick='budgetUnit(@json($fcPayload))'>
                                                <i class="fa fa-calculator"></i> {{ $unit ? __('ledger_budget.edit_working') : __('ledger_budget.quantity_x_amount') }}
                                            </button>
                                        @else
                                            {{ $money($b) }}
                                            @php $fc = $forecasts[$line->id] ?? null; @endphp
                                        @endif
                                        @if($fc && $fc->isUnitBasis())
                                            <span class="forecast-basis" id="un_basis_{{ $line->id }}">
                                                {{ rtrim(rtrim(number_format((float) $fc->quantity, 2, '.', ''), '0'), '.') }} &times; {{ $M($fc->rate) }}
                                                @if($fc->note)<br><span class="ap-muted">{{ $fc->note }}</span>@endif
                                            </span>
                                        @endif
                                        @if(($delegated[$line->id] ?? 0) > 0)
                                            <small class="ap-muted" style="display: block;">@lang('ledger_budget.of_which_delegated', ['amount' => $M($delegated[$line->id])])</small>
                                            @if($b && $delegated[$line->id] > $b)
                                                <small class="text-danger" style="display: block;">@lang('ledger_budget.more_delegated')</small>
                                            @endif
                                        @endif
                                    @endif
                                </td>
                                <td class="num">{{ $money($a) }}</td>
                                <td class="num {{ $varianceClass }}" @if($b) title="{{ $favourable ? __('ledger_budget.favourable') : __('ledger_budget.unfavourable') }}" @endif>
                                    @if($b){{ $M($v) }}@else<span class="ap-muted" title="{{ __('ledger_budget.no_budget_for_line') }}">—</span>@endif
                                </td>
                            </tr>
                            @if(isset($groupEnds[$line->id]))
                                @php
                                    $gh = $groupEnds[$line->id];
                                    $gb = $budgeted[$gh->id] ?? 0; $ga = $actual[$gh->id] ?? 0; $gp = $priorActual[$gh->id] ?? 0;
                                    $gv = $ga - $gb; $gFav = $gh->section === 'income' ? $gv >= 0 : $gv <= 0;
                                @endphp
                                <tr class="sheet-subtotal">
                                    <td></td>
                                    <td class="subtotal-name">@lang('ledger_budget.total') {{ $gh->display_name }}</td>
                                    <td class="num prior-col">{{ $gp ? $money($gp) : '—' }}</td>
                                    <td class="num">{{ $money($gb) }}</td>
                                    <td class="num">{{ $money($ga) }}</td>
                                    <td class="num {{ $gb ? ($gv == 0 ? '' : ($gFav ? 'variance-good' : 'variance-bad')) : '' }}">@if($gb){{ $M($gv) }}@else<span class="ap-muted">—</span>@endif</td>
                                </tr>
                            @endif
                        @endforeach
                        @if($currentSection === 'income')
                            @include('ledger.budget-sheet.partials.total', ['label' => __('ledger_budget.total_income_caps'), 'key' => 'income', 'favourUp' => true])
                        @elseif($currentSection === 'expenditure')
                            @include('ledger.budget-sheet.partials.total', ['label' => __('ledger_budget.total_expenditure_caps'), 'key' => 'expenditure', 'favourUp' => false])
                            @include('ledger.budget-sheet.partials.total', ['label' => __('ledger_budget.closing_balance_caps'), 'key' => 'closing', 'favourUp' => null])
                        @else
                            @include('ledger.budget-sheet.partials.total', ['label' => __('ledger_budget.total_capital_caps'), 'key' => 'capital', 'favourUp' => false])
                        @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($editable)
                @can('budget-edit')
                <div class="box-footer text-right no-print">
                    <small class="ap-muted" id="un_totals_hint" style="display: none; margin-right: 12px;">@lang('ledger_budget.totals_refresh_hint')</small>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> @lang('ledger_budget.save_figures')</button>
                </div>
                @endcan
            @else
                <div class="box-footer ap-muted">@lang('ledger_budget.figures_locked_footer')</div>
            @endif
        </div>
    </form>

    {{-- The closing balance follows the paper form, so it is not a cash figure. --}}
    <div class="box box-solid">
        <div class="box-header with-border"><h3 class="box-title">@lang('ledger_budget.reconciliation_to_cash')</h3></div>
        <div class="box-body">
            <p class="ap-muted">@lang('ledger_budget.reconciliation_to_cash_text')</p>
            <table class="table table-condensed ap-table" style="max-width: 520px;">
                <tbody>
                    <tr><td>@lang('ledger_budget.closing_as_presented')</td><td class="num">{{ $money($totals['actual']['closing']) }}</td></tr>
                    <tr><td>@lang('ledger_budget.add_back_depreciation')</td><td class="num">{{ $money($totals['actual']['depreciation']) }}</td></tr>
                    <tr><td>@lang('ledger_budget.less_capital')</td><td class="num">({{ $money($totals['actual']['capital']) }})</td></tr>
                    <tr class="ap-total-row"><td>@lang('ledger_budget.expected_cash')</td><td class="num">{{ $money($totals['actual']['cash']) }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

@if($editable)
<div class="modal fade" id="unitModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="unitForm" onsubmit="return false;">
                <input type="hidden" id="un_line_id">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('ledger_budget.cancel') }}"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">@lang('ledger_budget.quantity_x_amount') <small style="display: block;" id="un_line_label"></small></h4>
                </div>
                <div class="modal-body">
                    <p class="ap-muted">@lang('ledger_budget.unit_intro')</p>
                    <div class="row">
                        <div class="col-sm-5 form-group">
                            <label for="un_quantity">@lang('ledger_budget.quantity') <span class="text-danger">*</span></label>
                            <input type="number" min="0" step="0.01" class="form-control" id="un_quantity" required>
                            <p class="help-block">@lang('ledger_budget.quantity_help')</p>
                        </div>
                        <div class="col-sm-7 form-group">
                            <label for="un_rate">@lang('ledger_budget.amount_each') <span class="text-danger">*</span></label>
                            <input type="number" min="0" step="0.01" class="form-control" id="un_rate" required>
                        </div>
                    </div>
                    <div class="fc-result">
                        <div class="ap-muted">@lang('ledger_budget.would_be_budgeted_at')</div>
                        <div class="value" id="un_amount">0</div>
                    </div>
                    <div class="fc-warn" id="un_error" style="display: none;"></div>
                    <div class="form-group" style="margin-top: 12px;">
                        <label for="un_note">@lang('ledger_budget.note')</label>
                        <input type="text" class="form-control" id="un_note" maxlength="500" placeholder="{{ __('ledger_budget.unit_note_placeholder') }}">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">@lang('ledger_budget.cancel')</button>
                    <button type="button" class="btn btn-primary" id="un_use">@lang('ledger_budget.use_this_figure')</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@include('ledger.partials.review_modal')
@endsection

@section('javascript')
@include('ledger.partials.review_script')
@if($editable)
<script>
(function () {
    'use strict';
    var T = {!! json_encode(['edit_working' => __('ledger_budget.edit_working'), 'saved' => __('ledger_budget.saved'), 'failed' => __('ledger_budget.unit_failed')], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};
    var fmt = new Intl.NumberFormat(undefined, { maximumFractionDigits: 2 });
    var el = function (id) { return document.getElementById(id); };
    function product() {
        return Math.round((parseFloat(el('un_quantity').value) || 0) * (parseFloat(el('un_rate').value) || 0) * 100) / 100;
    }
    function recalc() { el('un_amount').textContent = fmt.format(product()); }
    // Seeded from the row's own hidden inputs, so the dialog shows what is on the sheet right now.
    window.budgetUnit = function (line) {
        el('un_line_id').value = line.line_id;
        el('un_line_label').textContent = line.code + ' — ' + line.name;
        el('un_quantity').value = el('un_q_' + line.line_id).value;
        el('un_rate').value = el('un_r_' + line.line_id).value;
        el('un_note').value = el('un_n_' + line.line_id).value;
        el('un_error').style.display = 'none';
        recalc();
    };
    // Saved at once, in the background; the row is only updated once the server has confirmed.
    function useFigure() {
        var id = el('un_line_id').value;
        if (!id) { return; }
        var quantity = el('un_quantity').value, rate = el('un_rate').value;
        if (quantity === '' || rate === '' || parseFloat(quantity) < 0 || parseFloat(rate) < 0) {
            el('unitForm').reportValidity();
            return;
        }
        var button = el('un_use');
        button.disabled = true;
        el('un_error').style.display = 'none';
        $.ajax({
            url: {!! json_encode(route('ledger.budget-sheet.unit-basis', $budget->id)) !!},
            method: 'POST',
            dataType: 'json',
            data: { _token: {!! json_encode(csrf_token()) !!}, budget_line_id: id, quantity: quantity, rate: rate, note: el('un_note').value }
        }).done(function (data) {
            if (!data || !data.ok) { return fail(data); }
            applyWorking(id, data);
            $('#unitModal').modal('hide');
        }).fail(function (xhr) { fail(xhr.responseJSON); })
          .always(function () { button.disabled = false; });
    }
    function fail(data) {
        var box = el('un_error');
        box.textContent = (data && (data.message || (data.errors ? data.errors.join(' ') : ''))) || T.failed;
        box.style.display = '';
    }
    function applyWorking(id, saved) {
        var figure = document.querySelector('input[name="amounts[' + id + ']"]');
        if (figure) { figure.value = saved.amount > 0 ? saved.amount : ''; }
        el('un_q_' + id).value = saved.quantity;
        el('un_r_' + id).value = saved.rate;
        el('un_n_' + id).value = saved.note || '';
        var basis = el('un_basis_' + id);
        if (!basis) {
            basis = document.createElement('span');
            basis.className = 'forecast-basis';
            basis.id = 'un_basis_' + id;
            el('un_btn_' + id).insertAdjacentElement('afterend', basis);
        }
        basis.textContent = fmt.format(saved.quantity) + ' × ' + fmt.format(saved.rate);
        if (saved.note) {
            basis.appendChild(document.createElement('br'));
            var note = document.createElement('span');
            note.className = 'ap-muted';
            note.textContent = saved.note;
            basis.appendChild(note);
        }
        basis.appendChild(document.createElement('br'));
        var done = document.createElement('span');
        done.className = 'un-saved';
        done.textContent = T.saved;
        basis.appendChild(done);
        el('un_btn_' + id).innerHTML = '<i class="fa fa-calculator"></i> ';
        el('un_btn_' + id).appendChild(document.createTextNode(T.edit_working));
        var hint = el('un_totals_hint');
        if (hint) { hint.style.display = ''; }
    }
    $(function () {
        ['un_quantity', 'un_rate'].forEach(function (id) { if (el(id)) { el(id).addEventListener('input', recalc); } });
        if (el('un_use')) { el('un_use').addEventListener('click', useFigure); }
        if (el('unitForm')) {
            el('unitForm').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); useFigure(); } });
        }
    });
})();
</script>
@endif
@endsection
