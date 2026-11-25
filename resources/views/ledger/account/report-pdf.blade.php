{{-- The filtered income or expense list as a document (paxhitestbed admin/account/report-pdf): every row the screen's filter selects. --}}
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: sans-serif; font-size: 10pt; color: #15233b; }
    h1 { font-size: 15pt; margin: 0 0 2pt; color: {{ $kind === 'expense' ? '#9a3b12' : '#1856a4' }}; }
    .sub { color: #64748b; font-size: 9pt; margin-bottom: 10pt; }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; font-size: 8.5pt; color: #4a5870; border-bottom: 1px solid #cbd5e1; padding: 4pt; }
    td { padding: 4pt; border-bottom: 1px solid #eef1f6; font-size: 9pt; }
    .n { text-align: right; }
    tfoot td { font-weight: bold; border-top: 1.5pt solid #15233b; }
</style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="sub">
        {{ $books['name'] ?? '' }} · {{ $window['label'] }}
        @if($window['start'] && $window['end']) ({{ \Carbon\Carbon::parse($window['start'])->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($window['end'])->format('d/m/Y') }})@endif
        · {{ number_format($summary['count']) }} @lang('ledger_accounts.records') · @lang('ledger_accounts.generated') {{ $generatedAt->format('d/m/Y H:i') }}
    </div>
    <table>
        <thead>
            <tr>
                <th>#</th><th>@lang('ledger_accounts.field_date')</th><th>@lang('ledger_accounts.field_title')</th><th>@lang('ledger_accounts.field_category')</th>
                <th>@lang('ledger_accounts.field_payment_method')</th><th>@lang('ledger_accounts.field_invoice_id')</th><th class="n">@lang('ledger_accounts.field_amount')</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $i => $row)
            <tr>
                <td>{{ $i + 1 }}</td><td>{{ $row->date->format('d/m/Y') }}</td><td>{{ $row->title }}</td><td>{{ $row->category->name ?? '-' }}</td>
                <td>{{ \App\Ledger\Services\PaymentBook::methodLabel($row->payment_method) }}</td><td>{{ $row->invoice_id }}</td>
                <td class="n">{{ \App\Ledger\Ledger::money($row->amount) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr><td colspan="6">@lang('ledger_accounts.field_total')</td><td class="n">{{ \App\Ledger\Ledger::money($summary['total']) }} FCFA</td></tr>
        </tfoot>
    </table>
</body>
</html>
