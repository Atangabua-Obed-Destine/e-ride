@extends('adminmodule::layouts.master')

@section('title', $venue->exists ? $venue->name : translate('Add a market'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <div class="mb-3">
                <h2 class="fs-22 mb-1">{{ $venue->exists ? $venue->name : translate('Add a market') }}</h2>
                <p class="fs-12 text-muted mb-0"><a href="{{ route('admin.market.venues') }}">{{ translate('Markets') }}</a></p>
            </div>

            <form action="{{ $venue->exists ? route('admin.market.venue-update', $venue->id) : route('admin.market.venue-store') }}" method="POST">
                @csrf
                <div class="row g-3">
                    <div class="col-lg-7">
                        <div class="card mb-3"><div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">{{ translate('Name') }}</label>
                                <input name="name" class="form-control" required maxlength="80" value="{{ old('name', $venue->name) }}" placeholder="Bamenda Main Market">
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-sm-6">
                                    <label class="form-label">{{ translate('City') }}</label>
                                    <input name="city" class="form-control" maxlength="60" value="{{ old('city', $venue->city) }}">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label">{{ translate('Area') }}</label>
                                    <input name="area_label" class="form-control" maxlength="120" value="{{ old('area_label', $venue->area_label) }}" placeholder="Commercial Avenue, Bamenda">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ translate('About (optional)') }}</label>
                                <textarea name="about" class="form-control" rows="2" maxlength="500">{{ old('about', $venue->about) }}</textarea>
                            </div>
                            <div class="row g-3">
                                <div class="col-sm-6">
                                    <label class="form-label">{{ translate('Centre latitude') }}</label>
                                    <input name="latitude" class="form-control" required value="{{ old('latitude', $venue->latitude) }}">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label">{{ translate('Centre longitude') }}</label>
                                    <input name="longitude" class="form-control" required value="{{ old('longitude', $venue->longitude) }}">
                                </div>
                            </div>
                            @if($venue->latitude)
                                <a class="fs-12" href="https://www.google.com/maps?q={{ $venue->latitude }},{{ $venue->longitude }}" target="_blank" rel="noopener">{{ translate('Check on Google Maps') }}</a>
                            @endif
                        </div></div>
                    </div>
                    <div class="col-lg-5">
                        <div class="card mb-3"><div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">{{ translate('Gates') }}</label>
                                <textarea name="entrances" class="form-control font-monospace fs-12" rows="4" placeholder="Main gate | 5.9601, 10.1512">{{ old('entrances', collect($venue->entrances ?? [])->map(fn($e) => ($e['name'] ?? '') . ' | ' . ($e['lat'] ?? '') . ', ' . ($e['lng'] ?? ''))->implode("\n")) }}</textarea>
                                <div class="form-text">{{ translate('One per line: name | latitude, longitude. Riders are dropped at the gate nearest the shed.') }}</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ translate('Blocks or lines') }}</label>
                                <textarea name="blocks" class="form-control fs-12" rows="3" placeholder="Block A&#10;Block B">{{ old('blocks', implode("\n", $venue->blockList())) }}</textarea>
                                <div class="form-text">{{ translate('Suggested to shop owners when they give their shed. They can also type their own.') }}</div>
                            </div>
                            <div class="row g-3 align-items-end">
                                <div class="col-6">
                                    <label class="form-label">{{ translate('Order in the list') }}</label>
                                    <input type="number" name="sort" class="form-control" min="0" max="999" value="{{ old('sort', $venue->sort ?? 0) }}">
                                </div>
                                <div class="col-6">
                                    <label class="d-flex justify-content-between align-items-center form-control">
                                        <span>{{ translate('Shown in the app') }}</span>
                                        <span class="switcher">
                                            <input type="hidden" name="is_active" value="0">
                                            <input class="switcher_input" type="checkbox" name="is_active" value="1" {{ old('is_active', $venue->is_active ?? true) ? 'checked' : '' }}>
                                            <span class="switcher_control"></span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div></div>
                        <button class="btn btn-primary w-100">{{ translate('Save') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
