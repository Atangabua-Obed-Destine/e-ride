@extends($accLayout)

@section('title', translate('Settings'))

@php
    use Modules\BusManagement\Lib\AccFormat as F;
    use Modules\BusManagement\Entities\BusStaff;
    $payFrom = ['mtn' => 'MTN Mobile Money', 'orange' => 'Orange Money', 'bank' => 'Bank account', 'safe' => 'Office safe', 'fapshi' => 'Fapshi balance (app payments)'];
    $isManager = $canReopen;
@endphp

@push('head')
    @include('busmanagement::partner.accounts._style')
@endpush

@section('content')
@include('busmanagement::partner.accounts._nav')

<div class="row g-3">
    <div class="col-lg-6">
        <div class="acc-card">
            <h2 class="acc-h">{{ translate('Months') }}</h2>
            <p class="b-hint">{{ $isCompanyBooks ? translate('Close a month once its reconciliations are done and the bus wear is posted (on the 1st). Nothing can be posted in a closed month.') : translate('Close a month once its agent cash counts and reconciliations are done. Nothing can be posted in a closed month: tickets and refunds that come late are posted in the next open month.') }}</p>
            <table class="acc-t"><tbody>
            @foreach($months as $m)
                <tr>
                    <td class="fw-bold">{{ $m['month']->translatedFormat('F Y') }}</td>
                    <td>
                        @if($m['row'] && $m['row']->closed_at)<span class="b-tag mute"><i class="bi bi-lock"></i> {{ translate('Closed') }} {{ \Illuminate\Support\Carbon::parse($m['row']->closed_at)->format('d/m/Y') }}</span>
                        @elseif($m['over'])<span class="b-tag warn">{{ translate('Over, still open') }}</span>
                        @else<span class="b-tag ok">{{ translate('Current') }}</span>@endif
                        @if($m['row'] && $m['row']->reopen_reason)<div class="acc-tag">{{ translate('Reopened') }}: {{ $m['row']->reopen_reason }}</div>@endif
                    </td>
                    <td class="text-end">
                        @if($m['over'] && !($m['row'] && $m['row']->closed_at))
                            <form class="d-inline" data-acc-action="close" data-acc-title="{{ translate('Close the month') }} · {{ $m['month']->translatedFormat('F Y') }}">
                                <input type="hidden" name="month" value="{{ $m['month']->toDateString() }}">
                                <button class="btn btn-soft btn-sm">{{ translate('Close the month') }}</button>
                            </form>
                        @elseif($m['row'] && $m['row']->closed_at && $isManager)
                            <button type="button" class="btn btn-light btn-sm" data-bs-toggle="collapse" data-bs-target="#reopen-{{ $m['month']->format('Ym') }}">{{ translate('Reopen') }}</button>
                        @endif
                    </td>
                </tr>
                @if($m['row'] && $m['row']->closed_at && $isManager)
                    <tr class="collapse" id="reopen-{{ $m['month']->format('Ym') }}"><td colspan="3">
                        <form class="d-flex gap-2" data-acc-action="reopen" data-acc-title="{{ translate('Reopen') }} {{ $m['month']->translatedFormat('F Y') }}">
                            <input type="hidden" name="month" value="{{ $m['month']->toDateString() }}">
                            <input type="text" class="form-control form-control-sm" name="reason" minlength="5" maxlength="300" required placeholder="{{ translate('Why it must be reopened') }}" aria-label="{{ translate('Why it must be reopened') }}">
                            <button class="btn btn-soft btn-sm">{{ translate('Check') }}</button>
                        </form>
                    </td></tr>
                @endif
            @endforeach
            </tbody></table>
        </div>

        <div class="acc-card">
            <h2 class="acc-h">{{ translate('Years') }}</h2>
            <p class="b-hint">{{ translate('A year (1 January to 31 December) closes once all its months are closed. Its income and spending go to the Result of the year, and nothing more can be dated in it.') }}
                @if($isCompanyBooks) {{ translate('The head office closes it for itself and every branch together.') }}@endif</p>
            <table class="acc-t"><tbody>
            @foreach($years as $y)
                <tr>
                    <td class="fw-bold">{{ $y['year'] }}</td>
                    <td>
                        @if($y['row'] && $y['row']->closed_at)<span class="b-tag mute"><i class="bi bi-lock"></i> {{ translate('Closed') }}</span> <span class="acc-tag">{{ translate('Result') }} {{ F::n($y['row']->result) }}</span>
                        @elseif($y['over'])<span class="b-tag warn">{{ translate('Over, still open') }}</span>
                        @else<span class="b-tag ok">{{ translate('Current') }}</span>@endif
                        @if($y['row'] && $y['row']->reopen_reason)<div class="acc-tag">{{ translate('Reopened') }}: {{ $y['row']->reopen_reason }}</div>@endif
                    </td>
                    <td class="text-end">
                        @if($headOfficeCloses)
                            <span class="acc-tag">{{ translate('Closed by the head office') }}</span>
                        @elseif($y['over'] && !($y['row'] && $y['row']->closed_at))
                            <form class="d-inline" data-acc-action="closeyear" data-acc-title="{{ translate('Close the year') }} {{ $y['year'] }}">
                                <input type="hidden" name="year" value="{{ $y['year'] }}">
                                <button class="btn btn-soft btn-sm">{{ translate('Close the year') }}</button>
                            </form>
                        @elseif($y['row'] && $y['row']->closed_at && $isManager)
                            <form class="d-flex gap-2" data-acc-action="reopenyear" data-acc-title="{{ translate('Reopen the year') }} {{ $y['year'] }}">
                                <input type="hidden" name="year" value="{{ $y['year'] }}">
                                <input type="text" class="form-control form-control-sm" name="reason" minlength="5" maxlength="300" required placeholder="{{ translate('Why it must be reopened') }}" aria-label="{{ translate('Why it must be reopened') }}">
                                <button class="btn btn-light btn-sm">{{ translate('Reopen') }}</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody></table>
        </div>

        <form class="acc-card acc-form" method="POST" action="{{ route($acc . '.settings.save') }}">
            @csrf
            <input type="hidden" name="eride_from" value="{{ $books->setting('eride_from', 'mtn') }}">
            @unless($isCompanyBooks)<input type="hidden" name="refunds_from" value="{{ $books->setting('refunds_from', 'mtn') }}">@endunless
            <h2 class="acc-h">{{ translate('VAT') }}</h2>
            <p class="b-hint">{{ translate('Only if these books are registered for VAT. Ask your tax adviser whether your tickets carry VAT.') }}</p>
            <input type="hidden" name="vat_registered" value="0">
            <label class="small d-block mb-2"><input type="checkbox" class="form-check-input" name="vat_registered" value="1" @checked($books->setting('vat_registered'))> {{ translate('Registered for VAT') }}</label>
            @unless($isCompanyBooks)
                <input type="hidden" name="vat_on_tickets" value="0">
                <label class="small d-block mb-2"><input type="checkbox" class="form-check-input" name="vat_on_tickets" value="1" @checked($books->setting('vat_on_tickets'))> {{ translate('Ticket prices include VAT') }}</label>
            @endunless
            <div class="row g-2"><div class="col-sm-4"><label class="form-label" for="st_vat">{{ translate('VAT rate') }} (%)</label>
                <input type="number" class="form-control" id="st_vat" name="vat_rate" min="0" max="50" step="0.01" value="{{ $books->setting('vat_rate', 19.25) }}"></div></div>
            <h2 class="acc-h mt-4">{{ translate('Employer rates for staff pay') }} (%)</h2>
            <p class="b-hint">{{ translate('The work accident rate depends on your risk group on the CNPS registration. Confirm every rate with your accountant.') }}</p>
            @php $rates = array_merge(\Modules\BusManagement\Lib\CameroonPayroll::EMPLOYER_DEFAULTS, (array)$books->setting('pay_rates', [])); @endphp
            <div class="row g-2">
                @foreach(['pension' => translate('CNPS pension'), 'family' => translate('CNPS family allowances'), 'accident' => translate('CNPS work accident'), 'cfc' => translate('CFC (housing fund)'), 'fne' => translate('FNE (employment fund)')] as $k => $l)
                    <div class="col-sm-4"><label class="form-label" for="rate_{{ $k }}">{{ $l }}</label>
                        <input type="number" class="form-control" id="rate_{{ $k }}" name="pay_rates[{{ $k }}]" min="0" max="30" step="0.01" value="{{ $rates[$k] }}"></div>
                @endforeach
            </div>
            <button class="btn btn-dark btn-sm mt-3">{{ translate('Save') }}</button>
        </form>

        <form class="acc-card acc-form" method="POST" action="{{ route($acc . '.settings.save') }}">
            @csrf
            <h2 class="acc-h">{{ translate('Which account pays') }}</h2>
            <div class="row g-2">
                @unless($isCompanyBooks)
                <div class="col-sm-6"><label class="form-label" for="st_ref">{{ translate('Refunds to passengers are sent from') }}</label>
                    <select class="form-select" id="st_ref" name="refunds_from">@foreach($payFrom as $k => $l)<option value="{{ $k }}" @selected($books->setting('refunds_from', 'mtn') === $k)>{{ translate($l) }}</option>@endforeach</select></div>
                @endunless
                <div class="col-sm-6"><label class="form-label" for="st_er">{{ translate('E-Ride statements are paid from') }}</label>
                    <select class="form-select" id="st_er" name="eride_from">@foreach($payFrom as $k => $l)<option value="{{ $k }}" @selected($books->setting('eride_from', 'mtn') === $k)>{{ translate($l) }}</option>@endforeach</select></div>
            </div>
            <div class="b-hint mt-2">{{ translate('Used for the refunds and statement payments posted from now on.') }}</div>
            <button class="btn btn-dark btn-sm mt-3">{{ translate('Save') }}</button>
        </form>
    </div>

    <div class="col-lg-6">
        <div class="acc-card">
            <h2 class="acc-h">{{ translate('Chart of accounts') }}</h2>
            <div class="acc-scroll"><table class="acc-t">
                <thead><tr><th>{{ translate('Account') }}</th><th class="n">{{ translate('Balance') }}</th><th></th></tr></thead>
                <tbody>
                @foreach($accounts as $a)
                    <tr class="{{ $a->active ? '' : 'void' }}">
                        <td><span class="fw-semibold">{{ $a->code }}</span> {{ $a->displayName() }}
                            @if($a->role)<span class="acc-tag">· {{ translate('automatic') }}</span>@endif
                            @if($a->money)<span class="acc-tag">· {{ translate('money') }}</span>@endif</td>
                        <td class="n">{{ F::n($balances[(string)$a->id] ?? 0, true) }}</td>
                        <td class="text-end"><button type="button" class="btn btn-light btn-sm" data-bs-toggle="collapse" data-bs-target="#acc-{{ $a->code }}" aria-label="{{ translate('Edit') }}"><i class="bi bi-pencil"></i></button></td>
                    </tr>
                    <tr class="collapse" id="acc-{{ $a->code }}"><td colspan="3">
                        <form method="POST" action="{{ route($acc . '.account', $a->id) }}" class="d-flex flex-wrap gap-2 align-items-center">
                            @csrf
                            <input type="text" class="form-control form-control-sm" name="name" value="{{ $a->name }}" maxlength="120" required style="max-width: 320px" aria-label="{{ translate('Name') }}">
                            @unless($a->role)
                                <input type="hidden" name="active" value="0">
                                <label class="small"><input type="checkbox" class="form-check-input" name="active" value="1" @checked($a->active)> {{ translate('In use') }}</label>
                            @endunless
                            <button class="btn btn-dark btn-sm">{{ translate('Save') }}</button>
                        </form>
                    </td></tr>
                @endforeach
                </tbody>
            </table></div>
        </div>
        <form class="acc-card acc-form" method="POST" action="{{ route($acc . '.account') }}">
            @csrf
            <h2 class="acc-h">{{ translate('Add an account') }}</h2>
            <div class="row g-2">
                <div class="col-sm-3"><label class="form-label" for="na_code">{{ translate('Number') }}</label>
                    <input type="text" class="form-control" id="na_code" name="code" pattern="[1-8][0-9]{2,7}" maxlength="8" required placeholder="6054"></div>
                <div class="col-sm-9"><label class="form-label" for="na_name">{{ translate('Name') }}</label>
                    <input type="text" class="form-control" id="na_name" name="name" maxlength="120" required></div>
                <div class="col-sm-6"><label class="form-label" for="na_type">{{ translate('For class 4 and 8') }}</label>
                    <select class="form-select" id="na_type" name="type"><option value="liability">{{ translate('Something the branch owes') }}</option><option value="asset">{{ translate('Something the branch has') }}</option></select></div>
                <div class="col-sm-6 d-flex align-items-end"><label class="small"><input type="checkbox" class="form-check-input" name="money" value="1"> {{ translate('A place money is kept (class 5)') }}</label></div>
            </div>
            <div class="b-hint mt-2">{{ translate('Class 6 accounts appear as spending categories, class 7 as income categories.') }}</div>
            <button class="btn btn-dark btn-sm mt-3">{{ translate('Add') }}</button>
        </form>
    </div>
</div>
@include('busmanagement::partner.accounts._review')
@endsection
