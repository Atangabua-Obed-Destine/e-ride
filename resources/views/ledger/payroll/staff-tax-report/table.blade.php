{{-- The per-staff tax distribution table (paxhitestbed admin/staff-tax-report index and pdf). --}}
@php $money = fn ($v) => \App\Ledger\Ledger::money($v); @endphp
<table class="table table-bordered table-sm ap-table" width="100%" style="border-collapse: collapse;">
    <thead>
        <tr>
            <th rowspan="2">@lang('ledger_payroll.field_staff_id')</th>
            <th rowspan="2">@lang('ledger_payroll.field_name')</th>
            <th rowspan="2" class="ap-num">@lang('ledger_payroll.field_basic_salary')</th>
            @foreach($tax_columns as $c)<th colspan="2" class="text-center">{{ $c['title'] }}@if(!empty($c['dependent'])) <small>(@lang('ledger_payroll.dependent'))</small>@endif</th>@endforeach
            <th rowspan="2" class="ap-num">@lang('ledger_payroll.field_employee_tax')</th>
            <th rowspan="2" class="ap-num">@lang('ledger_payroll.field_employer_tax')</th>
            <th rowspan="2" class="ap-num">@lang('ledger_payroll.field_net_salary')</th>
            <th rowspan="2" class="ap-num">@lang('ledger_payroll.field_total_cost')</th>
            <th rowspan="2" class="ap-num">@lang('ledger_payroll.rate')</th>
        </tr>
        <tr>@foreach($tax_columns as $c)<th class="ap-num">@lang('ledger_payroll.employee')</th><th class="ap-num">@lang('ledger_payroll.employer')</th>@endforeach</tr>
    </thead>
    <tbody>
        @forelse($staff_rows as $r)
        <tr>
            <td>{{ $r['staff']->staff_no }}</td>
            <td>{{ $r['staff']->name }}@if($r['exemption_count']) <span class="badge bg-info text-dark">{{ $r['exemption_count'] }} @lang('ledger_payroll.exemptions_lc')</span>@endif</td>
            <td class="ap-num">{{ $money($r['basic_salary']) }}</td>
            @foreach($tax_columns as $c)
            @php $cell = $r[$c['type'] === 'group' ? 'groups' : 'standalone'][$c['id']] ?? ['employee' => 0, 'employer' => 0, 'is_exempt' => false]; @endphp
            <td class="ap-num">{{ $cell['employee'] ? $money($cell['employee']) : '-' }}@if($cell['is_exempt'])*@endif</td>
            <td class="ap-num">{{ $cell['employer'] ? $money($cell['employer']) : '-' }}</td>
            @endforeach
            <td class="ap-num"><strong>{{ $money($r['employee_tax_total']) }}</strong></td>
            <td class="ap-num">{{ $money($r['employer_tax_total']) }}</td>
            <td class="ap-num">{{ $money($r['net_salary']) }}</td>
            <td class="ap-num">{{ $money($r['total_cost']) }}</td>
            <td class="ap-num">{{ $r['basic_salary'] > 0 ? number_format($r['employee_tax_total'] / $r['basic_salary'] * 100, 2, ',', '') : '0' }}%</td>
        </tr>
        @empty
        <tr><td colspan="{{ 8 + 2 * count($tax_columns) }}" class="text-center text-muted">@lang('ledger_payroll.no_staff')</td></tr>
        @endforelse
    </tbody>
    <tfoot>
        <tr class="ap-total-row">
            <th colspan="2">@lang('ledger_payroll.grand_total') ({{ $staff_count }})</th>
            <th class="ap-num">{{ $money($grand_totals['basic_salary']) }}</th>
            @foreach($tax_columns as $c)
            @php $g = $grand_totals[$c['type'] === 'group' ? 'groups' : 'standalone'][$c['id']] ?? ['employee' => 0, 'employer' => 0]; @endphp
            <th class="ap-num">{{ $money($g['employee']) }}</th><th class="ap-num">{{ $money($g['employer']) }}</th>
            @endforeach
            <th class="ap-num">{{ $money($grand_totals['employee_tax_total']) }}</th>
            <th class="ap-num">{{ $money($grand_totals['employer_tax_total']) }}</th>
            <th class="ap-num">{{ $money($grand_totals['net_salary']) }}</th>
            <th class="ap-num">{{ $money($grand_totals['total_cost']) }}</th>
            <th></th>
        </tr>
    </tfoot>
</table>
<small class="text-muted">* @lang('ledger_payroll.exempt_marker')</small>
