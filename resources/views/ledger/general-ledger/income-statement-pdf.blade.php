@extends('ledger.general-ledger.pdf-layout')

@php
    $fmt = fn ($v) => round((float) $v) < 0 ? '('.\App\Ledger\Ledger::money(abs((float) $v)).')' : \App\Ledger\Ledger::money($v);
    $FS = \App\Ledger\Services\FinancialStatements::class;
    $dual = fn ($a, $b) => __('ledger_gl.'.$a) === __('ledger_gl.'.$b) ? __('ledger_gl.'.$a) : __('ledger_gl.'.$a).' ('.__('ledger_gl.'.$b).')';
@endphp

@section('title', __('ledger_gl.income_statement'))
@section('report_title', $dual('income_statement', 'compte_de_resultat'))

@section('content')
    <p class="text-center" style="margin-bottom: 14px;">
        <strong>{{ __('ledger_gl.period') }}:</strong>
        {{ $startDate ? \Carbon\Carbon::parse($startDate)->format('d/m/Y') : __('ledger_gl.from_start_of_books') }}
        &ndash; {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}
        &middot; {{ __('ledger_gl.from_posted_entries') }}
    </p>

    <div class="section-title">{{ $dual('revenue', 'produits') }} &ndash; {{ __('ledger_gl.class') }} 7</div>
    <table>
        <tbody>
            @forelse($revenue as $account)
            <tr><td>{{ $account->account_code }} - {{ $FS::name($account) }}</td><td class="text-right">{{ $fmt($account->amount) }}</td></tr>
            @empty
            <tr><td colspan="2" class="text-center">{{ __('ledger_gl.no_revenue_recorded') }}</td></tr>
            @endforelse
            <tr class="totals-row"><td>{{ __('ledger_gl.total_revenue') }}</td><td class="text-right">{{ $fmt($totalRevenue) }}</td></tr>
        </tbody>
    </table>

    <div class="section-title">{{ $dual('expenses', 'charges') }} &ndash; {{ __('ledger_gl.class') }} 6</div>
    <table>
        <tbody>
            @forelse($expenses as $account)
            <tr><td>{{ $account->account_code }} - {{ $FS::name($account) }}</td><td class="text-right">{{ $fmt($account->amount) }}</td></tr>
            @empty
            <tr><td colspan="2" class="text-center">{{ __('ledger_gl.no_expenses_recorded') }}</td></tr>
            @endforelse
            <tr class="totals-row"><td>{{ __('ledger_gl.total_expenses') }}</td><td class="text-right">{{ $fmt($totalExpenses) }}</td></tr>
        </tbody>
    </table>

    @if($otherResults->isNotEmpty())
    <div class="section-title">{{ __('ledger_gl.other_results') }} (HAO &ndash; {{ __('ledger_gl.class') }} 8)</div>
    <table>
        <tbody>
            @foreach($otherResults as $account)
            <tr><td>{{ $account->account_code }} - {{ $FS::name($account) }}</td><td class="text-right">{{ $fmt($account->amount) }}</td></tr>
            @endforeach
            <tr class="totals-row"><td>{{ __('ledger_gl.total_other_results') }}</td><td class="text-right">{{ $fmt($totalOtherResults) }}</td></tr>
        </tbody>
    </table>
    @endif

    <table style="margin-top: 12px;">
        <tbody>
            <tr class="totals-row">
                <td>{{ $netProfit >= 0 ? __('ledger_gl.net_profit') : __('ledger_gl.net_loss') }} ({{ $netProfit >= 0 ? __('ledger_gl.benefice_net') : __('ledger_gl.perte_nette') }})</td>
                <td class="text-right">{{ $fmt($netProfit) }}</td>
            </tr>
        </tbody>
    </table>
@endsection
