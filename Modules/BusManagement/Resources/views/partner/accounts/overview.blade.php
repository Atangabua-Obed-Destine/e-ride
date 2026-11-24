@extends('busmanagement::partner.layout')

@section('title', translate('Accounts'))

@php use Modules\BusManagement\Lib\AccFormat as F; @endphp

@push('head')
    @include('busmanagement::partner.accounts._style')
@endpush

@section('content')
@include('busmanagement::partner.accounts._nav')

@if(!$o['balanced'] || $o['pending'] > 0)
    <div class="rv-warn mb-3">
        @if(!$o['balanced']){{ translate('The books do not balance. Contact E-Ride.') }}@endif
        @if($o['pending'] > 0){{ translate(key: '{n} ticket payments or refunds could not be posted yet (a closed month?). Reopen the month or contact E-Ride.', replace: ['n' => $o['pending']]) }}@endif
    </div>
@endif

<div class="d-flex flex-wrap gap-2 mb-3">
    <a class="btn btn-dark btn-sm" href="{{ route('bus-partner.accounts.spending') }}#new"><i class="bi bi-receipt"></i> {{ translate('Record an expense') }}</a>
    <a class="btn btn-soft btn-sm" href="{{ route('bus-partner.accounts.cash') }}"><i class="bi bi-cash-stack"></i> {{ translate('Count an agent cash') }}</a>
    <a class="btn btn-soft btn-sm" href="{{ route('bus-partner.accounts.money') }}#move"><i class="bi bi-arrow-left-right"></i> {{ translate('Move money') }}</a>
    <a class="btn btn-soft btn-sm" href="{{ route('bus-partner.accounts.money') }}#income"><i class="bi bi-plus-circle"></i> {{ translate('Other income') }}</a>
</div>

<h2 class="acc-h">{{ translate(key: '{month} so far', replace: ['month' => today()->translatedFormat('F Y')]) }}</h2>
@php
    $m = $o['month'];
    $tickets = collect($m['income'])->filter(fn($r) => in_array($r['account']->role, ['sales_online', 'sales_counter', 'refunds_given'], true))->sum('amount');
@endphp
<div class="acc-grid mb-4">
    <div class="acc-fig"><div class="k">{{ translate('Ticket sales (trips that left)') }}</div><div class="v">{{ F::n($tickets) }}</div></div>
    <div class="acc-fig"><div class="k">{{ translate('Other income') }}</div><div class="v">{{ F::n($m['total_income'] - $tickets) }}</div></div>
    <div class="acc-fig"><div class="k">{{ translate('Spending') }}</div><div class="v">{{ F::n($m['total_spending']) }}</div></div>
    <div class="acc-fig"><div class="k">{{ translate('Result') }}</div><div class="v {{ F::cls($m['result']) }}">{{ F::n($m['result']) }}</div></div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="acc-card">
            <h2 class="acc-h">{{ translate('Where the money is') }}</h2>
            <table class="acc-t">
                <tbody>
                @foreach($o['money'] as $row)
                    <tr><td><a href="{{ route('bus-partner.accounts.journal', ['account' => $row['account']->id, 'from' => $books->starts_on->toDateString()]) }}">{{ $row['account']->label() }}</a></td>
                        <td class="n {{ F::cls($row['balance']) }}">{{ F::n($row['balance']) }}</td></tr>
                @endforeach
                <tr class="tot"><td>{{ translate('Total') }}</td><td class="n">{{ F::n($o['money']->sum('balance')) }}</td></tr>
                </tbody>
            </table>
        </div>
        <div class="acc-card">
            <h2 class="acc-h">{{ translate('Counter cash with the agents') }}</h2>
            @forelse($o['agents'] as $ag)
                <div class="d-flex align-items-center gap-2 py-1 border-bottom">
                    <div class="flex-grow-1"><b>{{ $ag['name'] }}</b>
                        <div class="acc-tag">{{ $ag['last_count'] ? translate('Last counted') . ' ' . \Illuminate\Support\Carbon::parse($ag['last_count'])->format('d/m H:i') : translate('Never counted') }}</div></div>
                    <span class="fw-bold {{ F::cls($ag['held']) }}">{{ F::n($ag['held']) }}</span>
                    @if($ag['user_id'])<a class="btn btn-soft btn-sm" href="{{ route('bus-partner.accounts.cash', ['agent' => $ag['user_id']]) }}">{{ translate('Count') }}</a>@endif
                </div>
            @empty
                <div class="acc-empty">{{ translate('No agent holds counter cash.') }}</div>
            @endforelse
        </div>
    </div>
    <div class="col-lg-6">
        <div class="acc-card">
            <h2 class="acc-h">{{ translate('Held and owed') }}</h2>
            <table class="acc-t"><tbody>
                <tr><td>{{ translate('Tickets paid for trips not gone yet') }}<div class="acc-tag">{{ translate('Becomes sales when each bus leaves.') }}</div></td><td class="n">{{ F::n($o['held']) }}</td></tr>
                <tr><td>{{ translate('Refunds owed to passengers') }}</td><td class="n">{{ F::n($o['refunds_owed']) }}</td></tr>
                <tr><td>{{ translate('E-Ride (commission owed)') }}</td><td class="n">{{ F::n($o['eride']) }}</td></tr>
                <tr><td>{{ translate('Suppliers') }}</td><td class="n">{{ F::n($o['suppliers']) }}</td></tr>
            </tbody></table>
        </div>
        <div class="acc-card">
            <h2 class="acc-h">{{ translate('Bills to pay') }}</h2>
            @forelse($o['bills'] as $b)
                <div class="d-flex align-items-center gap-2 py-1 border-bottom">
                    <div class="flex-grow-1"><a href="{{ route('bus-partner.accounts.document', $b->id) }}" class="fw-bold text-decoration-none" style="color: inherit">{{ $b->supplier }}</a>
                        <div class="acc-tag">{{ $b->number }} · {{ $b->account ? translate($b->account->name) : '' }}@if($b->due_on) · {{ translate('due') }} {{ $b->due_on->format('d/m/Y') }}@endif</div></div>
                    @if($b->due_on && $b->due_on->lt(today()))<span class="b-tag bad">{{ translate('Late') }}</span>@endif
                    <span class="fw-bold">{{ F::n($b->owed) }}</span>
                </div>
            @empty
                <div class="acc-empty">{{ translate('No bill waiting.') }}</div>
            @endforelse
        </div>
        <div class="b-hint mt-2">
            @if($o['balanced']){{ translate('Books checked: debits equal credits.') }} · @endif
            @if($o['closed']){{ translate(key: 'Closed up to {month}', replace: ['month' => \Illuminate\Support\Carbon::parse($o['closed'])->translatedFormat('F Y')]) }} · @endif
            {{ translate('Amounts in FCFA') }}
        </div>
    </div>
</div>
@endsection
