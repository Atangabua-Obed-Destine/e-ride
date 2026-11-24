@extends('busmanagement::partner.layout')

@section('title', translate('Journal'))

@php use Modules\BusManagement\Lib\AccFormat as F; @endphp

@push('head')
    @include('busmanagement::partner.accounts._style')
@endpush

@section('content')
@include('busmanagement::partner.accounts._nav')

<div class="acc-card mb-3">
    <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
        <div><label class="form-label small fw-bold mb-1" for="j_acc">{{ translate('Account') }}</label>
            <select class="form-select form-select-sm" id="j_acc" name="account" style="min-width: 260px">
                <option value="">{{ translate('All entries (journal)') }}</option>
                @foreach($accounts as $a)<option value="{{ $a->id }}" @selected($account && $account->id === $a->id)>{{ $a->label() }}</option>@endforeach
            </select></div>
        <div><label class="form-label small fw-bold mb-1" for="j_from">{{ translate('From') }}</label><input type="date" class="form-control form-control-sm" id="j_from" name="from" value="{{ $from }}"></div>
        <div><label class="form-label small fw-bold mb-1" for="j_to">{{ translate('To') }}</label><input type="date" class="form-control form-control-sm" id="j_to" name="to" value="{{ $to }}"></div>
        @if(!$account)
            <div><label class="form-label small fw-bold mb-1" for="j_q">{{ translate('Search') }}</label><input type="search" class="form-control form-control-sm" id="j_q" name="q" value="{{ request('q') }}" placeholder="{{ translate('Number, ticket code, words') }}"></div>
        @endif
        @if($agent)<input type="hidden" name="agent" value="{{ $agent }}">@endif
        <button class="btn btn-soft btn-sm">{{ translate('Show') }}</button>
        @if(!$account)<a href="#new-entry" class="btn btn-dark btn-sm ms-auto">{{ translate('New journal entry') }}</a>@endif
    </form>
</div>

@if($account)
    <div class="acc-card">
        <h2 class="acc-h">{{ $account->label() }}@if($agent) · {{ $agents->firstWhere('id', $agent)['name'] ?? '' }}@endif</h2>
        <div class="acc-scroll"><table class="acc-t">
            <thead><tr><th>{{ translate('Date') }}</th><th>{{ translate('Entry') }}</th><th>{{ translate('Description') }}</th><th class="n">{{ translate('Debit') }}</th><th class="n">{{ translate('Credit') }}</th><th class="n">{{ translate('Balance') }}</th></tr></thead>
            <tbody>
            <tr class="sub"><td colspan="5">{{ translate('Balance before') }} {{ \Illuminate\Support\Carbon::parse($from)->format('d/m/Y') }}</td><td class="n">{{ F::n($ledger['opening']) }}</td></tr>
            @forelse($ledger['rows'] as $row)
                @php $l = $row['line']; @endphp
                <tr><td class="text-nowrap">{{ $l->date->format('d/m/Y') }}</td><td class="text-nowrap">{{ $l->entry->number }}</td>
                    <td>{{ $l->entry->memo }}@if($l->memo)<div class="acc-tag">{{ $l->memo }}</div>@endif</td>
                    <td class="n">{{ F::n($l->debit, true) }}</td><td class="n">{{ F::n($l->credit, true) }}</td><td class="n {{ F::cls($row['balance']) }}">{{ F::n($row['balance']) }}</td></tr>
            @empty
                <tr><td colspan="6" class="acc-empty">{{ translate('No movement in this period.') }}</td></tr>
            @endforelse
            <tr class="tot"><td colspan="5">{{ translate('Balance at') }} {{ \Illuminate\Support\Carbon::parse($to)->format('d/m/Y') }}</td><td class="n">{{ F::n($ledger['closing']) }}</td></tr>
            </tbody>
        </table></div>
        @if($ledger['truncated'])<div class="rv-warn mt-2">{{ translate('Only the first 3000 lines are shown: choose a shorter period.') }}</div>@endif
    </div>
