@extends('busmanagement::partner.layout')

@section('title', \Modules\BusManagement\Service\ClosureService::kindLabel($closure->kind))

@php
    use Modules\BusManagement\Service\ClosureService;
    $notice = app(ClosureService::class)->notice($closure);
    $statusTag = ['scheduled' => ['mute', translate('Scheduled')], 'boarding' => ['ok', translate('Boarding')], 'departed' => ['blue', translate('Departed')], 'cancelled' => ['bad', translate('Not running')]];
@endphp

@section('content')
<a href="{{ route('bus-partner.closures') }}" class="small">&larr; {{ translate('Lockdowns and ghost towns') }}</a>
<div class="d-flex flex-wrap align-items-center gap-2 my-2">
    <h1 class="fs-5 fw-bold mb-0 flex-grow-1">{{ ClosureService::kindLabel($closure->kind) }}{{ $closure->note ? ': ' . $closure->note : '' }}</h1>
    @if($closure->status === 'lifted')<span class="b-tag mute">{{ translate('Lifted') }}</span>
    @elseif($closure->isAtRisk())<span class="b-tag warn">{{ translate(key: 'At risk, decide by {time}', replace: ['time' => $closure->decide_by->translatedFormat('D j M H:i')]) }}</span>
    @else<span class="b-tag bad">{{ $closure->outcome === 'move' ? translate('No travel: passengers moved') : translate('No travel: passengers refunded') }}</span>@endif
</div>
<div class="b-hint mb-3">{{ $notice['when'] }}</div>

@if($closure->status === 'active')
    <div class="b-card p-3 mb-3 d-flex flex-wrap gap-2 align-items-center">
        @if($closure->isAtRisk() && $busStaff->role === 'manager')
            <span class="fw-semibold me-2">{{ translate('It is happening:') }}</span>
            <form method="POST" action="{{ route('bus-partner.closures.confirm', $closure->id) }}" class="m-0">@csrf<input type="hidden" name="outcome" value="move"><button class="btn btn-primary">{{ translate('Move the passengers') }}</button></form>
            <form method="POST" action="{{ route('bus-partner.closures.confirm', $closure->id) }}" class="m-0">@csrf<input type="hidden" name="outcome" value="cancel"><button class="btn btn-dark">{{ translate('Refund everyone') }}</button></form>
            <span class="mx-2 b-hint">{{ translate('or') }}</span>
        @endif
        @if($busStaff->role === 'manager')
            <form method="POST" action="{{ route('bus-partner.closures.lift', $closure->id) }}" class="m-0" onsubmit="return confirm(@json(translate('This lifts it. Departures that have not left go back on sale.')))">@csrf
                <button class="btn btn-soft">{{ $closure->isAtRisk() ? translate('It is not happening: lift') : translate('Over early: lift') }}</button></form>
        @endif
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-5">
        <div class="b-card p-3">
            <h2 class="b-h mb-2">{{ translate(key: 'Departures ({n})', replace: ['n' => $departures->count()]) }}</h2>
            @forelse($departures as $d)
                <div class="d-flex justify-content-between py-1 border-top small"><span>{{ $d->leavesAt()->translatedFormat('D j M, H:i') }} · {{ $d->destination?->city }}</span>
                    <span class="b-tag {{ $closure->isAtRisk() && $d->isOpen() ? 'warn' : ($statusTag[$d->status][0] ?? 'mute') }}">{{ $closure->isAtRisk() && $d->isOpen() ? translate('At risk') : ($statusTag[$d->status][1] ?? $d->status) }}</span></div>
            @empty
                <div class="b-hint">{{ translate('No departure inside yet. New ones that fall inside are stopped by themselves.') }}</div>
            @endforelse
        </div>
    </div>
    <div class="col-lg-7">
        <div class="b-card p-3">
            <h2 class="b-h mb-1">{{ translate('Passengers') }}</h2>
            <div class="b-hint mb-2">{{ translate('App passengers were told in the app. Call the counter passengers.') }}</div>
            @forelse($bookings as $b)
                @php $seats = $b->tickets->whereIn('status', ['valid', 'boarded', 'held', 'cancelled'])->sortBy(fn($t) => (int)$t->seat)->pluck('seat')->implode(', '); @endphp
                <div class="d-flex flex-wrap gap-2 align-items-center py-2 border-top">
                    <div class="flex-grow-1" style="min-width:200px"><b>{{ $b->tickets->first()?->passenger_name }}</b>
                        <span class="b-tag {{ $b->source === 'app' ? 'blue' : 'mute' }}">{{ $b->source === 'app' ? translate('App') : translate('Counter') }}</span>
                        <div class="b-hint">{{ translate('Seat') }} {{ $seats }} · <code>{{ $b->code }}</code>{{ $b->phone ? ' · ' : '' }}@if($b->phone)<a href="tel:{{ $b->phone }}">{{ $b->phone }}</a>@endif</div></div>
                    <div class="small text-end">
                        @if($b->status === 'paid' && $b->moved_at)
                            {{ translate('Moved to') }} <b>{{ $b->departure?->leavesAt()->translatedFormat('D j M, H:i') }}</b>@if($b->move_kept_at) · <span class="text-success">{{ translate('kept') }}</span>@endif
                        @elseif($b->status === 'paid')
                            <span class="text-warning">{{ translate('Warned, waiting for your decision') }}</span>
                        @elseif($b->status === 'cancelled')
                            {{ translate('Refund') }} {{ getCurrencyFormat($b->refund_amount) }} · {{ $b->refund_status === 'sent' ? translate('sent') : translate('to send') }}
                        @else
                            {{ translate('Request declined') }}
                        @endif
                    </div>
                </div>
            @empty
                <div class="b-hint">{{ translate('No passenger affected.') }}</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
