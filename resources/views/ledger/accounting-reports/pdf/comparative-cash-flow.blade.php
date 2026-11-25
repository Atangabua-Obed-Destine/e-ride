@extends('ledger.general-ledger.pdf-layout')
@php
    $L = fn ($k) => __('ledger_reports.'.$k);
    $m = fn ($v) => \App\Ledger\Ledger::money($v);
    $cur = session('currency')['symbol'] ?? 'FCFA';
    $pct = fn ($v) => number_format((float) $v, 1, ',', ' ').'%';
    $cls = fn ($v) => (float) $v >= 0 ? 'text-success' : 'text-danger';
@endphp
@section('title', $title)
@section('report_title', $L('comparative_cash_flow_statement'))

@section('content')
    @if(isset($data['periods']))
    <p class="text-center" style="margin-bottom: 14px;">
        <strong>{{ $L('comparing') }}:</strong>
        {{ $data['periods']['current']['name'] ?? $L('current_period') }} {{ $L('vs') }} {{ $data['periods']['previous']['name'] ?? $L('previous_period') }}
    </p>
    @endif

    <table>
        <thead>
            <tr>
                <th>{{ $L('description') }}</th>
                <th class="text-right">{{ $data['periods']['current']['name'] ?? $L('current') }}</th>
                <th class="text-right">{{ $data['periods']['previous']['name'] ?? $L('previous') }}</th>
                <th class="text-right">{{ $L('variance') }}</th>
                <th class="text-right">{{ $L('change_pct') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach(['operating', 'investing', 'financing'] as $section)
            <tr class="totals-row"><td colspan="5"><strong>{{ $L($section.'_activities') }}</strong></td></tr>
            @foreach($data[$section]['items'] ?? [] as $item)
            <tr>
                <td style="padding-left: 20px;">{{ $item['description'] ?? '' }}</td>
                <td class="text-right">{{ $m($item['current'] ?? 0) }}</td>
                <td class="text-right">{{ $m($item['previous'] ?? 0) }}</td>
                <td class="text-right {{ $cls($item['variance'] ?? 0) }}">{{ $m($item['variance'] ?? 0) }}</td>
                <td class="text-right {{ $cls($item['change_percent'] ?? 0) }}">{{ $pct($item['change_percent'] ?? 0) }}</td>
            </tr>
            @endforeach
            <tr style="background-color: #e9ecef;">
                <td><strong>{{ $L('net_cash_from_'.$section) }}</strong></td>
                <td class="text-right"><strong>{{ $m($data[$section]['current_total'] ?? 0) }}</strong></td>
                <td class="text-right"><strong>{{ $m($data[$section]['previous_total'] ?? 0) }}</strong></td>
                <td class="text-right {{ $cls($data[$section]['variance'] ?? 0) }}"><strong>{{ $m($data[$section]['variance'] ?? 0) }}</strong></td>
                <td class="text-right {{ $cls($data[$section]['change_percent'] ?? 0) }}"><strong>{{ $pct($data[$section]['change_percent'] ?? 0) }}</strong></td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="totals-row">
                <td><strong>{{ $L('net_change_in_cash') }}</strong></td>
                <td class="text-right">{{ $m($data['net_change']['current'] ?? 0) }} {{ $cur }}</td>
                <td class="text-right">{{ $m($data['net_change']['previous'] ?? 0) }} {{ $cur }}</td>
                <td class="text-right {{ $cls($data['net_change']['variance'] ?? 0) }}">{{ $m($data['net_change']['variance'] ?? 0) }} {{ $cur }}</td>
                <td class="text-right {{ $cls($data['net_change']['change_percent'] ?? 0) }}">{{ $pct($data['net_change']['change_percent'] ?? 0) }}</td>
            </tr>
        </tfoot>
    </table>
@endsection
