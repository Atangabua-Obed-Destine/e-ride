@extends($accLayout)

@section('title', translate('Accounts'))

@php use Modules\BusManagement\Lib\AccFormat as F; @endphp

@push('head')
    @include('busmanagement::partner.accounts._style')
@endpush

@section('content')
@include('busmanagement::partner.accounts._nav')

@if($o['health'])
    <div class="rv-problem mb-3"><b>{{ translate('The books disagree with the bus records') }}</b>
        <ul class="mb-0 ps-3">@foreach($o['health'] as $h)<li>{{ $h }}</li>@endforeach</ul></div>
@endif
@if(!$o['balanced'] || $o['pending'] > 0)
    <div class="rv-warn mb-3">
        @if(!$o['balanced']){{ translate('The books do not balance. Contact E-Ride.') }}@endif
        @if($o['pending'] > 0){{ translate(key: '{n} ticket payments or refunds could not be posted yet (a closed month?). Reopen the month or contact E-Ride.', replace: ['n' => $o['pending']]) }}@endif
    </div>
@endif

<div class="d-flex flex-wrap gap-2 mb-3">
    <a class="btn btn-dark btn-sm" href="{{ route($acc . '.spending') }}#new"><i class="bi bi-receipt"></i> {{ translate('Record an expense') }}</a>
    @if($isCompanyBooks)
        <a class="btn btn-soft btn-sm" href="{{ route($acc . '.money') }}#branch"><i class="bi bi-diagram-3"></i> {{ translate('Money with a branch') }}</a>
    @else
        <a class="btn btn-soft btn-sm" href="{{ route($acc . '.cash') }}"><i class="bi bi-cash-stack"></i> {{ translate('Count an agent cash') }}</a>
    @endif
    @if($carriesBuses)<a class="btn btn-soft btn-sm" href="{{ route($acc . '.buses') }}"><i class="bi bi-bus-front"></i> {{ translate('Buses') }}</a>@endif
    <a class="btn btn-soft btn-sm" href="{{ route($acc . '.money') }}#move"><i class="bi bi-arrow-left-right"></i> {{ translate('Move money') }}</a>
    <a class="btn btn-soft btn-sm" href="{{ route($acc . '.money') }}#income"><i class="bi bi-plus-circle"></i> {{ translate('Other income') }}</a>
</div>

<h2 class="acc-h">{{ translate(key: '{month} so far', replace: ['month' => today()->translatedFormat('F Y')]) }}</h2>
@php
    $m = $o['month'];
    $tickets = collect($m['income'])->filter(fn($r) => in_array($r['account']->role, ['sales_online', 'sales_counter', 'refunds_given'], true))->sum('amount');
