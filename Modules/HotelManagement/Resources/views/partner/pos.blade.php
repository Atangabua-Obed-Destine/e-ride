@extends('hotelmanagement::partner.layout')
@section('title', translate('POS Innova'))

@section('content')
<a href="{{ route('hotel-partner.setup') }}" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left" aria-hidden="true"></i> {{ translate('Setup') }}</a>
<div class="row g-3 mt-0">
    <div class="col-lg-5">
        <div class="h-card p-3 mb-3">
            <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                <h1 class="h-h m-0">{{ translate('POS Innova') }}</h1>
                @if(!$link)
                    <span class="h-tag mute">{{ translate('Not linked') }}</span>
                @elseif(!$link->linked_at)
                    <span class="h-tag warn">{{ translate('Waiting for the POS') }}</span>
                @elseif($link->isConnected())
                    <span class="h-tag ok">{{ translate('Connected') }}</span>
                @else
                    <span class="h-tag bad">{{ translate('Not heard from') }}</span>
                @endif
            </div>
            <div class="h-hint mb-3">{{ translate('Running your rooms in POS Innova: link it, and guests see the free rooms from POS. E-Ride requests then show on the POS hotel home, where reception keeps or books the room. The chat with guests stays here.') }}</div>

            @if($newKey)
                <div class="p-2 mb-3" style="border:2px solid #15803d;border-radius:10px">
                    <div class="fw-bold small">{{ translate('Your key for POS Innova') }}</div>
                    <div class="h-hint mb-2">{{ translate('Copy it now: E-Ride shows it only once. In POS Innova, open E-Ride, then E-Ride link, and paste the address and the key.') }}</div>
                    <label class="small fw-semibold" for="pos-url">{{ translate('Address') }}</label>
                    <div class="input-group input-group-sm mb-2">
                        <input class="form-control" id="pos-url" value="{{ $apiUrl }}" readonly>
                        <button class="btn btn-soft" type="button" data-copy="#pos-url">{{ translate('Copy') }}</button>
                    </div>
                    <label class="small fw-semibold" for="pos-key">{{ translate('Key') }}</label>
                    <div class="input-group input-group-sm">
                        <input class="form-control font-monospace" id="pos-key" value="{{ $newKey }}" readonly>
                        <button class="btn btn-soft" type="button" data-copy="#pos-key">{{ translate('Copy') }}</button>
                    </div>
                </div>
            @endif

            @if($link)
                <div class="small text-muted mb-3">
                    <div>{{ translate('Key') }}: <span class="font-monospace">{{ $link->key_hint }}…</span></div>
                    @if($link->last_seen_at)<div>{{ translate('Last heard from') }}: {{ $link->last_seen_at->diffForHumans() }}</div>@endif
                    @if($link->last_availability_at)<div>{{ translate('Free rooms updated') }}: {{ $link->last_availability_at->diffForHumans() }}</div>@endif
                    @if($link->hotel_day)<div>{{ translate('Hotel day in POS') }}: {{ $link->hotel_day->format('d/m/Y') }}</div>@endif
                </div>
                @if($link->linked_at && !$link->isConnected())
                    <div class="alert alert-warning small">{{ translate('E-Ride has not heard from POS for a while. Guests see "Ask the reception" until it is back. Check that the POS computer is on and has internet.') }}</div>
                @endif
                <div class="d-flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('hotel-partner.pos.link') }}" onsubmit="return confirm(@js(translate('A new key stops the current one from working.')))">@csrf
                        <button class="btn btn-soft btn-sm">{{ translate('New key') }}</button>
                    </form>
                    <form method="POST" action="{{ route('hotel-partner.pos.revoke') }}" onsubmit="return confirm(@js(translate('Unlinked, POS Innova stops updating E-Ride, and your rooms board works from E-Ride again.')))">@csrf
                        <button class="btn btn-soft btn-sm" style="color:#b91c1c">{{ translate('Unlink') }}</button>
                    </form>
                </div>
            @else
                <ol class="small text-muted ps-3">
                    <li>{{ translate('Press Link POS Innova: E-Ride gives you an address and a key.') }}</li>
                    <li>{{ translate('In POS Innova, open E-Ride, then E-Ride link, and paste them.') }}</li>
                    <li>{{ translate('The room types of POS appear here. Link each one to a room type guests see.') }}</li>
                </ol>
                <form method="POST" action="{{ route('hotel-partner.pos.link') }}">@csrf
                    <button class="btn btn-primary">{{ translate('Link POS Innova') }}</button>
                </form>
            @endif
        </div>
    </div>

    <div class="col-lg-7">
        <div class="h-card p-3">
            <h2 class="h-h mb-1">{{ translate('Room types from POS') }}</h2>
            <div class="h-hint mb-3">{{ translate('Guests see a room type once it is linked. Its price and number of guests come from POS. Its photos, beds and amenities stay those set on E-Ride.') }}</div>
            @forelse($posTypes as $t)
                <div class="d-flex flex-wrap align-items-center gap-2 py-2 border-top">
                    <div class="flex-grow-1" style="min-width:180px">
                        <div class="fw-bold">{{ $t->name }}</div>
                        <div class="small text-muted">
                            {{ number_format($t->eride_price) }} FCFA · {{ translate(key: '{n} guests', replace: ['n' => $t->capacity]) }} · {{ translate(key: '{n} rooms', replace: ['n' => $t->rooms_total]) }}
                            @if(is_array($t->free) && count($t->free)) · <span class="{{ ($t->free[0] ?? 0) > 0 ? 'text-success' : 'text-danger' }}">{{ translate(key: '{n} free tonight', replace: ['n' => $t->free[0] ?? 0]) }}</span>@endif
                        </div>
                        @if($t->roomType)<div class="small mt-1"><i class="bi bi-link-45deg" aria-hidden="true"></i> {{ $t->roomType->name }}</div>@endif
                    </div>
                    @if($t->room_type_id)
                        <form method="POST" action="{{ route('hotel-partner.pos.map', $t->id) }}">@csrf
                            <input type="hidden" name="room_type_id" value="">
                            <button class="btn btn-soft btn-sm">{{ translate('Unlink') }}</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('hotel-partner.pos.map', $t->id) }}" class="d-flex gap-1">@csrf
                            <label class="visually-hidden" for="map{{ $t->id }}">{{ translate('Room type') }}</label>
                            <select id="map{{ $t->id }}" name="room_type_id" class="form-select form-select-sm" style="max-width:180px" required>
                                <option value="">{{ translate('Choose') }}</option>
                                @foreach($roomTypes as $r)<option value="{{ $r->id }}" @selected(\Illuminate\Support\Str::lower($r->name) === \Illuminate\Support\Str::lower($t->name))>{{ $r->name }}</option>@endforeach
                            </select>
                            <button class="btn btn-primary btn-sm">{{ translate('Link') }}</button>
                        </form>
                        <form method="POST" action="{{ route('hotel-partner.pos.create', $t->id) }}">@csrf
                            <button class="btn btn-soft btn-sm">{{ translate('Add as a new type') }}</button>
                        </form>
                    @endif
                </div>
            @empty
                <div class="text-muted small">{{ $link && $link->linked_at ? translate('POS has not sent its room types yet. They arrive a few minutes after it connects.') : translate('Link POS Innova first.') }}</div>
            @endforelse
        </div>
    </div>
</div>
@endsection

@push('script')
<script>
    document.querySelectorAll('[data-copy]').forEach(function (b) {
        b.addEventListener('click', function () {
            var i = document.querySelector(b.getAttribute('data-copy'));
            i.select();
            (navigator.clipboard ? navigator.clipboard.writeText(i.value) : Promise.resolve(document.execCommand('copy'))).then(function () {
                b.textContent = @json(translate('Copied'));
            });
        });
    });
</script>
@endpush
