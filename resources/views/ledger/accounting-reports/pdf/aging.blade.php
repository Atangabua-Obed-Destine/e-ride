@extends('ledger.general-ledger.pdf-layout')
@php
    $L = fn ($k, $r = []) => __('ledger_reports.'.$k, $r);
    $m = fn ($v) => \App\Ledger\Ledger::money($v);
    $cur = session('currency')['symbol'] ?? 'FCFA';
    $isR = $reportType === 'receivables_aging';
    $s = $data['summary'] ?? [];
    $d = fn ($x) => \Carbon\Carbon::parse($x)->format('d/m/Y');
@endphp
@section('title', $title)
@section('report_title', $L($isR ? 'receivables_aging_report' : 'payables_aging_report'))

@section('content')
    @if(isset($data['as_of_date']))
    <p class="text-center" style="margin-bottom: 14px;"><strong>{{ $L('as_of_date') }}:</strong> {{ $d($data['as_of_date']) }}</p>
    @endif

    {{-- Summary --}}
    @if(isset($data['summary']))
    <table>
        <tr>
            @foreach([['current', 'current_0_30_days', 'text-success'], ['days_31_60', 'days_31_60', ''], ['days_61_90', 'days_61_90', ''], ['days_91_120', 'days_91_120', ''], ['over_120', 'over_120_days', 'text-danger'], ['total', 'total_outstanding', '']] as [$k, $label, $cls])
            <td class="text-center" style="background:#f8f9fa">
                <div style="font-size:7pt; color:#666; text-transform:uppercase">{{ $L($label) }}</div>
                <div class="{{ $cls }}" style="font-size:11pt; font-weight:bold">{{ $m($s[$k] ?? 0) }} {{ $cur }}</div>
            </td>
            @endforeach
        </tr>
    </table>
    @endif

    <div class="section-title">{{ $L($isR ? 'aging_details_by_customer' : 'aging_details_by_supplier') }}</div>
    <table>
        <thead>
            <tr>
                <th>{{ $L($isR ? 'customer' : 'supplier') }}</th>
                <th class="text-right">{{ $L('current') }}</th>
                <th class="text-right">{{ $L('days_31_60') }}</th>
                <th class="text-right">{{ $L('days_61_90') }}</th>
                <th class="text-right">{{ $L('days_91_120') }}</th>
                <th class="text-right">{{ $L('over_120_days') }}</th>
                <th class="text-right">{{ $L('total') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data['details'] ?? [] as $item)
            <tr>
                <td>{{ $item['customer_name'] ?? '' }}</td>
                <td class="text-right">{{ $m($item['current'] ?? 0) }}</td>
                <td class="text-right">{{ $m($item['days_31_60'] ?? 0) }}</td>
                <td class="text-right">{{ $m($item['days_61_90'] ?? 0) }}</td>
                <td class="text-right">{{ $m($item['days_91_120'] ?? 0) }}</td>
                <td class="text-right text-danger">{{ $m($item['over_120'] ?? 0) }}</td>
                <td class="text-right"><strong>{{ $m($item['total'] ?? 0) }}</strong></td>
            </tr>
            @empty
            <tr><td colspan="7" class="text-center">{{ $L($isR ? 'no_receivables_data_found' : 'no_payables_data_found') }}</td></tr>
            @endforelse
        </tbody>
        @if(isset($data['summary']))
        <tfoot>
            <tr class="totals-row">
                <td>{{ $L('grand_total') }}</td>
                <td class="text-right">{{ $m($s['current'] ?? 0) }}</td>
                <td class="text-right">{{ $m($s['days_31_60'] ?? 0) }}</td>
                <td class="text-right">{{ $m($s['days_61_90'] ?? 0) }}</td>
                <td class="text-right">{{ $m($s['days_91_120'] ?? 0) }}</td>
                <td class="text-right text-danger">{{ $m($s['over_120'] ?? 0) }}</td>
                <td class="text-right">{{ $m($s['total'] ?? 0) }}</td>
            </tr>
        </tfoot>
        @endif
    </table>

    {{-- tie-out --}}
    <table style="width:60%">
        <tr><td>{{ $L('owed_on_the_documents_above') }}</td><td class="text-right">{{ $m($s['total'] ?? 0) }}</td></tr>
        <tr><td>{{ $L($isR ? 'balance_of_the_customer_account' : 'balance_of_the_supplier_account') }} ({{ implode(', ', $data['ledger_accounts'] ?? []) }})</td><td class="text-right">{{ $m($data['ledger_balance'] ?? 0) }}</td></tr>
        <tr class="totals-row"><td>{{ $L('difference') }}</td><td class="text-right">{{ $m($data['difference'] ?? 0) }}</td></tr>
    </table>
    <p style="font-size:8pt; color:#666">{{ abs($data['difference'] ?? 0) > 0.005 ? $L($isR ? 'ledger_and_documents_differ_customers' : 'ledger_and_documents_differ_suppliers') : $L('documents_agree_with_ledger') }}</p>

    <div class="section-title">{{ $L('documents_behind_these_figures') }}</div>
    <table>
        <thead>
            <tr>
                <th>{{ $L($isR ? 'customer' : 'supplier') }}</th>
                <th>{{ $L('document') }}</th>
                <th>{{ $L('date') }}</th>
                <th>{{ $L('due') }}</th>
                <th class="text-right">{{ $L('days_overdue') }}</th>
                <th class="text-right">{{ $L('owed') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['details'] ?? [] as $item)
            @foreach($item['documents'] as $doc)
            <tr>
                <td>{{ $item['customer_name'] }}</td>
                <td>{{ $doc['number'] }} ({{ __('ledger.doc_'.$doc['type']) }})</td>
                <td>{{ $d($doc['date']) }}</td>
                <td>{{ $d($doc['due_date']) }}</td>
                <td class="text-right">{{ $doc['days_overdue'] }}</td>
                <td class="text-right">{{ $m($doc['owed']) }}</td>
            </tr>
            @endforeach
            @endforeach
        </tbody>
    </table>
@endsection
