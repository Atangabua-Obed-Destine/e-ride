@extends('adminmodule::layouts.master')

@section('title', translate('Bus agencies'))

@php
    use Modules\BusManagement\Entities\BusAgency;
    $statusTag = [
        BusAgency::CHECKING => ['bg-warning-subtle text-warning-emphasis', translate('Checking')],
        BusAgency::SETUP => ['bg-info-subtle text-info-emphasis', translate('Portal open, setting up')],
        BusAgency::LIVE => ['bg-success-subtle text-success-emphasis', translate('Bookable')],
        BusAgency::PAUSED => ['bg-danger-subtle text-danger-emphasis', translate('Paused')],
    ];
    $pct = fn($v) => rtrim(rtrim(number_format((float)$v, 2), '0'), '.') . '%';
@endphp

@section('content')
<div class="main-content">
    <div class="container-fluid">
        <h2 class="fs-22 mb-1">{{ translate('Bus agencies') }}</h2>
        <p class="fs-13 text-muted mb-3">{{ translate('Agencies start from their Google listing. E-Ride checks them, then opens their partner portal. Passengers pay each agency through its own Fapshi account. E-Ride bills a % commission every month.') }}</p>
        @if ($errors->any())
            <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        @if(!$settings['enabled'])
            <div class="alert alert-warning">{{ translate('Bus booking is off in the app. Agencies can still be verified and set up. Turn it on in the settings below when the first one is ready.') }}</div>
        @endif

        <div class="card mb-3">
            <div class="card-header"><h5 class="mb-0">{{ translate('Bus companies') }}</h5>
                <div class="fs-12 text-muted">{{ translate('A company groups its branches (each a verified agency) under one head office that watches them all.') }}</div></div>
            <div class="card-body">
                @foreach($companies as $c)
                    <div class="d-flex flex-wrap gap-2 align-items-center border-bottom py-2">
                        <div class="flex-grow-1"><b>{{ $c->name }}</b> · {{ translate(key: '{n} branch(es)', replace: ['n' => $c->branches_count]) }}
                            <div class="fs-12 text-muted">{{ $c->collectsMoney() ? translate('One company account') : translate('Each branch its own account') }} · {{ $c->billedTogether() ? translate('One statement for the company') : translate('A statement per branch') }}</div></div>
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.bus.companies.show', $c->id) }}">{{ translate('Open') }}</a>
                    </div>
                @endforeach
                <form method="POST" action="{{ route('admin.bus.companies.store') }}" class="d-flex flex-wrap gap-2 mt-2">
                    @csrf
                    <input name="name" class="form-control" style="max-width:320px" required maxlength="120" placeholder="{{ translate('Company name, e.g. Vatican Express') }}" aria-label="{{ translate('Company name') }}">
                    <button class="btn btn-primary">{{ translate('Add a company') }}</button>
                </form>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h5 class="mb-0">{{ translate('Agencies on E-Ride') }}</h5></div>
            <div class="table-responsive">
                <table class="table table-borderless align-middle mb-0">
                    <thead class="table-light"><tr><th>{{ translate('Agency') }}</th><th>{{ translate('Status') }}</th><th>{{ translate('From') }}</th><th>{{ translate('Fapshi') }}</th><th>{{ translate('Open departures') }}</th><th>{{ translate('App tickets this month') }}</th><th>{{ translate('Commission app / counter') }}</th><th>{{ translate('Owed to E-Ride') }}</th><th></th></tr></thead>
                    <tbody>
                    @forelse($agencies as $a)
                        <tr>
                            <td><div class="fw-semibold">{{ $a->name }}</div>@if($a->company)<div class="fs-12"><span class="badge bg-primary-subtle text-primary-emphasis">{{ $a->displayName() }}</span></div>@endif<div class="fs-12 text-muted">{{ $a->address }}</div></td>
                            <td><span class="badge {{ $statusTag[$a->status][0] }}">{{ $statusTag[$a->status][1] }}</span>@if($a->status === BusAgency::PAUSED && $a->pause_note)<div class="fs-12 text-muted">{{ $a->pause_note }}</div>@endif</td>
                            <td>{{ $a->origin_city }}</td>
                            <td>@if($a->fapshi_ok_at)<span class="badge bg-success-subtle text-success-emphasis">{{ translate('Connected') }}</span>@else<span class="text-muted fs-12">{{ translate('Not yet') }}</span>@endif</td>
                            <td>{{ $openDepartures[$a->id] ?? 0 }}</td>
                            <td>{{ $appTickets[$a->id] ?? 0 }}</td>
                            <td>{{ $pct($a->commission_app_pct) }} / {{ $pct($a->commission_counter_pct) }}</td>
                            <td>@if($o = $owed->get($a->id)){{ getCurrencyFormat($o->total) }}@if(\Illuminate\Support\Carbon::parse($o->due_on)->lt(today()))<div class="fs-12 text-danger">{{ translate('Late') }}</div>@endif @else<span class="text-muted">0</span>@endif</td>
                            <td class="text-end"><a class="btn btn-sm {{ $a->status === BusAgency::CHECKING ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('admin.bus.show', $a->id) }}">{{ $a->status === BusAgency::CHECKING ? translate('Continue checking') : translate('Open') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-muted">{{ translate('No agency yet. Load the Google bus agencies below and start with one.') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex flex-wrap align-items-center gap-2">
                <h5 class="mb-0 flex-grow-1">{{ translate('Bus agencies on Google near Bamenda') }}</h5>
                <form method="GET" class="d-flex gap-2 align-items-center">
                    <input type="hidden" name="load" value="1">
                    <input type="number" step="any" name="latitude" class="form-control form-control-sm" style="width:120px" value="{{ $lat }}" aria-label="{{ translate('Latitude') }}">
                    <input type="number" step="any" name="longitude" class="form-control form-control-sm" style="width:120px" value="{{ $lng }}" aria-label="{{ translate('Longitude') }}">
                    <button class="btn btn-sm btn-primary">{{ $google === false ? translate('Load from Google') : translate('Reload') }}</button>
                </form>
            </div>
            <div class="card-body">
                @if($google === false)
                    <p class="text-muted mb-0">{{ translate('Each Google search costs money, so it only runs when you ask.') }}</p>
                @elseif($google === null)
                    <div class="alert alert-warning mb-0">{{ translate('Google did not answer, or the monthly limit is reached.') }}</div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <tbody>
                            @foreach($google as $g)
                                <tr class="{{ $g['status'] === 'hidden' ? 'opacity-50' : '' }}">
                                    <td><div class="fw-semibold">{{ $g['name'] }}</div><div class="fs-12 text-muted">{{ $g['address'] }} · {{ $g['distance_km'] }} km{{ $g['rating'] ? ' · ★ ' . $g['rating'] . ' (' . $g['reviews'] . ')' : '' }}</div></td>
                                    <td class="text-end text-nowrap">
                                        @if($known->has($g['google_place_id']))
                                            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.bus.show', $known[$g['google_place_id']]) }}">{{ translate('On E-Ride') }}</a>
                                        @elseif($g['status'] === 'hidden')
                                            <span class="fs-12 text-muted me-2">{{ translate('Hidden from the app') }}</span>
                                            <form method="POST" action="{{ route('admin.place.google.unhide') }}" class="d-inline">@csrf<input type="hidden" name="google_place_id" value="{{ $g['google_place_id'] }}"><button class="btn btn-sm btn-outline-secondary">{{ translate('Show again') }}</button></form>
                                        @else
                                            <form method="POST" action="{{ route('admin.bus.start') }}" class="d-inline">@csrf
                                                @foreach(['google_place_id', 'name', 'address', 'phone'] as $k)<input type="hidden" name="{{ $k }}" value="{{ $g[$k] }}">@endforeach
                                                <input type="hidden" name="lat" value="{{ $g['latitude'] }}"><input type="hidden" name="lng" value="{{ $g['longitude'] }}">
                                                <button class="btn btn-sm btn-primary">{{ translate('Verify this agency') }}</button></form>
                                            <form method="POST" action="{{ route('admin.place.google.hide') }}" class="d-inline">@csrf
                                                <input type="hidden" name="google_place_id" value="{{ $g['google_place_id'] }}"><input type="hidden" name="label" value="{{ $g['name'] }}">
                                                <input type="hidden" name="reason" value="{{ translate('Not a bus agency') }}">
                                                <button class="btn btn-sm btn-outline-danger">{{ translate('Not a bus agency: hide') }}</button></form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <form method="POST" action="{{ route('admin.bus.settings') }}" class="card">
            @csrf
            <div class="card-header"><h5 class="mb-0">{{ translate('Bus settings') }}</h5></div>
            <div class="card-body">
                <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" id="bus_enabled" name="enabled" value="1" @checked($settings['enabled'])><label class="form-check-label fw-semibold" for="bus_enabled">{{ translate('Bus booking on in the app') }}</label></div>
                <div class="row g-3">
                    @foreach([
                        ['answer_minutes', 'Minutes the agency has to answer a seat request', 2, 60, 1],
                        ['pay_minutes', 'Minutes the passenger has to pay once accepted', 5, 60, 1],
                        ['open_days', 'Days ahead departures open for booking', 1, 60, 1],
                        ['max_seats', 'Most seats in one request', 1, 10, 1],
                        ['commission_app_pct', 'Default commission on app tickets (% of the fare)', 0, 50, 0.01],
                        ['commission_counter_pct', 'Default commission on counter tickets (% of the fare)', 0, 50, 0.01],
                        ['statement_due_day', 'Monthly statement due on this day of the next month', 1, 28, 1],
                        ['late_pause_days', 'Pause online booking when a statement is this many days late', 1, 60, 1],
                        ['refund_hours', 'Hours an agency has to send a refund it owes', 6, 168, 1],
                        ['decline_pause_pct', 'Pause an agency declining or ignoring more than this % of requests (30 days, 0 = never)', 0, 100, 1],
                    ] as [$key, $label, $min, $max, $step])
                        <div class="col-md-6 col-xl-4"><label class="form-label" for="bs_{{ $key }}">{{ translate($label) }}</label><input id="bs_{{ $key }}" type="number" name="{{ $key }}" class="form-control" min="{{ $min }}" max="{{ $max }}" step="{{ $step }}" value="{{ $settings[$key] }}" required></div>
                    @endforeach
                </div>
                <p class="fs-12 text-muted mt-3 mb-0">{{ translate('New agencies start with the default commissions. Each agency can have its own, on its page.') }}</p>
            </div>
            <div class="card-footer text-end"><button class="btn btn-primary">{{ translate('Save') }}</button></div>
        </form>

        {{-- The bus website (eride.../bus) and the WhatsApp/SMS messages its travellers get. --}}
        <form method="POST" action="{{ route('admin.bus.web-settings') }}" class="card mt-3" autocomplete="off">
            @csrf
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">{{ translate('Web booking and messages') }}</h5>
                <a href="{{ route('bus.web.home') }}" target="_blank" rel="noopener" class="fs-12">{{ route('bus.web.home') }}</a>
            </div>
            <div class="card-body">
                <p class="fs-12 text-muted">{{ translate('Web bookings this month') }}: <strong>{{ $webBookings }}</strong>.
                    {{ translate('Messages in the last 30 days') }}:
                    @forelse($messages as $m) <span class="badge bg-light text-dark me-1">{{ $m->channel }} · {{ $m->status }}: {{ $m->n }}</span> @empty {{ translate('none') }} @endforelse
                </p>
                @foreach([
                    ['booking', 'Bus booking on the website', 'Off: the pages still show departures, with "Book in the app".'],
                    ['signup', 'Create an account on the website', 'Off: new people are sent to the app to sign up.'],
                    ['signup_code', 'SMS code when creating an account', 'Proves the phone number. Needs an SMS gateway switched on (3rd party > SMS). Turn it on before launch so nobody signs up with another person’s number.'],
                    ['index', 'Show the website on Google', 'Lets search engines list the home, route and agency pages.'],
                    ['sms_backup', 'SMS backup', 'Messages go by SMS when WhatsApp is not connected or a WhatsApp message fails. Needs an SMS gateway switched on.'],
                ] as [$key, $label, $hint])
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="web_{{ $key }}" name="web_{{ $key }}" value="1" @checked($web[$key])>
                        <label class="form-check-label fw-semibold" for="web_{{ $key }}">{{ translate($label) }}</label>
                        <div class="fs-12 text-muted">{{ translate($hint) }}</div>
                    </div>
                @endforeach

                <hr>
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                    <h6 class="mb-0">{{ translate('WhatsApp messages (Meta WhatsApp Cloud API)') }}</h6>
                    @if(\Modules\Gateways\Services\WhatsAppCloud::ready())
                        <span class="badge bg-success">{{ translate('Connected') }}</span>
                    @else
                        <span class="badge bg-warning text-dark">{{ translate('Not connected: messages go by SMS') }}</span>
                    @endif
                </div>
                <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" id="wa_enabled" name="wa_enabled" value="1" @checked($wa['enabled'])><label class="form-check-label fw-semibold" for="wa_enabled">{{ translate('Send WhatsApp messages') }}</label></div>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label" for="wa_phone_number_id">{{ translate('Phone number ID') }}</label><input id="wa_phone_number_id" name="wa_phone_number_id" class="form-control" value="{{ $wa['phone_number_id'] }}" placeholder="{{ translate('From Meta: WhatsApp > API setup') }}"></div>
                    <div class="col-md-6"><label class="form-label" for="wa_api_version">{{ translate('API version') }}</label><input id="wa_api_version" name="wa_api_version" class="form-control" value="{{ $wa['api_version'] }}"></div>
                    <div class="col-md-6"><label class="form-label" for="wa_token">{{ translate('Permanent access token') }}</label><input id="wa_token" type="password" name="wa_token" class="form-control" placeholder="{{ $wa['token'] !== '' ? translate('Saved. Type a new one to change it') : translate('System user token from Meta Business') }}"></div>
                    <div class="col-md-6"><label class="form-label" for="wa_app_secret">{{ translate('App secret') }}</label><input id="wa_app_secret" type="password" name="wa_app_secret" class="form-control" placeholder="{{ $wa['app_secret'] !== '' ? translate('Saved. Type a new one to change it') : translate('Meta app > Settings > Basic') }}"></div>
                    <div class="col-md-6"><label class="form-label">{{ translate('Webhook callback URL') }}</label><input class="form-control" readonly value="{{ route('bus.web.whatsapp.webhook') }}"></div>
                    <div class="col-md-6"><label class="form-label">{{ translate('Webhook verify token') }}</label><input class="form-control" readonly value="{{ $wa['verify_token'] !== '' ? $wa['verify_token'] : translate('Shown after the first save') }}"></div>
                </div>
                <p class="fs-12 text-muted mt-3 mb-2">{{ translate('Create these message templates in Meta (category Utility), in English (en) and French (fr), each with a "Visit website" button to') }} <code>{{ url('/bus/t') }}/@{{1}}</code>:</p>
                <div class="table-responsive">
                    <table class="table table-sm fs-12 mb-0">
                        <thead><tr><th>{{ translate('Template') }}</th><th>English</th><th>Français</th></tr></thead>
                        <tbody>
                        @foreach(\Modules\BusManagement\Lib\BusMessenger::TEMPLATES as $tpl)
                            <tr><td><code>{{ $tpl['name'] }}</code></td><td>{{ $tpl['en'] }}</td><td>{{ $tpl['fr'] }}</td></tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer text-end"><button class="btn btn-primary">{{ translate('Save') }}</button></div>
        </form>
    </div>
</div>
@endsection
