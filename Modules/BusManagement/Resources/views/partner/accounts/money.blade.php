@extends($accLayout)

@section('title', translate('Money'))

@php use Modules\BusManagement\Lib\AccFormat as F; @endphp

@push('head')
    @include('busmanagement::partner.accounts._style')
@endpush

@section('content')
@include('busmanagement::partner.accounts._nav')

<div class="acc-grid mb-3">
    @foreach($o['money'] as $row)
        <div class="acc-fig"><a href="{{ route($acc . '.journal', ['account' => $row['account']->id, 'from' => $books->starts_on->toDateString()]) }}">
            <div class="k">{{ $row['account']->label() }}</div><div class="v {{ F::cls($row['balance']) }}">{{ F::n($row['balance']) }}</div></a></div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <form class="acc-card acc-form h-100" id="move" data-acc-action="transfer" data-acc-title="{{ translate('Move money') }}">
            <h2 class="acc-h">{{ translate('Move money') }}</h2>
            <p class="b-hint">{{ translate('Cash taken to the bank, Mobile Money withdrawn, Fapshi paid out to MTN...') }}</p>
            <div class="row g-2">
                <div class="col-12"><label class="form-label" for="tr_date">{{ translate('Date') }}</label>
                    <input type="date" class="form-control" id="tr_date" name="date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required></div>
                <div class="col-6"><label class="form-label" for="tr_from">{{ translate('From') }}</label>
                    <select class="form-select" id="tr_from" name="money_account_id" required><option value="">{{ translate('Choose') }}</option>
                        @foreach($moneyAccounts->where('role', '!=', 'counter_cash') as $ma)<option value="{{ $ma->id }}">{{ $ma->displayName() }}</option>@endforeach</select></div>
                <div class="col-6"><label class="form-label" for="tr_to">{{ translate('To') }}</label>
                    <select class="form-select" id="tr_to" name="to_account_id" required><option value="">{{ translate('Choose') }}</option>
                        @foreach($moneyAccounts->where('role', '!=', 'counter_cash') as $ma)<option value="{{ $ma->id }}">{{ $ma->displayName() }}</option>@endforeach</select></div>
                <div class="col-6"><label class="form-label" for="tr_amount">{{ translate('Amount') }}</label>
                    <input type="number" class="form-control" id="tr_amount" name="amount" min="1" step="1" required></div>
                <div class="col-6"><label class="form-label" for="tr_fee">{{ translate('Charge taken') }}</label>
                    <input type="number" class="form-control" id="tr_fee" name="fee" min="0" step="1" placeholder="0"></div>
                <div class="col-12"><label class="form-label" for="tr_ref">{{ translate('Reference (slip or transaction)') }}</label>
                    <input type="text" class="form-control" id="tr_ref" name="reference" maxlength="100"></div>
            </div>
            <button type="submit" class="btn btn-primary mt-3">{{ translate('Check and record') }}</button>
        </form>
    </div>
    <div class="col-lg-4">
        <form class="acc-card acc-form h-100" id="income" data-acc-action="income" data-acc-title="{{ translate('Record other income') }}">
            <h2 class="acc-h">{{ translate('Other income') }}</h2>
            <p class="b-hint">{{ translate('Parcels, luggage, bus hire: money that is not a ticket.') }}</p>
            <div class="row g-2">
                <div class="col-6"><label class="form-label" for="in_date">{{ translate('Date') }}</label>
                    <input type="date" class="form-control" id="in_date" name="date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required></div>
                <div class="col-6"><label class="form-label" for="in_amount">{{ translate('Amount') }}</label>
                    <input type="number" class="form-control" id="in_amount" name="amount" min="1" step="1" required></div>
                <div class="col-12"><label class="form-label" for="in_cat">{{ translate('Category') }}</label>
                    <select class="form-select" id="in_cat" name="account_id" required><option value="">{{ translate('Choose') }}</option>
                        @foreach($incomeCats as $c)<option value="{{ $c->id }}">{{ $c->label() }}</option>@endforeach</select></div>
            </div>
            <div class="row g-2 mt-0">
                @include('busmanagement::partner.accounts._money_select', ['label' => translate('Received into'), 'idp' => 'in'])
            </div>
            <div class="row g-2 mt-0">
                <div class="col-12"><label class="form-label" for="in_bus">{{ translate('Bus (optional)') }}</label>
                    <select class="form-select" id="in_bus" name="vehicle_id"><option value="">{{ translate('Not for one bus') }}</option>
                        @foreach($buses as $bus)<option value="{{ $bus->id }}">{{ $bus->registration }}</option>@endforeach</select></div>
                <div class="col-12"><label class="form-label" for="in_note">{{ translate('Note (optional)') }}</label>
                    <input type="text" class="form-control" id="in_note" name="note" maxlength="500"></div>
            </div>
            <button type="submit" class="btn btn-primary mt-3">{{ translate('Check and record') }}</button>
        </form>
    </div>
    <div class="col-lg-4">
        <form class="acc-card acc-form h-100" id="check" method="GET" action="{{ route($acc . '.reconcile') }}">
            <h2 class="acc-h">{{ translate('Reconcile a statement') }}</h2>
            <p class="b-hint">{{ translate('With a bank or Mobile Money statement: tick the movements it shows, and see whether it agrees with the books.') }}</p>
            <div class="row g-2">
                <div class="col-12"><label class="form-label" for="ck_acc">{{ translate('Account') }}</label>
                    <select class="form-select" id="ck_acc" name="account" required><option value="">{{ translate('Choose') }}</option>
                        @foreach($moneyAccounts->where('role', '!=', 'counter_cash') as $ma)<option value="{{ $ma->id }}">{{ $ma->displayName() }}</option>@endforeach</select></div>
                <div class="col-6"><label class="form-label" for="ck_date">{{ translate('Statement date') }}</label>
                    <input type="date" class="form-control" id="ck_date" name="date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required></div>
                <div class="col-6"><label class="form-label" for="ck_bal">{{ translate('Balance on the statement') }}</label>
                    <input type="number" class="form-control" id="ck_bal" name="counted" step="1" required></div>
            </div>
            <button type="submit" class="btn btn-primary mt-3">{{ translate('Next: tick the movements') }}</button>
        </form>
    </div>
