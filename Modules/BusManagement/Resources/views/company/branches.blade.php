@extends('busmanagement::company.layout')

@section('title', translate('Branches'))

@php use Modules\BusManagement\Lib\CameroonRegions; @endphp

@push('head')
<style>
    .bl { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px; }
    .bl-card { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 14px; display: flex; flex-direction: column; gap: 6px; }
</style>
@endpush

@section('content')
<h1 class="fs-5 fw-bold mb-2">{{ translate('Branches') }}</h1>
@include('busmanagement::company._filters')
<div class="bl">
    @foreach($branches as $b)
        <article class="bl-card">
            <div class="d-flex gap-2 align-items-baseline flex-wrap"><b class="fs-6">{{ $b->branchLabel() }}</b><span class="b-hint">{{ CameroonRegions::label($b->region) }}</span></div>
            <div class="b-hint">{{ $b->origin_city }}{{ $b->address ? ' · ' . $b->address : '' }}</div>
            <div class="d-flex flex-wrap gap-2">
                @if($b->online_paused_at)<span class="b-tag bad">{{ translate('App sales paused') }}</span>
                @elseif($b->status === 'live')<span class="b-tag ok">{{ translate('Taking bookings') }}</span>
                @elseif($b->status === 'paused')<span class="b-tag bad">{{ translate('Paused by E-Ride') }}</span>
                @else<span class="b-tag warn">{{ translate('Setting up') }}</span>@endif
                @if($busCompany->collectsMoney())<span class="b-tag mute">{{ translate('Company account') }}</span>@elseif($b->fapshi_ok_at)<span class="b-tag ok">{{ translate('Own account') }}</span>@else<span class="b-tag warn">{{ translate('Not connected') }}</span>@endif
                <span class="b-tag mute"><i class="bi bi-people"></i> {{ $staffCount[$b->id] ?? 0 }}</span>
            </div>
            @if($b->status === 'paused' && $b->pause_note)<div class="b-hint">{{ $b->pause_note }}</div>@endif
            <a class="btn btn-soft btn-sm align-self-start mt-1" href="{{ route('bus-company.branches.show', $b->id) }}">{{ translate('Departures and fares') }}</a>
        </article>
    @endforeach
</div>
@endsection
