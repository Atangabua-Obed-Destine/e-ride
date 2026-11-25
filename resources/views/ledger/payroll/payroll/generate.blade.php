@extends('layouts.app')
@section('title', $title)
@section('css')
@include('ledger.partials.style')
<style>
    /* paxhitestbed admin/payroll/generate styles */
    .payroll-header-card { background: linear-gradient(135deg, #4e73df 0%, #224abe 100%); border-radius: 10px; color: #fff; position: relative; overflow: hidden; }
    .payroll-header-card .staff-avatar { width: 70px; height: 70px; border-radius: 50%; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 28px; font-weight: 700; border: 3px solid rgba(255,255,255,0.4); flex-shrink: 0; }
    .payroll-header-card .staff-meta dt { font-size: 11px; opacity: 0.7; margin-bottom: 1px; text-transform: uppercase; letter-spacing: .5px; }
    .payroll-header-card .staff-meta dd { font-size: 14px; font-weight: 600; margin-bottom: 8px; }
    .payroll-period-badge, .salary-highlight { background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25); border-radius: 8px; padding: 12px 16px; text-align: center; }
    .payroll-period-badge .period-month { font-size: 22px; font-weight: 700; line-height: 1.1; }
    .payroll-period-badge .period-label, .salary-highlight .sal-label { font-size: 10px; text-transform: uppercase; opacity: 0.7; }
    .salary-highlight .sal-value { font-size: 20px; font-weight: 700; }
    .payroll-status-pill { display: inline-flex; align-items: center; gap: 5px; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
    .payroll-status-pill.draft { background: #fff3cd; color: #856404; }
    .payroll-status-pill.new-payroll { background: #cce5ff; color: #004085; }
    .section-card { border-radius: 10px; border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.06); overflow: hidden; }
    .section-card .card-header { border-bottom: 2px solid #f1f3f5; background: #fff; padding: 14px 20px; display: flex; align-items: center; justify-content: space-between; }
    .section-card .card-header h5 { font-size: 15px; font-weight: 600; margin: 0; }
    .section-card .card-header .section-desc { font-size: 12px; color: #858796; margin: 2px 0 0 0; }
    .calc-field-group { background: #f8f9fc; border-radius: 8px; padding: 14px 16px; margin-bottom: 12px; border-left: 4px solid #dee2e6; }
    .calc-field-group.highlight-earning { border-left-color: #4e73df; background: #eef2ff; }
    .calc-field-group.highlight-gross { border-left-color: #36b9cc; background: #e8f8fb; }
    .calc-field-group.highlight-tax { border-left-color: #e74a3b; background: #fef5f4; }
    .calc-field-group.highlight-employer { border-left-color: #f6c23e; background: #fffdf2; }
    .calc-field-group.highlight-net { border-left-color: #1cc88a; background: #e6f9f0; }
    .calc-field-group.highlight-cost { border-left-color: #5a5c69; background: #f0f0f5; }
    .calc-field-group label { font-size: 12px; font-weight: 600; color: #5a5c69; text-transform: uppercase; letter-spacing: .3px; margin-bottom: 4px; }
    .calc-field-group .form-control { font-size: 16px; font-weight: 700; border: none; background: transparent; padding: 2px 0; height: auto; }
    .calc-field-group .field-desc { font-size: 11px; color: #858796; margin-top: 2px; }
    .tax-breakdown-panel { background: #fff; border: 1px solid #e3e6f0; border-radius: 8px; padding: 14px; margin-top: 10px; }
    .tax-item { display: flex; align-items: flex-start; padding: 6px 0; border-bottom: 1px solid #f1f3f5; }
    .tax-item:last-child { border-bottom: none; }
    .tax-item .tax-icon { width: 24px; flex-shrink: 0; padding-top: 2px; }
    .tax-item .tax-info { flex: 1; }
    .tax-item .tax-name { font-weight: 600; font-size: 13px; }
    .tax-item .tax-detail { font-size: 11px; color: #858796; }
    .tax-item .tax-amount { font-weight: 700; font-size: 13px; text-align: right; white-space: nowrap; }
    .payroll-summary-footer { background: linear-gradient(135deg, #1a1a2e 0%, #2c3e50 100%); color: #fff; border-radius: 10px; padding: 20px; }
    .summary-item { text-align: center; padding: 8px; }
    .summary-item .sum-label { font-size: 10px; text-transform: uppercase; letter-spacing: .5px; opacity: 0.6; }
    .summary-item .sum-value { font-size: 18px; font-weight: 700; }
    .text-success-light { color: #5dff9e; } .text-danger-light { color: #ff7675; } .text-warning-light { color: #ffeaa7; } .text-info-light { color: #74b9ff; }
    .summary-arrow { display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,0.3); font-size: 20px; }
    .entry-row { padding: 10px 0; border-bottom: 1px solid #f1f3f5; }
    .confirm-warning-banner { background: #fff8e1; border: 1px solid #ffe082; border-left: 4px solid #f6c23e; border-radius: 6px; padding: 12px 16px; margin-bottom: 18px; display: flex; gap: 10px; color: #856404; font-size: 13px; }
    .confirm-summary-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px; }
    .confirm-summary-item { background: #f8f9fc; border-radius: 8px; padding: 12px 14px; border-left: 3px solid #dee2e6; }
    .confirm-summary-item.item-net, .confirm-summary-item.item-cost { grid-column: 1 / -1; }
    .confirm-summary-item.item-net { border-left-color: #1cc88a; background: #e6f9f0; }
    .confirm-summary-item .csi-label { font-size: 11px; text-transform: uppercase; color: #858796; font-weight: 600; }
    .confirm-summary-item .csi-value { font-size: 17px; font-weight: 700; color: #2d3436; }
</style>
@endsection

{{--
  paxhitestbed admin/payroll/generate: the header (staff, period, basic salary), allowances, deductions
  (loan repayments read-only), the calculation with its problems panel and tax panel (the server's
  itemised lines, previewed by PayrollController::preview as lines are typed), the confirmation, and
  the summary band. E-Ride: monthly pay only, so paxhitestbed's attendance section is left out.
--}}
@section('content')
@php
    $money = fn ($v) => \App\Ledger\Ledger::money($v);
    $initials = strtoupper(mb_substr($row->first_name ?? '', 0, 1).mb_substr($row->last_name ?? '', 0, 1));
    $taxable_titles = $allowance_types->where('is_taxable', true)->pluck('title')->all();
    $period = \Carbon\Carbon::parse($salary_month);
@endphp
<section class="content">
    @include('ledger.account.errors')

    <div class="card section-card mb-3">
        <div class="card-body py-3 px-4 d-flex align-items-center justify-content-between flex-wrap">
            <div>
                <h5 class="mb-1" style="font-weight: 700;"><i class="fas fa-file-invoice-dollar text-primary me-2"></i>@lang('ledger_payroll.btn_generate') {{ $title }}</h5>
                <p class="mb-0 text-muted" style="font-size: 13px;">@lang('ledger_payroll.generate_intro')</p>
            </div>
            <div class="d-flex gap-2 mt-2 mt-md-0">
                <a href="{{ route($route.'.index', ['month' => $selected_month, 'year' => $selected_year]) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-arrow-left me-1"></i> @lang('ledger_payroll.btn_back')</a>
                <a href="{{ route($route.'.generate', ['id' => $row->id, 'month' => $selected_month, 'year' => $selected_year]) }}" class="btn btn-outline-info btn-sm"><i class="fas fa-sync-alt me-1"></i> @lang('ledger_payroll.btn_refresh')</a>
            </div>
        </div>
    </div>

    <div class="card payroll-header-card mb-3">
        <div class="card-body p-4">
            <div class="row align-items-center">
                <div class="col-lg-5 col-md-6 mb-3 mb-lg-0">
                    <div class="d-flex align-items-center">
                        <div class="staff-avatar me-3">{{ $initials }}</div>
                        <div>
                            <h5 class="mb-0 text-white" style="font-weight: 700; font-size: 18px;">{{ $row->name }}</h5>
                            <div style="font-size: 13px; opacity: 0.85;">
                                <span class="me-3"><i class="fas fa-id-badge me-1"></i> #{{ $row->staff_no }}</span>
                                @if($payroll)
                                <span class="payroll-status-pill draft"><i class="fas fa-edit"></i> @lang('ledger_payroll.draft')</span>
                                @else
                                <span class="payroll-status-pill new-payroll"><i class="fas fa-plus-circle"></i> @lang('ledger_payroll.new_payroll')</span>
                                @endif
                            </div>
                            <dl class="staff-meta row mb-0 mt-2" style="font-size: 12px;">
                                <div class="col-6"><dt>@lang('ledger_payroll.field_department')</dt><dd>{{ $row->department->title ?? '-' }}</dd></div>
                                <div class="col-6"><dt>@lang('ledger_payroll.field_designation')</dt><dd>{{ $row->designation->title ?? '-' }}</dd></div>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3 mb-lg-0">
                    <dl class="staff-meta mb-0">
                        <dt>@lang('ledger_payroll.field_contract_type')</dt>
                        <dd>{{ $row->contract_type == 1 ? __('ledger_payroll.contract_type_full_time') : ($row->contract_type == 2 ? __('ledger_payroll.contract_type_part_time') : '-') }}</dd>
                        <dt>@lang('ledger_payroll.field_salary_type')</dt>
                        <dd><i class="fas fa-calendar-alt me-1" style="opacity: 0.7;"></i>@lang('ledger_payroll.salary_type_fixed')</dd>
                    </dl>
                </div>
                <div class="col-lg-4 col-md-12">
                    <div class="row">
                        <div class="col-6"><div class="payroll-period-badge">
                            <div class="period-label">@lang('ledger_payroll.payroll_period')</div>
                            <div class="period-month">{{ $period->translatedFormat('M') }}</div>
                            <div style="font-size: 13px; opacity: 0.8;">{{ $selected_year }}</div>
                        </div></div>
                        <div class="col-6"><div class="salary-highlight">
                            <div class="sal-label">@lang('ledger_payroll.field_basic_salary')</div>
                            <div class="sal-value">{{ $money($calc['basic_salary']) }}</div>
                            <div style="font-size: 11px; opacity: 0.7;">FCFA / @lang('ledger_payroll.month')</div>
                        </div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <form id="payrollForm" action="{{ route($route.'.store') }}" method="post">
        @csrf
        <input type="hidden" name="staff_id" value="{{ $row->id }}">
        <input type="hidden" name="basic_salary" value="{{ round($calc['basic_salary']) }}">
        <input type="hidden" name="salary_month" value="{{ $salary_month }}">

        <div class="row">
            @if(!empty($carried_from))
            <div class="col-12 mb-2"><div class="alert alert-info py-2 mb-0" style="font-size: 13px;"><i class="fas fa-copy me-1"></i> @lang('ledger_payroll.carried_from', ['month' => $carried_from])</div></div>
            @endif

            {{-- allowances --}}
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="card section-card h-100">
                    <div class="card-header">
                        <div><h5><i class="fas fa-plus-circle text-success me-2"></i>@lang('ledger_payroll.field_total_allowance')</h5>
                            <p class="section-desc mb-0">@lang('ledger_payroll.allowances_desc')</p></div>
                        <button id="addAllowance" type="button" class="btn btn-success btn-sm"><i class="fas fa-plus me-1"></i> @lang('ledger_payroll.btn_add')</button>
                    </div>
                    <div class="card-body" id="allowanceList">
                        @foreach($start_allowances as $detail)
                        <div class="row entry-row align-items-end allowance-row">
                            <div class="col-md-5 mb-1">
                                <label class="form-label" style="font-size: 12px;">@lang('ledger_payroll.field_title') <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm" name="allowance_titles[]" required>
                                    <option value="{{ $detail['title'] }}" selected>{{ $detail['title'] }}{{ in_array($detail['title'], $taxable_titles) ? ' · '.__('ledger_payroll.taxed_lc') : '' }}</option>
                                    @foreach($allowance_types as $type)@if($type->title != $detail['title'])<option value="{{ $type->title }}">{{ $type->title }}{{ $type->is_taxable ? ' · '.__('ledger_payroll.taxed_lc') : '' }}</option>@endif @endforeach
                                </select>
                            </div>
                            <div class="col-md-5 mb-1">
                                <label class="form-label" style="font-size: 12px;">@lang('ledger_payroll.field_amount') (FCFA) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm calc-input" name="allowances[]" value="{{ round((float) $detail['amount']) }}" required>
                            </div>
                            <div class="col-md-2 mb-1 text-center"><button type="button" class="btn btn-outline-danger btn-sm remove-row" title="@lang('ledger_payroll.remove')"><i class="fas fa-trash-alt"></i></button></div>
                        </div>
                        @endforeach
                        @if(count($start_allowances) == 0)
                        <div class="text-center py-3 text-muted placeholder-row"><i class="fas fa-hand-holding-usd" style="font-size: 28px; opacity: 0.3;"></i><p class="mb-0 mt-1" style="font-size: 12px;">@lang('ledger_payroll.no_allowances_yet')</p></div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- deductions --}}
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="card section-card h-100">
                    <div class="card-header">
                        <div><h5><i class="fas fa-minus-circle text-danger me-2"></i>@lang('ledger_payroll.field_total_deduction')</h5>
                            <p class="section-desc mb-0">@lang('ledger_payroll.deductions_desc')</p></div>
                        <button id="addDeduction" type="button" class="btn btn-danger btn-sm"><i class="fas fa-plus me-1"></i> @lang('ledger_payroll.btn_add')</button>
                    </div>
                    <div class="card-body" id="deductionList">
                        @foreach($start_deductions as $detail)
                        <div class="row entry-row align-items-end deduction-row">
                            <div class="col-md-5 mb-1">
                                <label class="form-label" style="font-size: 12px;">@lang('ledger_payroll.field_title') <span class="text-danger">*</span></label>
                                @if(!empty($detail['loan_id']))
                                <input type="text" class="form-control form-control-sm" name="deduction_titles[]" value="{{ $detail['title'] }}" readonly>
                                <input type="hidden" name="deduction_loans[]" value="{{ $detail['loan_id'] }}">
                                @php $loanLeft = optional($open_loans->firstWhere('id', $detail['loan_id']))->available($payroll?->id); @endphp
                                <small class="text-muted"><i class="fas fa-hand-holding-usd"></i> @lang('ledger_payroll.loan_repayment')@if($loanLeft !== null) · @lang('ledger_payroll.left', ['left' => $money($loanLeft)])@endif</small>
                                @else
                                <select class="form-select form-select-sm" name="deduction_titles[]" required>
                                    <option value="{{ $detail['title'] }}" selected>{{ $detail['title'] }}</option>
                                    @foreach($deduction_types as $type)@if($type->title != $detail['title'])<option value="{{ $type->title }}">{{ $type->title }}</option>@endif @endforeach
                                </select>
                                <input type="hidden" name="deduction_loans[]" value="">
                                @endif
                            </div>
                            <div class="col-md-5 mb-1">
                                <label class="form-label" style="font-size: 12px;">@lang('ledger_payroll.field_amount') (FCFA) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm calc-input" name="deductions[]" value="{{ round((float) $detail['amount']) }}" required>
                            </div>
                            <div class="col-md-2 mb-1 text-center"><button type="button" class="btn btn-outline-danger btn-sm remove-row" title="@lang('ledger_payroll.remove')"><i class="fas fa-trash-alt"></i></button></div>
                        </div>
                        @endforeach
                        @if(count($start_deductions) == 0)
                        <div class="text-center py-3 text-muted placeholder-row"><i class="fas fa-file-invoice" style="font-size: 28px; opacity: 0.3;"></i><p class="mb-0 mt-1" style="font-size: 12px;">@lang('ledger_payroll.no_deductions_yet')</p></div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- the calculation --}}
            <div class="col-lg-4 col-md-12 mb-3">
                <div class="card section-card h-100">
                    <div class="card-header">
                        <div><h5><i class="fas fa-calculator text-info me-2"></i>@lang('ledger_payroll.field_calculate')</h5>
                            <p class="section-desc mb-0">@lang('ledger_payroll.calculate_desc')</p></div>
                        <button type="button" class="btn btn-outline-info btn-sm ms-2" id="btnRecalculate" title="@lang('ledger_payroll.recalculate')"><i class="fas fa-sync-alt"></i></button>
                    </div>
                    <div class="card-body">
                        <div class="calc-field-group highlight-earning">
                            <label>@lang('ledger_payroll.field_total_earning') (FCFA)</label>
                            <input type="text" class="form-control" name="total_earning" value="{{ round($calc['total_earning']) }}" readonly>
                            <div class="field-desc">@lang('ledger_payroll.earning_desc')</div>
                            @foreach($calc['notes'] as $note)<div class="field-desc text-warning"><i class="fas fa-exclamation-triangle"></i> {{ $note }}</div>@endforeach
                        </div>
                        <div class="calc-field-group">
                            <label>@lang('ledger_payroll.field_total_allowance') (FCFA)</label>
                            <input type="text" class="form-control text-success" name="total_allowance" value="{{ round($calc['total_allowance']) }}" readonly>
                            <div class="field-desc">@lang('ledger_payroll.allowance_sum_desc')</div>
                        </div>
                        <div class="calc-field-group">
                            <label>@lang('ledger_payroll.field_total_deduction') (FCFA)</label>
                            <input type="text" class="form-control text-danger" name="total_deduction" value="{{ round($calc['total_deduction']) }}" readonly>
                            <div class="field-desc">@lang('ledger_payroll.deduction_sum_desc')</div>
                        </div>
                        <div class="calc-field-group highlight-gross">
                            <label>@lang('ledger_payroll.field_gross_salary') (FCFA)</label>
                            <input type="text" class="form-control" name="gross_salary" value="{{ round($calc['gross_salary']) }}" readonly style="color: #36b9cc;">
                            <div class="field-desc">@lang('ledger_payroll.gross_desc')</div>
                        </div>
                        <hr class="my-2" style="border-style: dashed;">
                        <div class="calc-field-group highlight-tax">
                            <label>@lang('ledger_payroll.field_employee_tax') (FCFA)</label>
                            <input type="text" class="form-control" name="tax" value="{{ round($calc['tax']) }}" readonly style="color: #e74a3b;">
                            <div class="field-desc">@lang('ledger_payroll.effective_rate') <strong id="effectiveRate">{{ $calc['effective_rate'] }}%</strong></div>
                            <div class="field-desc text-muted"><i class="fas fa-info-circle"></i>
                                <span id="taxBaseNote">@lang('ledger_payroll.taxed_on', ['amount' => $money($calc['taxable_salary']), 'taxable' => $money($calc['taxable_allowance'])])</span>
                                @can('allowance-type-view') <a href="{{ route('ledger.allowance-type.index') }}" target="_blank">@lang('ledger_payroll.which_allowances_taxed')</a>@endcan
                            </div>
                            <div class="alert alert-danger py-2 px-2 mt-2 mb-0" id="payrollProblems" style="font-size: 12px; {{ empty($calc['problems']) ? 'display:none;' : '' }}">
                                <strong><i class="fas fa-exclamation-triangle"></i> @lang('ledger_payroll.cannot_save_yet')</strong>
                                <ul class="mb-0 ps-3 mt-1" id="payrollProblemList">@foreach($calc['problems'] as $problem)<li>{{ $problem }}</li>@endforeach</ul>
                            </div>
                            <div class="tax-breakdown-panel" id="taxBreakdownPanel">
                                @forelse($calc['tax_lines'] as $line)
                                <div class="tax-item">
                                    <div class="tax-icon"><i class="fas fa-receipt text-secondary"></i></div>
                                    <div class="tax-info"><div class="tax-name">{{ $line['label'] }}</div>
                                        @if($line['employer'] > 0)<div class="tax-detail">@lang('ledger_payroll.staff_side') {{ $money($line['employee']) }} · @lang('ledger_payroll.employer_side') {{ $money($line['employer']) }}</div>@endif
                                    </div>
                                    <div class="tax-amount text-danger">{{ $money($line['employee']) }} FCFA</div>
                                </div>
                                @empty
                                <p class="text-muted text-center mb-0" style="font-size:12px"><i class="fas fa-info-circle"></i> @lang('ledger_payroll.no_tax_applies')</p>
                                @endforelse
                            </div>
                        </div>
                        <input type="hidden" name="employer_tax" value="{{ round($calc['employer_tax']) }}">
                        <div class="calc-field-group highlight-employer" id="employerTaxGroup" style="{{ $calc['employer_tax'] > 0 ? '' : 'display:none;' }}">
                            <label>@lang('ledger_payroll.field_employer_tax') (FCFA)</label>
                            <input type="text" class="form-control" id="employer_tax_display" value="{{ round($calc['employer_tax']) }}" readonly style="color: #d48806;">
                            <div class="field-desc">@lang('ledger_payroll.employer_tax_desc')</div>
                        </div>
                        <hr class="my-2" style="border-style: dashed;">
                        <div class="calc-field-group highlight-net">
                            <label>@lang('ledger_payroll.field_net_salary') (FCFA)</label>
                            <input type="text" class="form-control" name="net_salary" value="{{ round($calc['net_salary']) }}" readonly style="color: #1cc88a; font-size: 20px;">
                            <div class="field-desc">@lang('ledger_payroll.net_desc')</div>
                        </div>
                        <input type="hidden" name="total_cost" value="{{ round($calc['total_cost']) }}">
                        <div class="calc-field-group highlight-cost" id="totalCostGroup" style="{{ $calc['employer_tax'] > 0 ? '' : 'display:none;' }}">
                            <label>@lang('ledger_payroll.field_total_cost') (FCFA)</label>
                            <input type="text" class="form-control" id="total_cost_display" value="{{ round($calc['total_cost']) }}" readonly style="color: #5a5c69; font-size: 18px;">
                            <div class="field-desc">@lang('ledger_payroll.total_cost_desc')</div>
                        </div>
                        <div class="mt-3 d-grid">
                            <button type="button" id="btnShowConfirmModal" {{ empty($calc['problems']) ? '' : 'disabled' }} class="btn btn-success" style="padding: 10px; font-size: 15px; font-weight: 600;">
                                <i class="fas fa-check-circle me-1"></i> @lang('ledger_payroll.btn_save') {{ $title }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- the confirmation --}}
        <div class="modal fade" id="payrollConfirmModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" style="max-width: 540px;"><div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #1a1a2e 0%, #2c3e50 100%); color: #fff;">
                    <h5 class="modal-title"><i class="fas fa-clipboard-check me-2"></i>@lang('ledger_payroll.confirm_submission')</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="@lang('ledger_payroll.btn_close')"></button>
                </div>
                <div class="modal-body">
                    <div class="confirm-warning-banner"><i class="fas fa-exclamation-triangle"></i><div><strong class="d-block">@lang('ledger_payroll.review_carefully')</strong>@lang('ledger_payroll.review_carefully_text')</div></div>
                    <div class="mb-3"><strong>{{ $row->name }}</strong> <small class="text-muted">#{{ $row->staff_no }} · {{ $period->translatedFormat('F Y') }} · {{ $row->department->title ?? '-' }}</small></div>
                    <div class="confirm-summary-grid">
                        @foreach(['total_earning' => 'Earning', 'gross_salary' => 'Gross', 'total_allowance' => 'Allowance', 'total_deduction' => 'Deduction', 'tax' => 'Tax', 'employer_tax' => 'EmployerTax', 'net_salary' => 'Net', 'total_cost' => 'Cost'] as $field => $id)
                        <div class="confirm-summary-item {{ $field === 'net_salary' ? 'item-net' : ($field === 'total_cost' ? 'item-cost' : '') }}" id="confirm{{ $id }}Row">
                            <div class="csi-label">@lang('ledger_payroll.field_'.($field === 'tax' ? 'employee_tax' : $field))</div>
                            <div class="csi-value" id="confirm{{ $id }}">0</div>
                        </div>
                        @endforeach
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fas fa-arrow-left me-1"></i> @lang('ledger_payroll.go_back_review')</button>
                    <button type="button" id="btnConfirmPayroll" class="btn btn-success" style="font-weight: 600;"><i class="fas fa-check-double me-1"></i> @lang('ledger_payroll.confirm_and_save')</button>
                </div>
            </div></div>
        </div>
    </form>

    <div class="payroll-summary-footer mb-4">
        <div class="row align-items-center">
            <div class="col text-center"><div class="summary-item"><div class="sum-label">@lang('ledger_payroll.field_total_earning')</div><div class="sum-value text-info-light">{{ $money($calc['total_earning']) }}</div></div></div>
            <div class="col-auto summary-arrow"><i class="fas fa-plus"></i></div>
            <div class="col text-center"><div class="summary-item"><div class="sum-label">@lang('ledger_payroll.field_total_allowance')</div><div class="sum-value text-success-light" id="summaryAllowance">{{ $money($calc['total_allowance']) }}</div></div></div>
            <div class="col-auto summary-arrow"><i class="fas fa-minus"></i></div>
            <div class="col text-center"><div class="summary-item"><div class="sum-label">@lang('ledger_payroll.field_total_deduction')</div><div class="sum-value text-danger-light" id="summaryDeduction">{{ $money($calc['total_deduction']) }}</div></div></div>
            <div class="col-auto summary-arrow"><i class="fas fa-minus"></i></div>
            <div class="col text-center"><div class="summary-item"><div class="sum-label">@lang('ledger_payroll.field_employee_tax')</div><div class="sum-value text-danger-light" id="summaryTax">{{ $money($calc['tax']) }}</div></div></div>
            <div class="col-auto summary-arrow"><i class="fas fa-equals"></i></div>
            <div class="col text-center"><div class="summary-item"><div class="sum-label">@lang('ledger_payroll.field_net_salary')</div><div class="sum-value text-success-light" style="font-size: 22px;" id="summaryNet">{{ $money($calc['net_salary']) }}</div></div></div>
            <div class="col-auto summary-arrow"><i class="fas fa-building" style="opacity: 0.5;"></i></div>
            <div class="col text-center"><div class="summary-item"><div class="sum-label">@lang('ledger_payroll.field_total_cost')</div><div class="sum-value text-warning-light" id="summaryCost">{{ $money($calc['total_cost']) }}</div></div></div>
        </div>
    </div>
</section>
@endsection

@section('javascript')
<script>
(function ($) {
    "use strict";
    var T = {!! json_encode([
        'title' => __('ledger_payroll.field_title'), 'amount' => __('ledger_payroll.field_amount'), 'select' => __('ledger_payroll.select'),
        'remove' => __('ledger_payroll.remove'), 'taxed' => __('ledger_payroll.taxed_lc'), 'staff' => __('ledger_payroll.staff_side'),
        'employer' => __('ledger_payroll.employer_side'), 'none' => __('ledger_payroll.no_tax_applies'), 'failed' => __('ledger_payroll.preview_failed'),
        'taxedOn' => __('ledger_payroll.taxed_on'), 'saving' => __('ledger_payroll.saving'),
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!};
    var allowanceTypes = {!! json_encode($allowance_types->map(fn ($t) => ['title' => $t->title, 'taxed' => (bool) $t->is_taxable])->values(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!};
    var deductionTypes = {!! json_encode($deduction_types->pluck('title')->values(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!};
    var esc = function (t) { return $('<div>').text(t).html(); };
    var fmt = function (v) { return Math.round(parseFloat(v) || 0).toLocaleString('fr-FR'); };

    function row(kind) {
        var opts = '<option value="">' + esc(T.select) + '</option>';
        if (kind === 'allowance') {
            allowanceTypes.forEach(function (t) { opts += '<option value="' + esc(t.title) + '">' + esc(t.title) + (t.taxed ? ' · ' + esc(T.taxed) : '') + '</option>'; });
        } else {
            deductionTypes.forEach(function (t) { opts += '<option value="' + esc(t) + '">' + esc(t) + '</option>'; });
        }
        return '<div class="row entry-row align-items-end ' + kind + '-row">'
            + '<div class="col-md-5 mb-1"><label class="form-label" style="font-size:12px">' + esc(T.title) + ' <span class="text-danger">*</span></label>'
            + '<select class="form-select form-select-sm" name="' + kind + '_titles[]" required>' + opts + '</select>'
            + (kind === 'deduction' ? '<input type="hidden" name="deduction_loans[]" value="">' : '') + '</div>'
            + '<div class="col-md-5 mb-1"><label class="form-label" style="font-size:12px">' + esc(T.amount) + ' (FCFA) <span class="text-danger">*</span></label>'
            + '<input type="text" class="form-control form-control-sm calc-input" name="' + kind + 's[]" value="" required></div>'
            + '<div class="col-md-2 mb-1 text-center"><button type="button" class="btn btn-outline-danger btn-sm remove-row" title="' + esc(T.remove) + '"><i class="fas fa-trash-alt"></i></button></div></div>';
    }
    $('#addAllowance').on('click', function () { $('#allowanceList .placeholder-row').hide(); $('#allowanceList').append(row('allowance')); });
    $('#addDeduction').on('click', function () { $('#deductionList .placeholder-row').hide(); $('#deductionList').append(row('deduction')); });
    $(document).on('click', '.remove-row', function () { $(this).closest('.entry-row').remove(); recalc(); });
    $(document).on('keyup change', '.calc-input, select[name="allowance_titles[]"], select[name="deduction_titles[]"]', function () { recalc(); });
    $('#btnRecalculate').on('click', function () { recalc(); });

    var timer = null, req = null;
    function recalc() { clearTimeout(timer); timer = setTimeout(preview, 250); }
    function preview() {
        var $f = $('#payrollForm');
        var data = $f.find('input[name="_token"], input[name="staff_id"], input[name="salary_month"], [name="allowance_titles[]"], input[name="allowances[]"], [name="deduction_titles[]"], input[name="deduction_loans[]"], input[name="deductions[]"]').serialize();
        if (req) { req.abort(); }
        $('#btnShowConfirmModal').prop('disabled', true);
        req = $.ajax({ url: {!! json_encode(route($route.'.preview')) !!}, method: 'POST', data: data, dataType: 'json' }).done(function (c) {
            var set = function (n, v) { $f.find('input[name="' + n + '"]').val(Math.round(parseFloat(v) || 0)); };
            ['total_earning', 'total_allowance', 'total_deduction', 'gross_salary', 'tax', 'employer_tax', 'net_salary', 'total_cost'].forEach(function (k) { set(k, c[k]); });
            $('#employer_tax_display').val(Math.round(c.employer_tax));
            $('#total_cost_display').val(Math.round(c.total_cost));
            $('#employerTaxGroup, #totalCostGroup').toggle(c.employer_tax > 0);
            $('#effectiveRate').text(c.effective_rate + '%');
            $('#taxBaseNote').text(T.taxedOn.replace(':amount', fmt(c.taxable_salary)).replace(':taxable', fmt(c.taxable_allowance)));
            var html = '';
            $.each(c.tax_lines, function (_, l) {
                html += '<div class="tax-item"><div class="tax-icon"><i class="fas fa-receipt text-secondary"></i></div><div class="tax-info"><div class="tax-name">' + esc(l.label) + '</div>';
                if (l.employer > 0) { html += '<div class="tax-detail">' + esc(T.staff) + ' ' + fmt(l.employee) + ' · ' + esc(T.employer) + ' ' + fmt(l.employer) + '</div>'; }
                html += '</div><div class="tax-amount text-danger">' + fmt(l.employee) + ' FCFA</div></div>';
            });
            $('#taxBreakdownPanel').html(html || '<p class="text-muted text-center mb-0" style="font-size:12px"><i class="fas fa-info-circle"></i> ' + esc(T.none) + '</p>');
            var problems = '';
            $.each(c.problems, function (_, p) { problems += '<li>' + esc(p) + '</li>'; });
            $('#payrollProblemList').html(problems);
            $('#payrollProblems').toggle(c.problems.length > 0);
            $('#btnShowConfirmModal').prop('disabled', c.problems.length > 0);
            $('#summaryAllowance').text(fmt(c.total_allowance)); $('#summaryDeduction').text(fmt(c.total_deduction));
            $('#summaryTax').text(fmt(c.tax)); $('#summaryNet').text(fmt(c.net_salary)); $('#summaryCost').text(fmt(c.total_cost));
        }).fail(function (xhr, status) {
            if (status === 'abort') { return; }
            $('#payrollProblemList').html('<li>' + esc(T.failed) + '</li>');
            $('#payrollProblems').show();
        });
    }

    var modalEl = document.getElementById('payrollConfirmModal');
    $('#btnShowConfirmModal').on('click', function () {
        var v = function (n) { return $('#payrollForm input[name="' + n + '"]').val(); };
        var map = {Earning: 'total_earning', Gross: 'gross_salary', Allowance: 'total_allowance', Deduction: 'total_deduction', Tax: 'tax', EmployerTax: 'employer_tax', Net: 'net_salary', Cost: 'total_cost'};
        Object.keys(map).forEach(function (k) { $('#confirm' + k).text(fmt(v(map[k])) + ' FCFA'); });
        var employer = parseInt(v('employer_tax')) > 0;
        $('#confirmEmployerTaxRow, #confirmCostRow').toggle(employer);
        if (window.bootstrap && bootstrap.Modal) { bootstrap.Modal.getOrCreateInstance(modalEl).show(); } else { $(modalEl).modal('show'); }
    });
    $('#btnConfirmPayroll').on('click', function () {
        $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> ' + esc(T.saving));
        document.getElementById('payrollForm').submit();
    });
})(jQuery);
</script>
@endsection
