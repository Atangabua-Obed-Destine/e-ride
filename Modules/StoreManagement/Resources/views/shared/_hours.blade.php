{{-- Weekly opening hours grid. Leave every day empty to be open all day, every day. --}}
@php
    $hours = old('opening_hours', $store->opening_hours ?? []);
    $dayNames = ['mon' => translate('Monday'), 'tue' => translate('Tuesday'), 'wed' => translate('Wednesday'), 'thu' => translate('Thursday'), 'fri' => translate('Friday'), 'sat' => translate('Saturday'), 'sun' => translate('Sunday')];
@endphp
<div class="store-hours">
    @foreach(\Modules\StoreManagement\Entities\Store::DAYS as $day)
        @php($row = $hours[$day] ?? [])
        <div class="store-hours__row d-flex flex-wrap align-items-center gap-2 py-2 border-bottom">
            <div class="store-hours__day fw-semibold" style="min-width: 96px">{{ $dayNames[$day] }}</div>
            <input type="time" class="form-control form-control-sm" style="max-width: 120px" name="opening_hours[{{ $day }}][open]" value="{{ $row['open'] ?? '' }}" aria-label="{{ $dayNames[$day] }} {{ translate('opens') }}">
            <span class="text-muted">-</span>
            <input type="time" class="form-control form-control-sm" style="max-width: 120px" name="opening_hours[{{ $day }}][close]" value="{{ $row['close'] ?? '' }}" aria-label="{{ $dayNames[$day] }} {{ translate('closes') }}">
            <label class="d-flex align-items-center gap-1 fs-12 ms-auto">
                <input type="hidden" name="opening_hours[{{ $day }}][closed]" value="0">
                <input type="checkbox" name="opening_hours[{{ $day }}][closed]" value="1" {{ !empty($row['closed']) ? 'checked' : '' }}>
                {{ translate('Closed_all_day') }}
            </label>
        </div>
    @endforeach
    <p class="fs-12 text-muted mt-2 mb-0">{{ translate('Leave every day empty to stay open all day. Orders are only taken inside these hours and while the store is switched open.') }}</p>
</div>
