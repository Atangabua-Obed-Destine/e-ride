<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    @include('ledger.payroll.payroll._payslip_style')
</head>
<body>
{{-- paxhitestbed admin/payroll/payslip-pdf: the same payslip, for mPDF. --}}
@include('ledger.payroll.payroll.payslip_body', ['pdf' => true])
</body>
</html>