@else
    <div class="acc-card">
        @forelse($entries as $e)
            <div class="rv-entry">
                <div class="d-flex flex-wrap justify-content-between gap-2 mb-1">
                    <div><b>{{ $e->number }}</b> · {{ $e->memo }}
                        @if($e->source_type === 'document' && isset($docs[$e->source_id]))<a class="acc-tag ms-1" href="{{ route('bus-partner.accounts.document', $e->source_id) }}">{{ $docs[$e->source_id]->number }}</a>@endif
                        @if($e->reverses_id)<span class="b-tag mute ms-1">{{ translate('Reversal') }}</span>@endif
                        @if($e->reversed_by_id)<span class="b-tag warn ms-1">{{ translate('Reversed') }}</span>@endif</div>
                    <span class="acc-tag text-nowrap">{{ $e->date->format('d/m/Y') }}</span>
                </div>
                <table class="acc-t"><tbody>
                    @foreach($e->lines as $l)
                        <tr><td>{{ $l->account?->label() }}</td><td class="n" style="width: 120px">{{ F::n($l->debit, true) }}</td><td class="n" style="width: 120px">{{ F::n($l->credit, true) }}</td></tr>
                    @endforeach
                </tbody></table>
            </div>
        @empty
            <div class="acc-empty">{{ translate('No entry in this period.') }}</div>
        @endforelse
        {{ $entries->links() }}
    </div>

    <form class="acc-card acc-form mt-3" id="new-entry" data-acc-action="journal" data-acc-title="{{ translate('Journal entry') }}">
        <h2 class="acc-h">{{ translate('New journal entry') }}</h2>
        <p class="b-hint">{{ translate('For corrections and month-end amounts (rent due, staff pay owed). Tickets, refunds and agent cash post on their own and cannot be entered here.') }}</p>
        <div class="row g-2 mb-2">
            <div class="col-sm-3"><label class="form-label" for="je_date">{{ translate('Date') }}</label>
                <input type="date" class="form-control" id="je_date" name="date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required></div>
            <div class="col-sm-6"><label class="form-label" for="je_note">{{ translate('Description') }}</label>
                <input type="text" class="form-control" id="je_note" name="note" maxlength="500" required></div>
            <div class="col-sm-3"><label class="form-label" for="je_rev">{{ translate('Reverse on (optional)') }}</label>
                <input type="date" class="form-control" id="je_rev" name="reverse_on" value="">
                <div class="b-hint">{{ translate('For an amount due at month end: the 1st of next month.') }}</div></div>
        </div>
        <div class="acc-scroll"><table class="acc-t" id="jeLines">
            <thead><tr><th>{{ translate('Account') }}</th><th>{{ translate('Bus') }}</th><th class="n">{{ translate('Debit') }}</th><th class="n">{{ translate('Credit') }}</th><th></th></tr></thead>
            <tbody></tbody>
            <tfoot><tr class="tot"><td colspan="2"><button type="button" class="btn btn-soft btn-sm" id="jeAdd">{{ translate('Add a line') }}</button></td>
                <td class="n" id="jeD">0</td><td class="n" id="jeC">0</td><td></td></tr></tfoot>
        </table></div>
        <button type="submit" class="btn btn-primary mt-3">{{ translate('Check and post') }}</button>
    </form>
    <template id="jeRow">
        <tr>
            <td><select class="form-select form-select-sm" data-n="account_id"><option value="">{{ translate('Choose') }}</option>
                @foreach($allAccounts->whereNotIn('role', ['counter_cash', 'unearned']) as $a)<option value="{{ $a->id }}">{{ $a->label() }}</option>@endforeach</select></td>
            <td><select class="form-select form-select-sm" data-n="vehicle_id"><option value=""></option>@foreach($buses as $bus)<option value="{{ $bus->id }}">{{ $bus->registration }}</option>@endforeach</select></td>
            <td><input type="number" class="form-control form-control-sm text-end" data-n="debit" min="0" step="1" style="width: 120px"></td>
            <td><input type="number" class="form-control form-control-sm text-end" data-n="credit" min="0" step="1" style="width: 120px"></td>
            <td><button type="button" class="btn btn-sm btn-light" data-del aria-label="{{ translate('Remove') }}">&times;</button></td>
        </tr>
    </template>
    @include('busmanagement::partner.accounts._review')
@endif
@endsection

@if(!$account)
@push('script')
<script>
    (function () {
        var body = document.querySelector('#jeLines tbody'), tpl = document.getElementById('jeRow');
        function renumber() {
            var d = 0, c = 0;
            body.querySelectorAll('tr').forEach(function (tr, i) {
                tr.querySelectorAll('[data-n]').forEach(function (el) { el.name = 'lines[' + i + '][' + el.getAttribute('data-n') + ']'; });
                d += +(tr.querySelector('[data-n=debit]').value || 0); c += +(tr.querySelector('[data-n=credit]').value || 0);
            });
            document.getElementById('jeD').textContent = d.toLocaleString('fr-FR');
            document.getElementById('jeC').textContent = c.toLocaleString('fr-FR');
            document.getElementById('jeD').classList.toggle('neg', d !== c);
            document.getElementById('jeC').classList.toggle('neg', d !== c);
        }
        function add() { body.appendChild(tpl.content.cloneNode(true)); renumber(); }
        document.getElementById('jeAdd').addEventListener('click', add);
        body.addEventListener('input', renumber);
        body.addEventListener('click', function (e) { if (e.target.closest('[data-del]') && body.children.length > 2) { e.target.closest('tr').remove(); renumber(); } });
        add(); add();
    })();
</script>
@endpush
@endif
