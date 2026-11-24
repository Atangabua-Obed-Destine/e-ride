@extends('adminmodule::layouts.master')

@section('title', $company->name)

@php
    use Modules\BusManagement\Entities\BusAgency;
    use Modules\BusManagement\Entities\BusCompany;
    use Modules\BusManagement\Entities\BusCompanyStaff;
    use Modules\BusManagement\Lib\CameroonRegions;
    use Modules\BusManagement\Service\AgencyService;
    $modeLabel = fn($m) => $m === BusCompany::COMPANY ? translate('One company account') : translate('Each branch its own');
    $billLabel = fn($m) => $m === BusCompany::COMPANY ? translate('One statement for the company') : translate('A statement per branch');
@endphp

@section('content')
<div class="main-content">
    <div class="container-fluid">
        <a class="fs-13" href="{{ route('admin.bus.index') }}">&larr; {{ translate('Bus agencies') }}</a>
        <h2 class="fs-22 mt-2 mb-1">{{ $company->name }}</h2>
        <p class="fs-13 text-muted mb-3">{{ translate('A bus company: its head office watches every branch, with a few controls. Each branch is a verified bus agency with its own city, portal and counter.') }}</p>
        @if ($errors->any())
            <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        @if(session('company_login'))
            <div class="alert alert-success">{{ translate(key: 'Head office login made. They sign in at {url} with {login} and the password you set. Give it to them yourself.', replace: session('company_login')) }}</div>
        @endif

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0">{{ translate('Branches') }}</h5></div>
                    <div class="table-responsive">
                        <table class="table table-borderless align-middle mb-0">
                            <thead class="table-light"><tr><th>{{ translate('Branch') }}</th><th>{{ translate('Region') }}</th><th>{{ translate('Status') }}</th><th></th></tr></thead>
                            <tbody>
                            @forelse($branches as $b)
                                <tr>
                                    <td><div class="fw-semibold">{{ $b->displayName() }}</div><div class="fs-12 text-muted">{{ $b->name }}</div></td>
                                    <td>{{ CameroonRegions::label($b->region) }}</td>
                                    <td>{{ $b->status }}</td>
                                    <td class="text-end text-nowrap">
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.bus.show', $b->id) }}">{{ translate('Open') }}</a>
                                        <form method="POST" action="{{ route('admin.bus.companies.unlink', [$company->id, $b->id]) }}" class="d-inline" onsubmit="return confirm(@js(translate('Remove this branch from the company? Its buses go with it.')))">
                                            @csrf<button class="btn btn-sm btn-outline-danger">{{ translate('Remove') }}</button></form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted">{{ translate('No branch yet. Link the verified agencies of this company below.') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="card-body border-top">
                        <form method="POST" action="{{ route('admin.bus.companies.link', $company->id) }}" class="row g-2 align-items-end">
                            @csrf
                            <div class="col-md-5"><label class="form-label">{{ translate('Agency to add as a branch') }}</label>
                                <select name="agency_id" class="form-select" required>
                                    <option value="">{{ translate('Choose') }}</option>
                                    @foreach($free as $a)<option value="{{ $a->id }}">{{ $a->name }} · {{ $a->origin_city }}</option>@endforeach
                                </select></div>
                            <div class="col-md-3"><label class="form-label">{{ translate('Branch name') }}</label>
                                <input name="branch_label" class="form-control" maxlength="80" placeholder="{{ translate('The city, by default') }}"></div>
                            <div class="col-md-2"><label class="form-label">{{ translate('Region') }}</label>
                                <select name="region" class="form-select"><option value="">{{ translate('From the city') }}</option>
                                    @foreach(CameroonRegions::REGIONS as $k => $r)<option value="{{ $k }}">{{ translate($r) }}</option>@endforeach</select></div>
                            <div class="col-md-2"><button class="btn btn-primary w-100">{{ translate('Add') }}</button></div>
                        </form>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0">{{ translate('Head office logins') }}</h5></div>
                    <div class="card-body">
                        @forelse($staff as $s)
                            <div class="d-flex flex-wrap gap-2 align-items-center border-bottom py-2">
                                <div class="flex-grow-1"><b>{{ $s->user?->first_name }}</b> · {{ BusCompanyStaff::roleLabel($s->role) }}
                                    <div class="fs-12 text-muted">{{ $s->user ? AgencyService::loginOf($s->user) : '' }}@if($s->role === BusCompanyStaff::REGIONAL) · {{ $branches->whereIn('id', (array)$s->agency_ids)->map->branchLabel()->implode(', ') }}@endif</div></div>
                                <form method="POST" action="{{ route('admin.bus.companies.staff.toggle', [$company->id, $s->id]) }}">@csrf
                                    <input type="hidden" name="active" value="{{ $s->active ? 0 : 1 }}">
                                    <button class="btn btn-sm {{ $s->active ? 'btn-outline-danger' : 'btn-outline-success' }}">{{ $s->active ? translate('Switch off') : translate('Switch on') }}</button></form>
                            </div>
                        @empty
                            <p class="text-muted">{{ translate('No login yet. Start with the director.') }}</p>
                        @endforelse
                        <form method="POST" action="{{ route('admin.bus.companies.staff', $company->id) }}" class="row g-2 mt-2">
                            @csrf
                            <div class="col-md-4"><label class="form-label">{{ translate('Name') }}</label><input name="name" class="form-control" required maxlength="80" value="{{ old('name') }}"></div>
                            <div class="col-md-4"><label class="form-label">{{ translate('Email (optional)') }}</label><input name="email" type="email" class="form-control" maxlength="120" value="{{ old('email') }}"></div>
                            <div class="col-md-4"><label class="form-label">{{ translate('Role') }}</label>
                                <select name="role" class="form-select">@foreach(BusCompanyStaff::ROLES as $r)<option value="{{ $r }}">{{ BusCompanyStaff::roleLabel($r) }}</option>@endforeach</select></div>
                            <div class="col-md-8"><label class="form-label">{{ translate('Branches (regional manager only)') }}</label>
                                <select name="agency_ids[]" class="form-select" multiple size="3">@foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->branchLabel() }}</option>@endforeach</select></div>
                            <div class="col-md-4"><label class="form-label">{{ translate('Password') }}</label><input name="password" type="password" class="form-control" required minlength="8" autocomplete="new-password"></div>
                            <div class="col-12"><button class="btn btn-primary">{{ translate('Make the login') }}</button></div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0">{{ translate('Company') }}</h5></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.bus.companies.update', $company->id) }}">
                            @csrf
                            <label class="form-label">{{ translate('Name') }}</label><input name="name" class="form-control mb-2" required maxlength="120" value="{{ $company->name }}">
                            <label class="form-label">{{ translate('About') }}</label><textarea name="about" class="form-control mb-2" rows="2" maxlength="1000">{{ $company->about }}</textarea>
                            <label class="form-label">{{ translate('Turnaround time (minutes)') }}</label>
                            <input name="turnaround_minutes" type="number" min="0" max="720" class="form-control mb-1" value="{{ $company->turnaround_minutes }}">
                            <div class="fs-12 text-muted mb-2">{{ translate('How long a bus needs after arriving before it can leave again. E-Ride uses it to stop one bus being on two departures at once.') }}</div>
                            <button class="btn btn-primary">{{ translate('Save') }}</button>
                        </form>
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0">{{ translate('Money and E-Ride bill') }}</h5></div>
                    <div class="card-body">
                        <p class="fs-13 text-muted">{{ translate('The director chooses these in the head office portal. Shown here for E-Ride.') }}</p>
                        <div><b>{{ translate('Passengers pay') }}</b>: {{ $modeLabel($company->money_mode) }}@if($company->collectsMoney()) · {{ $company->fapshi_ok_at ? translate('connected') : translate('not connected') }}@endif</div>
                        <div><b>{{ translate('E-Ride bill') }}</b>: {{ $billLabel($company->bill_mode) }}</div>
                        @if($statements->isNotEmpty())
                            <hr><div class="fw-semibold mb-1">{{ translate('Company statements') }}</div>
                            @foreach($statements as $st)<div class="fs-13">{{ $st->month->translatedFormat('F Y') }} · {{ getCurrencyFormat($st->total) }} · {{ $st->status }}</div>@endforeach
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
