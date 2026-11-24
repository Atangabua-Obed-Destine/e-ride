@extends($accLayout)

@section('title', translate('Buses'))

@php use Modules\BusManagement\Lib\AccFormat as F; @endphp

@push('head')
    @include('busmanagement::partner.accounts._style')
@endpush

@section('content')
@include('busmanagement::partner.accounts._nav')

<div class="acc-card mb-3">
    <h2 class="acc-h">{{ translate('Buses in the books') }}</h2>
    <div class="acc-scroll"><table class="acc-t">
        <thead><tr><th>{{ translate('Bus') }}</th><th>{{ translate('Since') }}</th><th class="n">{{ translate('Value') }}</th><th class="n">{{ translate('Wear each month') }}</th>
            <th class="n">{{ translate('Worn so far') }}</th><th class="n">{{ translate('Value left') }}</th><th>{{ translate('Worn down by') }}</th><th></th></tr></thead>
        <tbody>
        @forelse($assets as $row)
            @php $a = $row['asset']; @endphp
            <tr class="{{ in_array($a->status, ['void'], true) ? 'void' : '' }}">
                <td class="fw-semibold">{{ $a->name }}
                    @if($isCompanyBooks && $a->vehicle?->agency)<div class="acc-tag">{{ translate('Wear charged to') }} {{ $a->vehicle->agency->branchLabel() }}</div>@endif</td>
                <td class="text-nowrap">{{ $a->starts_on->format('d/m/Y') }}</td>
                <td class="n">{{ F::n($a->value) }}</td>
                <td class="n">{{ $a->status === 'active' ? F::n($row['monthly']) : '' }}</td>
                <td class="n">{{ $row['worn'] !== null ? F::n($row['worn']) : '' }}</td>
                <td class="n fw-bold">{{ $row['left'] !== null ? F::n($row['left']) : '' }}</td>
                <td class="text-nowrap">
                    @if($a->status === 'active'){{ $row['ends']->translatedFormat('M Y') }}
                    @elseif($a->status === 'sold')<span class="b-tag mute">{{ translate('Sold') }} {{ $a->disposed_on?->format('d/m/Y') }}</span>
                    @elseif($a->status === 'retired')<span class="b-tag mute">{{ translate('Retired') }} {{ $a->disposed_on?->format('d/m/Y') }}</span>
                    @else<span class="b-tag mute">{{ translate('Void') }}</span>@endif
                </td>
                <td class="text-end text-nowrap">
                    @if($a->document_id)<a class="btn btn-light btn-sm" href="{{ route($acc . '.document', $a->disposal_document_id ?? $a->document_id) }}">{{ translate('Open') }}</a>@endif
                    @if($a->status === 'active')<button type="button" class="btn btn-soft btn-sm" data-bs-toggle="collapse" data-bs-target="#dispose-{{ $a->id }}">{{ translate('Sell or retire') }}</button>@endif
                </td>
            </tr>
            @if($a->status === 'active')
                <tr class="collapse" id="dispose-{{ $a->id }}"><td colspan="8">
                    <form class="acc-form" data-acc-action="dispose" data-acc-title="{{ translate('Sell or retire a bus') }} · {{ $a->name }}">
                        <input type="hidden" name="asset" value="{{ $a->id }}">
                        <div class="row g-2">
                            <div class="col-sm-3"><label class="form-label" for="dp_date_{{ $a->id }}">{{ translate('Date') }}</label>
                                <input type="date" class="form-control" id="dp_date_{{ $a->id }}" name="date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required></div>
                            <div class="col-sm-3"><label class="form-label" for="dp_how_{{ $a->id }}">{{ translate('What happens') }}</label>
                                <select class="form-select" id="dp_how_{{ $a->id }}" name="how" data-how><option value="sold">{{ translate('Sold') }}</option><option value="retired">{{ translate('Retired (scrapped, written off)') }}</option></select></div>
                            <div class="col-sm-3" data-sold><label class="form-label" for="dp_price_{{ $a->id }}">{{ translate('Sale price') }}</label>
                                <input type="number" class="form-control" id="dp_price_{{ $a->id }}" name="price" min="0" step="1"></div>
                            <div class="col-sm-3" data-sold><label class="form-label" for="dp_money_{{ $a->id }}">{{ translate('Received into') }}</label>
                                <select class="form-select" id="dp_money_{{ $a->id }}" name="money_account_id"><option value="">{{ translate('Choose') }}</option>
                                    @foreach($moneyAccounts->where('role', '!=', 'counter_cash') as $ma)<option value="{{ $ma->id }}">{{ $ma->displayName() }}</option>@endforeach</select></div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm mt-2">{{ translate('Check') }}</button>
                    </form>
                </td></tr>
            @endif
        @empty
            <tr><td colspan="8" class="acc-empty">{{ translate('No bus in the books yet.') }}</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <form class="acc-card acc-form" data-acc-action="asset" data-acc-title="{{ translate('Add a bus to the books') }}">
            <h2 class="acc-h">{{ translate('Add a bus to the books') }}</h2>
            <div class="row g-2">
                <div class="col-sm-6"><label class="form-label" for="as_bus">{{ translate('Bus') }}</label>
                    <select class="form-select" id="as_bus" name="vehicle_id" required><option value="">{{ translate('Choose') }}</option>
                        @foreach($buses as $bus)@unless(in_array((string)$bus->id, $inBooks, true))<option value="{{ $bus->id }}">{{ $bus->registration }}{{ $bus->name ? ' · ' . $bus->name : '' }}</option>@endunless @endforeach</select></div>
                <div class="col-sm-6"><label class="form-label" for="as_date">{{ translate('In the books from (wear starts that month)') }}</label>
                    <input type="date" class="form-control" id="as_date" name="date" value="{{ today()->toDateString() }}" min="{{ $books->starts_on->toDateString() }}" max="{{ today()->toDateString() }}" required></div>
                <div class="col-sm-4"><label class="form-label" for="as_value">{{ translate('Value') }} (FCFA)</label>
                    <input type="number" class="form-control" id="as_value" name="value" min="1" step="1" required></div>
                <div class="col-sm-4"><label class="form-label" for="as_years">{{ translate('Years it will still run') }}</label>
                    <input type="number" class="form-control" id="as_years" name="years" min="0.5" max="40" step="0.5" value="5" required></div>
                <div class="col-sm-4"><label class="form-label" for="as_end">{{ translate('Worth at the end (resale or scrap)') }}</label>
                    <input type="number" class="form-control" id="as_end" name="end_value" min="0" step="1" value="0"></div>
                <div class="col-12"><div class="rv-note" id="as_monthly"></div></div>
                <div class="col-12">
                    <div class="seg" role="radiogroup" aria-label="{{ translate('How it came') }}">
                        <input type="radio" name="funded_by" id="as_owner" value="owner" checked><label for="as_owner">{{ translate('Already owned (brought in by the owner)') }}</label>
                        <input type="radio" name="funded_by" id="as_paid" value="paid"><label for="as_paid">{{ translate('Bought and paid now') }}</label>
                        <input type="radio" name="funded_by" id="as_owed" value="owed"><label for="as_owed">{{ translate('Bought, still owed') }}</label>
                    </div>
                </div>
            </div>
            <div class="row g-2 mt-0" data-funded="paid" hidden>
                @include('busmanagement::partner.accounts._money_select', ['label' => translate('Paid from'), 'idp' => 'as', 'required' => false])
            </div>
            <div class="row g-2 mt-0" data-funded="owed" hidden>
                <div class="col-sm-6"><label class="form-label" for="as_owed_to">{{ translate('Owed to') }}</label>
                    <select class="form-select" id="as_owed_to" name="owed_to"><option value="supplier">{{ translate('The seller (a bill to pay)') }}</option><option value="loan">{{ translate('A lender (a loan)') }}</option></select></div>
                <div class="col-sm-6"><label class="form-label" for="as_supplier">{{ translate('Seller or lender') }}</label>
                    <input type="text" class="form-control" id="as_supplier" name="supplier" maxlength="120"></div>
            </div>
            <div class="row g-2 mt-0">
                <div class="col-sm-6"><label class="form-label" for="as_ref">{{ translate('Reference (invoice, valuation)') }}</label>
                    <input type="text" class="form-control" id="as_ref" name="reference" maxlength="100"></div>
                <div class="col-sm-6"><label class="form-label" for="as_note">{{ translate('Note (optional)') }}</label>
                    <input type="text" class="form-control" id="as_note" name="note" maxlength="500"></div>
            </div>
            <button type="submit" class="btn btn-primary mt-3">{{ translate('Check and add') }}</button>
        </form>
    </div>
    <div class="col-lg-5">
        <div class="acc-card">
            <h2 class="acc-h">{{ translate('How to choose the value') }}</h2>
            <ul class="b-hint ps-3 mb-0" style="font-size: 13.5px">
                <li class="mb-2">{{ translate('A bus bought recently: the price paid, with registration and first fitting out.') }}</li>
                <li class="mb-2">{{ translate('An older bus, or a price nobody remembers: what it would sell for today. Ask a dealer or another agency.') }}</li>
                <li class="mb-2">{{ translate('Years: how long it will still run on your roads. For a new coach, 5 years is a common start: your accountant confirms the rate the tax office accepts.') }}</li>
                <li class="mb-2">{{ translate('Worth at the end: what you expect to sell it for, or its scrap value. It can be zero.') }}</li>
                <li>{{ translate('Each month, (value - worth at the end) divided by the months is posted as the bus wear. A better figure later is corrected with a journal entry.') }}</li>
            </ul>
        </div>
    </div>
