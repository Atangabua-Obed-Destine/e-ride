@extends($accLayout)

@section('title', translate('Staff pay'))

@php
    use Modules\BusManagement\Lib\AccFormat as F;
    $jobs = ['driver' => translate('Driver'), 'loader' => translate('Loader'), 'counter' => translate('Counter agent'), 'manager' => translate('Manager'), 'other' => translate('Other')];
    $active = $employees->where('active', true);
    $money = $moneyAccounts->where('role', '!=', 'counter_cash');
@endphp

@push('head')
    @include('busmanagement::partner.accounts._style')
@endpush

@section('content')
@include('busmanagement::partner.accounts._nav')

<div class="acc-grid mb-3">
    <div class="acc-fig"><div class="k">{{ translate('People on the staff') }}</div><div class="v">{{ $active->count() }}</div></div>
    <div class="acc-fig"><div class="k">{{ translate('Monthly pay (gross)') }}</div><div class="v">{{ F::n($active->sum('base_pay')) }}</div></div>
    <div class="acc-fig"><div class="k">{{ translate('Owed to CNPS') }}</div><div class="v">{{ F::n($owed['cnps']) }}</div></div>
    <div class="acc-fig"><div class="k">{{ translate('Owed to the State') }}</div><div class="v">{{ F::n($owed['state']) }}</div></div>
    @if($vatOn)<div class="acc-fig"><div class="k">{{ translate('VAT to pay') }}</div><div class="v">{{ F::n($owed['vat']) }}</div></div>@endif
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <form class="acc-card acc-form" data-acc-action="payroll" data-acc-title="{{ translate('Payroll') }}">
            <h2 class="acc-h">{{ translate('Prepare the payroll') }}</h2>
            <p class="b-hint">{{ translate('Each person is paid their monthly pay, plus any extra this month. CNPS, IRPP, CAC, CFC, CRTV and TDL are worked out for you; you see every payslip before anything is posted.') }}</p>
            <div class="row g-2 mb-2">
                <div class="col-sm-6"><label class="form-label" for="pr_month">{{ translate('Month') }}</label>
                    <input type="month" class="form-control" id="pr_month" name="month" value="{{ today()->format('Y-m') }}" max="{{ today()->format('Y-m') }}" required></div>
                <div class="col-sm-6"><label class="form-label" for="pr_date">{{ translate('Date of the payroll') }}</label>
                    <input type="date" class="form-control" id="pr_date" name="date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required></div>
            </div>
            @if($active->isEmpty())
                <div class="acc-empty">{{ translate('Add the people you pay first (below).') }}</div>
            @else
                <div class="acc-scroll"><table class="acc-t">
                    <thead><tr><th></th><th>{{ translate('Person') }}</th><th class="n">{{ translate('Monthly pay') }}</th><th class="n">{{ translate('Extra this month') }}</th></tr></thead>
                    <tbody>
                    @foreach($active as $e)
                        <tr>
                            <td><input type="hidden" name="staff[{{ $e->id }}][include]" value="0"><input type="checkbox" class="form-check-input" name="staff[{{ $e->id }}][include]" value="1" checked aria-label="{{ $e->name }}"></td>
                            <td>{{ $e->name }}<div class="acc-tag">{{ $e->jobLabel() }}</div></td>
                            <td class="n">{{ F::n($e->base_pay) }}</td>
                            <td class="n"><input type="number" class="form-control form-control-sm text-end" name="staff[{{ $e->id }}][extra]" min="0" step="1" placeholder="0" style="width: 130px; margin-left: auto"></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
                <button type="submit" class="btn btn-primary mt-3">{{ translate('Check the payslips') }}</button>
            @endif
        </form>

        <div class="acc-card">
            <h2 class="acc-h">{{ translate('Payrolls') }}</h2>
            <div class="acc-scroll"><table class="acc-t">
                <thead><tr><th>{{ translate('Month') }}</th><th>{{ translate('Number') }}</th><th class="n">{{ translate('People') }}</th><th class="n">{{ translate('Gross') }}</th><th class="n">{{ translate('Net') }}</th><th></th></tr></thead>
                <tbody>
                @forelse($payrolls as $p)
                    <tr class="{{ $p->status === 'void' ? 'void' : '' }}">
                        <td>{{ \Illuminate\Support\Carbon::createFromFormat('Y-m-d', ($p->lines['month'] ?? $p->date->format('Y-m')) . '-01')->translatedFormat('F Y') }}</td>
                        <td><a href="{{ route($acc . '.document', $p->id) }}">{{ $p->number }}</a></td>
                        <td class="n">{{ $p->lines['people'] ?? '' }}</td>
                        <td class="n">{{ F::n($p->amount) }}</td>
                        <td class="n">{{ F::n($p->lines['net'] ?? 0) }}</td>
                        <td>@if($p->status === 'void')<span class="b-tag mute">{{ translate('Void') }}</span>@elseif($p->owed > 0)<span class="b-tag warn">{{ translate('Staff not paid yet') }}</span>@else<span class="b-tag ok">{{ translate('Staff paid') }}</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="acc-empty">{{ translate('No payroll yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="acc-card">
            <h2 class="acc-h">{{ translate('Pay CNPS, the State or VAT') }}</h2>
            <p class="b-hint">{{ translate('What the payrolls withheld, and the employer charges, are owed until you pay them. Type the amount on the receipt.') }}</p>
            @foreach(array_filter(['cnps' => translate('CNPS (social security)'), 'state' => translate('Taxes withheld for the State'), 'vat' => $vatOn ? translate('VAT') : null]) as $to => $label)
                <form class="acc-form border-top pt-2 mt-2" data-acc-action="remit" data-acc-title="{{ translate('Pay') }} · {{ $label }}">
                    <input type="hidden" name="to" value="{{ $to }}">
                    <div class="d-flex justify-content-between align-items-baseline"><b>{{ $label }}</b><span class="acc-tag">{{ translate('Owed') }} {{ F::n($owed[$to]) }}</span></div>
                    @if($owed[$to] > 0.5)
                        <div class="row g-2 mt-1">
                            <div class="col-6"><input type="number" class="form-control form-control-sm" name="amount" min="1" max="{{ (int)ceil($owed[$to]) }}" value="{{ (int)round($owed[$to]) }}" step="1" required aria-label="{{ translate('Amount') }}"></div>
                            <div class="col-6"><input type="date" class="form-control form-control-sm" name="date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required aria-label="{{ translate('Date') }}"></div>
                            <div class="col-6"><select class="form-select form-select-sm" name="money_account_id" required aria-label="{{ translate('Paid from') }}"><option value="">{{ translate('Paid from') }}</option>
                                @foreach($money as $ma)<option value="{{ $ma->id }}">{{ $ma->displayName() }}</option>@endforeach</select></div>
                            <div class="col-6"><input type="text" class="form-control form-control-sm" name="reference" maxlength="100" placeholder="{{ translate('Receipt number') }}" aria-label="{{ translate('Receipt number') }}"></div>
                        </div>
                        <button type="submit" class="btn btn-soft btn-sm mt-2">{{ translate('Check and pay') }}</button>
                    @endif
                </form>
            @endforeach
        </div>

        @if($vatOn)
            <form class="acc-card acc-form" data-acc-action="vatreturn" data-acc-title="{{ translate('VAT return') }}">
                <h2 class="acc-h">{{ translate('VAT return of a month') }}</h2>
                <p class="b-hint">{{ translate('The VAT collected on sales less the VAT on purchases of the month moves to VAT to pay.') }}</p>
                <div class="row g-2">
                    <div class="col-6"><input type="month" class="form-control" name="month" value="{{ today()->subMonthNoOverflow()->format('Y-m') }}" max="{{ today()->subMonthNoOverflow()->format('Y-m') }}" required aria-label="{{ translate('Month') }}"></div>
                    <div class="col-6"><input type="date" class="form-control" name="date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required aria-label="{{ translate('Date') }}"></div>
                </div>
                <button type="submit" class="btn btn-soft mt-2">{{ translate('Check') }}</button>
            </form>
        @endif

        @if($remittances->isNotEmpty())
            <div class="acc-card">
                <h2 class="acc-h">{{ translate('Paid and filed') }}</h2>
                <table class="acc-t"><tbody>
                @foreach($remittances as $d)
                    <tr class="{{ $d->status === 'void' ? 'void' : '' }}"><td class="text-nowrap">{{ $d->date->format('d/m/Y') }}</td><td><a href="{{ route($acc . '.document', $d->id) }}">{{ $d->number }}</a></td>
                        <td>{{ $d->type === 'vat_return' ? translate('VAT return') : match ($d->lines['to'] ?? '') { 'cnps' => 'CNPS', 'vat' => translate('VAT'), default => translate('State') } }}</td><td class="n">{{ F::n($d->amount) }}</td></tr>
                @endforeach
                </tbody></table>
            </div>
        @endif
    </div>
</div>

<div class="acc-card mt-3">
    <h2 class="acc-h">{{ translate('The people you pay') }}</h2>
    <div class="acc-scroll"><table class="acc-t">
        <thead><tr><th>{{ translate('Name') }}</th><th>{{ translate('Job') }}</th><th>{{ translate('CNPS number') }}</th><th class="n">{{ translate('Monthly pay') }}</th><th></th></tr></thead>
        <tbody>
        @forelse($employees as $e)
            <tr class="{{ $e->active ? '' : 'void' }}"><td>{{ $e->name }}</td><td>{{ $e->jobLabel() }}</td><td>{{ $e->cnps_number }}</td><td class="n">{{ F::n($e->base_pay) }}</td>
                <td class="text-end"><button type="button" class="btn btn-light btn-sm" data-bs-toggle="collapse" data-bs-target="#emp-{{ $e->id }}" aria-label="{{ translate('Edit') }}"><i class="bi bi-pencil"></i></button></td></tr>
            <tr class="collapse" id="emp-{{ $e->id }}"><td colspan="5">
                <form method="POST" action="{{ route($acc . '.employee', $e->id) }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-sm-3"><input type="text" class="form-control form-control-sm" name="name" value="{{ $e->name }}" required maxlength="120" aria-label="{{ translate('Name') }}"></div>
                    <div class="col-sm-2"><select class="form-select form-select-sm" name="job" aria-label="{{ translate('Job') }}">@foreach($jobs as $k => $l)<option value="{{ $k }}" @selected($e->job === $k)>{{ $l }}</option>@endforeach</select></div>
                    <div class="col-sm-2"><input type="number" class="form-control form-control-sm" name="base_pay" value="{{ (int)$e->base_pay }}" min="0" step="1" required aria-label="{{ translate('Monthly pay') }}"></div>
                    <div class="col-sm-2"><input type="text" class="form-control form-control-sm" name="cnps_number" value="{{ $e->cnps_number }}" maxlength="40" placeholder="{{ translate('CNPS number') }}" aria-label="{{ translate('CNPS number') }}"></div>
                    <div class="col-sm-1"><input type="hidden" name="active" value="0"><label class="small"><input type="checkbox" class="form-check-input" name="active" value="1" @checked($e->active)> {{ translate('Paid') }}</label></div>
                    <div class="col-sm-2"><button class="btn btn-dark btn-sm">{{ translate('Save') }}</button></div>
                </form>
            </td></tr>
        @empty
            <tr><td colspan="5" class="acc-empty">{{ translate('Nobody yet.') }}</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <form method="POST" action="{{ route($acc . '.employee') }}" class="row g-2 align-items-end mt-2 acc-form">
        @csrf
        <div class="col-sm-3"><label class="form-label" for="ne_name">{{ translate('Name') }}</label><input type="text" class="form-control" id="ne_name" name="name" required maxlength="120"></div>
        <div class="col-sm-2"><label class="form-label" for="ne_job">{{ translate('Job') }}</label><select class="form-select" id="ne_job" name="job">@foreach($jobs as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
        <div class="col-sm-2"><label class="form-label" for="ne_pay">{{ translate('Monthly pay') }}</label><input type="number" class="form-control" id="ne_pay" name="base_pay" min="0" step="1" required></div>
        <div class="col-sm-2"><label class="form-label" for="ne_cnps">{{ translate('CNPS number') }}</label><input type="text" class="form-control" id="ne_cnps" name="cnps_number" maxlength="40"></div>
        <div class="col-sm-3"><button class="btn btn-dark">{{ translate('Add to the staff') }}</button></div>
    </form>
</div>
@include('busmanagement::partner.accounts._review')
@endsection
