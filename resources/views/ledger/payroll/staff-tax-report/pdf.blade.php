<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 8px; }
    h2 { font-size: 13px; margin: 0; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #ccc; padding: 2px 3px; }
    th { background: #f1f5f9; }
    .ap-num { text-align: right; }
    .badge { font-size: 7px; }
</style>
</head>
<body>
{{-- paxhitestbed admin/staff-tax-report/pdf: the same table, landscape. --}}
<h2>{{ $books['name'] ?? '' }} · {{ $title }}</h2>
<p>@lang('ledger_payroll.effective_date'): {{ \Carbon\Carbon::parse($effective_date)->format('d/m/Y') }} · @lang('ledger_payroll.generated_at') {{ $generated_at->format('d/m/Y H:i') }} · {{ \App\Ledger\Services\PaymentBook::userName($generated_by) }}</p>
@include('ledger.payroll.staff-tax-report.table')
</body>
</html>
