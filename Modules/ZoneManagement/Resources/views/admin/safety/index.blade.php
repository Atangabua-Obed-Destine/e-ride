@extends('adminmodule::layouts.master')

@section('title', translate('Safety map'))

@section('content')
    @php($fmt = fn($d) => $d->isToday() ? $d->format('H:i') : $d->format('D d M, H:i'))
    @php($statusBadge = ['open' => 'bg-warning text-dark', 'confirmed' => 'bg-danger', 'false' => 'bg-secondary', 'expired' => 'bg-light text-muted'])
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                <div>
                    <h2 class="fs-22 mb-1">{{ translate('Safety map') }}</h2>
                    <p class="fs-12 text-muted mb-0">{{ translate('Danger reports from drivers and riders, automatic no-go areas, and the pauses you set. A paused or no-go area refuses new trips that start or end inside it.') }}</p>
                </div>
                @if(!$settings['enabled'])
                    <span class="badge bg-warning text-dark fs-12">{{ translate('Off: turn it on in Settings below') }}</span>
                @endif
            </div>
            @if ($errors->any())
                <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif

            <div class="row g-3">
                <div class="col-xl-8">
                    <div class="card mb-3"><div class="card-body p-0">
                        <div id="safety-map" style="height: 460px; border-radius: 8px"></div>
                    </div></div>
                    <div class="fs-12 text-muted mb-3">
                        <span class="me-3"><span class="d-inline-block rounded-circle me-1" style="width:10px;height:10px;background:#dc3545"></span>{{ translate('Automatic, needs a decision') }}</span>
                        <span class="me-3"><span class="d-inline-block rounded-circle me-1" style="width:10px;height:10px;background:#fd7e14"></span>{{ translate('No-go area') }}</span>
                        <span class="me-3"><span class="d-inline-block rounded-circle me-1" style="width:10px;height:10px;background:#0d6efd"></span>{{ translate('Pause') }}</span>
                        <span class="me-3"><span class="d-inline-block rounded-circle me-1" style="width:10px;height:10px;background:#6c757d"></span>{{ translate('Planned') }}</span>
                        <span>{{ translate('Dots: reports in the last 24 hours. Click the map to place a pause.') }}</span>
                    </div>

                    @if($decide->isNotEmpty())
                        <div class="card mb-3 border-danger"><div class="card-body">
                            <h5 class="text-danger mb-1">{{ translate('Needs a decision') }}</h5>
                            <p class="fs-12 text-muted">{{ translate('Drivers reported danger here, so trips are already blocked. Confirm to keep it (for longer or wider if needed), or lift it.') }}</p>
                            @foreach($decide as $a)
                                <div class="border rounded p-3 mb-2">
                                    <div class="d-flex justify-content-between flex-wrap gap-2">
                                        <div><b>{{ $a->title }}</b> · {{ $a->reason }} · {{ $a->radius_m }} m
                                            <div class="fs-12 text-muted">{{ translate('Since') }} {{ $fmt($a->starts_at) }}, {{ translate('ends') }} {{ $fmt($a->ends_at) }} · {{ $a->reports()->count() }} {{ translate('reports') }}</div></div>
                                        @can('zone_edit')
                                            <form action="{{ route('admin.safety.lift', $a) }}" method="POST">@csrf<button class="btn btn-sm btn-outline-secondary">{{ translate('Lift') }}</button></form>
                                        @endcan
                                    </div>
                                    @can('zone_edit')
                                        <form action="{{ route('admin.safety.confirm', $a) }}" method="POST" class="d-flex flex-wrap gap-2 mt-2">@csrf
                                            <select name="minutes" class="form-select form-select-sm" style="width:auto">
                                                <option value="">{{ translate('Keep the end time') }}</option>
                                                @foreach([60 => '1 hour', 180 => '3 hours', 360 => '6 hours', 720 => '12 hours', 1440 => '24 hours'] as $m => $l)<option value="{{ $m }}">{{ translate($l) }}</option>@endforeach
                                            </select>
                                            <select name="radius_m" class="form-select form-select-sm" style="width:auto">
                                                <option value="">{{ $a->radius_m }} m</option>
                                                @foreach([300, 500, 1000, 2000] as $r)<option value="{{ $r }}">{{ $r }} m</option>@endforeach
                                            </select>
                                            <input type="text" name="reason" class="form-control form-control-sm" style="max-width:260px" maxlength="191" placeholder="{{ translate('Reason shown to people (optional)') }}">
                                            <button class="btn btn-sm btn-danger">{{ translate('Confirm') }}</button>
                                        </form>
                                    @endcan
                                </div>
                            @endforeach
                        </div></div>
                    @endif

                    <div class="card mb-3"><div class="card-body">
                        <h5 class="mb-3">{{ translate('In force and planned') }}</h5>
                        @forelse($inForce->concat($planned) as $a)
                            <div class="d-flex justify-content-between align-items-center border-bottom py-2 gap-2 flex-wrap">
                                <div>
                                    <span class="badge {{ $a->starts_at->isFuture() ? 'bg-secondary' : ($a->kind === 'pause' ? 'bg-primary' : 'bg-warning text-dark') }}">
                                        {{ $a->starts_at->isFuture() ? translate('Planned') : ($a->kind === 'pause' ? translate('Pause') : translate('No-go')) }}</span>
                                    <b class="ms-1">{{ $a->title }}</b> · {{ $a->reason }}
                                    <div class="fs-12 text-muted">{{ $a->zone_id ? translate('Whole zone') . ' ' . $a->zone?->name : $a->radius_m . ' m' }} · {{ $fmt($a->starts_at) }} {{ translate('to') }} {{ $fmt($a->ends_at) }}</div>
                                </div>
                                @can('zone_edit')
                                    <form action="{{ route('admin.safety.lift', $a) }}" method="POST" onsubmit="return confirm('{{ translate('Lift_this_now') }}')">@csrf
                                        <button class="btn btn-sm btn-outline-danger">{{ $a->starts_at->isFuture() ? translate('Cancel') : translate('Lift') }}</button></form>
                                @endcan
                            </div>
                        @empty
                            <p class="text-muted fs-14 mb-0">{{ translate('Nothing is paused or planned.') }}</p>
                        @endforelse
                    </div></div>

                    <div class="card mb-3"><div class="card-body">
                        <h5 class="mb-3">{{ translate('Reports in the last 24 hours') }}</h5>
                        <div class="table-responsive">
                            <table class="table table-borderless align-middle fs-14">
                                <thead class="table-light"><tr><th>{{ translate('When') }}</th><th>{{ translate('What') }}</th><th>{{ translate('Where') }}</th><th>{{ translate('By') }}</th><th>{{ translate('Status') }}</th><th class="text-center">{{ translate('Action') }}</th></tr></thead>
                                <tbody>
                                @forelse($reports as $r)
                                    <tr>
                                        <td>{{ $r->created_at->format('H:i') }}<div class="fs-12 text-muted">{{ $r->created_at->format('d M') }}</div></td>
                                        <td><b>{{ \Modules\ZoneManagement\Entities\DangerReport::label($r->type) }}</b>@if($r->note)<div class="fs-12 text-muted">{{ $r->note }}</div>@endif</td>
                                        <td>{{ $r->place ?: $r->zone?->name }}<div class="fs-12"><a href="https://www.google.com/maps/search/?api=1&query={{ $r->latitude }},{{ $r->longitude }}" target="_blank">{{ translate('Map') }}</a></div></td>
                                        <td>{{ $r->user?->first_name }} {{ $r->user?->last_name }}<div class="fs-12 text-muted">{{ translate($r->role) }} · {{ $r->user?->phone }}</div></td>
                                        <td><span class="badge {{ $statusBadge[$r->status] ?? 'bg-light text-dark' }}">{{ translate('report_' . $r->status) }}</span>
                                            @if($r->safety_area_id)<div class="fs-12 text-muted">{{ translate('in a blocked area') }}</div>@endif</td>
                                        <td class="text-center">
                                            @can('zone_edit')
                                                @if($r->status === 'open')
                                                    <form action="{{ route('admin.safety.report', $r) }}" method="POST" class="d-flex gap-1 justify-content-center">@csrf
                                                        <button name="status" value="confirmed" class="btn btn-sm btn-outline-danger" title="{{ translate('Riders nearby will see it too') }}">{{ translate('Confirm') }}</button>
                                                        <button name="status" value="false" class="btn btn-sm btn-outline-secondary">{{ translate('False') }}</button>
                                                    </form>
                                                @endif
                                            @endcan
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted py-4">{{ translate('No reports in the last 24 hours.') }}</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div></div>
                </div>

                <div class="col-xl-4">
                    @can('zone_edit')
                        <div class="card mb-3"><div class="card-body">
                            <h5 class="mb-1">{{ translate('Pause an area') }}</h5>
                            <p class="fs-12 text-muted">{{ translate('Now, or planned ahead, such as a ghost-town day. People in and near it are told when it starts, and 12 hours before if planned.') }}</p>
                            <form action="{{ route('admin.safety.pause') }}" method="POST" id="pause-form">@csrf
                                <div class="mb-2">
                                    <label class="form-label">{{ translate('What') }}</label>
                                    <select name="kind" class="form-select">
                                        <option value="pause">{{ translate('Pause (planned or general)') }}</option>
                                        <option value="no_go">{{ translate('No-go area (incident)') }}</option>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">{{ translate('Where') }}</label>
                                    <div class="d-flex gap-3 mb-2">
                                        <label class="fs-14"><input type="radio" name="where" value="circle" checked> {{ translate('A circle on the map') }}</label>
                                        <label class="fs-14"><input type="radio" name="where" value="zone"> {{ translate('A whole zone') }}</label>
                                    </div>
                                    <div id="where-circle">
                                        <div class="row g-2">
                                            <div class="col-6"><input type="number" step="any" name="latitude" id="pause-lat" class="form-control form-control-sm" placeholder="{{ translate('Latitude') }}" value="{{ old('latitude') }}"></div>
                                            <div class="col-6"><input type="number" step="any" name="longitude" id="pause-lng" class="form-control form-control-sm" placeholder="{{ translate('Longitude') }}" value="{{ old('longitude') }}"></div>
                                        </div>
                                        <select name="radius_m" id="pause-radius" class="form-select form-select-sm mt-2">
                                            @foreach([300, 500, 1000, 2000, 5000] as $r)<option value="{{ $r }}" {{ $r === 500 ? 'selected' : '' }}>{{ $r }} m</option>@endforeach
                                        </select>
                                        <div class="fs-12 text-muted mt-1">{{ translate('Click the map to place it.') }}</div>
                                    </div>
                                    <div id="where-zone" class="d-none">
                                        <select name="zone_id" class="form-select">
                                            @foreach($zones as $z)<option value="{{ $z->id }}">{{ $z->name }}</option>@endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-2"><input type="text" name="title" class="form-control" maxlength="120" required placeholder="{{ translate('Where, in words (e.g. Mile 2 Nkwen)') }}" value="{{ old('title') }}"></div>
                                <div class="mb-2"><input type="text" name="reason" class="form-control" maxlength="191" required placeholder="{{ translate('Reason people see (e.g. Ghost-town day)') }}" value="{{ old('reason') }}"></div>
                                <div class="row g-2 mb-3">
                                    <div class="col-6"><label class="form-label fs-12">{{ translate('Starts (empty = now)') }}</label><input type="datetime-local" name="starts_at" class="form-control form-control-sm" value="{{ old('starts_at') }}"></div>
                                    <div class="col-6"><label class="form-label fs-12">{{ translate('Ends') }}</label><input type="datetime-local" name="ends_at" class="form-control form-control-sm" required value="{{ old('ends_at') }}"></div>
                                </div>
                                <button class="btn btn-primary w-100" onclick="return confirm('{{ translate('Trips to and from this area will be refused. Continue') }}')">{{ translate('Pause') }}</button>
                            </form>
                        </div></div>

                        <div class="card mb-3"><div class="card-body">
                            <h5 class="mb-3">{{ translate('Settings') }}</h5>
                            <form action="{{ route('admin.safety.settings') }}" method="POST">@csrf
                                <div class="form-check form-switch mb-3">
                                    <input type="hidden" name="enabled" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="safety-enabled" name="enabled" value="1" {{ $settings['enabled'] ? 'checked' : '' }}>
                                    <label class="form-check-label" for="safety-enabled">{{ translate('Danger reports and area pause are on') }}</label>
                                </div>
                                @foreach([
                                    'auto_reports' => translate('Driver reports that block a spot'),
                                    'auto_radius_m' => translate('Within (metres), and the size of the no-go area'),
                                    'auto_window_min' => translate('Within (minutes)'),
                                    'auto_minutes' => translate('Automatic no-go lasts (minutes)'),
                                    'caution_radius_km' => translate('Warn drivers within (km)'),
                                    'reports_per_hour' => translate('Reports per person per hour'),
                                    'report_fade_min' => translate('A report warns for (minutes)'),
                                    'banner_radius_km' => translate('Show banners within (km)'),
                                ] as $key => $label)
                                    <div class="mb-2"><label class="form-label fs-12 mb-1">{{ $label }}</label>
                                        <input type="number" step="any" name="{{ $key }}" class="form-control form-control-sm" value="{{ $settings[$key] }}"></div>
                                @endforeach
                                <button class="btn btn-outline-primary w-100 mt-2">{{ translate('Save settings') }}</button>
                            </form>
                        </div></div>

                        <div class="card mb-3" id="trip-checkins"><div class="card-body">
                            <h5 class="mb-1">{{ translate('Trip check-ins') }}</h5>
                            <p class="fs-12 text-muted mb-3">{{ translate('When a ride stops too long, leaves its route or runs far too long, the rider is asked if they are OK. No answer or I need help opens a safety alert and tells their trusted contacts.') }}</p>
                            <form action="{{ route('admin.safety.checkin-settings') }}" method="POST">@csrf
                                <div class="form-check form-switch mb-3">
                                    <input type="hidden" name="enabled" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="checkin-enabled" name="enabled" value="1" {{ $checkinSettings['enabled'] ? 'checked' : '' }}>
                                    <label class="form-check-label" for="checkin-enabled">{{ translate('Trip check-ins and night sharing are on') }}</label>
                                </div>
                                @foreach([
                                    'stop_minutes' => translate('Ask after a stop of (minutes)'),
                                    'stop_m' => translate('A stop means moving less than (metres)'),
                                    'off_route_m' => translate('Off route means further from the route than (metres)'),
                                    'off_route_times' => translate('Off route that many minutes in a row'),
                                    'long_factor' => translate('Too long means this many times the estimate'),
                                    'answer_minutes' => translate('Time to answer before the alert (minutes)'),
                                    'quiet_minutes' => translate('After I am OK, do not ask again for (minutes)'),
                                    'night_from' => translate('Night sharing from (hour, 0 to 23)'),
                                    'night_to' => translate('Night sharing until (hour, 0 to 23)'),
                                ] as $key => $label)
                                    <div class="mb-2"><label class="form-label fs-12 mb-1">{{ $label }}</label>
                                        <input type="number" step="any" name="{{ $key }}" class="form-control form-control-sm" value="{{ $checkinSettings[$key] }}"></div>
                                @endforeach
                                <button class="btn btn-outline-primary w-100 mt-2">{{ translate('Save check-in settings') }}</button>
                            </form>
                        </div></div>
                    @endcan

                    @if($checkins->isNotEmpty())
                        <div class="card mb-3"><div class="card-body">
                            <h5 class="mb-3">{{ translate('Check-ins, last 24 hours') }}</h5>
                            @foreach($checkins as $ci)
                                <div class="fs-12 border-bottom py-1">
                                    <b>{{ translate('Trip') }} #{{ $ci->trip?->ref_id }}</b> · {{ \Modules\TripManagement\Entities\TripCheckin::reasonLabel($ci->reason) }}
                                    <span class="badge {{ in_array($ci->status, ['help', 'no_answer']) ? 'bg-danger' : ($ci->status === 'ok' ? 'bg-success' : 'bg-secondary') }}">{{ \Modules\TripManagement\Entities\TripCheckin::statusLabel($ci->status) }}</span>
                                    <div class="text-muted">{{ $ci->asked_at?->format('d M H:i') }}@if($ci->escalated_at) · {{ translate('safety alert opened') }}@endif</div>
                                </div>
                            @endforeach
                        </div></div>
                    @endif

                    @if($past->isNotEmpty())
                        <div class="card mb-3"><div class="card-body">
                            <h5 class="mb-3">{{ translate('Recently ended') }}</h5>
                            @foreach($past as $a)
                                <div class="fs-12 border-bottom py-1"><b>{{ $a->title }}</b> · {{ $a->reason }}
                                    <div class="text-muted">{{ $a->source === 'auto' ? translate('automatic') : translate('by admin') }} · {{ $a->lifted_at ? translate('lifted') . ' ' . $fmt($a->lifted_at) : translate('ended') . ' ' . $fmt($a->ends_at) }}</div></div>
                            @endforeach
                        </div></div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
    @php($map_key = businessConfig(GOOGLE_MAP_API)?->value['map_api_key'] ?? null)
    <script>
        "use strict";
        const safetyAreas = @json($mapAreas);
        const safetyReports = @json($mapReports);
        const colours = {decide: '#dc3545', no_go: '#fd7e14', pause: '#0d6efd', planned: '#6c757d'};
        let pickCircle = null;

        document.querySelectorAll('input[name="where"]').forEach(r => r.addEventListener('change', () => {
            const zone = document.querySelector('input[name="where"]:checked').value === 'zone';
            document.getElementById('where-zone').classList.toggle('d-none', !zone);
            document.getElementById('where-circle').classList.toggle('d-none', zone);
        }));

        function initSafetyMap() {
            const centre = safetyAreas.find(a => a.latitude) || safetyReports[0] || {latitude: 5.9597, longitude: 10.1460};
            const map = new google.maps.Map(document.getElementById('safety-map'), {
                center: {lat: Number(centre.latitude ?? centre.lat), lng: Number(centre.longitude ?? centre.lng)}, zoom: 13, mapTypeControl: false, streetViewControl: false,
            });
            safetyAreas.filter(a => a.latitude).forEach(a => {
                const c = colours[a.state] || '#0d6efd';
                const circle = new google.maps.Circle({map, center: {lat: Number(a.latitude), lng: Number(a.longitude)}, radius: a.radius_m,
                    strokeColor: c, strokeWeight: 2, fillColor: c, fillOpacity: 0.18});
                const info = new google.maps.InfoWindow({content: `<b>${a.title}</b><br>${a.reason}`});
                circle.addListener('click', e => { info.setPosition(e.latLng); info.open(map); });
            });
            safetyReports.forEach(r => {
                const m = new google.maps.Marker({map, position: {lat: Number(r.lat), lng: Number(r.lng)}, title: `${r.type} (${r.role}) ${r.at}`,
                    icon: {path: google.maps.SymbolPath.CIRCLE, scale: 6, fillColor: r.status === 'false' ? '#6c757d' : '#dc3545', fillOpacity: 0.9, strokeColor: '#fff', strokeWeight: 1}});
                const info = new google.maps.InfoWindow({content: `<b>${r.type}</b><br>${r.role} · ${r.at} · ${r.status}`});
                m.addListener('click', () => info.open(map, m));
            });
            map.addListener('click', e => {
                document.getElementById('pause-lat').value = e.latLng.lat().toFixed(6);
                document.getElementById('pause-lng').value = e.latLng.lng().toFixed(6);
                const radius = Number(document.getElementById('pause-radius').value);
                if (pickCircle) pickCircle.setMap(null);
                pickCircle = new google.maps.Circle({map, center: e.latLng, radius, strokeColor: '#0d6efd', strokeWeight: 2, fillColor: '#0d6efd', fillOpacity: 0.1, clickable: false});
            });
            document.getElementById('pause-radius').addEventListener('change', e => pickCircle && pickCircle.setRadius(Number(e.target.value)));
        }

        // Fresh reports keep coming: reload every 60 seconds unless the admin is typing.
        setInterval(() => {
            const el = document.activeElement;
            if (!el || !['INPUT', 'SELECT', 'TEXTAREA'].includes(el.tagName)) location.reload();
        }, 60000);
    </script>
    <script src="https://maps.googleapis.com/maps/api/js?key={{ $map_key }}&callback=initSafetyMap" async defer></script>
@endpush
