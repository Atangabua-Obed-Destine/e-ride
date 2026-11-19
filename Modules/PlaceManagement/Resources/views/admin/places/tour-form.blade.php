@extends('adminmodule::layouts.master')

@section('title', $tour->exists ? $tour->title : translate('New_tour'))

@section('content')
    @php($initialStops = old('stops', $tour->exists ? $tour->stops->map(fn($s) => ['place_id' => $s->place_id, 'note' => $s->note, 'note_fr' => $s->note_fr, 'stay_minutes' => $s->stay_minutes])->values()->all() : []))
    @php($placeData = $places->map(fn($p) => [
        'id' => $p->id, 'name' => $p->name, 'cat' => $p->explore_category, 'type' => $p->category, 'zone' => $p->zone_id,
        'lat' => $p->latitude, 'lng' => $p->longitude, 'guide' => $p->guide_status === 'published', 'things' => in_array($p->explore_category, $things, true),
    ])->values())
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex align-items-center gap-2 mb-3">
                <a href="{{ route('admin.place.tours') }}" class="btn btn-light btn-sm"><i class="bi bi-arrow-left"></i></a>
                <h2 class="fs-22 mb-0">{{ $tour->exists ? $tour->title : translate('New_tour') }}</h2>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif

            <form id="tour-form" action="{{ $tour->exists ? route('admin.place.tours.update', $tour) : route('admin.place.tours.store') }}" method="POST">
                @csrf
                @if($tour->exists) @method('PUT') @endif
                <div class="row g-3">
                    <div class="col-lg-7">
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="d-flex align-items-start justify-content-between gap-2 flex-wrap mb-2">
                                    <div>
                                        <h5 class="mb-1">{{ translate('Stops') }}</h5>
                                        <p class="fs-12 text-muted mb-0">{{ translate('Verified places in the tour\'s city, in the order riders go. Each stop is one ride from the stop before.') }}</p>
                                    </div>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="order-route"><i class="bi bi-signpost-2"></i> {{ translate('Shortest_order') }}</button>
                                </div>
                                <div id="stops"></div>
                                <div class="d-flex gap-2 align-items-center mt-2">
                                    <select id="add-place" class="form-select form-select-sm" style="max-width: 420px"></select>
                                    <button type="button" class="btn btn-primary btn-sm" id="add-stop"><i class="bi bi-plus-lg"></i> {{ translate('Add_stop') }}</button>
                                </div>
                                <div class="fs-12 text-muted mt-2">{{ translate('Missing a place? Add it under Verified listings (or from Google results), then come back.') }} <a href="{{ route('admin.place.google', ['explore' => 'visit']) }}" target="_blank">{{ translate('Google_results') }}</a></div>
                            </div>
                        </div>

                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="d-flex align-items-start justify-content-between gap-2 flex-wrap">
                                    <div>
                                        <h5 class="mb-1">{{ translate('Texts') }}</h5>
                                        <p class="fs-12 text-muted mb-2">{{ translate('Pick the stops, let the AI write the title, introduction and a note per stop, then correct them.') }}</p>
                                    </div>
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="tour-draft"><i class="bi bi-stars"></i> {{ translate('Draft_with_AI') }}</button>
                                </div>
                                <div class="alert alert-info fs-12 py-2 d-none" id="tour-draft-note"></div>
                                <div class="row g-3">
                                    <div class="col-sm-6">
                                        <label class="form-label">{{ translate('Title') }} (English) *</label>
                                        <input type="text" name="title" required maxlength="120" class="form-control" value="{{ old('title', $tour->title) }}" placeholder="{{ translate('Ex: Bamenda in a day') }}">
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label">{{ translate('Title') }} (Français)</label>
                                        <input type="text" name="title_fr" maxlength="120" class="form-control" value="{{ old('title_fr', $tour->title_fr) }}">
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label">{{ translate('Introduction') }} (English)</label>
                                        <textarea name="intro" rows="4" maxlength="1500" class="form-control">{{ old('intro', $tour->intro) }}</textarea>
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label">{{ translate('Introduction') }} (Français)</label>
                                        <textarea name="intro_fr" rows="4" maxlength="1500" class="form-control">{{ old('intro_fr', $tour->intro_fr) }}</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h5 class="mb-3">{{ translate('Tour') }}</h5>
                                <label class="form-label">{{ translate('City_(service_zone)') }} *</label>
                                <select name="zone_id" id="zone" class="form-select mb-3" required>
                                    @foreach($zones as $z)
                                        <option value="{{ $z->id }}" {{ old('zone_id', $tour->zone_id) === $z->id ? 'selected' : '' }}>{{ $z->name }}</option>
                                    @endforeach
                                </select>
                                <label class="form-label">{{ translate('Status') }}</label>
                                <select name="status" class="form-select mb-3">
                                    @foreach(['draft' => translate('Draft_(not_in_the_app)'), 'published' => translate('Published'), 'hidden' => translate('Hidden')] as $k => $label)
                                        <option value="{{ $k }}" {{ old('status', $tour->status) === $k ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <div class="row g-2 align-items-end">
                                    <div class="col-6">
                                        <label class="form-check d-flex align-items-center gap-2 mb-2">
                                            <input type="hidden" name="is_featured" value="0">
                                            <input class="form-check-input mt-0" type="checkbox" name="is_featured" value="1" {{ old('is_featured', $tour->is_featured) ? 'checked' : '' }}>
                                            <span class="form-check-label">{{ translate('Featured') }}</span>
                                        </label>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">{{ translate('Order') }}</label>
                                        <input type="number" name="sort" class="form-control" value="{{ old('sort', $tour->sort ?? 0) }}">
                                    </div>
                                </div>
                                <hr>
                                <div class="fs-12 text-muted" id="summary"></div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            @if($tour->exists)
                                <button type="submit" form="delete-tour" class="btn btn-outline-danger">{{ translate('Remove_tour') }}</button>
                            @endif
                            <button type="submit" class="btn btn-primary">{{ translate('Save') }}</button>
                        </div>
                    </div>
                </div>
            </form>
            @if($tour->exists)
                <form id="delete-tour" action="{{ route('admin.place.tours.destroy', $tour) }}" method="POST" onsubmit='return confirm(@json(translate("Remove this tour? Riders will no longer see it.")))'>
                    @csrf @method('DELETE')
                </form>
            @endif
        </div>
    </div>
@endsection

@push('script')
    <script>
        (function () {
            var places = @json($placeData), stops = @json($initialStops);
            var byId = {}; places.forEach(function (p) { byId[p.id] = p; });
            var zone = document.getElementById('zone'), list = document.getElementById('stops'), add = document.getElementById('add-place');
            var T = {
                up: @json(translate('Move up')), down: @json(translate('Move down')), remove: @json(translate('Remove')),
                note: @json(translate('Note for this stop (English)')), noteFr: @json(translate('Note (Français)')), minutes: @json(translate('Minutes')),
                noGuide: @json(translate('no published guide')), pick: @json(translate('Pick a verified place')), other: @json(translate('other categories')),
                empty: @json(translate('No stops yet.')), summary: @json(translate('stops, about :km km of rides (straight line x 1.3)')),
                drafting: @json(translate('Writing a draft, this takes up to half a minute...')), pickFirst: @json(translate('Pick the stops first.')),
                replace: @json(translate('Replace the title, introduction and notes with a new AI draft?'))
            };
            function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]; }); }
            function km(a, b) {
                var r = 6371, dLat = (b.lat - a.lat) * Math.PI / 180, dLng = (b.lng - a.lng) * Math.PI / 180;
                var x = Math.sin(dLat / 2) * Math.sin(dLat / 2) + Math.cos(a.lat * Math.PI / 180) * Math.cos(b.lat * Math.PI / 180) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
                return r * 2 * Math.atan2(Math.sqrt(x), Math.sqrt(1 - x));
            }
            function fillAdd() {
                var z = zone.value, used = stops.map(function (s) { return s.place_id; });
                var inZone = places.filter(function (p) { return p.zone === z && used.indexOf(p.id) < 0; });
                var things = inZone.filter(function (p) { return p.things; }), other = inZone.filter(function (p) { return !p.things; });
                var html = '<option value="">' + esc(T.pick) + '</option>';
                things.forEach(function (p) { html += '<option value="' + p.id + '">' + esc(p.name) + ' (' + esc(p.type) + ')' + (p.guide ? '' : ' - ' + esc(T.noGuide)) + '</option>'; });
                if (other.length) {
                    html += '<optgroup label="' + esc(T.other) + '">';
                    other.forEach(function (p) { html += '<option value="' + p.id + '">' + esc(p.name) + ' (' + esc(p.cat) + ')</option>'; });
                    html += '</optgroup>';
                }
                add.innerHTML = html;
            }
            function render() {
                if (!stops.length) { list.innerHTML = '<div class="text-muted fs-12 py-2">' + esc(T.empty) + '</div>'; }
                else {
                    list.innerHTML = stops.map(function (s, i) {
                        var p = byId[s.place_id] || { name: '?', type: '' };
                        var n = 'stops[' + i + ']';
                        return '<div class="border rounded p-2 mb-2">' +
                            '<div class="d-flex align-items-center gap-2 mb-2">' +
                            '<span class="badge bg-primary">' + (i + 1) + '</span>' +
                            '<strong class="flex-grow-1">' + esc(p.name) + ' <span class="fs-12 text-muted fw-normal">' + esc(p.type) + (p.guide ? '' : ' · ' + esc(T.noGuide)) + '</span></strong>' +
                            '<input type="hidden" name="' + n + '[place_id]" value="' + esc(s.place_id) + '">' +
                            '<button type="button" class="btn btn-light btn-sm" data-act="up" data-i="' + i + '" title="' + esc(T.up) + '"><i class="bi bi-arrow-up"></i></button>' +
                            '<button type="button" class="btn btn-light btn-sm" data-act="down" data-i="' + i + '" title="' + esc(T.down) + '"><i class="bi bi-arrow-down"></i></button>' +
                            '<button type="button" class="btn btn-outline-danger btn-sm" data-act="remove" data-i="' + i + '" title="' + esc(T.remove) + '"><i class="bi bi-x-lg"></i></button>' +
                            '</div><div class="row g-2">' +
                            '<div class="col-md-5"><input class="form-control form-control-sm" maxlength="400" name="' + n + '[note]" data-f="note" data-i="' + i + '" placeholder="' + esc(T.note) + '" value="' + esc(s.note) + '"></div>' +
                            '<div class="col-md-5"><input class="form-control form-control-sm" maxlength="400" name="' + n + '[note_fr]" data-f="note_fr" data-i="' + i + '" placeholder="' + esc(T.noteFr) + '" value="' + esc(s.note_fr) + '"></div>' +
                            '<div class="col-md-2"><input type="number" min="5" max="600" step="5" class="form-control form-control-sm" name="' + n + '[stay_minutes]" data-f="stay_minutes" data-i="' + i + '" placeholder="' + esc(T.minutes) + '" value="' + esc(s.stay_minutes) + '"></div>' +
                            '</div></div>';
                    }).join('');
                }
                var total = 0;
                for (var i = 1; i < stops.length; i++) {
                    var a = byId[stops[i - 1].place_id], b = byId[stops[i].place_id];
                    if (a && b) total += km(a, b) * 1.3;
                }
                document.getElementById('summary').textContent = stops.length + ' ' + T.summary.replace(':km', total.toFixed(1));
                fillAdd();
            }
            list.addEventListener('input', function (e) {
                var f = e.target.dataset.f; if (!f) return;
                stops[+e.target.dataset.i][f] = e.target.value;
            });
            list.addEventListener('click', function (e) {
                var b = e.target.closest('button[data-act]'); if (!b) return;
                var i = +b.dataset.i, act = b.dataset.act;
                if (act === 'remove') stops.splice(i, 1);
                if (act === 'up' && i > 0) stops.splice(i - 1, 0, stops.splice(i, 1)[0]);
                if (act === 'down' && i < stops.length - 1) stops.splice(i + 1, 0, stops.splice(i, 1)[0]);
                render();
            });
            document.getElementById('add-stop').addEventListener('click', function () {
                if (!add.value || stops.length >= {{ \Modules\PlaceManagement\Entities\Tour::MAX_STOPS }}) return;
                stops.push({ place_id: add.value, note: '', note_fr: '', stay_minutes: '' });
                render();
            });
            // Nearest next stop from the first one: fewer kilometres, cheaper rides.
            document.getElementById('order-route').addEventListener('click', function () {
                if (stops.length < 3) return;
                var rest = stops.slice(1), out = [stops[0]];
                while (rest.length) {
                    var last = byId[out[out.length - 1].place_id], best = 0, bestKm = Infinity;
                    rest.forEach(function (s, j) { var d = km(last, byId[s.place_id]); if (d < bestKm) { bestKm = d; best = j; } });
                    out.push(rest.splice(best, 1)[0]);
                }
                stops = out; render();
            });
            zone.addEventListener('change', fillAdd);

            var btn = document.getElementById('tour-draft'), note = document.getElementById('tour-draft-note'), form = document.getElementById('tour-form');
            btn.addEventListener('click', function () {
                if (!stops.length) { note.classList.remove('d-none'); note.textContent = T.pickFirst; return; }
                if ((form.elements.title.value.trim() || form.elements.intro.value.trim()) && !confirm(T.replace)) return;
                btn.disabled = true;
                note.classList.remove('d-none', 'alert-danger'); note.classList.add('alert-info'); note.textContent = T.drafting;
                var body = new FormData();
                body.append('_token', @json(csrf_token()));
                body.append('zone_id', zone.value);
                stops.forEach(function (s) { body.append('place_ids[]', s.place_id); });
                fetch(@json(route('admin.place.tours.draft')), { method: 'POST', body: body, headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                    .then(function (res) {
                        if (!res.ok) throw new Error(res.j.message || 'failed');
                        var d = res.j.draft;
                        ['title', 'title_fr', 'intro', 'intro_fr'].forEach(function (f) { if (d[f]) form.elements[f].value = d[f]; });
                        (d.notes || []).forEach(function (n, i) { if (stops[i]) { stops[i].note = n.note || stops[i].note; stops[i].note_fr = n.note_fr || stops[i].note_fr; } });
                        render();
                        note.textContent = res.j.message;
                    })
                    .catch(function (e) { note.classList.remove('alert-info'); note.classList.add('alert-danger'); note.textContent = e.message; })
                    .finally(function () { btn.disabled = false; });
            });
            render();
        })();
    </script>
@endpush
