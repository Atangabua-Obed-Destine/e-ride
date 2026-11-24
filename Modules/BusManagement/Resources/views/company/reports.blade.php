@extends('busmanagement::company.layout')

@section('title', translate('Reports'))

@php
    $m = fn($n) => number_format((float)$n, 0, '.', ',') . ' FCFA';
    $t = $report['totals'];
    $q = request()->getQueryString();
    $csv = fn($s) => route('bus-company.reports.csv', $s) . ($q ? '?' . $q : '');
@endphp

@section('content')
<h1 class="fs-5 fw-bold mb-2">{{ translate('Reports') }}</h1>
@include('busmanagement::company._filters')

<div class="kpis mb-3">
    <div class="kpi"><b>{{ $m($t['total']) }}</b><span>{{ translate('Taken (less refunds)') }}</span></div>
    <div class="kpi"><b>{{ $m($t['cash']) }}</b><span>{{ translate('Cash') }}</span></div>
    <div class="kpi"><b>{{ $m($t['momo']) }}</b><span>MTN Mobile Money</span></div>
    <div class="kpi"><b>{{ $m($t['orange']) }}</b><span>Orange Money</span></div>
    <div class="kpi"><b>{{ $t['tickets'] }}</b><span>{{ translate(key: 'Tickets: {app} in the app, {counter} at the counter', replace: ['app' => $t['app_tickets'], 'counter' => $t['counter_tickets']]) }}</span></div>
    <div class="kpi"><b>{{ $t['departures'] }}</b><span>{{ translate(key: 'Buses that left, {n} no-shows', replace: ['n' => $t['no_shows']]) }}</span></div>
    <div class="kpi"><b>{{ $m($t['commission']) }}</b><span>{{ translate('E-Ride commission') }}</span></div>
</div>