@endphp
<div class="acc-grid mb-4">
    @if($isCompanyBooks)
        <div class="acc-fig"><div class="k">{{ translate('Income') }}</div><div class="v">{{ F::n($m['total_income']) }}</div></div>
        <div class="acc-fig"><div class="k">{{ translate('Buses: value left') }}</div><div class="v">{{ F::n($o['buses']['value']) }}</div></div>
    @else
        <div class="acc-fig"><div class="k">{{ translate('Ticket sales (trips that left)') }}</div><div class="v">{{ F::n($tickets) }}</div></div>
        <div class="acc-fig"><div class="k">{{ translate('Other income') }}</div><div class="v">{{ F::n($m['total_income'] - $tickets) }}</div></div>
    @endif
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
                    <tr><td><a href="{{ route($acc . '.journal', ['account' => $row['account']->id, 'from' => $books->starts_on->toDateString()]) }}">{{ $row['account']->label() }}</a></td>
                        <td class="n {{ F::cls($row['balance']) }}">{{ F::n($row['balance']) }}</td></tr>
                @endforeach
                <tr class="tot"><td>{{ translate('Total') }}</td><td class="n">{{ F::n($o['money']->sum('balance')) }}</td></tr>
                </tbody>
            </table>
        </div>
        @if($isCompanyBooks)
        <div class="acc-card">
            <h2 class="acc-h">{{ translate('What the head office holds for each branch') }}</h2>
            <table class="acc-t"><tbody>
                @forelse($o['branches'] as $row)
                    <tr><td><a href="{{ route($acc . '.journal', ['account' => $row['account']->id, 'from' => $books->starts_on->toDateString()]) }}">{{ $row['account']->label() }}</a></td>
                        <td class="n {{ F::cls($row['balance']) }}">{{ F::n($row['balance']) }}</td></tr>
                @empty
                    <tr><td class="acc-empty">{{ translate('No branch yet.') }}</td></tr>
                @endforelse
            </tbody></table>
            <div class="b-hint mt-2">{{ translate('A negative amount means the branch owes the head office (bus wear charged, money sent).') }}</div>
        </div>
        @else
        <div class="acc-card">
            <h2 class="acc-h">{{ translate('Counter cash with the agents') }}</h2>
            @forelse($o['agents'] as $ag)
                <div class="d-flex align-items-center gap-2 py-1 border-bottom">
                    <div class="flex-grow-1"><b>{{ $ag['name'] }}</b>
                        <div class="acc-tag">{{ $ag['last_count'] ? translate('Last counted') . ' ' . \Illuminate\Support\Carbon::parse($ag['last_count'])->format('d/m H:i') : translate('Never counted') }}</div></div>
                    <span class="fw-bold {{ F::cls($ag['held']) }}">{{ F::n($ag['held']) }}</span>
                    @if($ag['user_id'])<a class="btn btn-soft btn-sm" href="{{ route($acc . '.cash', ['agent' => $ag['user_id']]) }}">{{ translate('Count') }}</a>@endif
                </div>
            @empty
                <div class="acc-empty">{{ translate('No agent holds counter cash.') }}</div>
            @endforelse
        </div>
        @endif
    </div>
    <div class="col-lg-6">
        <div class="acc-card">
            <h2 class="acc-h">{{ translate('Held and owed') }}</h2>
            <table class="acc-t"><tbody>
                @unless($isCompanyBooks)
                <tr><td>{{ translate('Tickets paid for trips not gone yet') }}<div class="acc-tag">{{ translate('Becomes sales when each bus leaves.') }}</div></td><td class="n">{{ F::n($o['held']) }}</td></tr>
                <tr><td>{{ translate('Refunds owed to passengers') }}</td><td class="n">{{ F::n($o['refunds_owed']) }}</td></tr>
                @endunless
                <tr><td>{{ translate('E-Ride (commission owed)') }}</td><td class="n">{{ F::n($o['eride']) }}</td></tr>
                <tr><td>{{ translate('Suppliers') }}</td><td class="n">{{ F::n($o['suppliers']) }}</td></tr>
                @if(abs($o['loans']) >= 0.5)<tr><td>{{ translate('Loans') }}</td><td class="n">{{ F::n($o['loans']) }}</td></tr>@endif
            </tbody></table>
        </div>
        <div class="acc-card">
            <h2 class="acc-h">{{ translate('Bills to pay') }}</h2>
            @forelse($o['bills'] as $b)
                <div class="d-flex align-items-center gap-2 py-1 border-bottom">
                    <div class="flex-grow-1"><a href="{{ route($acc . '.document', $b->id) }}" class="fw-bold text-decoration-none" style="color: inherit">{{ $b->supplier }}</a>
                        <div class="acc-tag">{{ $b->number }} · {{ $b->account ? $b->account->displayName() : '' }}@if($b->due_on) · {{ translate('due') }} {{ $b->due_on->format('d/m/Y') }}@endif</div></div>
                    @if($b->due_on && $b->due_on->lt(today()))<span class="b-tag bad">{{ translate('Late') }}</span>@endif
                    <span class="fw-bold">{{ F::n($b->owed) }}</span>
                </div>
            @empty
                <div class="acc-empty">{{ translate('No bill waiting.') }}</div>
            @endforelse
        </div>
        <div class="b-hint mt-2">
            @if($o['balanced'] && !$o['health']){{ $isCompanyBooks ? translate('Books checked: the head office account of each branch agrees with the branch books.') : translate('Books checked against the tickets and the agents cash: all agree.') }} · @endif
            @if($o['closed']){{ translate(key: 'Closed up to {month}', replace: ['month' => \Illuminate\Support\Carbon::parse($o['closed'])->translatedFormat('F Y')]) }} · @endif
            {{ translate('Amounts in FCFA') }}
        </div>
    </div>
</div>
@endsection
