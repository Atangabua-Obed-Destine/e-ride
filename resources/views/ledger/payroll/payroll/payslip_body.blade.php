{{--
  The payslip (paxhitestbed admin/payroll/print and payslip-pdf): header from Pay slip settings,
  employee information, earnings beside deductions (the taxes this payroll was charged, from its
  own itemised lines), the employer's contributions, net pay, signatures and footer. Tables, so the
  printed page and the PDF (mPDF) are the same document.
--}}
@php
    $money = fn ($v) => \App\Ledger\Ledger::money($v);
    $s = $row->staff;
    $applicable = array_values(array_filter($tax_lines, fn ($l) => $l['employee'] > 0));
    $employer = array_values(array_filter($tax_lines, fn ($l) => $l['employer'] > 0));
    $allowances = $row->details->where('status', 1);
    $deductions = $row->details->where('status', 0);
    $totalEarnings = (float) $row->total_earning + (float) $allowances->sum('amount');
    $totalDeductions = (float) $deductions->sum('amount') + (float) $row->tax;
    $logo = fn ($f) => $f ? ($pdf ?? false ? storage_path('app/public/'.$f) : \App\Ledger\Models\PrintSetting::fileUrl($f)) : null;
@endphp
<div class="payslip" style="max-width: {{ $print->width ?: '800px' }};">
    <table class="ps-head" width="100%">
        <tr>
            <td width="33%" valign="top">
                @if($print->logo_left)<img src="{{ $logo($print->logo_left) }}" style="max-height:60px"><br>@endif
                <strong class="ps-inst">{!! nl2br(e($print->header_left ?: ($books['name'] ?? ''))) !!}</strong>
            </td>
            <td width="34%" valign="top" align="center">{!! nl2br(e($print->header_center)) !!}</td>
            <td width="33%" valign="top" align="right">
                @if($print->logo_right)<img src="{{ $logo($print->logo_right) }}" style="max-height:60px"><br>@endif
                <div class="ps-title">{{ $print->title ?: __('ledger_payroll.pay_slip') }}</div>
                <div class="ps-period">{{ $row->salary_month->translatedFormat('F Y') }}</div>
                <div class="ps-ref">@lang('ledger_payroll.ref') {{ $print->prefix ?: 'PS' }}-{{ str_pad($row->id, 5, '0', STR_PAD_LEFT) }}</div>
                @if($print->header_right)<div>{!! nl2br(e($print->header_right)) !!}</div>@endif
            </td>
        </tr>
    </table>

    <div class="ps-bar">@lang('ledger_payroll.employee_information')</div>
    <table class="ps-info" width="100%">
        <tr>
            <td><span class="lbl">@lang('ledger_payroll.employee_name')</span><br><strong>{{ $s->name ?? '' }}</strong></td>
            <td><span class="lbl">@lang('ledger_payroll.field_staff_id')</span><br>{{ $s->staff_no ?? '-' }}</td>
            <td><span class="lbl">@lang('ledger_payroll.field_department')</span><br>{{ $s->department->title ?? '-' }}</td>
            <td><span class="lbl">@lang('ledger_payroll.field_designation')</span><br>{{ $s->designation->title ?? '-' }}</td>
        </tr>
        <tr>
            <td><span class="lbl">@lang('ledger_payroll.field_salary_type')</span><br>{{ $row->salary_type == 2 ? __('ledger_payroll.salary_type_hourly') : __('ledger_payroll.salary_type_fixed') }}</td>
            <td><span class="lbl">@lang('ledger_payroll.field_basic_salary')</span><br><strong>{{ $money($row->basic_salary) }} FCFA</strong></td>
            <td><span class="lbl">@lang('ledger_payroll.field_cnps_no')</span><br>{{ $s->cnps_no ?: '-' }}</td>
            <td><span class="lbl">@lang('ledger_payroll.field_niu')</span><br>{{ $s->niu ?: '-' }}</td>
        </tr>
        <tr>
            <td><span class="lbl">@lang('ledger_payroll.field_pay_date')</span><br>{{ $row->pay_date ? $row->pay_date->format('d/m/Y') : '-' }}</td>
            <td><span class="lbl">@lang('ledger_payroll.field_status')</span><br>{{ $row->isPaid() ? __('ledger_payroll.status_paid') : __('ledger_payroll.status_unpaid') }}</td>
            <td><span class="lbl">@lang('ledger_payroll.field_payment_method')</span><br>{{ $row->payment_method ? ($methods[$row->payment_method] ?? $row->payment_method) : '-' }}</td>
            <td><span class="lbl">@lang('ledger_payroll.field_pay_to')</span><br>{{ $s ? $s->payTo() : '-' }}</td>
        </tr>
    </table>

    @if($print->body)<div class="ps-body">{!! nl2br(e($print->body)) !!}</div>@endif

    <table width="100%" class="ps-cols">
        <tr>
            <td width="50%" valign="top">
                <table width="100%" class="ps-table">
                    <tr><th colspan="2" class="earn">@lang('ledger_payroll.earnings')</th></tr>
                    <tr>
                        <td>@if($row->salary_type == 2 && $row->hours_worked !== null) @lang('ledger_payroll.hours_worked_line', ['hours' => rtrim(rtrim(number_format((float) $row->hours_worked, 2, ',', ''), '0'), ','), 'rate' => $money($row->basic_salary)]) @elseif($row->paid_days !== null) @lang('ledger_payroll.basic_prorated', ['paid' => (float) $row->paid_days + 0, 'period' => (float) $row->period_days + 0]) @else @lang('ledger_payroll.basic_total_earning') @endif</td>
                        <td align="right">{{ $money($row->total_earning) }}</td>
                    </tr>
                    @forelse($allowances as $d)
                    <tr><td>{{ $d->title }}@if($d->is_taxable) <small>(@lang('ledger_payroll.taxed_lc'))</small>@endif</td><td align="right">{{ $money($d->amount) }}</td></tr>
                    @empty
                    <tr><td class="muted">@lang('ledger_payroll.no_additional_allowances')</td><td align="right">-</td></tr>
                    @endforelse
                    <tr class="sub"><td>@lang('ledger_payroll.total_earnings')</td><td align="right">{{ $money($totalEarnings) }} FCFA</td></tr>
                </table>
            </td>
            <td width="50%" valign="top">
                <table width="100%" class="ps-table">
                    <tr><th colspan="2" class="ded">@lang('ledger_payroll.deductions')</th></tr>
                    @foreach($applicable as $t)
                    <tr><td>{{ $t['label'] }}</td><td align="right">{{ $money($t['employee']) }}</td></tr>
                    @endforeach
                    @if(! $tax_lines_reconcile)
                    <tr><td>@lang('ledger_payroll.field_tax')</td><td align="right">{{ $money($row->tax) }}</td></tr>
                    @endif
                    @foreach($deductions as $d)
                    <tr><td>{{ $d->title }}@if($d->staff_loan_id) <small>(@lang('ledger_payroll.loan_repayment'))</small>@endif</td><td align="right">{{ $money($d->amount) }}</td></tr>
                    @endforeach
                    @if(empty($applicable) && $deductions->isEmpty())
                    <tr><td class="muted">@lang('ledger_payroll.no_deductions')</td><td align="right">-</td></tr>
                    @endif
                    <tr class="sub"><td>@lang('ledger_payroll.total_deductions')</td><td align="right">{{ $money($totalDeductions) }} FCFA</td></tr>
                </table>
            </td>
        </tr>
    </table>

    @if(count($employer))
    <div class="ps-bar">@lang('ledger_payroll.employer_contributions')</div>
    <table width="100%" class="ps-table">
        @foreach($employer as $t)
        <tr><td>{{ $t['label'] }}</td><td align="right">{{ $money($t['employer']) }}</td></tr>
        @endforeach
        <tr class="sub"><td>@lang('ledger_payroll.field_employer_tax')</td><td align="right">{{ $money($row->employer_tax) }} FCFA</td></tr>
    </table>
    @endif

    <table width="100%" class="ps-net">
        <tr>
            <td>@lang('ledger_payroll.field_gross_salary'): <strong>{{ $money($row->gross_salary) }}</strong></td>
            <td>@lang('ledger_payroll.field_employee_tax'): <strong>{{ $money($row->tax) }}</strong></td>
            <td align="right" class="net">@lang('ledger_payroll.net_pay'): {{ $money($row->net_salary) }} FCFA</td>
        </tr>
        <tr><td colspan="3" class="muted">@lang('ledger_payroll.field_total_cost'): {{ $money($row->total_cost) }} FCFA</td></tr>
    </table>

    <table width="100%" class="ps-sign">
        <tr>
            <td width="50%">@lang('ledger_payroll.employer_signature')<br><br>________________________</td>
            <td width="50%" align="right">@lang('ledger_payroll.employee_signature')<br><br>________________________</td>
        </tr>
    </table>
    <table width="100%" class="ps-foot">
        <tr>
            <td width="33%">{!! nl2br(e($print->footer_left)) !!}</td>
            <td width="34%" align="center">{!! nl2br(e($print->footer_center)) !!}</td>
            <td width="33%" align="right">{!! nl2br(e($print->footer_right)) !!}</td>
        </tr>
    </table>
</div>
