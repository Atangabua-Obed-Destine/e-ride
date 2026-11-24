@extends($accLayout)

@section('title', translate('Accounts'))

@push('head')
    @include('busmanagement::partner.accounts._style')
@endpush

@php
    $payFrom = ['mtn' => 'MTN Mobile Money', 'orange' => 'Orange Money', 'bank' => 'Bank account', 'safe' => 'Office safe', 'fapshi' => 'Fapshi balance (app payments)'];
    $moneyFields = [['safe', 'Office safe'], ['bank', 'Bank account'], ['mtn', 'MTN Mobile Money'], ['orange', 'Orange Money'], ['fapshi', 'Fapshi balance (app payments)']];
@endphp

@section('content')
<h1 class="fs-5 fw-bold mb-1">{{ $isCompanyBooks ? translate('Head office books') : translate('Accounts') }}</h1>
<p class="b-hint mb-3" style="max-width: 760px">
    @if($isCompanyBooks)
        {{ translate('The head office keeps its own books beside its branches: the buses and their wear, the company bank and Fapshi account, loans, and what the head office pays itself. Added to the branch books, they give the company as a whole.') }}
    @else
        {{ translate('The books of this branch, kept for you: every ticket paid, every refund and every trip that leaves is posted on its own. You add what the system cannot know: spending, other income, money moved, and the cash each agent hands in.') }}
    @endif
</p>

<div class="row g-3">
    <div class="col-lg-7">
        <form class="acc-card acc-form" data-acc-action="open" data-acc-title="{{ translate('Open the books') }}">
            <h2 class="acc-h">{{ translate('Open the books') }}</h2>
            <div class="row g-3">
                <div class="col-sm-6">
                    <label class="form-label" for="starts_on">{{ translate('Start date') }}</label>
                    <input type="date" class="form-control" id="starts_on" name="starts_on" value="{{ today()->toDateString() }}" min="{{ today()->subDays(92)->toDateString() }}" max="{{ today()->toDateString() }}" required>
                    <div class="b-hint mt-1">{{ $isCompanyBooks ? translate('From this day on, everything the head office does is posted. Today is simplest.') : translate('Tickets and refunds from this day on are posted. Today is simplest.') }}</div>
                </div>
            </div>

            <h3 class="fs-6 fw-bold mt-4 mb-1">{{ $isCompanyBooks ? translate('Money the head office has on the start date') : translate('Money the branch has on the start date') }}</h3>
            @unless($isCompanyBooks)<p class="b-hint mb-2">{{ translate('Count every agent cash first and put it in the safe figure: from the start, each agent cash is followed on its own.') }}</p>@endunless
            <div class="row g-3">
                @foreach($moneyFields as [$role, $label])
                    <div class="col-sm-6">
                        <label class="form-label" for="bal_{{ $role }}">{{ translate($label) }}</label>
                        <input type="number" class="form-control" id="bal_{{ $role }}" name="balances[{{ $role }}]" min="0" step="1" inputmode="numeric" placeholder="0">
                    </div>
                @endforeach
                @if(!$isCompanyBooks && $company && $company->collectsMoney())
                    <div class="col-sm-6">
                        <label class="form-label" for="bal_head_office">{{ translate('Head office (money it holds for the branch)') }}</label>
                        @if($companyBooks)
                            <div class="form-control bg-light">{{ translate('Taken from the head office books') }}</div>
                        @else
                            <input type="number" class="form-control" id="bal_head_office" name="balances[head_office]" min="0" step="1" inputmode="numeric" placeholder="0">
                        @endif
                    </div>
                @endif
                <div class="col-sm-6">
                    <label class="form-label" for="suppliers_owed">{{ translate('Owed to suppliers') }}</label>
                    <input type="number" class="form-control" id="suppliers_owed" name="suppliers_owed" min="0" step="1" inputmode="numeric" placeholder="0">
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="loans">{{ translate('Loans still to repay') }}</label>
                    <input type="number" class="form-control" id="loans" name="loans" min="0" step="1" inputmode="numeric" placeholder="0">
                </div>
            </div>

            <h3 class="fs-6 fw-bold mt-4 mb-1">{{ translate('Which account pays') }}</h3>
            <div class="row g-3">
                @unless($isCompanyBooks)
                    <div class="col-sm-6">
                        <label class="form-label" for="refunds_from">{{ translate('Refunds to passengers are sent from') }}</label>
                        <select class="form-select" id="refunds_from" name="refunds_from">@foreach($payFrom as $k => $l)<option value="{{ $k }}">{{ translate($l) }}</option>@endforeach</select>
                    </div>
                @endunless
                <div class="col-sm-6">
                    <label class="form-label" for="eride_from">{{ $isCompanyBooks ? translate('The E-Ride company statement is paid from') : translate('E-Ride statements are paid from') }}</label>
                    <select class="form-select" id="eride_from" name="eride_from">@foreach($payFrom as $k => $l)<option value="{{ $k }}" @selected($isCompanyBooks && $k === 'bank')>{{ translate($l) }}</option>@endforeach</select>
                </div>
            </div>
            @if($carriesBuses)<p class="b-hint mt-3 mb-0">{{ translate('The buses are added after, one by one, in Buses: their value, how many years they run and what they are worth at the end.') }}</p>@endif
            <button type="submit" class="btn btn-primary mt-4">{{ translate('Check and open the books') }}</button>
        </form>
    </div>
    <div class="col-lg-5">
        <div class="acc-card">
            <h2 class="acc-h">{{ translate('How it works') }}</h2>
            <ul class="b-hint ps-3 mb-0" style="font-size: 13.5px">
                @if($isCompanyBooks)
                    <li class="mb-2">{{ translate('Each branch keeps its own books. The head office keeps its account of each branch: money handed in, money sent, bus wear charged. It always equals the branch side.') }}</li>
                    <li class="mb-2">{{ translate('The company buses are carried here. Each month their wear is charged to the branch each bus belongs to.') }}</li>
                    <li class="mb-2">{{ translate('App and web payments on the company account, and the E-Ride company statement, post on their own.') }}</li>
                    <li class="mb-2">{{ translate('Reports show the head office alone, or the whole company: head office and branches added together.') }}</li>
                    <li>{{ translate('The year closes for the head office and all its branches together.') }}</li>
                @else
                    <li class="mb-2">{{ translate('A ticket paid is held for its trip until the bus leaves. Then it becomes sales, per bus, route and trip.') }}</li>
                    <li class="mb-2">{{ translate('Counter cash is followed per agent. When an agent hands in the cash, you count it: any difference is posted as cash short or over on that agent.') }}</li>
                    <li class="mb-2">{{ translate('E-Ride commission is posted when the statement is made, and paid when you pay it.') }}</li>
                    <li class="mb-2">{{ translate('Before anything is posted by hand you see exactly what will be written, and you confirm it.') }}</li>
                    <li>{{ translate('Months close in order. Nothing changes a closed month: a correction is a new entry.') }}</li>
                @endif
            </ul>
        </div>
    </div>
</div>
@include('busmanagement::partner.accounts._review')
@endsection
