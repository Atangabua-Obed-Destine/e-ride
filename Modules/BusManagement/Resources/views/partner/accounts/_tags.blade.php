{{-- Optional tags: the bus, the route, the trip (for profit per bus, route or trip). Param: $idp. A head office has buses only. --}}
<div class="col-sm-4">
    <label class="form-label" for="{{ $idp }}_bus">{{ translate('Bus (optional)') }}</label>
    <select class="form-select" id="{{ $idp }}_bus" name="vehicle_id">
        <option value="">{{ translate('Not for one bus') }}</option>
        @foreach($buses as $bus)<option value="{{ $bus->id }}">{{ $bus->registration }}{{ $bus->name ? ' · ' . $bus->name : '' }}</option>@endforeach
    </select>
</div>
@if(count($routes))
<div class="col-sm-4">
    <label class="form-label" for="{{ $idp }}_route">{{ translate('Route (optional)') }}</label>
    <select class="form-select" id="{{ $idp }}_route" name="destination_id">
        <option value="">{{ translate('Not for one route') }}</option>
        @foreach($routes as $rt)<option value="{{ $rt->id }}">→ {{ $rt->city }}</option>@endforeach
    </select>
</div>
<div class="col-sm-4">
    <label class="form-label" for="{{ $idp }}_trip">{{ translate('Trip (optional)') }}</label>
    <select class="form-select" id="{{ $idp }}_trip" name="departure_id">
        <option value="">{{ translate('Not for one trip') }}</option>
        @foreach($trips as $tr)<option value="{{ $tr->id }}">{{ $tr->leavesAt()->format('d/m H:i') }} → {{ $tr->destination?->city }}</option>@endforeach
    </select>
</div>
@endif
