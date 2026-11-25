@extends('ledger.general-ledger.pdf-layout')
@php
    $L = fn ($k) => __('ledger_reports.'.$k);
    $m = fn ($v) => \App\Ledger\Ledger::money($v);
    $cur = session('currency')['symbol'] ?? 'FCFA';
    $d = fn ($x) => \Carbon\Carbon::parse($x)->format('d/m/Y');
@endphp
@section('title', $title)
@section('report_title', $L('cash_flow_statement'))

@section('content')
    @if(isset($data['fiscal_year']))
    <p class="text-center" style="margin-bottom: 14px;">
        <strong>{{ $L('fiscal_year') }}:</strong> {{ $data['fiscal_year'] }}
        ({{ $d($data['start_date']) }} - {{ $d($data['end_date']) }})
    </p>
    @endif

    @foreach(['operating', 'investing', 'financing'] as $section)
    <div class="section-title">{{ $L($section.'_activities') }}</div>
    <table>
        <tbody>
            @foreach($data[$section.'_activities']['items'] ?? [] as $item)
            <tr>
                <td>{{ $item['description'] ?? '' }}</td>
                <td class="text-right">{{ $m($item['amount'] ?? 0) }} {{ $cur }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="totals-row">
                <td>{{ $L('net_cash_from_'.$section.'_activities') }}</td>
                <td class="text-right">{{ $m($data[$section.'_activities']['total'] ?? 0) }} {{ $cur }}</td>
            </tr>
        </tfoot>
    </table>
    @endforeach

    <div class="section-title">{{ $L('cash_flow_summary') }}</div>
    <table>
        <tbody>
            <tr>
                <td>{{ $L('net_increase_decrease_in_cash_pdf') }}</td>
                <td class="text-right {{ ($data['net_change_in_cash'] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}"><strong>{{ $m($data['net_change_in_cash'] ?? 0) }} {{ $cur }}</strong></td>
            </tr>
            <tr>
                <td>{{ $L('beginning_cash_balance') }}</td>
                <td class="text-right">{{ $m($data['beginning_cash_balance'] ?? 0) }} {{ $cur }}</td>
            </tr>
        </tbody>
        <tfoot>
            <tr class="totals-row">
                <td>{{ $L('ending_cash_balance') }}</td>
                <td class="text-right">{{ $m($data['ending_cash_balance'] ?? 0) }} {{ $cur }}</td>
            </tr>
        </tfoot>
    </table>
    @unless($data['is_balanced'] ?? true)
    <p class="text-danger">{{ $L('movements_do_not_explain_change_in_cash_by') }} {{ $m(abs($data['difference'])) }} {{ $cur }}. {{ $L('check_the_trial_balance') }}</p>
    @endunless
@endsection
