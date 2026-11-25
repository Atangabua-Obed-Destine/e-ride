{{-- A section total of the sheet. Expects $label, $key (income expenditure capital closing), $favourUp (true: more is good; false: less is good; null: no variance). --}}
@php
    $tb = $totals['budget'][$key];
    $ta = $totals['actual'][$key];
    $tv = $ta - $tb;
    $tGood = ($favourUp === null || ! $tb) ? null : ($favourUp ? $tv >= 0 : $tv <= 0);
@endphp
<tr class="sheet-total">
    <td></td>
    <td>{{ $label }}</td>
    <td class="num prior-col">{{ $previous ? ($priorTotals[$key] ? \App\Ledger\Ledger::money($priorTotals[$key]) : '—') : '—' }}</td>
    <td class="num">{{ $tb ? \App\Ledger\Ledger::money($tb) : '—' }}</td>
    <td class="num">{{ $ta ? \App\Ledger\Ledger::money($ta) : '—' }}</td>
    <td class="num {{ is_null($tGood) || $tv == 0 ? '' : ($tGood ? 'variance-good' : 'variance-bad') }}">{{ $favourUp === null ? '—' : \App\Ledger\Ledger::money($tv) }}</td>
</tr>
