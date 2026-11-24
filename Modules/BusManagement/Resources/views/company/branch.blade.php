@extends('busmanagement::company.layout')

@section('title', $agency->branchLabel())

@php
    use Modules\BusManagement\Service\ScheduleService;
    $canAct = $busCompanyStaff->canAct();
@endphp

@section('content')
<a class="small" href="{{ route('bus-company.branches') }}">&larr; {{ translate('Branches') }}</a>
<div class="d-flex flex-wrap align-items-center gap-2 mt-1 mb-3">
    <h1 class="fs-5 fw-bold mb-0 me-auto">{{ $agency->displayName() }}</h1>
    @if($agency->online_paused_at)<span class="b-tag bad">{{ translate('App sales paused') }} {{ $agency->online_paused_at->format('H:i') }}</span>@endif
    @if($canAct && $agency->status === 'live')
        <button class="btn btn-sm {{ $agency->online_paused_at ? 'btn-primary' : 'btn-dark' }}" id="pauseBtn" data-on="{{ $agency->online_paused_at ? 0 : 1 }}">
            {{ $agency->online_paused_at ? translate('Resume app sales') : translate('Pause app sales') }}</button>
    @endif
</div>

<section class="b-card p-3 mb-3" aria-labelledby="dTitle">
    <h2 class="b-h mb-2" id="dTitle">{{ translate('Departures, the next 7 days') }}</h2>
    <div class="ho-scroll">
        <table class="ho-table">
            <thead><tr><th>{{ translate('When') }}</th><th>{{ translate('To') }}</th><th>{{ translate('Class') }}</th><th>{{ translate('Bus') }}</th><th class="n">{{ translate('Seats taken') }}</th><th>{{ translate('Status') }}</th></tr></thead>
            <tbody>
            @forelse($departures as $d)
                <tr>
                    <td class="text-nowrap">{{ $d->leavesAt()->translatedFormat('D j M, H:i') }}</td>
                    <td>{{ $d->destination?->city }}</td>
                    <td>{{ ScheduleService::classLabel($d->class) }}</td>
                    <td>{!! $d->vehicle ? e($d->vehicle->registration) : '<span class="b-tag warn">' . e(translate('No bus')) . '</span>' !!}</td>
                    <td class="n">{{ $sold[$d->id] ?? 0 }} / {{ $d->capacity }}</td>
                    <td>{{ translate(ucfirst($d->status)) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="b-hint">{{ translate('No departure in the next 7 days.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="b-card p-3" aria-labelledby="fTitle">
    <h2 class="b-h mb-1" id="fTitle">{{ translate('Fares') }}</h2>
    <p class="b-hint mb-2">{{ $canAct ? translate('Change a fare and save: passengers see it at once. Tickets already sold keep their fare.') : translate('Fares of this branch, in FCFA.') }}</p>
    <form method="POST" action="{{ route('bus-company.branches.fares', $agency->id) }}">
        @csrf
        <div class="ho-scroll">
            <table class="ho-table">
                <thead><tr><th>{{ translate('Destination') }}</th><th>{{ translate('Stop') }}</th><th class="n">{{ translate('Classic') }}</th><th class="n">VIP</th><th class="n">{{ translate('Executive') }}</th></tr></thead>
                <tbody>
                @foreach($destinations as $dest)
                    @foreach($dest->dropPoints as $p)
                        <tr>
                            <td>{{ $loop->first ? $dest->city : '' }}@if($loop->first && !$dest->active) <span class="b-tag mute">{{ translate('Not offered') }}</span>@endif</td>
                            <td>{{ $p->name }}</td>
                            @foreach(['classic', 'vip', 'executive'] as $k)
                                <td class="n"><input class="form-control form-control-sm text-end" style="width:110px;display:inline-block" type="number" min="0" step="50"
                                    name="fares[{{ $p->id }}][{{ $k }}]" value="{{ $p->{'fare_' . $k} !== null ? (int)$p->{'fare_' . $k} : '' }}" @disabled(!$canAct)
                                    aria-label="{{ $dest->city }} {{ $p->name }} {{ ScheduleService::classLabel($k) }}"></td>
                            @endforeach
                        </tr>
                    @endforeach
                @endforeach
                </tbody>
            </table>
        </div>
        @if($canAct && $destinations->isNotEmpty())<button class="btn btn-primary mt-2">{{ translate('Save the fares') }}</button>@endif
    </form>
</section>
@endsection

@push('script')
<script>
(function () {
    var b = document.getElementById('pauseBtn');
    if (!b) return;
    b.addEventListener('click', function () {
        var on = b.dataset.on === '1';
        if (on && !confirm(@json(translate('Pause app sales at this branch? Requests waiting are declined. Its counter keeps selling.')))) return;
        b.disabled = true;
        busPost(@json(route('bus-company.branches.pause', $agency->id)), {pause: on}).then(function (d) { busToast(d.message); setTimeout(function () { location.reload(); }, 900); })
            .catch(function () { b.disabled = false; });
    });
})();
</script>
@endpush
