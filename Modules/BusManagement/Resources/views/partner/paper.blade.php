@extends('busmanagement::partner.layout')

@section('title', translate('Paper sheets'))

@push('head')
<style>
    .ps-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 10px; align-items: center; padding: 12px 0; border-top: 1px solid var(--line); }
    .ps-row:first-child { border-top: 0; }
    .rule-list { margin: 0; padding-left: 18px; }
    .rule-list li { margin-bottom: 4px; }
</style>
@endpush

@section('content')
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <div class="flex-grow-1">
        <h1 class="fs-5 fw-bold mb-0">{{ translate('Paper sheets') }}</h1>
        <div class="b-hint">{{ translate('A pencil seat sheet for each bus, so you can keep selling on paper when the light or the internet goes.') }}</div>
    </div>
    @include('busmanagement::partner._pause_button')
</div>

@if(!$on)
    <div class="alert alert-warning">{{ translate('The paper copy is off for your agency.') }}
        @if($busStaff->role === \Modules\BusManagement\Entities\BusStaff::MANAGER)<a href="{{ route('bus-partner.destinations') }}">{{ translate('Turn it on in Booking rules') }}</a>@endif</div>
@endif

<div class="row g-3">
    <div class="col-lg-8">
        <section class="b-card p-3" aria-labelledby="sheetsTitle">
            <h2 class="b-h mb-2" id="sheetsTitle">{{ translate('Sheets of the coming buses') }}</h2>
            @forelse($rows as $r)
                <div class="ps-row">
                    <div style="min-width:0">
                        <div class="fw-bold">{{ $r['label'] }} · {{ $r['class'] }} · {{ $r['d']->vehicle?->registration }}</div>
                        <div class="b-hint">
                            {{ translate(key: '{n} seats taken in E-Ride', replace: ['n' => $r['taken']]) }}
                            · @if($r['d']->paper_checked_at){{ translate(key: 'checked {time}', replace: ['time' => $r['d']->paper_checked_at->translatedFormat('D H:i')]) }}@else{{ translate('never checked') }}@endif
                            @if($r['todo'])<span class="b-tag bad ms-1">{{ translate(key: '{n} to do', replace: ['n' => $r['todo']]) }}</span>@endif
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <a class="btn btn-soft btn-sm" href="{{ route('bus-partner.paper.print', $r['d']->id) }}" target="_blank" rel="noopener"><i class="bi bi-printer"></i> {{ translate('Print') }}</a>
                        <a class="btn btn-dark btn-sm" href="{{ route('bus-partner.paper.show', $r['d']->id) }}">{{ translate('Check') }}</a>
                    </div>
                </div>
            @empty
                <div class="b-hint">{{ translate('No bus in the coming days.') }}</div>
            @endforelse
        </section>
    </div>
    <div class="col-lg-4">
        <section class="b-card p-3" aria-labelledby="howTitle">
            <h2 class="b-h mb-2" id="howTitle">{{ translate('How it works') }}</h2>
            <ol class="rule-list small">
                <li>{{ translate('Print the sheet of each bus in the morning, or draw the seats by hand.') }}</li>
                <li>{{ translate('Mark in pencil. Every seat you accept or sell in E-Ride goes on the sheet at once.') }}</li>
                <li>{{ translate('When E-Ride frees a seat (payment time ran out, cancelled), the alarm rings: erase it.') }}</li>
                <li>{{ translate('Stepping out: pause app sales. When the light goes, sell on paper from the sheet.') }}</li>
                <li>{{ translate('When you are back: type in the paper sales, check each sheet, then resume app sales.') }}</li>
            </ol>
            <div class="b-hint">{{ translate('Always cross-check the sheet with E-Ride. Each sheet is checked again 30 minutes before its bus leaves.') }}</div>
        </section>
    </div>
</div>
@endsection
