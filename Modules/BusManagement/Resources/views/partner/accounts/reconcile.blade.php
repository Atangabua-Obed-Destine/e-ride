@extends($accLayout)

@section('title', translate('Reconcile'))

@php use Modules\BusManagement\Lib\AccFormat as F; @endphp

@push('head')
    @include('busmanagement::partner.accounts._style')
@endpush

@section('content')
@include('busmanagement::partner.accounts._nav')

<form class="acc-form" id="recForm" data-acc-action="check" data-acc-title="{{ translate('Reconcile') }} · {{ $account->displayName() }}">
    <input type="hidden" name="account_id" value="{{ $account->id }}">
    <input type="hidden" name="date" value="{{ $date }}">
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="acc-card">
                <h2 class="acc-h">{{ translate('Reconcile') }} · {{ $account->label() }}</h2>
                <p class="b-hint">{{ translate('Tick every movement you find on the statement. What stays unticked is money in or out that the statement does not show yet.') }}
                    @if($last) {{ translate(key: 'Last reconciled on {date}.', replace: ['date' => $last->date->format('d/m/Y')]) }}@endif</p>
                <div class="acc-scroll"><table class="acc-t">
                    <thead><tr><th><input type="checkbox" class="form-check-input" id="recAll" aria-label="{{ translate('Tick all') }}"></th><th>{{ translate('Date') }}</th><th>{{ translate('Description') }}</th>
                        <th class="n">{{ translate('In') }}</th><th class="n">{{ translate('Out') }}</th></tr></thead>
                    <tbody>
                    @forelse($lines as $l)
                        <tr>
                            <td><input type="checkbox" class="form-check-input" name="cleared[]" value="{{ $l->id }}" data-in="{{ $l->debit }}" data-out="{{ $l->credit }}" aria-label="{{ translate('On the statement') }}"></td>
                            <td class="text-nowrap">{{ $l->date->format('d/m/Y') }}</td>
                            <td>{{ $l->entry?->memo }}<div class="acc-tag">{{ $l->entry?->number }}</div></td>
                            <td class="n">{{ F::n($l->debit, true) }}</td><td class="n">{{ F::n($l->credit, true) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="acc-empty">{{ translate('Every movement is already matched.') }}</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="acc-card" style="position: sticky; top: 120px">
                <h2 class="acc-h">{{ translate('Statement of') }} {{ \Illuminate\Support\Carbon::parse($date)->format('d/m/Y') }}</h2>
                <label class="form-label" for="recBal">{{ translate('Balance on the statement') }}</label>
                <input type="number" class="form-control mb-3" id="recBal" name="counted" step="1" required value="{{ $statement !== null ? (int)round($statement) : '' }}">
                <table class="acc-t"><tbody>
                    <tr><td>{{ translate('Money in, not on the statement yet') }}</td><td class="n" id="recIn">0</td></tr>
                    <tr><td>{{ translate('Payments out, not on the statement yet') }}</td><td class="n" id="recOut">0</td></tr>
                    <tr><td>{{ translate('The books should show') }}</td><td class="n fw-bold" id="recAdj">0</td></tr>
                    <tr><td>{{ translate('Balance in the books') }}</td><td class="n">{{ F::n($book) }}</td></tr>
                    <tr class="tot"><td>{{ translate('Difference') }}</td><td class="n" id="recDiff">0</td></tr>
                </tbody></table>
                <label class="form-label mt-3" for="recAs">{{ translate('A difference left is') }}</label>
                <select class="form-select" id="recAs" name="post_as">
                    <option value="none">{{ translate('Nothing: it agrees') }}</option>
                    <option value="charges">{{ translate('Charges taken (statement below the books)') }}</option>
                    <option value="income">{{ translate('Interest or money received (statement above the books)') }}</option>
                </select>
                <label class="form-label mt-2" for="recRef">{{ translate('Statement reference (optional)') }}</label>
                <input type="text" class="form-control" id="recRef" name="reference" maxlength="100">
                <button type="submit" class="btn btn-primary mt-3 w-100">{{ translate('Check and reconcile') }}</button>
                <a class="btn btn-light btn-sm mt-2 w-100" href="{{ route($acc . '.money') }}">{{ translate('Back') }}</a>
            </div>
        </div>
    </div>
</form>
@include('busmanagement::partner.accounts._review')
@endsection

@push('script')
<script>
    (function () {
        var book = {{ json_encode(round($book, 2)) }}, f = document.getElementById('recForm');
        var fmt = function (v) { return Math.round(v).toLocaleString('fr-FR'); };
        function calc() {
            var inT = 0, outT = 0;
            f.querySelectorAll('input[name="cleared[]"]').forEach(function (c) { if (!c.checked) { inT += +c.dataset.in; outT += +c.dataset.out; } });
            var st = +(document.getElementById('recBal').value || 0), adj = st + inT - outT, diff = adj - book;
            document.getElementById('recIn').textContent = fmt(inT);
            document.getElementById('recOut').textContent = fmt(outT);
            document.getElementById('recAdj').textContent = fmt(adj);
            var d = document.getElementById('recDiff');
            d.textContent = fmt(diff);
            d.classList.toggle('neg', Math.abs(diff) >= 0.5);
            var as = document.getElementById('recAs');
            if (Math.abs(diff) < 0.5) as.value = 'none'; else if (as.value === 'none') as.value = diff < 0 ? 'charges' : 'income';
        }
        f.addEventListener('change', calc);
        f.addEventListener('input', calc);
        document.getElementById('recAll').addEventListener('change', function (e) {
            f.querySelectorAll('input[name="cleared[]"]').forEach(function (c) { c.checked = e.target.checked; });
            calc();
        });
        calc();
    })();
</script>
@endpush
