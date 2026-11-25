<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('ledger_payroll.pay_slip') }} - {{ $row->staff->name ?? '' }} - {{ $row->salary_month->translatedFormat('F Y') }}</title>
    @include('ledger.payroll.payroll._payslip_style')
</head>
<body>
{{-- paxhitestbed admin/payroll/print: the payslip in its own window, with a print button. --}}
<div class="no-print" style="text-align:center; margin: 10px;">
    <button type="button" onclick="window.print()">{{ __('ledger_payroll.btn_print') }}</button>
    <a href="{{ route('ledger.payroll.pdf', $row->id) }}">{{ __('ledger_payroll.payslip_pdf') }}</a>
</div>
@include('ledger.payroll.payroll.payslip_body', ['pdf' => false])
</body>
</html>
