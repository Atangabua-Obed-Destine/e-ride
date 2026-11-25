@extends($accLayout)

@section('title', translate('Budget'))

@php use Modules\BusManagement\Lib\AccFormat as F; @endphp

@push('head')
    @include('busmanagement::partner.accounts._style')
    <style>
        .bud input { width: 92px; height: 32px; padding: 0 6px; text-align: right; border: 1px solid #cfd5da; border-radius: 6px; font: inherit; font-size: 12.5px; }
        .bud td { padding: 4px; }
        .bud th { padding: 4px; }
        .bud .acct { min-width: 200px; position: sticky; left: 0; background: #fff; z-index: 1; }
    </style>
@endpush

@section('content')
@include('busmanagement::partner.accounts._nav')

<div class="acc-card">
    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
        <h2 class="acc-h m-0">{{ translate('Budget') }} {{ $year }}</h2>
        <form method="GET" class="d-flex gap-2 ms-auto">
            <select class="form-select form-select-sm" name="year" onchange="this.form.submit()" aria-label="{{ translate('Year') }}">
                @for($y = $books->starts_on->year; $y <= today()->year + 1; $y++)<option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>@endfor
            </select>
        </form>
        <a class="btn btn-soft btn-sm" href="{{ route($acc . '.reports', ['tab' => 'budget', 'from' => $year . '-01-01', 'to' => min($year . '-12-31', today()->toDateString())]) }}">{{ translate('Budget against actual') }}</a>
    </div>
    <p class="b-hint">{{ translate('What you plan to earn and spend each month, per account. Type a monthly figure in January and use = to copy it to every month. A budget is a plan: nothing is posted.') }}</p>
    <form method="POST" action="{{ route($acc . '.budget.save') }}">
        @csrf
        <input type="hidden" name="year" value="{{ $year }}">
        <div class="acc-scroll"><table class="acc-t bud">
            <thead><tr><th class="acct">{{ translate('Account') }}</th>
                @for($m = 1; $m <= 12; $m++)<th class="n">{{ \Illuminate\Support\Carbon::create($year, $m, 1)->translatedFormat('M') }}</th>@endfor
                <th class="n">{{ translate('Year') }}</th></tr></thead>
            <tbody>
            @foreach([['revenue', translate('Income')], ['expense', translate('Spending')]] as [$type, $label])
                <tr class="sub"><td class="acct" colspan="14">{{ $label }}</td></tr>
                @foreach($accounts->where('type', $type) as $a)
                    @php $row = $budget[(string)$a->id] ?? []; @endphp
                    <tr data-row>
                        <td class="acct">{{ $a->label() }} <button type="button" class="btn btn-light btn-sm py-0 px-1" data-copy title="{{ translate('Same every month') }}" aria-label="{{ translate('Same every month') }}">=</button></td>
                        @for($m = 1; $m <= 12; $m++)
                            <td><input type="number" min="0" step="1" name="amounts[{{ $a->id }}][{{ $m }}]" value="{{ isset($row[$m]) && $row[$m] > 0 ? (int)$row[$m] : '' }}" aria-label="{{ $a->label() }} {{ $m }}"></td>
                        @endfor
                        <td class="n fw-bold" data-total>{{ F::n(array_sum($row)) }}</td>
                    </tr>
                @endforeach
            @endforeach
            </tbody>
        </table></div>
        <button class="btn btn-dark mt-3">{{ translate('Save the budget') }}</button>
    </form>
</div>
@endsection

@push('script')
<script>
    (function () {
        function total(tr) {
            var t = 0; tr.querySelectorAll('input').forEach(function (i) { t += +(i.value || 0); });
            tr.querySelector('[data-total]').textContent = Math.round(t).toLocaleString('fr-FR');
        }
        document.querySelectorAll('[data-row]').forEach(function (tr) {
            tr.addEventListener('input', function () { total(tr); });
            tr.querySelector('[data-copy]').addEventListener('click', function () {
                var inputs = tr.querySelectorAll('input'), first = inputs[0].value;
                inputs.forEach(function (i) { i.value = first; });
                total(tr);
            });
        });
    })();
</script>
@endpush
