@extends('ledger.general-ledger.pdf-layout')

@section('title', __('ledger_gl.account_ledger'))
@section('report_title', __('ledger_gl.account_ledger').' — '.$account->account_code.' '.\App\Ledger\Services\Preview::accountName($account))

@php
    $fmt = fn ($v) => \App\Ledger\Ledger::money((float) $v);
    $debitNormal = $account->normal_balance === 'debit';
    $running = (float) $openingBalance;
@endphp

@section('content')
    <p class="text-center" style="margin-bottom: 14px;">
        <strong>{{ __('ledger_gl.period') }}:</strong>
        {{ $startDate ? \Carbon\Carbon::parse($startDate)->format('d/m/Y') : __('ledger_gl.from_start_of_books') }}
        &ndash; {{ $endDate ? \Carbon\Carbon::parse($endDate)->format('d/m/Y') : now()->format('d/m/Y') }}
        &middot; {{ __('ledger_gl.normal_balance') }}: {{ $debitNormal ? __('ledger_gl.normal_debit') : __('ledger_gl.normal_credit') }}
    </p>

    <table>
        <thead>
            <tr>
                <th>{{ __('ledger_gl.date') }}</th>
                <th>{{ __('ledger_gl.entry_number') }}</th>
                <th>{{ __('ledger_gl.description') }}</th>
                <th class="text-right">{{ __('ledger_gl.debit') }}</th>
                <th class="text-right">{{ __('ledger_gl.credit') }}</th>
                <th class="text-right">{{ __('ledger_gl.running_balance') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr class="font-bold">
                <td colspan="5">{{ __('ledger_gl.opening_balance') }}</td>
                <td class="text-right">{{ $fmt($openingBalance) }}</td>
            </tr>
            @foreach($transactions as $t)
            @php $running += $debitNormal ? ($t->debit - $t->credit) : ($t->credit - $t->debit); @endphp
            <tr>
                <td>{{ $t->journalEntry->entry_date->format('d/m/Y') }}</td>
                <td>{{ $t->journalEntry->entry_number }}</td>
                <td>{{ $t->description ?: $t->journalEntry->description }}</td>
                <td class="text-right">{{ $t->debit > 0 ? $fmt($t->debit) : '' }}</td>
                <td class="text-right">{{ $t->credit > 0 ? $fmt($t->credit) : '' }}</td>
                <td class="text-right">{{ $fmt($running) }}</td>
            </tr>
            @endforeach
            <tr class="totals-row">
                <td colspan="3">{{ __('ledger_gl.closing_balance') }} ({{ $transactions->count() }} {{ __('ledger_gl.entries') }})</td>
                <td class="text-right">{{ $fmt($transactions->sum('debit')) }}</td>
                <td class="text-right">{{ $fmt($transactions->sum('credit')) }}</td>
                <td class="text-right">{{ $fmt($closingBalance) }}</td>
            </tr>
        </tbody>
    </table>
@endsection
