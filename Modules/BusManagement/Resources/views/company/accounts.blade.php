@extends('busmanagement::company.layout')

@section('title', translate('Accounts'))

@php use Modules\BusManagement\Lib\AccFormat as F; @endphp

@push('head')
    @include('busmanagement::partner.accounts._style')
@endpush

@section('content')
<h1 class="fs-5 fw-bold mb-1">{{ translate('Accounts') }}</h1>
<p class="b-hint mb-3">{{ translate('Each branch keeps its own books. Here they are side by side, for the period chosen. Ticket sales count when the bus leaves.') }}</p>
@include('busmanagement::company._filters')

<div class="acc-card mb-3">
    <div class="acc-scroll"><table class="acc-t">
        <thead><tr>
            <th>{{ translate('Branch') }}</th>
            <th class="n">{{ translate('Ticket sales') }}</th><th class="n">{{ translate('Other income') }}</th><th class="n">{{ translate('Fuel') }}</th>
            <th class="n">{{ translate('All spending') }}</th><th class="n">{{ translate('Result') }}</th><th class="n">{{ translate('Cash short') }}</th>
            <th class="n">{{ translate('Money held') }}</th><th class="n">{{ translate('With agents') }}</th><th class="n">{{ translate('Tickets for trips ahead') }}</th><th class="n">{{ translate('Owed') }}</th><th></th>
        </tr></thead>
        <tbody>
        @foreach($rows as $r)
            @php $f = $r['f']; @endphp
            <tr>
                <td class="fw-semibold">{{ $r['branch']->branchLabel() }}<div class="acc-tag">{{ $r['branch']->origin_city }}</div></td>
                @if($f)
                    <td class="n">{{ F::n($f['tickets']) }}</td><td class="n">{{ F::n($f['other_income']) }}</td><td class="n">{{ F::n($f['fuel']) }}</td>
                    <td class="n">{{ F::n($f['spending']) }}</td><td class="n fw-bold {{ F::cls($f['result']) }}">{{ F::n($f['result']) }}</td>
                    <td class="n {{ $f['cash_short'] > 0 ? 'neg' : '' }}">{{ F::n($f['cash_short'], true) }}</td>
                    <td class="n">{{ F::n($f['money']) }}</td><td class="n">{{ F::n($f['agents']) }}</td><td class="n">{{ F::n($f['held']) }}</td><td class="n">{{ F::n($f['owes']) }}</td>
                    <td class="text-nowrap">
                        @if(!$f['balanced'] || $f['pending'] > 0)<span class="b-tag bad" title="{{ implode(' · ', $f['health']) }}">{{ translate('Check') }}</span>@endif
                        <a class="btn btn-soft btn-sm" href="{{ request()->fullUrlWithQuery(['branch' => $r['branch']->id, 'tab' => 'income']) }}">{{ translate('Open') }}</a>
                    </td>
                @else
                    <td colspan="11" class="acc-tag">{{ translate('This branch has not opened its books yet (Accounts, in the branch portal).') }}</td>
                @endif
            </tr>
        @endforeach
        </tbody>
        @if(collect($rows)->filter(fn($r) => $r['f'])->count() > 1)
            <tfoot><tr class="tot"><td>{{ translate('All branches') }}</td>
                @foreach(['tickets', 'other_income', 'fuel', 'spending', 'result', 'cash_short', 'money', 'agents', 'held', 'owes'] as $k)<td class="n {{ $k === 'result' ? F::cls($totals[$k]) : '' }}">{{ F::n($totals[$k]) }}</td>@endforeach<td></td></tr></tfoot>
        @endif
    </table></div>
    <div class="b-hint mt-2">{{ translate('Amounts in FCFA') }} · {{ $from->format('d/m/Y') }} - {{ $to->format('d/m/Y') }}. {{ translate('Money held, agents cash and what is owed are as of today.') }}</div>
</div>

@if($one && $tab)
    <div class="acc-card">
        <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
            <h2 class="acc-h m-0">{{ $one['branch']->branchLabel() }}</h2>
            @foreach(['income' => translate('Income statement'), 'profit' => translate('Profit by bus, route or trip'), 'cash' => translate('Cash flow'), 'balance' => translate('Balance sheet')] as $k => $label)
                <a class="btn btn-sm {{ $tab === $k ? 'btn-dark' : 'btn-soft' }}" href="{{ request()->fullUrlWithQuery(['tab' => $k]) }}">{{ $label }}</a>
            @endforeach
            @if($tab === 'profit')
                <span class="ms-2 d-flex gap-1">@foreach(['bus' => translate('Bus'), 'route' => translate('Route'), 'trip' => translate('Trip')] as $k => $label)
                    <a class="btn btn-sm {{ $by === $k ? 'btn-dark' : 'btn-light' }}" href="{{ request()->fullUrlWithQuery(['by' => $k]) }}">{{ $label }}</a>@endforeach</span>
            @endif
        </div>
        @include('busmanagement::partner.accounts._report_tables', ['data' => $detail])
    </div>
@endif
@endsection
