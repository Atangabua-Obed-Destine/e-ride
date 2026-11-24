@extends('busmanagement::company.layout')

@section('title', translate('Money'))

@php
    use Modules\BusManagement\Entities\BusCompany;
    $isDirector = $busCompanyStaff->isDirector();
    $m = fn($n) => number_format((float)$n, 0, '.', ',') . ' FCFA';
@endphp

@section('content')
<h1 class="fs-5 fw-bold mb-3">{{ translate('Money and the E-Ride bill') }}</h1>
@if(session('fapshi_error'))<div class="alert alert-danger">{{ session('fapshi_error') }}</div>@endif

<div class="row g-3">
    <div class="col-lg-6">
        <form method="POST" action="{{ route('bus-company.money.modes') }}" class="b-card p-3 mb-3">
            @csrf
            <fieldset @disabled(!$isDirector)>
            <h2 class="b-h mb-2">{{ translate('Where passengers pay') }}</h2>
            <label class="form-check mb-1"><input class="form-check-input" type="radio" name="money_mode" value="branch" @checked(!$busCompany->collectsMoney())>
                <b>{{ translate('Each branch into its own Fapshi account') }}</b><div class="b-hint">{{ translate('Every branch connects its own account in its Payments page.') }}</div></label>
            <label class="form-check mb-3"><input class="form-check-input" type="radio" name="money_mode" value="company" @checked($busCompany->collectsMoney())>
                <b>{{ translate('All branches into one company account') }}</b><div class="b-hint">{{ translate('Connect the company account below first. Branches then need no account of their own.') }}</div></label>
            <h2 class="b-h mb-2">{{ translate('How E-Ride bills you') }}</h2>
            <label class="form-check mb-1"><input class="form-check-input" type="radio" name="bill_mode" value="branch" @checked(!$busCompany->billedTogether())>
                <b>{{ translate('A statement for each branch') }}</b><div class="b-hint">{{ translate('Each branch manager pays its own.') }}</div></label>
            <label class="form-check mb-3"><input class="form-check-input" type="radio" name="bill_mode" value="company" @checked($busCompany->billedTogether())>
                <b>{{ translate('One statement for the company') }}</b><div class="b-hint">{{ translate('From next month: one total, with the detail per branch. You pay it here.') }}</div></label>
            @if($isDirector)<button class="btn btn-primary">{{ translate('Save') }}</button>@endif
            </fieldset>
        </form>

        @if($isDirector)
        <form method="POST" action="{{ route('bus-company.money.fapshi') }}" class="b-card p-3 mb-3">
            @csrf
            <h2 class="b-h mb-1">{{ translate('Company Fapshi account') }}</h2>
            <div class="mb-2">@if($busCompany->fapshi_ok_at)<span class="b-tag ok">{{ translate('Connected') }} {{ $busCompany->fapshi_ok_at->format('d/m H:i') }}</span>@else<span class="b-tag warn">{{ translate('Not connected') }}</span>@endif</div>
            <div class="row g-2">
                <div class="col-12"><label class="form-label small fw-semibold" for="api_user">{{ translate('API user') }}</label><input id="api_user" name="api_user" class="form-control" required value="{{ $fapshi['api_user'] ?? '' }}" autocomplete="off"></div>
                <div class="col-12"><label class="form-label small fw-semibold" for="api_key">{{ translate('API key') }}</label><input id="api_key" name="api_key" type="password" class="form-control" placeholder="{{ !empty($fapshi['api_key']) ? translate('Saved. Leave empty to keep it.') : '' }}" autocomplete="off"></div>
                <div class="col-12"><label class="form-label small fw-semibold" for="webhook_secret">{{ translate('Webhook secret') }}</label><input id="webhook_secret" name="webhook_secret" type="password" class="form-control" placeholder="{{ !empty($fapshi['webhook_secret']) ? translate('Saved. Leave empty to keep it.') : '' }}" autocomplete="off"></div>
                <div class="col-6"><label class="form-label small fw-semibold" for="mode">{{ translate('Mode') }}</label>
                    <select id="mode" name="mode" class="form-select"><option value="live" @selected(($fapshi['mode'] ?? 'live') === 'live')>{{ translate('Live') }}</option><option value="test" @selected(($fapshi['mode'] ?? '') === 'test')>{{ translate('Test') }}</option></select></div>
                <div class="col-12"><div class="b-hint">{{ translate('In your Fapshi dashboard, set the webhook to') }} <code>{{ $webhookUrl }}</code></div></div>
            </div>
            <button class="btn btn-dark mt-2">{{ translate('Save and test') }}</button>
        </form>
        @endif
    </div>

    <div class="col-lg-6">
        @if($busCompany->billedTogether() || $companyStatements->isNotEmpty())
        <section class="b-card p-3 mb-3">
            <h2 class="b-h mb-2">{{ translate('Company statements') }}</h2>
            @forelse($companyStatements as $s)
                <div class="d-flex flex-wrap gap-2 align-items-center border-bottom py-2">
                    <div class="flex-grow-1"><b>{{ $s->month->translatedFormat('F Y') }}</b> · {{ $m($s->total) }}<div class="b-hint">{{ translate('Due') }} {{ $s->due_on?->translatedFormat('j M') }}</div></div>
                    @if($s->status === 'paid')<span class="b-tag ok">{{ translate('Paid') }}</span>
                    @elseif($isDirector)<form method="POST" action="{{ route('bus-company.money.pay', $s->id) }}" class="m-0">@csrf<button class="btn btn-primary btn-sm">{{ translate('Pay with Mobile Money') }}</button></form>
                    @else<span class="b-tag warn">{{ translate('To pay') }}</span>@endif
                </div>
            @empty
                <div class="b-hint">{{ translate('The first company statement comes on the 1st of next month.') }}</div>
            @endforelse
        </section>
        @endif
        <section class="b-card p-3">
            <h2 class="b-h mb-2">{{ translate('Branch statements') }}</h2>
            <div class="ho-scroll"><table class="ho-table">
                <thead><tr><th>{{ translate('Month') }}</th><th>{{ translate('Branch') }}</th><th class="n">{{ translate('Commission') }}</th><th>{{ translate('Status') }}</th></tr></thead>
                <tbody>
                @forelse($branchStatements as $s)
                    <tr><td>{{ $s->month->translatedFormat('M Y') }}</td><td>{{ $s->agency?->branchLabel() }}</td><td class="n">{{ $m($s->total) }}</td>
                        <td>@if($s->status === 'paid')<span class="b-tag ok">{{ translate('Paid') }}</span>@else<span class="b-tag warn">{{ $s->company_statement_id ? translate('In the company statement') : translate('To pay') }}</span>@endif</td></tr>
                @empty
                    <tr><td colspan="4" class="b-hint">{{ translate('No statement yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </section>
    </div>
</div>
@endsection
