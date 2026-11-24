@extends('busmanagement::partner.layout')

@section('title', translate('Spending'))

@php use Modules\BusManagement\Lib\AccFormat as F; @endphp

@push('head')
    @include('busmanagement::partner.accounts._style')
@endpush

@section('content')
@include('busmanagement::partner.accounts._nav')

<form class="acc-card acc-form mb-3" id="new" data-acc-action="expense" data-acc-title="{{ translate('Record an expense') }}">
    <h2 class="acc-h">{{ translate('Record an expense') }}</h2>
    <div class="row g-3">
        <div class="col-sm-3"><label class="form-label" for="ex_date">{{ translate('Date') }}</label>
            <input type="date" class="form-control" id="ex_date" name="date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required></div>
        <div class="col-sm-5"><label class="form-label" for="ex_cat">{{ translate('Category') }}</label>
            <select class="form-select" id="ex_cat" name="account_id" required><option value="">{{ translate('Choose') }}</option>
                @foreach($expenseCats as $c)<option value="{{ $c->id }}">{{ $c->label() }}</option>@endforeach</select></div>
        <div class="col-sm-4"><label class="form-label" for="ex_amount">{{ translate('Amount') }} (FCFA)</label>
            <input type="number" class="form-control" id="ex_amount" name="amount" min="1" step="1" inputmode="numeric" required></div>
        <div class="col-12">
            <div class="seg" role="radiogroup" aria-label="{{ translate('Paid') }}">
                <input type="radio" name="paid" id="ex_now" value="now" checked><label for="ex_now">{{ translate('Paid now') }}</label>
                <input type="radio" name="paid" id="ex_later" value="later"><label for="ex_later">{{ translate('A bill to pay later') }}</label>
            </div>
        </div>
    </div>
    <div class="row g-3 mt-0" data-paid="now">
        @include('busmanagement::partner.accounts._money_select', ['label' => translate('Paid from'), 'idp' => 'ex'])
    </div>
    <div class="row g-3 mt-0" data-paid="later" hidden>
        <div class="col-sm-6"><label class="form-label" for="ex_supplier">{{ translate('Supplier') }}</label>
            <input type="text" class="form-control" id="ex_supplier" name="supplier" maxlength="120"></div>
        <div class="col-sm-6"><label class="form-label" for="ex_due">{{ translate('Due on (optional)') }}</label>
            <input type="date" class="form-control" id="ex_due" name="due_on"></div>
    </div>
    <div class="row g-3 mt-0">
        @include('busmanagement::partner.accounts._tags', ['idp' => 'ex'])
        <div class="col-sm-4"><label class="form-label" for="ex_ref">{{ translate('Receipt or invoice number') }}</label>
            <input type="text" class="form-control" id="ex_ref" name="reference" maxlength="100"></div>
        <div class="col-sm-8"><label class="form-label" for="ex_note">{{ translate('Note (optional)') }}</label>
            <input type="text" class="form-control" id="ex_note" name="note" maxlength="500"></div>
    </div>
    <button type="submit" class="btn btn-primary mt-3">{{ translate('Check and record') }}</button>
</form>

@if($bills->isNotEmpty())
<div class="acc-card mb-3">
    <h2 class="acc-h">{{ translate('Bills to pay') }}</h2>
    <div class="acc-scroll"><table class="acc-t">
        <thead><tr><th>{{ translate('Bill') }}</th><th>{{ translate('Supplier') }}</th><th>{{ translate('Due on') }}</th><th class="n">{{ translate('Total') }}</th><th class="n">{{ translate('Still owed') }}</th><th></th></tr></thead>
        <tbody>
        @foreach($bills as $b)
            <tr>
                <td><a href="{{ route('bus-partner.accounts.document', $b->id) }}">{{ $b->number }}</a><div class="acc-tag">{{ $b->date->format('d/m/Y') }} · {{ $b->account ? translate($b->account->name) : '' }}</div></td>
                <td>{{ $b->supplier }}</td>
                <td>@if($b->due_on){{ $b->due_on->format('d/m/Y') }}@if($b->due_on->lt(today())) <span class="b-tag bad">{{ translate('Late') }}</span>@endif @else <span class="acc-tag">-</span>@endif</td>
                <td class="n">{{ F::n($b->amount) }}</td>
                <td class="n fw-bold">{{ F::n($b->owed) }}</td>
                <td class="text-end"><a class="btn btn-soft btn-sm" href="{{ route('bus-partner.accounts.document', $b->id) }}#pay">{{ translate('Pay') }}</a></td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
</div>
@endif

