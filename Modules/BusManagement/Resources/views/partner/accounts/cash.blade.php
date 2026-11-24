@extends('busmanagement::partner.layout')

@section('title', translate('Agent cash'))

@php
    use Modules\BusManagement\Lib\AccFormat as F;
    $held = collect($holding)->keyBy('user_id');
    $pick = request('agent');
    $safe = $moneyAccounts->firstWhere('role', 'safe');
@endphp

@push('head')
    @include('busmanagement::partner.accounts._style')
@endpush

@section('content')
@include('busmanagement::partner.accounts._nav')

<div class="row g-3">
    <div class="col-lg-5">
        <form class="acc-card acc-form" data-acc-action="count" data-acc-title="{{ translate('Count an agent cash') }}">
            <h2 class="acc-h">{{ translate('Count an agent cash') }}</h2>
            <p class="b-hint">{{ translate('At the end of a shift: count the cash with the agent, then put it in the safe or take it to the bank. What the agent should hold is worked out from their sales and refunds.') }}</p>
            <div class="row g-2">
                <div class="col-12"><label class="form-label" for="ct_agent">{{ translate('Agent') }}</label>
                    <select class="form-select" id="ct_agent" name="staff_user_id" required>
                        <option value="">{{ translate('Choose') }}</option>
                        @foreach($agents as $ag)
                            <option value="{{ $ag['id'] }}" data-held="{{ $held[$ag['id']]['held'] ?? 0 }}" @selected($pick === $ag['id'])>{{ $ag['name'] }} · {{ F::n($held[$ag['id']]['held'] ?? 0) }}</option>
                        @endforeach
                    </select></div>
                <div class="col-12"><div class="rv-note" id="ct_should"></div></div>
                <div class="col-6"><label class="form-label" for="ct_counted">{{ translate('Cash counted') }}</label>
                    <input type="number" class="form-control" id="ct_counted" name="counted" min="0" step="1" inputmode="numeric" required></div>
                <div class="col-6"><label class="form-label" for="ct_to">{{ translate('Put in') }}</label>
                    <select class="form-select" id="ct_to" name="to_account_id" required>
                        @foreach($moneyAccounts->where('role', '!=', 'counter_cash') as $ma)<option value="{{ $ma->id }}" @selected($safe && $ma->id === $safe->id)>{{ translate($ma->name) }}</option>@endforeach
                    </select></div>
                <div class="col-12"><label class="form-label" for="ct_note">{{ translate('Note (needed when the cash is short or over)') }}</label>
                    <input type="text" class="form-control" id="ct_note" name="note" maxlength="500"></div>
            </div>
            <button type="submit" class="btn btn-primary mt-3">{{ translate('Check the count') }}</button>
        </form>
    </div>
    <div class="col-lg-7">
        <div class="acc-card">
            <h2 class="acc-h">{{ translate('Cash with each agent now') }}</h2>
            <table class="acc-t">
                <thead><tr><th>{{ translate('Agent') }}</th><th>{{ translate('Last counted') }}</th><th class="n">{{ translate('Holds') }}</th></tr></thead>
                <tbody>
                @forelse($holding as $h)
                    <tr><td>{{ $h['name'] }}</td><td>{{ $h['last_count'] ? \Illuminate\Support\Carbon::parse($h['last_count'])->format('d/m H:i') : translate('Never') }}</td>
                        <td class="n fw-bold {{ F::cls($h['held']) }}">@if($h['user_id'])<a href="{{ route('bus-partner.accounts.journal', ['account' => $moneyAccounts->firstWhere('role', 'counter_cash')?->id, 'agent' => $h['user_id'], 'from' => $books->starts_on->toDateString()]) }}">{{ F::n($h['held']) }}</a>@else{{ F::n($h['held']) }}@endif</td></tr>
                @empty
                    <tr><td colspan="3" class="acc-empty">{{ translate('No agent holds counter cash.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
            <div class="b-hint mt-2">{{ translate('A negative amount means the agent gave out more than they took in (a refund paid from their cash).') }}</div>
        </div>
    </div>
</div>

<div class="acc-card mt-3">
    <h2 class="acc-h">{{ translate('Counts') }}</h2>
    <div class="acc-scroll"><table class="acc-t">
        <thead><tr><th>{{ translate('When') }}</th><th>{{ translate('Number') }}</th><th>{{ translate('Agent') }}</th><th>{{ translate('Counted by') }}</th><th>{{ translate('Put in') }}</th>
            <th class="n">{{ translate('Should hold') }}</th><th class="n">{{ translate('Counted') }}</th><th class="n">{{ translate('Difference') }}</th></tr></thead>
        <tbody>
        @forelse($counts as $c)
            @php $diff = $c->counted - $c->expected; @endphp
            <tr class="{{ $c->status === 'void' ? 'void' : '' }}">
                <td class="text-nowrap">{{ $c->created_at->format('d/m/Y H:i') }}</td>
                <td><a href="{{ route('bus-partner.accounts.document', $c->id) }}">{{ $c->number }}</a></td>
                <td>{{ trim(($c->staffUser->first_name ?? '') . ' ' . ($c->staffUser->last_name ?? '')) }}</td>
                <td>{{ $c->creator->first_name ?? '' }}</td>
                <td>{{ translate($c->toAccount?->name ?? '') }}</td>
                <td class="n">{{ F::n($c->expected) }}</td>
                <td class="n">{{ F::n($c->counted) }}</td>
                <td class="n fw-bold {{ F::cls($diff) }}">{{ F::n($diff) }}@if($c->note)<div class="acc-tag fw-normal">{{ $c->note }}</div>@endif</td>
            </tr>
        @empty
            <tr><td colspan="8" class="acc-empty">{{ translate('No count yet.') }}</td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $counts->links() }}
</div>

@include('busmanagement::partner.accounts._review')
@endsection

@push('script')
<script>
    (function () {
        var sel = document.getElementById('ct_agent'), out = document.getElementById('ct_should'), T = @json(translate('Should hold now'));
        function show() {
            var o = sel.selectedOptions[0];
            out.textContent = o && o.value ? T + ': ' + Number(o.getAttribute('data-held') || 0).toLocaleString('fr-FR') + ' FCFA' : '';
        }
        sel.addEventListener('change', show);
        show();
    })();
</script>
@endpush