</div>

@if($isCompanyBooks)
<form class="acc-card acc-form mt-3" id="branch" data-acc-action="branchmoney" data-acc-title="{{ translate('Money with a branch') }}">
    <h2 class="acc-h">{{ translate('Money with a branch') }}</h2>
    <p class="b-hint">{{ translate('Takings a branch hands in to the head office, or money the head office sends to a branch. It is posted in both books at once.') }}</p>
    @if(!count($branchBooks))
        <div class="acc-empty">{{ translate('No branch has opened its books yet.') }}</div>
    @else
    <div class="row g-2">
        <div class="col-sm-4"><label class="form-label" for="bm_branch">{{ translate('Branch') }}</label>
            <select class="form-select" id="bm_branch" name="branch_id" required><option value="">{{ translate('Choose') }}</option>
                @foreach($branchBooks as $bb)<option value="{{ $bb['branch']->id }}">{{ $bb['branch']->branchLabel() }}</option>@endforeach</select></div>
        <div class="col-sm-4"><label class="form-label" for="bm_dir">{{ translate('Direction') }}</label>
            <select class="form-select" id="bm_dir" name="direction"><option value="from_branch">{{ translate('Handed in by the branch') }}</option><option value="to_branch">{{ translate('Sent to the branch') }}</option></select></div>
        <div class="col-sm-4"><label class="form-label" for="bm_date">{{ translate('Date') }}</label>
            <input type="date" class="form-control" id="bm_date" name="date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required></div>
        <div class="col-sm-4"><label class="form-label" for="bm_amount">{{ translate('Amount') }}</label>
            <input type="number" class="form-control" id="bm_amount" name="amount" min="1" step="1" required></div>
        <div class="col-sm-4"><label class="form-label" for="bm_ho">{{ translate('Head office account') }}</label>
            <select class="form-select" id="bm_ho" name="money_account_id" required><option value="">{{ translate('Choose') }}</option>
                @foreach($moneyAccounts as $ma)<option value="{{ $ma->id }}">{{ $ma->displayName() }}</option>@endforeach</select></div>
        <div class="col-sm-4"><label class="form-label" for="bm_br">{{ translate('Branch account') }}</label>
            <select class="form-select" id="bm_br" name="branch_account_id" required><option value="">{{ translate('Choose the branch first') }}</option>
                @foreach($branchBooks as $bb)
                    @foreach($bb['accounts'] as $ba)<option value="{{ $ba->id }}" data-branch="{{ $bb['branch']->id }}" hidden>{{ $ba->displayName() }}</option>@endforeach
                @endforeach</select></div>
        <div class="col-sm-6"><label class="form-label" for="bm_ref">{{ translate('Reference (slip or transaction)') }}</label>
            <input type="text" class="form-control" id="bm_ref" name="reference" maxlength="100"></div>
    </div>
    <button type="submit" class="btn btn-primary mt-3">{{ translate('Check and record') }}</button>
    @endif