</div>
@include('busmanagement::partner.accounts._review')
@endsection

@push('script')
<script>
    (function () {
        var f = document.querySelector('form[data-acc-action=asset]');
        function sync() {
            var v = (f.querySelector('input[name=funded_by]:checked') || {}).value;
            f.querySelector('[data-funded=paid]').hidden = v !== 'paid';
            f.querySelector('[data-funded=owed]').hidden = v !== 'owed';
            f.querySelector('[data-funded=paid] [data-money-select]').required = v === 'paid';
            var val = +f.querySelector('[name=value]').value || 0, end = +f.querySelector('[name=end_value]').value || 0, yrs = +f.querySelector('[name=years]').value || 0;
            var m = Math.round(yrs * 12);
            document.getElementById('as_monthly').textContent = val > 0 && m > 0 ? @json(translate('Wear each month')) + ': ' + Math.round((val - end) / m).toLocaleString('fr-FR') + ' FCFA' : '';
        }
        f.addEventListener('change', sync);
        f.addEventListener('input', sync);
        sync();
        document.querySelectorAll('[data-how]').forEach(function (s) {
            function t() { s.closest('form').querySelectorAll('[data-sold]').forEach(function (e) { e.hidden = s.value !== 'sold'; }); }
            s.addEventListener('change', t); t();
        });
    })();
</script>
@endpush
