@extends('hotelmanagement::partner.layout')
@section('title', translate('Rooms'))

@php
    use Modules\HotelManagement\Entities\HotelStay;
    $label = [
        HotelStay::KEPT => [translate('Kept'), 'warn'], HotelStay::BOOKED => [translate('Booked'), 'blue'],
        HotelStay::IN_HOUSE => [translate('In the house'), 'ok'],
    ];
@endphp

@section('content')
<div class="h-card p-3 mb-3 d-flex flex-wrap align-items-center gap-3">
    <i class="bi bi-pc-display fs-3" aria-hidden="true"></i>
    <div class="flex-grow-1">
        <div class="h-h">{{ translate('Your rooms are in POS Innova') }}</div>
        <div class="h-hint">{{ translate('Keep, book, check in and check out in POS: E-Ride follows it. This page shows what guests see and your E-Ride guests.') }}</div>
    </div>
    @if($link->isConnected())
        <span class="h-tag ok">{{ translate('Connected') }}</span>
    @else
        <span class="h-tag bad">{{ translate('Not heard from') }}</span>
    @endif
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="h-card p-3">
            <h2 class="h-h mb-2">{{ translate('What guests see') }}</h2>
            @forelse($posTypes as $t)
                <div class="d-flex align-items-center gap-2 py-2 border-top">
                    <div class="flex-grow-1">
                        <div class="fw-bold">{{ $t->roomType?->name ?? $t->name }}</div>
                        <div class="small text-muted">{{ number_format($t->eride_price) }} FCFA · {{ translate(key: '{n} rooms', replace: ['n' => $t->rooms_total]) }}</div>
                    </div>
                    @foreach([0, 1] as $i)
                        <div class="text-center" style="min-width:78px">
                            <div class="small text-muted">{{ $i === 0 ? translate('Tonight') : translate('Tomorrow') }}</div>
                            <div class="fw-bold">{{ isset($t->free[$i]) ? $t->free[$i] : '-' }} <span class="small text-muted fw-normal">{{ translate('free') }}</span></div>
                        </div>
                    @endforeach
                </div>
            @empty
                <div class="text-muted small">{{ translate('No room type is linked yet.') }} <a href="{{ route('hotel-partner.pos') }}">{{ translate('Link them') }}</a></div>
            @endforelse
        </div>
    </div>
    <div class="col-lg-6">
        <div class="h-card p-3">
            <h2 class="h-h mb-2">{{ translate('E-Ride guests') }}</h2>
            @forelse($stays as $s)
                <div class="d-flex flex-wrap align-items-center gap-2 py-2 border-top">
                    <div class="flex-grow-1">
                        <div class="fw-bold">{{ $s->guest_name ?: translate('Guest') }} <span class="h-tag {{ $label[$s->status][1] ?? 'mute' }}">{{ $label[$s->status][0] ?? $s->status }}</span></div>
                        <div class="small text-muted">{{ $s->type?->name }} · {{ $s->check_in->format('d/m') }} → {{ $s->check_out->format('d/m') }} · POS {{ $s->pos_ref }}
                            @if($s->status === HotelStay::KEPT && $s->kept_until) · {{ translate(key: 'until {time}', replace: ['time' => $s->kept_until->format('d/m H:i')]) }}@endif</div>
                    </div>
                    @if($s->inquiry_id)<a class="btn btn-soft btn-sm" href="{{ route('hotel-partner.inbox', ['q' => $s->inquiry_id]) }}">{{ translate('Open the chat') }}</a>@endif
                </div>
            @empty
                <div class="text-muted small">{{ translate('No E-Ride guest is kept, booked or in the house right now.') }}</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