</form>
@push('script')
<script>
    (function () {
        var b = document.getElementById('bm_branch'), a = document.getElementById('bm_br');
        if (!b) return;
        b.addEventListener('change', function () {
            a.value = '';
            a.querySelectorAll('option[data-branch]').forEach(function (o) { o.hidden = o.getAttribute('data-branch') !== b.value; });
        });
    })();
</script>
@endpush
@endif

<div class="acc-card mt-3">
    <h2 class="acc-h">{{ translate('Recorded') }}</h2>
    <div class="acc-scroll"><table class="acc-t">
        <thead><tr><th>{{ translate('Date') }}</th><th>{{ translate('Number') }}</th><th>{{ translate('What') }}</th><th>{{ translate('Details') }}</th><th class="n">{{ translate('Amount') }}</th></tr></thead>
        <tbody>
        @forelse($docs as $d)
            <tr class="{{ $d->status === 'void' ? 'void' : '' }}">
                <td class="text-nowrap">{{ $d->date->format('d/m/Y') }}</td>
                <td><a href="{{ route($acc . '.document', $d->id) }}">{{ $d->number }}</a></td>
                <td>{{ $d->typeLabel() }}</td>
                <td>
                    @if($d->type === 'branch_money'){{ ($d->lines['direction'] ?? '') === 'from_branch' ? ($isCompanyBooks ? translate('Handed in by the branch') : translate('Handed in to the head office')) : ($isCompanyBooks ? translate('Sent to the branch') : translate('Received from the head office')) }}@if(isset($d->lines['branch_id'])) · {{ \Modules\BusManagement\Entities\BusAgency::find($d->lines['branch_id'])?->branchLabel() }}@endif
                    @elseif($d->type === 'transfer'){{ ($d->moneyAccount?->displayName() ?? '') }} → {{ ($d->toAccount?->displayName() ?? '') }}
                    @elseif($d->type === 'income'){{ ($d->account?->displayName() ?? '') }} · {{ ($d->moneyAccount?->displayName() ?? '') }}
                    @else{{ ($d->account?->displayName() ?? '') }}: {{ translate('statement') }} {{ F::n($d->counted) }} · {{ translate('books') }} {{ F::n($d->expected) }}@endif
                </td>
                <td class="n">{{ F::n($d->amount, $d->type === 'check') }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="acc-empty">{{ translate('Nothing recorded yet.') }}</td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $docs->links() }}
</div>

@include('busmanagement::partner.accounts._review')
@endsection
