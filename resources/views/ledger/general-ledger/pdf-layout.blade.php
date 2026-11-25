<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Report')</title>
    <style>
        body { font-family: sans-serif; font-size: 10pt; color: #333; }
        .report-header { text-align: center; margin-bottom: 18px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .company-name { font-size: 16pt; font-weight: bold; color: #1a1a1a; }
        .report-title { font-size: 13pt; font-weight: bold; color: #444; margin: 6px 0; }
        .report-date { font-size: 8pt; color: #666; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        th, td { border: 1px solid #ddd; padding: 5px 7px; text-align: left; }
        th { background-color: #f5f5f5; font-weight: bold; color: #333; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold td { font-weight: bold; }
        .text-danger { color: #dc3545; }
        .text-success { color: #28a745; }
        .section-title { font-size: 11pt; font-weight: bold; color: #333; margin: 14px 0 6px; padding-bottom: 4px; border-bottom: 1px solid #ddd; }
        .totals-row td { background-color: #e9ecef; font-weight: bold; }
        .report-footer { margin-top: 20px; padding-top: 10px; border-top: 1px solid #ddd; font-size: 8pt; color: #666; text-align: center; }
    </style>
</head>
<body>
    <div class="report-header">
        <div class="company-name">{{ $businessName ?? '' }}</div>
        <div class="report-title">@yield('report_title')</div>
        <div class="report-date">{{ __('ledger_gl.generated_on') }}: {{ now()->format('d/m/Y H:i') }}</div>
    </div>

    @yield('content')

    <div class="report-footer">
        <p>{{ $businessName ?? '' }} - {{ __('ledger_gl.accounting_module') }}</p>
        <p>{{ __('ledger_gl.system_generated') }}</p>
    </div>
</body>
</html>
