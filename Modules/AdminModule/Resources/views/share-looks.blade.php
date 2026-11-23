@extends('adminmodule::layouts.master')

@section('title', translate('Share pictures'))

@section('content')
    @php
        $layoutNames = [
            'photos' => translate('Four items with photos'),
            'list' => translate('Price list'),
            'one' => translate('One item, big'),
            'brand' => translate('Logo and cover only'),
            'routes' => translate('Destinations and fares'),
            'times' => translate('Departure times'),
        ];
        $kindNames = ['store' => translate('Food & Shops partners'), 'market' => translate('E-Ride Market shops'), 'bus' => translate('Bus agencies')];
        $palette = array_pad($settings['palette'], 12, '');
    @endphp
    <div class="main-content">
        <div class="container-fluid" style="max-width: 980px">
            <h2 class="fs-22 mb-1">{{ translate('Share pictures') }}</h2>
            <p class="text-muted mb-3">{{ translate('Partners share a WhatsApp Status picture and a poster of their store, shop or agency. Here you choose what they may change: their colour, the layout, and a picture they designed themselves.') }}</p>
            @if ($errors->any())
                <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif

            <div class="d-flex gap-3 mb-3">
                <div class="card flex-fill"><div class="card-body py-2"><div class="fs-4 fw-bold">{{ $counts['colors'] }}</div><div class="fs-12 text-muted">{{ translate('Partners using their own colour') }}</div></div></div>
                <div class="card flex-fill"><div class="card-body py-2"><div class="fs-4 fw-bold">{{ $counts['designs'] }}</div><div class="fs-12 text-muted">{{ translate('Pictures they designed') }}</div></div></div>
            </div>

            <form action="{{ route('admin.share-looks.save') }}" method="POST">@csrf
                <div class="card mb-3"><div class="card-body d-flex flex-column gap-3">
                    <h5 class="mb-0">{{ translate('Who can change their look') }}</h5>
                    @foreach($kindNames as $k => $label)
                        <div class="form-check form-switch">
                            <input type="hidden" name="{{ $k }}_on" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" id="s-{{ $k }}" name="{{ $k }}_on" value="1" {{ $settings['on'][$k] ? 'checked' : '' }}>
                            <label class="form-check-label" for="s-{{ $k }}">{{ $label }}</label>
                        </div>
                    @endforeach
                    <div class="fs-12 text-muted">{{ translate('Off: their pictures use the E-Ride look again. What they chose is kept for when you turn it back on.') }}</div>
                    <div class="form-check form-switch">
                        <input type="hidden" name="page_color" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="s-page" name="page_color" value="1" {{ $settings['page_color'] ? 'checked' : '' }}>
                        <label class="form-check-label" for="s-page">{{ translate('Their colour also on the top of their page') }}</label>
                        <div class="fs-12 text-muted">{{ translate('The page people open from the link, and their page in the app. Buttons and the rest of the app keep the E-Ride colours.') }}</div>
                    </div>
                </div></div>

                <div class="card mb-3"><div class="card-body d-flex flex-column gap-3">
                    <h5 class="mb-0">{{ translate('Colours') }}</h5>
                    <div>
                        <label class="form-label">{{ translate('Colours offered') }}</label>
                        <div class="d-flex flex-wrap gap-2" id="palette">
                            @foreach($palette as $i => $c)
                                <div class="d-flex flex-column align-items-center gap-1" style="width:64px">
                                    <input type="color" class="form-control form-control-color" style="width:56px;height:40px;padding:2px" value="{{ $c ?: '#ffffff' }}" data-slot="{{ $i }}" aria-label="{{ translate('Colour') }} {{ $i + 1 }}">
                                    <label class="fs-12"><input type="checkbox" data-use="{{ $i }}" {{ $c ? 'checked' : '' }}> {{ translate('Offer') }}</label>
                                    <input type="hidden" name="palette[]" value="{{ $c }}" data-value="{{ $i }}">
                                </div>
                            @endforeach
                        </div>
                        <div class="fs-12 text-muted mt-1">{{ translate('E-Ride red is always offered. Text on the pictures turns white or dark by itself so it stays readable.') }}</div>
                    </div>
                    <div class="form-check form-switch">
                        <input type="hidden" name="any_color" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="s-any" name="any_color" value="1" {{ $settings['any_color'] ? 'checked' : '' }}>
                        <label class="form-check-label" for="s-any">{{ translate('Partners can also pick any colour of their own') }}</label>
                        <div class="fs-12 text-muted">{{ translate('For a brand colour. Off: only the colours above.') }}</div>
                    </div>
                </div></div>

                <div class="card mb-3"><div class="card-body d-flex flex-column gap-3">
                    <h5 class="mb-0">{{ translate('Layouts of the Status picture') }}</h5>
                    <div class="row g-3">
                        @foreach(\App\Lib\ShareLooks::LAYOUTS as $k => $all)
                            <div class="col-md-4">
                                <div class="fw-semibold mb-1">{{ $kindNames[$k] }}</div>
                                @foreach($all as $layout)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="l-{{ $k }}-{{ $layout }}" name="layouts[{{ $k }}][]" value="{{ $layout }}" {{ in_array($layout, $settings['layouts'][$k], true) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="l-{{ $k }}-{{ $layout }}">{{ $layoutNames[$layout] }}</label>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                    <div class="fs-12 text-muted">{{ translate('At least one stays on. A partner whose layout you turn off gets the first one left.') }}</div>
                </div></div>

                <div class="card mb-3"><div class="card-body d-flex flex-column gap-3">
                    <h5 class="mb-0">{{ translate('Pictures they designed') }}</h5>
                    <div class="form-check form-switch">
                        <input type="hidden" name="designs" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="s-designs" name="designs" value="1" {{ $settings['designs'] ? 'checked' : '' }}>
                        <label class="form-check-label" for="s-designs">{{ translate('Partners can share a picture they designed (a flyer from Canva or a designer)') }}</label>
                        <div class="fs-12 text-muted">{{ translate('It goes live at once. You can take it down below, and the partner sees why.') }}</div>
                    </div>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label" for="s-strip">{{ translate('E-Ride strip under their picture') }}</label>
                            <select id="s-strip" name="strip" class="form-select">
                                <option value="required" {{ $settings['strip'] === 'required' ? 'selected' : '' }}>{{ translate('Always (recommended)') }}</option>
                                <option value="optional" {{ $settings['strip'] === 'optional' ? 'selected' : '' }}>{{ translate('The partner chooses') }}</option>
                            </select>
                            <div class="fs-12 text-muted mt-1">{{ translate('The strip has their QR code and link. Without it, people who see the picture cannot open the page, and visits and new users are not counted.') }}</div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="s-mb">{{ translate('Largest picture (MB)') }}</label>
                            <input type="number" id="s-mb" name="max_mb" class="form-control" min="1" max="10" required value="{{ $settings['max_mb'] }}">
                        </div>
                    </div>
                </div></div>

                <div class="d-flex justify-content-end mb-4">
                    <button type="submit" class="btn btn-primary">{{ translate('Save') }}</button>
                </div>
            </form>

            <div class="card mb-3"><div class="card-body">
                <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
                    <h5 class="mb-0">{{ translate('Pictures partners designed') }}</h5>
                    <div class="btn-group btn-group-sm" role="group">
                        <a href="{{ route('admin.share-looks.index') }}" class="btn {{ $kind ? 'btn-outline-primary' : 'btn-primary' }}">{{ translate('All') }}</a>
                        @foreach($kindNames as $k => $label)
                            <a href="{{ route('admin.share-looks.index', ['kind' => $k]) }}" class="btn {{ $kind === $k ? 'btn-primary' : 'btn-outline-primary' }}">{{ $label }}</a>
                        @endforeach
                    </div>
                </div>
                @if($designs->isEmpty())
                    <p class="text-muted mb-0">{{ translate('No partner has shared a picture of their own yet.') }}</p>
                @else
                    <div class="row g-3">
                        @foreach($designs as $d)
                            @php [$name, $what, $link] = $owners[$d->id]; @endphp
                            <div class="col-sm-6 col-lg-4">
                                <div class="border rounded p-2 h-100 d-flex flex-column gap-2" style="border-radius:6px">
                                    <a href="{{ $d->designUrl() }}" target="_blank" rel="noopener">
                                        <img src="{{ $d->designUrl() }}" alt="{{ $name }}" style="width:100%;height:260px;object-fit:contain;background:#F4F6F6;border-radius:6px">
                                    </a>
                                    <div>
                                        <div class="fw-semibold text-truncate">@if($link)<a href="{{ $link }}" target="_blank" rel="noopener">{{ $name }}</a>@else{{ $name }}@endif</div>
                                        <div class="fs-12 text-muted">{{ $what }} · {{ $d->design_at?->diffForHumans() }}{{ $d->strip ? '' : ' · ' . translate('no E-Ride strip') }}</div>
                                    </div>
                                    @can('place_edit')
                                        <form action="{{ route('admin.share-looks.take-down', $d->id) }}" method="POST" class="d-flex gap-2 mt-auto"
                                              onsubmit="return confirm(@js(translate('Take this picture down? The partner sees the reason.')))">@csrf
                                            <select name="reason" class="form-select form-select-sm" aria-label="{{ translate('Reason') }}">
                                                @foreach($reasons as $r)<option value="{{ $r }}">{{ translate($r) }}</option>@endforeach
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-outline-danger text-nowrap">{{ translate('Take down') }}</button>
                                        </form>
                                    @endcan
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-3">{{ $designs->links() }}</div>
                @endif
            </div></div>

            @if($removed->isNotEmpty())
                <div class="card mb-4"><div class="card-body">
                    <h5 class="mb-2">{{ translate('Recently taken down') }}</h5>
                    <ul class="mb-0 ps-3">
                        @foreach($removed as $r)
                            <li>{{ $removedOwners[$r->id][0] }} ({{ $removedOwners[$r->id][1] }}): {{ translate($r->removed_reason) }} · {{ $r->removed_at->diffForHumans() }}</li>
                        @endforeach
                    </ul>
                </div></div>
            @endif
        </div>
    </div>
@endsection

@push('script')
<script>
    // Palette: a slot is offered when ticked; its colour goes in the hidden field.
    document.querySelectorAll('#palette [data-slot]').forEach(function (picker) {
        const i = picker.dataset.slot;
        const use = document.querySelector('[data-use="' + i + '"]');
        const value = document.querySelector('[data-value="' + i + '"]');
        const sync = function () { value.value = use.checked ? picker.value : ''; };
        picker.addEventListener('input', function () { use.checked = true; sync(); });
        use.addEventListener('change', sync);
    });
</script>
@endpush
