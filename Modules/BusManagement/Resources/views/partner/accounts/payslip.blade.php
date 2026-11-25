@php
    use Modules\BusManagement\Lib\AccFormat as F;
    $month = $slip->month->translatedFormat('F Y');
    $ownerName = $owner instanceof \Modules\BusManagement\Entities\BusCompany ? $owner->name : $owner->displayName();
    $e = $slip->employee;
    $emp = $slip->detail['employer'] ?? [];
    $withheld = $slip->cnps + $slip->irpp + $slip->cac + $slip->cfc + $slip->crtv + $slip->tdl;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ translate('Payslip') }} · {{ $e?->name }} · {{ $month }}</title>
    <style>
        body { font: 13.5px/1.45 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; color: #17191c; margin: 0; background: #f4f5f6; }
        .sheet { max-width: 760px; margin: 24px auto; background: #fff; border: 1px solid #dfe3e6; border-radius: 6px; padding: 28px 32px; }
        h1 { font-size: 20px; margin: 0; }
        .top { display: flex; justify-content: space-between; gap: 16px; border-bottom: 2px solid #17191c; padding-bottom: 12px; margin-bottom: 16px; }
        .muted { color: #5d6670; }
        .who { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 24px; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        th { text-align: left; font-size: 12px; color: #5d6670; border-bottom: 1px solid #dfe3e6; padding: 6px 4px; }
        td { padding: 6px 4px; border-bottom: 1px solid #f0f2f4; }
        .n { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        tr.tot td { font-weight: 800; border-top: 2px solid #17191c; border-bottom: 0; }
        .net { display: flex; justify-content: space-between; align-items: center; background: #17191c; color: #fff; border-radius: 6px; padding: 12px 16px; font-size: 16px; font-weight: 800; margin: 8px 0 16px; }
        .actions { max-width: 760px; margin: 0 auto; text-align: right; padding-top: 12px; }
        .actions button { border: 0; border-radius: 6px; background: #e02020; color: #fff; font-weight: 700; padding: 8px 14px; cursor: pointer; }
        .foot { font-size: 11.5px; color: #5d6670; }
        @media print { body { background: #fff; } .sheet { border: 0; margin: 0; padding: 0; } .actions { display: none; } }
    </style>
</head>
<body>
<div class="actions"><button onclick="window.print()">{{ translate('Print') }}</button></div>
<div class="sheet">
    <div class="top">
        <div><h1>{{ translate('Payslip') }}</h1><div class="muted">{{ $month }} · {{ $slip->document?->number }}</div></div>
        <div style="text-align: right"><b>{{ $ownerName }}</b><div class="muted">{{ translate('Employer') }}</div></div>
    </div>
    <div class="who">
        <div><span class="muted">{{ translate('Name') }}:</span> <b>{{ $e?->name ?? ($slip->detail['name'] ?? '') }}</b></div>
        <div><span class="muted">{{ translate('Job') }}:</span> {{ $e?->jobLabel() }}</div>
        <div><span class="muted">{{ translate('CNPS number') }}:</span> {{ $e?->cnps_number ?: '-' }}</div>
        <div><span class="muted">{{ translate('Taxpayer number (NIU)') }}:</span> {{ $e?->niu ?: '-' }}</div>
    </div>
    <table>
        <thead><tr><th>{{ translate('Pay') }}</th><th class="n">FCFA</th></tr></thead>
        <tbody>
            <tr><td>{{ translate('Monthly pay') }}</td><td class="n">{{ F::n($slip->base) }}</td></tr>
            @if($slip->extra > 0)<tr><td>{{ translate('Extra this month') }}</td><td class="n">{{ F::n($slip->extra) }}</td></tr>@endif
            <tr class="tot"><td>{{ translate('Gross pay') }}</td><td class="n">{{ F::n($slip->gross) }}</td></tr>
        </tbody>
    </table>
    <table>
        <thead><tr><th>{{ translate('Withheld') }}</th><th class="n">FCFA</th></tr></thead>
        <tbody>
            <tr><td>{{ translate('CNPS, pension (4.2%)') }}</td><td class="n">{{ F::n($slip->cnps) }}</td></tr>
            <tr><td>{{ translate('Income tax (IRPP)') }}</td><td class="n">{{ F::n($slip->irpp) }}</td></tr>
            <tr><td>{{ translate('Council surcharge (CAC, 10% of IRPP)') }}</td><td class="n">{{ F::n($slip->cac) }}</td></tr>
            <tr><td>{{ translate('Housing fund (CFC, 1%)') }}</td><td class="n">{{ F::n($slip->cfc) }}</td></tr>
            <tr><td>{{ translate('Audio-visual tax (CRTV)') }}</td><td class="n">{{ F::n($slip->crtv) }}</td></tr>
            <tr><td>{{ translate('Local development tax (TDL)') }}</td><td class="n">{{ F::n($slip->tdl) }}</td></tr>
            <tr class="tot"><td>{{ translate('Total withheld') }}</td><td class="n">{{ F::n($withheld) }}</td></tr>
        </tbody>
    </table>
    <div class="net"><span>{{ translate('Net pay') }}</span><span>{{ F::n($slip->net) }} FCFA</span></div>
    <table>
        <thead><tr><th>{{ translate('Paid by the employer, on top') }}</th><th class="n">FCFA</th></tr></thead>
        <tbody>
            <tr><td>{{ translate('CNPS: pension, family allowances, work accident') }}</td><td class="n">{{ F::n($slip->employer_cnps) }}</td></tr>
            <tr><td>{{ translate('Housing fund and employment fund (CFC, FNE)') }}</td><td class="n">{{ F::n($slip->employer_taxes) }}</td></tr>
            <tr class="tot"><td>{{ translate('Cost to the employer') }}</td><td class="n">{{ F::n($slip->gross + $slip->employer_cnps + $slip->employer_taxes) }}</td></tr>
        </tbody>
    </table>
    <p class="foot">{{ translate('Worked out with the DGI scale for IRPP and the CNPS rules. Made with E-Ride.') }}</p>
</div>
</body>
</html>
