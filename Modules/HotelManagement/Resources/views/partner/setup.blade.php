@extends('hotelmanagement::partner.layout')
@section('title', translate('Setup'))

@php
    use Modules\HotelManagement\Entities\HotelRoomType;
    use Modules\HotelManagement\Transformers\HotelPresenter;
    $labels = HotelRoomType::amenityLabels();
    $steps = [
        'types' => translate('Room types'),
        'photos' => translate('A photo for each type'),
        'rooms' => translate('Room numbers for each type'),
        'phone' => translate('Reception phone'),
    ];
    $editing = $adding ? null : $current;
@endphp

@push('head')
<style>
    .st-type { display: block; padding: 10px; border-radius: 10px; color: var(--ink); text-decoration: none; }
    .st-type:hover { background: #f4f6f6; color: var(--ink); }
    .st-type.is-on { background: #fdecec; }
    .st-photo { width: 110px; position: relative; }
    .st-photo img { width: 110px; height: 80px; object-fit: cover; border-radius: 10px; display: block; }
    .st-photo label { font-size: 12px; display: flex; gap: 4px; align-items: center; margin-top: 4px; }
    .st-chip { display: inline-flex; align-items: center; gap: 6px; min-height: 36px; padding: 0 6px 0 12px; border-radius: 8px; background: #eef1f1; font-weight: 700; font-size: 13px; }
    .st-chip button { border: 0; background: transparent; width: 30px; height: 30px; border-radius: 6px; color: var(--muted); }
    .st-chip button:hover { background: #dfe5e4; color: #b91c1c; }
</style>
@endpush

@section('content')
<div class="h-card p-3 mb-3 d-flex flex-wrap align-items-center gap-3">
    <div class="flex-grow-1">
        <div class="h-h">{{ in_array(false, $checklist, true) ? translate('Almost ready for guests') : translate('Ready: guests can ask you') }}</div>
        <div class="h-hint">{{ translate('Guests see your hotel in the app once every step is done. The hotel photos, address and map pin come from your E-Ride listing.') }}</div>
    </div>
    @foreach($steps as $key => $label)
        <span class="small fw-semibold d-inline-flex align-items-center gap-1" style="color:{{ $checklist[$key] ? '#15803d' : '#8a4f00' }}">
            <i class="bi {{ $checklist[$key] ? 'bi-check-circle-fill' : 'bi-circle' }}" aria-hidden="true"></i>{{ $label }}<span class="visually-hidden">: {{ $checklist[$key] ? translate('Done') : translate('To do') }}</span>
        </span>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-lg-3">
        <div class="h-card p-2">
            <div class="p-2 h-h">{{ translate('Room types') }}</div>
            @foreach($types as $t)
                <a href="{{ route('hotel-partner.setup', ['type' => $t->id]) }}" class="st-type {{ $editing && $editing->id === $t->id ? 'is-on' : '' }}">
                    <span class="d-block fw-bold">{{ $t->name }}</span>
                    <span class="d-block small" style="color:{{ $t->rooms->isEmpty() || empty($t->photos) ? '#8a4f00' : '#3e4a48' }}">
                        {{ translate(key: '{n} room(s) · from FCFA {price}', replace: ['n' => $t->rooms->count(), 'price' => number_format($t->price_from)]) }}@if(empty($t->photos)) · {{ translate('No photo yet') }}@endif
                    </span>
                </a>
            @endforeach
            <a href="{{ route('hotel-partner.setup', ['type' => 'new']) }}" class="btn btn-soft w-100 mt-2" style="border-style:dashed">{{ translate('Add a room type') }}</a>
        </div>
    </div>

    <div class="col-lg-6">
        <form method="POST" enctype="multipart/form-data" class="h-card p-3 mb-3"
              action="{{ $editing ? route('hotel-partner.setup.types.update', $editing->id) : route('hotel-partner.setup.types.store') }}">
            @csrf
            <h1 class="h-h mb-3" style="font-size:18px">{{ $editing ? $editing->name : translate('New room type') }}</h1>
            <div class="row g-3">
                <div class="col-sm-6"><label for="name" class="small text-muted">{{ translate('Name guests see') }}</label>
                    <input id="name" name="name" maxlength="80" required class="form-control" value="{{ old('name', $editing?->name) }}" placeholder="{{ translate('For example: Standard double') }}"></div>
                <div class="col-sm-6"><label for="price_from" class="small text-muted">{{ translate('Price from, per night (FCFA)') }}</label>
                    <input id="price_from" name="price_from" type="number" min="500" step="500" required class="form-control" value="{{ old('price_from', $editing?->price_from) }}"></div>
                <div class="col-sm-6"><label for="beds" class="small text-muted">{{ translate('Beds') }}</label>
                    <input id="beds" name="beds" maxlength="80" class="form-control" value="{{ old('beds', $editing?->beds) }}" placeholder="{{ translate('For example: 1 double bed') }}"></div>
                <div class="col-sm-6"><label for="max_guests" class="small text-muted">{{ translate('Guests at most') }}</label>
                    <input id="max_guests" name="max_guests" type="number" min="1" max="20" required class="form-control" value="{{ old('max_guests', $editing?->max_guests ?? 2) }}"></div>
                <div class="col-12"><label for="description" class="small text-muted">{{ translate('A few words (optional)') }}</label>
                    <textarea id="description" name="description" maxlength="600" rows="2" class="form-control">{{ old('description', $editing?->description) }}</textarea></div>
            </div>
            <div class="small text-muted mt-3 mb-1">{{ translate('In the room') }}</div>
            <div class="d-flex flex-wrap gap-2">
                @foreach($labels as $key => $label)
                    <span class="chip-opt"><input type="checkbox" name="amenities[]" id="am_{{ $key }}" value="{{ $key }}" @checked(in_array($key, old('amenities', $editing?->amenities ?? []), true))><label for="am_{{ $key }}">{{ $label }}</label></span>
                @endforeach
            </div>
            <div class="small text-muted mt-3 mb-1">{{ translate('Photos of this room (at least one, up to 6). Untick a photo to remove it.') }}</div>
            <input type="hidden" name="keep_photos_sent" value="1">
            <div class="d-flex flex-wrap gap-3 mb-2">
                @foreach($editing?->photos ?? [] as $i => $file)
                    <div class="st-photo">
                        <img src="{{ HotelPresenter::photo($file) }}" alt="{{ translate('Photo') }} {{ $i + 1 }}">
                        <label><input type="checkbox" name="keep_photos[]" value="{{ $file }}" checked> {{ translate('Keep') }}</label>
                    </div>
                @endforeach
            </div>
            <label for="photos" class="visually-hidden">{{ translate('Add photos') }}</label>
            <input id="photos" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple class="form-control mb-3">
            <div class="d-flex gap-2">
                <button class="btn btn-primary px-4" style="min-height:46px">{{ translate('Save room type') }}</button>
            </div>
        </form>

        @if($editing)
            <div class="h-card p-3 mb-3">
                <div class="h-h mb-1">{{ translate('Room numbers of this type') }}</div>
                <div class="h-hint mb-2">{{ translate('Guests book a type. You give the room number when you keep it or when they arrive.') }}</div>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    @forelse($editing->rooms as $room)
                        <form method="POST" action="{{ route('hotel-partner.setup.rooms.remove', $room->id) }}" class="st-chip" onsubmit="return confirm({{ json_encode(translate('Remove this room?')) }})">
                            @csrf{{ $room->number }}@if($room->floor)<span class="fw-normal text-muted small">{{ $room->floor }}</span>@endif
                            <button aria-label="{{ translate('Remove room') }} {{ $room->number }}"><i class="bi bi-x-lg"></i></button>
                        </form>
                    @empty
                        <span class="h-hint" style="color:#8a4f00">{{ translate('No room numbers yet.') }}</span>
                    @endforelse
                </div>
                <form method="POST" action="{{ route('hotel-partner.setup.rooms.add', $editing->id) }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-sm-6"><label for="numbers" class="small text-muted">{{ translate('Add numbers') }}</label>
                        <input id="numbers" name="numbers" required class="form-control" placeholder="101-105, 108" value="{{ old('numbers') }}"></div>
                    <div class="col-sm-4"><label for="floor" class="small text-muted">{{ translate('Floor (optional)') }}</label>
                        <input id="floor" name="floor" maxlength="40" class="form-control" placeholder="{{ translate('Ground floor') }}"></div>
                    <div class="col-sm-2"><button class="btn btn-soft w-100" style="min-height:42px">{{ translate('Add') }}</button></div>
                </form>
            </div>
            <form method="POST" action="{{ route('hotel-partner.setup.types.remove', $editing->id) }}" onsubmit="return confirm({{ json_encode(translate('Remove this room type and its rooms?')) }})">
                @csrf<button class="btn btn-link text-danger p-0">{{ translate('Remove this room type') }}</button>
            </form>
        @endif
    </div>

    <div class="col-lg-3">
        <form method="POST" action="{{ route('hotel-partner.setup.times') }}" class="h-card p-3">
            @csrf
            <h2 class="h-h mb-2">{{ translate('Hotel times') }}</h2>
            <div class="row g-2 mb-2">
                <div class="col-6"><label for="check_in_from" class="small text-muted">{{ translate('Check-in from') }}</label><input id="check_in_from" type="time" name="check_in_from" class="form-control" required value="{{ old('check_in_from', $hotel->check_in_from) }}"></div>
                <div class="col-6"><label for="check_out_by" class="small text-muted">{{ translate('Check-out by') }}</label><input id="check_out_by" type="time" name="check_out_by" class="form-control" required value="{{ old('check_out_by', $hotel->check_out_by) }}"></div>
            </div>
            <div class="small text-muted mb-1">{{ translate('Reception answers in the app') }}</div>
            <div class="d-flex flex-wrap gap-2 mb-2">
                <span class="chip-opt"><input type="radio" name="answer_always" id="aa1" value="1" @checked(old('answer_always', $hotel->answer_always ? '1' : '0') === '1')><label for="aa1">{{ translate('Day and night') }}</label></span>
                <span class="chip-opt"><input type="radio" name="answer_always" id="aa0" value="0" @checked(old('answer_always', $hotel->answer_always ? '1' : '0') === '0')><label for="aa0">{{ translate('Between two hours') }}</label></span>
            </div>
            <div class="row g-2 mb-2">
                <div class="col-6"><label for="answer_from" class="small text-muted">{{ translate('From') }}</label><input id="answer_from" type="time" name="answer_from" class="form-control" value="{{ old('answer_from', $hotel->answer_from) }}"></div>
                <div class="col-6"><label for="answer_until" class="small text-muted">{{ translate('Until') }}</label><input id="answer_until" type="time" name="answer_until" class="form-control" value="{{ old('answer_until', $hotel->answer_until) }}"></div>
            </div>
            <label for="reception_phone" class="small text-muted">{{ translate('Reception phone (for the Call button)') }}</label>
            <input id="reception_phone" name="reception_phone" class="form-control mb-2" required value="{{ old('reception_phone', $hotel->reception_phone) }}" placeholder="+237 6XX XX XX XX">
            <label for="keep_max_hours" class="small text-muted">{{ translate('Keep a room at most (hours, same day)') }}</label>
            <input id="keep_max_hours" type="number" name="keep_max_hours" min="1" max="24" class="form-control mb-1" required value="{{ old('keep_max_hours', $hotel->keep_max_hours) }}">
            <div class="h-hint mb-3">{{ translate('For later dates, a room is kept at most until the evening of the arrival day.') }}</div>
            <button class="btn btn-primary w-100" style="min-height:46px">{{ translate('Save') }}</button>
        </form>
    </div>
</div>
@endsection