<div class="acc-card">
    <div class="d-flex flex-wrap gap-2 align-items-end justify-content-between mb-2">
        <h2 class="acc-h m-0">{{ translate('Expenses') }}</h2>
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
            <input type="date" class="form-control form-control-sm" name="from" value="{{ $from }}" aria-label="{{ translate('From') }}" style="width: 150px">
            <input type="date" class="form-control form-control-sm" name="to" value="{{ $to }}" aria-label="{{ translate('To') }}" style="width: 150px">
            <select class="form-select form-select-sm" name="category" aria-label="{{ translate('Category') }}" style="width: 200px"><option value="">{{ translate('All categories') }}</option>
                @foreach($expenseCats as $c)<option value="{{ $c->id }}" @selected(request('category') == $c->id)>{{ translate($c->name) }}</option>@endforeach</select>
            <select class="form-select form-select-sm" name="bus" aria-label="{{ translate('Bus') }}" style="width: 160px"><option value="">{{ translate('All buses') }}</option>
                @foreach($buses as $bus)<option value="{{ $bus->id }}" @selected(request('bus') == $bus->id)>{{ $bus->registration }}</option>@endforeach</select>
            <button class="btn btn-soft btn-sm">{{ translate('Show') }}</button>
        </form>
    </div>
    <div class="acc-scroll"><table class="acc-t">
        <thead><tr><th>{{ translate('Date') }}</th><th>{{ translate('Number') }}</th><th>{{ translate('Category') }}</th><th>{{ translate('Paid from') }}</th><th>{{ translate('Bus') }}</th><th class="n">{{ translate('Amount') }}</th></tr></thead>
        <tbody>
        @forelse($expenses as $e)
            <tr class="{{ $e->status === 'void' ? 'void' : '' }}">
                <td class="text-nowrap">{{ $e->date->format('d/m/Y') }}</td>
                <td><a href="{{ route('bus-partner.accounts.document', $e->id) }}">{{ $e->number }}</a></td>
                <td>{{ $e->account ? translate($e->account->name) : '' }}@if($e->note)<div class="acc-tag">{{ $e->note }}</div>@endif</td>
                <td>{{ $e->moneyAccount ? translate($e->moneyAccount->name) : ($e->supplier ? translate('Bill') . ' · ' . $e->supplier : '') }}</td>
                <td>{{ $e->vehicle?->registration }}</td>
                <td class="n">{{ F::n($e->amount) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="acc-empty">{{ translate('No expense in this period.') }}</td></tr>
        @endforelse
        </tbody>
        @if($expenses->count())
            <tfoot><tr class="tot"><td colspan="5">{{ translate('Total on this page') }}</td><td class="n">{{ F::n($expenses->where('status', 'posted')->sum('amount')) }}</td></tr></tfoot>
        @endif
    </table></div>
    {{ $expenses->links() }}
</div>

@if($payments->isNotEmpty())
<div class="acc-card">
    <h2 class="acc-h">{{ translate('Bill payments') }}</h2>
    <div class="acc-scroll"><table class="acc-t">
        <thead><tr><th>{{ translate('Date') }}</th><th>{{ translate('Number') }}</th><th>{{ translate('Bill') }}</th><th>{{ translate('Paid from') }}</th><th class="n">{{ translate('Amount') }}</th></tr></thead>
        <tbody>
        @foreach($payments as $p)
            <tr class="{{ $p->status === 'void' ? 'void' : '' }}"><td>{{ $p->date->format('d/m/Y') }}</td><td><a href="{{ route('bus-partner.accounts.document', $p->id) }}">{{ $p->number }}</a></td>
                <td>{{ $p->parent?->number }} · {{ $p->supplier }}</td><td>{{ $p->moneyAccount ? translate($p->moneyAccount->name) : '' }}</td><td class="n">{{ F::n($p->amount) }}</td></tr>
        @endforeach
        </tbody>
    </table></div>
</div>
@endif

@include('busmanagement::partner.accounts._review')
@endsection

@push('script')
<script>
    (function () {
        var form = document.getElementById('new');
        function sync() {
            var later = document.getElementById('ex_later').checked;
            form.querySelector('[data-paid=now]').hidden = later;
            form.querySelector('[data-paid=later]').hidden = !later;
            document.getElementById('ex_money').required = !later;
            document.getElementById('ex_supplier').required = later;
            if (later) { form.querySelector('[data-agent-wrap]').hidden = true; form.querySelector('[data-agent-wrap] select').required = false; document.getElementById('ex_money').value = ''; }
        }
        form.querySelectorAll('input[name=paid]').forEach(function (r) { r.addEventListener('change', sync); });
        sync();
    })();
</script>
@endpush
