@php
    $points = old('points') && session('open') === ($d?->id ?? 'new')
        ? old('points')
        : ($d ? $d->dropPoints->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'minutes_from_start' => $p->minutes_from_start,
            'fare_classic' => $p->fare_classic, 'fare_vip' => $p->fare_vip, 'fare_executive' => $p->fare_executive])->all() : [['name' => '']]);
    $money = fn($v) => $v !== null && $v !== '' ? (int)$v : '';
@endphp
<details class="b-card" @if($expanded) open @endif>
    <summary class="p-3 d-flex align-items-center gap-2" style="cursor:pointer;list-style:none">
        @if($d)
            <span class="flex-grow-1"><span class="fw-bold">{{ $d->city }}</span>
                <span class="b-hint d-block">{{ $d->dropPoints->pluck('name')->implode(' · ') }}{{ $d->duration_minutes ? ' · ' . translate(key: 'about {time}', replace: ['time' => intdiv($d->duration_minutes, 60) . ' h' . ($d->duration_minutes % 60 ? ' ' . str_pad($d->duration_minutes % 60, 2, '0', STR_PAD_LEFT) : '')]) : '' }}</span></span>
            @if(!$d->active)<span class="b-tag mute">{{ translate('Not offered') }}</span>@endif
            @php $from = $d->dropPoints->map(fn($p) => $p->fare_classic ?? $p->fare_vip ?? $p->fare_executive)->filter()->min(); @endphp
            @if($from)<span class="b-tag blue">{{ translate(key: 'From {amount}', replace: ['amount' => number_format($from, 0, ',', ' ') . ' FCFA']) }}</span>@endif
        @else
            <span class="flex-grow-1 fw-bold text-danger"><i class="bi bi-plus-circle me-1"></i>{{ translate('New destination') }}</span>
        @endif
    </summary>
    <form method="POST" action="{{ route('bus-partner.destinations.save', $d?->id) }}" class="px-3 pb-3" data-dest>
        @csrf
        <div class="row g-2 mb-3">
            <div class="col-sm-6"><label class="form-label small fw-semibold">{{ translate('City') }}</label><input name="city" class="form-control" required maxlength="80" value="{{ old('city') && session('open') === ($d?->id ?? 'new') ? old('city') : $d?->city }}" placeholder="Douala"></div>
            <div class="col-sm-3"><label class="form-label small fw-semibold">{{ translate('Trip (minutes)') }}</label><input type="number" name="duration_minutes" class="form-control" min="10" max="2880" value="{{ $d?->duration_minutes }}" placeholder="330"></div>
            @if($d)<div class="col-sm-3 d-flex align-items-end"><div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" name="active" value="1" id="act_{{ $d->id }}" @checked($d->active)><label class="form-check-label small fw-semibold" for="act_{{ $d->id }}">{{ translate('Offered') }}</label></div></div>@endif
        </div>
        <div class="fw-semibold small mb-1">{{ translate('Drop-off points, in order (the last one is the end of the line)') }}</div>
        <div class="b-hint mb-2">{{ translate('Leave a class empty if that class does not stop there.') }}</div>
        <div style="overflow-x:auto">
            <table class="table table-sm align-middle mb-2" style="min-width:620px">
                <thead><tr class="small text-muted"><th>{{ translate('Name') }}</th><th style="width:110px">{{ translate('Minutes after leaving') }}</th><th style="width:110px">{{ translate('Classic') }}</th><th style="width:110px">{{ translate('VIP') }}</th><th style="width:110px">{{ translate('Executive') }}</th><th style="width:40px"></th></tr></thead>
                <tbody data-points>
                    @foreach($points as $p)
                        <tr data-point>
                            <td>@if(!empty($p['id']))<input type="hidden" data-f="id" value="{{ $p['id'] }}">@endif<input class="form-control form-control-sm" data-f="name" value="{{ $p['name'] ?? '' }}" placeholder="{{ translate('Akwa office') }}" aria-label="{{ translate('Drop-off point') }}"></td>
                            <td><input type="number" class="form-control form-control-sm" data-f="minutes_from_start" min="0" value="{{ $p['minutes_from_start'] ?? '' }}" aria-label="{{ translate('Minutes after leaving') }}"></td>
                            <td><input type="number" class="form-control form-control-sm" data-f="fare_classic" min="0" value="{{ $money($p['fare_classic'] ?? null) }}" aria-label="{{ translate('Classic fare') }}"></td>
                            <td><input type="number" class="form-control form-control-sm" data-f="fare_vip" min="0" value="{{ $money($p['fare_vip'] ?? null) }}" aria-label="{{ translate('VIP fare') }}"></td>
                            <td><input type="number" class="form-control form-control-sm" data-f="fare_executive" min="0" value="{{ $money($p['fare_executive'] ?? null) }}" aria-label="{{ translate('Executive fare') }}"></td>
                            <td><button type="button" class="btn btn-sm text-danger" data-remove-point aria-label="{{ translate('Remove') }}"><i class="bi bi-x-lg"></i></button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <template><tr data-point>
            <td><input class="form-control form-control-sm" data-f="name" placeholder="{{ translate('Akwa office') }}" aria-label="{{ translate('Drop-off point') }}"></td>
            <td><input type="number" class="form-control form-control-sm" data-f="minutes_from_start" min="0" aria-label="{{ translate('Minutes after leaving') }}"></td>
            <td><input type="number" class="form-control form-control-sm" data-f="fare_classic" min="0" aria-label="{{ translate('Classic fare') }}"></td>
            <td><input type="number" class="form-control form-control-sm" data-f="fare_vip" min="0" aria-label="{{ translate('VIP fare') }}"></td>
            <td><input type="number" class="form-control form-control-sm" data-f="fare_executive" min="0" aria-label="{{ translate('Executive fare') }}"></td>
            <td><button type="button" class="btn btn-sm text-danger" data-remove-point aria-label="{{ translate('Remove') }}"><i class="bi bi-x-lg"></i></button></td>
        </tr></template>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <button type="button" class="btn btn-soft btn-sm" data-add-point>{{ translate('Add a drop-off point') }}</button>
            <span class="b-hint flex-grow-1">{{ translate('Everyone gets on here, so a seat is taken for the whole trip, wherever the passenger gets off.') }}</span>
            <button class="btn btn-dark">{{ $d ? translate('Save') : translate('Add the destination') }}</button>
        </div>
    </form>
</details>