<section class="b-card p-3 mb-3" aria-labelledby="rBranch">
    <div class="d-flex align-items-center gap-2 mb-2"><h2 class="b-h me-auto" id="rBranch">{{ translate('By branch') }}</h2><a class="btn btn-soft btn-sm" href="{{ $csv('branches') }}"><i class="bi bi-download"></i> CSV</a></div>
    <div class="ho-scroll"><table class="ho-table">
        <thead><tr><th>{{ translate('Branch') }}</th><th class="n">{{ translate('Tickets app / counter') }}</th><th class="n">{{ translate('Taken') }}</th><th class="n">{{ translate('Cash') }}</th><th class="n">MTN</th><th class="n">Orange</th>
            <th class="n">{{ translate('Seats filled') }}</th><th class="n">{{ translate('No-shows') }}</th><th class="n">{{ translate('Answer time') }}</th><th class="n">{{ translate('Declined / not answered') }}</th><th class="n">{{ translate('Cancelled') }}</th><th class="n">{{ translate('Refunds to send') }}</th></tr></thead>
        <tbody>
        @foreach($report['branches'] as $b)
            <tr>
                <td><b>{{ $b['name'] }}</b><div class="b-hint">{{ $b['region'] }}</div></td>
                <td class="n">{{ $b['app_tickets'] }} / {{ $b['counter_tickets'] }}</td>
                <td class="n"><b>{{ $m($b['total']) }}</b></td>
                <td class="n">{{ $m($b['cash']) }}</td><td class="n">{{ $m($b['momo']) }}</td><td class="n">{{ $m($b['orange']) }}</td>
                <td class="n">{{ $b['fill'] !== null ? $b['fill'] . '%' : '·' }}</td>
                <td class="n">{{ $b['no_shows'] }}</td>
                <td class="n">{{ $b['answer_minutes'] !== null ? $b['answer_minutes'] . ' min' : '·' }}</td>
                <td class="n {{ ($b['declined'] + $b['unanswered']) > 0 ? 'text-danger' : '' }}">{{ $b['declined'] }} / {{ $b['unanswered'] }}@if($b['asked']) <div class="b-hint">{{ translate(key: '{n} asked', replace: ['n' => $b['asked']]) }}</div>@endif</td>
                <td class="n">{{ $b['cancelled'] }}</td>
                <td class="n {{ $b['refunds_due'] ? 'text-warning fw-bold' : '' }}">{{ $b['refunds_due'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
</section>

<section class="b-card p-3 mb-3" aria-labelledby="rAgent">
    <div class="d-flex align-items-center gap-2 mb-1"><h2 class="b-h me-auto" id="rAgent">{{ translate('Counter agents') }}</h2><a class="btn btn-soft btn-sm" href="{{ $csv('agents') }}"><i class="bi bi-download"></i> CSV</a></div>
    <p class="b-hint mb-2">{{ translate('What each agent sold at the counter and how it was paid. Paper sales typed in late, and sales in the name of the person paying only, are worth a look.') }}</p>
    <div class="ho-scroll"><table class="ho-table">
        <thead><tr><th>{{ translate('Agent') }}</th><th>{{ translate('Branch') }}</th><th class="n">{{ translate('Tickets') }}</th><th class="n">{{ translate('Taken') }}</th><th class="n">{{ translate('Cash') }}</th><th class="n">MTN</th><th class="n">Orange</th><th class="n">{{ translate('Paper sales') }}</th><th class="n">{{ translate('Payer only') }}</th></tr></thead>
        <tbody>
        @forelse($report['agents'] as $a)
            <tr><td><b>{{ $a['name'] }}</b></td><td>{{ $a['branch'] }}</td><td class="n">{{ $a['tickets'] }}</td><td class="n"><b>{{ $m($a['total']) }}</b></td>
                <td class="n">{{ $m($a['cash']) }}</td><td class="n">{{ $m($a['momo']) }}</td><td class="n">{{ $m($a['orange']) }}</td>
                <td class="n {{ $a['paper_sales'] ? 'text-warning fw-bold' : '' }}">{{ $a['paper_sales'] }}</td><td class="n">{{ $a['payer_only'] }}</td></tr>
        @empty
            <tr><td colspan="9" class="b-hint">{{ translate('No counter sale in this period.') }}</td></tr>
        @endforelse
        </tbody>
    </table></div>
</section>

<section class="b-card p-3 mb-3" aria-labelledby="rRoute">
    <div class="d-flex align-items-center gap-2 mb-2"><h2 class="b-h me-auto" id="rRoute">{{ translate('By route') }}</h2><a class="btn btn-soft btn-sm" href="{{ $csv('routes') }}"><i class="bi bi-download"></i> CSV</a></div>
    <div class="ho-scroll"><table class="ho-table">
        <thead><tr><th>{{ translate('Route') }}</th><th>{{ translate('Branch') }}</th><th class="n">{{ translate('Buses that left') }}</th><th class="n">{{ translate('Seats sold') }}</th><th class="n">{{ translate('Seats filled') }}</th><th class="n">{{ translate('Fares') }}</th></tr></thead>
        <tbody>
        @forelse($report['routes'] as $r)
            <tr><td><b>{{ $r['from'] }} &rarr; {{ $r['to'] }}</b></td><td>{{ $r['branch'] }}</td><td class="n">{{ $r['departures'] }}</td><td class="n">{{ $r['sold'] }} / {{ $r['capacity'] }}</td>
                <td class="n">{{ $r['fill'] !== null ? $r['fill'] . '%' : '·' }}</td><td class="n">{{ $m($r['fares']) }}</td></tr>
        @empty
            <tr><td colspan="6" class="b-hint">{{ translate('No bus left in this period.') }}</td></tr>
        @endforelse
        </tbody>
    </table></div>
</section>

<section class="b-card p-3" aria-labelledby="rDay">
    <div class="d-flex align-items-center gap-2 mb-2"><h2 class="b-h me-auto" id="rDay">{{ translate('By day') }}</h2><a class="btn btn-soft btn-sm" href="{{ $csv('days') }}"><i class="bi bi-download"></i> CSV</a></div>
    <div class="ho-scroll"><table class="ho-table">
        <thead><tr><th>{{ translate('Day') }}</th><th class="n">{{ translate('Tickets') }}</th><th class="n">{{ translate('App') }}</th><th class="n">{{ translate('Counter') }}</th><th class="n">{{ translate('Taken') }}</th></tr></thead>
        <tbody>
        @forelse($report['days'] as $d)
            <tr><td>{{ \Illuminate\Support\Carbon::parse($d['day'])->translatedFormat('D j M') }}</td><td class="n">{{ $d['tickets'] }}</td><td class="n">{{ $m($d['app']) }}</td><td class="n">{{ $m($d['counter']) }}</td><td class="n"><b>{{ $m($d['total']) }}</b></td></tr>
        @empty
            <tr><td colspan="5" class="b-hint">{{ translate('Nothing sold in this period.') }}</td></tr>
        @endforelse
        </tbody>
    </table></div>
</section>
@endsection
