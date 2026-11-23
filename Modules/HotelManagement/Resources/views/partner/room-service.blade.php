@extends('hotelmanagement::partner.layout')
@section('title', translate('Room service'))

@section('content')
<a href="{{ route('hotel-partner.setup') }}" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left" aria-hidden="true"></i> {{ translate('Setup') }}</a>
<div class="row g-3 mt-0">
    <div class="col-lg-5">
        <div class="h-card p-3 mb-3">
            <h1 class="h-h mb-1">{{ translate('Room service') }}</h1>
            @if($restaurant)
                <div class="h-hint mb-2">{{ translate(key: 'Guests checked in can order from {store} to their room in the E-Ride app. The restaurant staff bring it and ask for the code at the door.', replace: ['store' => $restaurant->displayName()]) }}</div>
                <span class="h-tag ok">{{ $restaurant->displayName() }}</span>
            @else
                <div class="h-hint">{{ translate('Your restaurant is not linked yet. Ask E-Ride to link it: it must be on E-Ride Food & Shops.') }}</div>
            @endif
            @if($hotel->usesPos())
                <div class="small mt-3">
                    <span class="h-tag {{ $hotel->pos_room_bill ? 'ok' : 'mute' }}">{{ $hotel->pos_room_bill ? translate('Room bill on') : translate('Room bill off') }}</span>
                    <div class="h-hint mt-1">{{ translate('Guests booked through E-Ride can put room service on their room bill when you allow it in POS Innova (hotel settings). Others pay by Mobile Money.') }}</div>
                </div>
            @endif
        </div>

        <div class="h-card p-3">
            <h2 class="h-h mb-1">{{ translate('Room cards') }}</h2>
            <div class="h-hint mb-2">{{ translate('A card with a QR code for each room: guests scan it to order room service, with the room already filled in. Each card has its own code, not the room number, so nobody can make cards for other rooms.') }}</div>
            <form method="POST" action="{{ route('hotel-partner.room-service.make') }}">@csrf
                <label class="small fw-semibold" for="rooms">{{ translate('Room numbers') }}</label>
                <textarea id="rooms" name="rooms" class="form-control mb-1" rows="2" placeholder="101-110, 201, 202" maxlength="2000">{{ old('rooms', $suggested) }}</textarea>
                <div class="h-hint mb-2">{{ translate('Ranges like 101-110 work. Rooms that already have a card keep it.') }}</div>
                <button class="btn btn-primary btn-sm">{{ translate('Make the cards') }}</button>
            </form>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="h-card p-3">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <h2 class="h-h m-0">{{ translate(key: '{n} room cards', replace: ['n' => $codes->count()]) }}</h2>
                @if($codes->count())<a href="{{ route('hotel-partner.room-service.print') }}" target="_blank" class="btn btn-soft btn-sm"><i class="bi bi-printer" aria-hidden="true"></i> {{ translate('Print the cards') }}</a>@endif
            </div>
            @forelse($codes as $c)
                <div class="d-flex align-items-center gap-2 py-2 border-top">
                    <div class="fw-bold" style="min-width:90px">{{ translate('Room') }} {{ $c->room_label }}</div>
                    <div class="small text-muted flex-grow-1 font-monospace">{{ $c->url() }}</div>
                    <form method="POST" action="{{ route('hotel-partner.room-service.reissue', $c->id) }}" onsubmit="return confirm(@js(translate('The old card of this room will stop working.')))">@csrf
                        <button class="btn btn-soft btn-sm">{{ translate('New card') }}</button>
                    </form>
                </div>
            @empty
                <div class="text-muted small">{{ translate('No room card yet.') }}</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
