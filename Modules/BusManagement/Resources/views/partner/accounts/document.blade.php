@extends($accLayout)

@section('title', $doc->number)

@php use Modules\BusManagement\Lib\AccFormat as F; @endphp

@push('head')
    @include('busmanagement::partner.accounts._style')
@endpush

@section('content')
@include('busmanagement::partner.accounts._nav')

<div class="row g-3">
    <div class="col-lg-6">
        <div class="acc-card">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <h2 class="acc-h">{{ $doc->typeLabel() }} {{ $doc->number }}</h2>
                @if($doc->status === 'void')<span class="b-tag bad">{{ translate('Void') }}</span>@elseif($doc->type === 'expense' && $doc->owed > 0)<span class="b-tag warn">{{ translate('Not fully paid') }}</span>@else<span class="b-tag ok">{{ translate('Posted') }}</span>@endif
            </div>
            <table class="acc-t"><tbody>
                <tr><td>{{ translate('Date') }}</td><td>{{ $doc->date->format('d/m/Y') }}</td></tr>
                @if($doc->account)<tr><td>{{ in_array($doc->type, ['check']) ? translate('Account') : translate('Category') }}</td><td>{{ $doc->account->label() }}</td></tr>@endif
                @if($doc->type !== 'check')<tr><td>{{ translate('Amount') }}</td><td class="fw-bold">{{ F::n($doc->amount) }} FCFA</td></tr>@endif
                @if($doc->moneyAccount && $doc->type !== 'cash_count')<tr><td>{{ in_array($doc->type, ['income']) ? translate('Received into') : ($doc->type === 'transfer' ? translate('From') : translate('Paid from')) }}</td><td>{{ $doc->moneyAccount->label() }}</td></tr>@endif
                @if($doc->toAccount)<tr><td>{{ $doc->type === 'cash_count' ? translate('Put in') : translate('To') }}</td><td>{{ $doc->toAccount->label() }}</td></tr>@endif
                @if($doc->fee > 0)<tr><td>{{ translate('Charge') }}</td><td>{{ F::n($doc->fee) }}</td></tr>@endif
                @if($doc->supplier)<tr><td>{{ translate('Supplier') }}</td><td>{{ $doc->supplier }}</td></tr>@endif
                @if($doc->type === 'expense' && !$doc->money_account_id)<tr><td>{{ translate('Still owed') }}</td><td class="fw-bold">{{ F::n($doc->owed) }}</td></tr>@endif
                @if($doc->due_on)<tr><td>{{ translate('Due on') }}</td><td>{{ $doc->due_on->format('d/m/Y') }}</td></tr>@endif
                @if($doc->parent)<tr><td>{{ translate('Bill') }}</td><td><a href="{{ route($acc . '.document', $doc->parent->id) }}">{{ $doc->parent->number }}</a></td></tr>@endif
                @if($doc->staffUser)<tr><td>{{ translate('Agent') }}</td><td>{{ trim($doc->staffUser->first_name . ' ' . $doc->staffUser->last_name) }}</td></tr>@endif
                @if($doc->type === 'cash_count')
                    <tr><td>{{ translate('Should hold') }}</td><td>{{ F::n($doc->expected) }}</td></tr>
                    <tr><td>{{ translate('Counted') }}</td><td>{{ F::n($doc->counted) }}</td></tr>
                    <tr><td>{{ translate('Difference') }}</td><td class="fw-bold {{ F::cls($doc->counted - $doc->expected) }}">{{ F::n($doc->counted - $doc->expected) }}</td></tr>
                @elseif($doc->type === 'check')
                    <tr><td>{{ translate('Balance on the statement') }}</td><td>{{ F::n($doc->counted) }}</td></tr>
                    <tr><td>{{ translate('Money in, not on the statement yet') }}</td><td>{{ F::n($doc->lines['in_transit'] ?? 0) }}</td></tr>
                    <tr><td>{{ translate('Payments out, not on the statement yet') }}</td><td>{{ F::n($doc->lines['outstanding'] ?? 0) }}</td></tr>
                    <tr><td>{{ translate('Balance in the books') }}</td><td>{{ F::n($doc->expected) }}</td></tr>
                    <tr><td>{{ translate('Charges or interest posted') }}</td><td class="fw-bold">{{ F::n($doc->amount) }}</td></tr>
                    <tr><td>{{ translate('Movements matched') }}</td><td>{{ count($doc->lines['cleared'] ?? []) }}</td></tr>
                @endif
                @if($doc->vehicle)<tr><td>{{ translate('Bus') }}</td><td>{{ $doc->vehicle->registration }}</td></tr>@endif
                @if($doc->destination)<tr><td>{{ translate('Route') }}</td><td>{{ $doc->destination->city }}</td></tr>@endif
                @if($doc->reverse_on)<tr><td>{{ translate('Reverses itself on') }}</td><td>{{ $doc->reverse_on->format('d/m/Y') }}</td></tr>@endif
                @if($doc->reference)<tr><td>{{ translate('Reference') }}</td><td>{{ $doc->reference }}</td></tr>@endif
                @if($doc->note)<tr><td>{{ translate('Note') }}</td><td>{{ $doc->note }}</td></tr>@endif
                <tr><td>{{ translate('Recorded by') }}</td><td>{{ $doc->creator?->first_name }} · {{ $doc->created_at->format('d/m/Y H:i') }}</td></tr>
                @if($doc->status === 'void')<tr><td>{{ translate('Voided') }}</td><td>{{ $doc->voided_at?->format('d/m/Y H:i') }} · {{ $doc->void_reason }}</td></tr>@endif
            </tbody></table>
        </div>

        @if($doc->type === 'expense' && $payments->isNotEmpty())
            <div class="acc-card">
                <h2 class="acc-h">{{ translate('Payments') }}</h2>
                <table class="acc-t"><tbody>
                    @foreach($payments as $p)
                        <tr class="{{ $p->status === 'void' ? 'void' : '' }}"><td>{{ $p->date->format('d/m/Y') }}</td><td><a href="{{ route($acc . '.document', $p->id) }}">{{ $p->number }}</a></td>
                            <td>{{ ($p->moneyAccount?->displayName() ?? '') }}</td><td class="n">{{ F::n($p->amount) }}</td></tr>
                    @endforeach
                </tbody></table>
            </div>
        @endif

        @if($doc->type === 'expense' && $doc->status === 'posted' && $doc->owed > 0)
            <form class="acc-card acc-form" id="pay" data-acc-action="pay" data-acc-title="{{ translate('Pay a bill') }}">
                <input type="hidden" name="document" value="{{ $doc->id }}">
                <h2 class="acc-h">{{ translate('Pay this bill') }}</h2>
                <div class="row g-2">
                    <div class="col-sm-6"><label class="form-label" for="pb_date">{{ translate('Date') }}</label>
                        <input type="date" class="form-control" id="pb_date" name="date" value="{{ today()->toDateString() }}" min="{{ $doc->date->toDateString() }}" max="{{ today()->toDateString() }}" required></div>
                    <div class="col-sm-6"><label class="form-label" for="pb_amount">{{ translate('Amount') }}</label>
                        <input type="number" class="form-control" id="pb_amount" name="amount" min="1" max="{{ (int)ceil($doc->owed) }}" value="{{ (int)round($doc->owed) }}" step="1" required></div>
                    @include('busmanagement::partner.accounts._money_select', ['label' => translate('Paid from'), 'idp' => 'pb'])
                    <div class="col-12"><label class="form-label" for="pb_ref">{{ translate('Reference (receipt, transfer)') }}</label>
                        <input type="text" class="form-control" id="pb_ref" name="reference" maxlength="100"></div>
                </div>
                <button type="submit" class="btn btn-primary mt-3">{{ translate('Check and pay') }}</button>
            </form>
        @endif
    </div>
    <div class="col-lg-6">
        <div class="acc-card">
            <h2 class="acc-h">{{ translate('Entries') }}</h2>
            @forelse($entries as $e)
                <div class="rv-entry">
                    <div class="d-flex justify-content-between gap-2 mb-1"><b>{{ $e['number'] }} · {{ $e['memo'] }}</b><span class="acc-tag text-nowrap">{{ $e['date'] }}</span></div>
                    <table class="acc-t"><thead><tr><th>{{ translate('Account') }}</th><th class="n">{{ translate('Debit') }}</th><th class="n">{{ translate('Credit') }}</th></tr></thead><tbody>
                        @foreach($e['lines'] as $l)<tr><td>{{ $l['account'] }}@if($l['tags'])<div class="acc-tag">{{ $l['tags'] }}</div>@endif</td><td class="n">{{ $l['debit'] }}</td><td class="n">{{ $l['credit'] }}</td></tr>@endforeach
                    </tbody></table>
                </div>
            @empty
                <div class="acc-empty">{{ translate('Nothing is posted: it is only recorded.') }}</div>
            @endforelse
        </div>

        @if($doc->status === 'posted' && $doc->type !== 'opening')
            <form class="acc-card acc-form" data-acc-action="void" data-acc-title="{{ translate('Void') }} {{ $doc->number }}">
                <input type="hidden" name="document" value="{{ $doc->id }}">
                <h2 class="acc-h">{{ translate('Void') }}</h2>
                <p class="b-hint">{{ translate('A mistake is not deleted: its entry is reversed, and the document stays, marked void. Record it again correctly after.') }}</p>
                <label class="form-label" for="vd_reason">{{ translate('Why') }}</label>
                <input type="text" class="form-control" id="vd_reason" name="reason" minlength="3" maxlength="300" required>
                <button type="submit" class="btn btn-soft mt-3 text-danger">{{ translate('Check and void') }}</button>
            </form>
        @endif
    </div>
</div>
@include('busmanagement::partner.accounts._review')
@endsection
