@extends('busmanagement::company.layout')

@section('title', translate('Buses'))

@php use Modules\BusManagement\Service\ScheduleService; @endphp

@push('head')
<style>
    .bus { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 12px 14px; margin-bottom: 10px; }
    .trip { display: grid; grid-template-columns: 120px minmax(0, 1fr) auto; gap: 8px; padding: 6px 0; border-top: 1px solid #f0f2f2; font-size: 13.5px; align-items: start; }
    .trip:first-of-type { border-top: 0; }
    @media (max-width: 575px) { .trip { grid-template-columns: minmax(0, 1fr); gap: 2px; } }
</style>
@endpush

@section('content')
<h1 class="fs-5 fw-bold mb-1">{{ translate('Buses') }}</h1>
<p class="b-hint mb-3">{{ translate('Your buses are shared by all branches. E-Ride never puts one bus on two trips at once: a trip keeps it busy until it arrives, plus the turnaround time.') }}
    {{ translate(key: 'Turnaround: {n} min.', replace: ['n' => $busCompany->turnaround_minutes]) }}</p>
@forelse($plan as $row)
    @php $bus = $row['bus']; @endphp
    <article class="bus">
        <div class="d-flex flex-wrap gap-2 align-items-baseline mb-1">
            <b class="fs-6">{{ $bus->registration }}</b><span class="b-hint">{{ $bus->name }} · {{ ScheduleService::classLabel($bus->class) }} · {{ $bus->total_seats }} {{ translate('seats') }}</span>
            <span class="b-hint">· {{ translate('Home branch') }}: {{ $bus->agency?->branchLabel() }}</span>
            <span class="ms-auto">@if($bus->status === 'ready')<span class="b-tag ok">{{ translate('Ready') }}</span>@elseif($bus->status === 'garage')<span class="b-tag warn">{{ translate('In the garage') }}</span>@else<span class="b-tag mute">{{ translate('Retired') }}</span>@endif</span>
        </div>
        @forelse($row['trips'] as $t)
            <div class="trip">
                <div class="fw-semibold text-nowrap">{{ $t['start']->translatedFormat('D j M, H:i') }}</div>
                <div>{{ $t['from'] }} &rarr; {{ $t['to'] }} <span class="b-hint">· {{ $t['branch'] }} · {{ translate('busy until') }} {{ $t['end']->translatedFormat($t['end']->isSameDay($t['start']) ? 'H:i' : 'D H:i') }}</span>
                    @if($t['elsewhere'])<div class="text-warning small fw-semibold"><i class="bi bi-geo-alt"></i> {{ translate(key: 'The bus will be in {city} after its last trip.', replace: ['city' => $t['elsewhere']]) }}</div>@endif</div>
                <div>@if($t['clash'])<span class="b-tag bad">{{ translate('Two trips at once') }}</span>@elseif($t['d']->status === 'departed')<span class="b-tag blue">{{ translate('On the road') }}</span>@endif</div>
            </div>
        @empty
            <div class="b-hint">{{ translate('No trip in the coming days.') }}</div>
        @endforelse
    </article>
@empty
    <div class="b-card p-4 text-center b-hint">{{ translate('No bus yet. Branch managers add buses in their Fleet page.') }}</div>
@endforelse
@endsection
