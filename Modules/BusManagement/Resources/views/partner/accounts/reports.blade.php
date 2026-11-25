@extends($accLayout)

@section('title', translate('Reports'))

@push('head')
    @include('busmanagement::partner.accounts._style')
@endpush

@php
    $tabs = ['income' => translate('Income statement'), 'profit' => translate('Profit by bus, route or trip'), 'cash' => translate('Cash flow'), 'budget' => translate('Budget against actual'), 'trial' => translate('Trial balance'), 'balance' => translate('Balance sheet')];
    $q = ['from' => $from, 'to' => $to, 'by' => $by, 'scope' => $scope];
    if ($scope === 'all') {
        unset($tabs['cash'], $tabs['budget']);
    }
@endphp

@section('content')
@include('busmanagement::partner.accounts._nav')

@if($isCompanyBooks)
<div class="seg mb-2" role="tablist">
    <input type="radio" id="sc_own" @checked($scope === 'own') onchange="location.href='{{ route($acc . '.reports', array_merge($q, ['tab' => $tab, 'scope' => 'own'])) }}'"><label for="sc_own">{{ translate('Head office books') }}</label>
    <input type="radio" id="sc_all" @checked($scope === 'all') onchange="location.href='{{ route($acc . '.reports', array_merge($q, ['tab' => $tab === 'cash' ? 'income' : $tab, 'scope' => 'all'])) }}'"><label for="sc_all">{{ translate('The whole company (head office and branches)') }}</label>
</div>
@endif
<div class="d-flex flex-wrap gap-2 mb-3">
    @foreach($tabs as $k => $label)
        <a class="btn btn-sm {{ $tab === $k ? 'btn-dark' : 'btn-soft' }}" href="{{ route($acc . '.reports', $q + ['tab' => $k]) }}">{{ $label }}</a>
    @endforeach
</div>

<div class="acc-card">
    <form method="GET" class="d-flex flex-wrap gap-2 align-items-end mb-3">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <input type="hidden" name="scope" value="{{ $scope }}">
        @if($tab !== 'balance')
            <div><label class="form-label small fw-bold mb-1" for="r_from">{{ translate('From') }}</label><input type="date" class="form-control form-control-sm" id="r_from" name="from" value="{{ $from }}"></div>
        @endif
        <div><label class="form-label small fw-bold mb-1" for="r_to">{{ $tab === 'balance' ? translate('At') : translate('To') }}</label><input type="date" class="form-control form-control-sm" id="r_to" name="to" value="{{ $to }}"></div>
        @if($tab === 'profit')
            <div class="seg">
                @foreach(['bus' => translate('Bus'), 'route' => translate('Route'), 'trip' => translate('Trip')] as $k => $label)
                    <input type="radio" name="by" id="by_{{ $k }}" value="{{ $k }}" @checked($by === $k) onchange="this.form.submit()"><label for="by_{{ $k }}">{{ $label }}</label>
                @endforeach
            </div>
        @endif
        <button class="btn btn-soft btn-sm">{{ translate('Show') }}</button>
        @if($tab === 'budget')<a class="btn btn-dark btn-sm ms-auto" href="{{ route($acc . '.budget', ['year' => \Illuminate\Support\Carbon::parse($from)->year]) }}">{{ translate('Edit the budget') }}</a>@endif
        <a class="btn btn-soft btn-sm {{ $tab === 'budget' ? '' : 'ms-auto' }}" href="{{ route($acc . '.reports', $q + ['tab' => $tab, 'csv' => 1]) }}"><i class="bi bi-download"></i> CSV</a>
        <button type="button" class="btn btn-soft btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> {{ translate('Print') }}</button>
    </form>
    <h2 class="acc-h">{{ $tabs[$tab] }} · {{ $tab === 'balance' ? \Illuminate\Support\Carbon::parse($to)->format('d/m/Y') : \Illuminate\Support\Carbon::parse($from)->format('d/m/Y') . ' - ' . \Illuminate\Support\Carbon::parse($to)->format('d/m/Y') }}</h2>
    @include('busmanagement::partner.accounts._report_tables', ['ledgerUrl' => $scope === 'all' ? null : fn($a) => route($acc . '.journal', ['account' => $a->id, 'from' => $from, 'to' => $to])])
    @if($scope === 'all')<div class="b-hint mt-2">{{ translate('The head office books and every branch that keeps books, added together. What passes between the head office and a branch cancels out and is left out.') }}</div>@endif
    <div class="b-hint mt-2">{{ translate('Amounts in FCFA') }} · {{ $scope === 'all' ? $busCompany->name : $accOwnerName }}</div>
</div>
@endsection

@push('head')
<style>@media print { .b-top, .acc-sub, form, .btn { display: none !important; } .acc-card { border: 0; } }</style>
@endpush
