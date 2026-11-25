@extends('layouts.app')
@section('title', __('ledger_gl.account_ledger'))

@php
    $m = fn ($v) => \App\Ledger\Ledger::money($v);
    $cur = session('currency')['symbol'] ?? 'FCFA';
    $d = fn ($x) => \Carbon\Carbon::parse($x)->format('d/m/Y');
@endphp

@section('content')
@include('ledger.partials.style')
@include('ledger.general-ledger.partials.print')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ __('ledger_gl.account_ledger') }} - {{ $account->account_code }}: {{ \App\Ledger\Services\Preview::accountName($account) }}</h1>
</section>
<section class="content">
    <div class="box box-primary">
        <div class="box-header with-border no-print">
            <div class="box-tools pull-right">
                @can('general-ledger-export')
                <a href="{{ route('ledger.general-ledger.export-pdf', array_merge(['id' => $account->id], request()->only('start_date', 'end_date'))) }}" class="btn btn-danger btn-sm">
                    <i class="fas fa-file-pdf"></i> {{ __('ledger_gl.export_pdf') }}
                </a>
                @endcan
                <button type="button" class="btn btn-primary btn-sm" onclick="window.print()"><i class="fas fa-print"></i> {{ __('ledger_gl.print') }}</button>
                <a href="{{ route('ledger.general-ledger.index') }}" class="btn btn-default btn-sm"><i class="fas fa-arrow-left"></i> {{ __('ledger_gl.back') }}</a>
            </div>
        </div>
        <div class="box-body">
            {{-- Account Information --}}
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-condensed table-bordered">
                        <tr><th width="40%">{{ __('ledger_gl.account_code') }}</th><td><strong>{{ $account->account_code }}</strong></td></tr>
                        <tr><th>{{ __('ledger_gl.account_name') }}</th><td>{{ $account->account_name }}</td></tr>
                        @if($account->account_name_fr)
                        <tr><th>{{ __('ledger_gl.account_name') }} (FR)</th><td>{{ $account->account_name_fr }}</td></tr>
                        @endif
                        <tr><th>{{ __('ledger_gl.account_type') }}</th><td>{{ __('ledger_gl.type_'.strtolower($account->account_type)) }}</td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-condensed table-bordered">
                        <tr>
                            <th width="40%">{{ __('ledger_gl.period') }}</th>
                            <td>
                                @if($startDate && $endDate)
                                    {{ $d($startDate) }} - {{ $d($endDate) }}
                                @else
                                    {{ __('ledger_gl.all_periods') }}
                                @endif
                            </td>
                        </tr>
                        <tr><th>{{ __('ledger_gl.opening_balance') }}</th><td class="ap-num"><strong>{{ $m($openingBalance) }}</strong> {{ $cur }}</td></tr>
                        <tr><th>{{ __('ledger_gl.closing_balance') }}</th><td class="ap-num"><strong>{{ $m($closingBalance) }}</strong> {{ $cur }}</td></tr>
                        <tr>
                            <th>{{ __('ledger_gl.movement') }}</th>
                            <td class="ap-num">
                                <span class="label label-{{ $closingBalance >= $openingBalance ? 'success' : 'danger' }}">{{ $m(abs($closingBalance - $openingBalance)) }} {{ $cur }}</span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            {{-- Filter Form --}}
            <form action="{{ route('ledger.general-ledger.account', $account->id) }}" method="GET" class="no-print" style="margin-bottom: 15px;">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="start_date">{{ __('ledger_gl.start_date') }}</label>
                            <input type="date" class="form-control input-sm" id="start_date" name="start_date" value="{{ request('start_date') }}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="end_date">{{ __('ledger_gl.end_date') }}</label>
                            <input type="date" class="form-control input-sm" id="end_date" name="end_date" value="{{ request('end_date') }}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary btn-sm btn-block"><i class="fas fa-filter"></i> {{ __('ledger_gl.filter') }}</button>
                        </div>
                    </div>
                </div>
            </form>

            {{-- Transactions Table --}}
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-condensed ap-table" id="account_ledger_table">
                    <thead>
                        <tr>
                            <th width="8%">{{ __('ledger_gl.date') }}</th>
                            <th width="12%">{{ __('ledger_gl.entry_number') }}</th>
                            <th>{{ __('ledger_gl.description') }}</th>
                            <th width="10%" class="ap-num">{{ __('ledger_gl.debit') }}</th>
                            <th width="10%" class="ap-num">{{ __('ledger_gl.credit') }}</th>
                            <th width="12%" class="ap-num">{{ __('ledger_gl.running_balance') }}</th>
                            <th width="8%" class="no-print">{{ __('ledger_gl.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="info">
                            <td colspan="5" class="text-right"><strong>{{ __('ledger_gl.opening_balance') }}</strong></td>
                            <td class="ap-num"><strong>{{ $m($openingBalance) }}</strong></td>
                            <td class="no-print"></td>
                        </tr>

                        @php $runningBalance = $openingBalance; @endphp
                        @forelse($transactions as $transaction)
                        @php
                            $runningBalance += $account->normal_balance == 'debit'
                                ? $transaction->debit - $transaction->credit
                                : $transaction->credit - $transaction->debit;
                        @endphp
                        <tr class="gl-line">
                            <td>{{ $transaction->journalEntry->entry_date->format('d/m/Y') }}</td>
                            <td><a href="{{ route('ledger.journal-entries.show', $transaction->journal_entry_id) }}">{{ $transaction->journalEntry->entry_number }}</a></td>
                            <td>
                                {{ $transaction->description }}
                                @if($transaction->journalEntry->description != $transaction->description)
                                <br><small class="text-muted">{{ $transaction->journalEntry->description }}</small>
                                @endif
                            </td>
                            <td class="ap-num">{{ $transaction->debit > 0 ? $m($transaction->debit) : '-' }}</td>
                            <td class="ap-num">{{ $transaction->credit > 0 ? $m($transaction->credit) : '-' }}</td>
                            <td class="ap-num gl-running"><strong class="text-{{ $runningBalance >= 0 ? 'success' : 'danger' }}">{{ $m($runningBalance) }}</strong></td>
                            <td class="text-center no-print">
                                <a href="{{ route('ledger.journal-entries.show', $transaction->journal_entry_id) }}" class="btn btn-info btn-xs" title="{{ __('ledger_gl.view') }}"><i class="fas fa-eye"></i></a>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="text-center">{{ __('ledger_gl.no_transactions_found') }}</td></tr>
                        @endforelse

                        <tr class="success">
                            <td colspan="5" class="text-right"><strong>{{ __('ledger_gl.closing_balance') }}</strong></td>
                            <td class="ap-num" id="gl_closing"><strong>{{ $m($closingBalance) }}</strong></td>
                            <td class="no-print"></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Summary --}}
            @if($transactions->count() > 0)
            <div class="alert alert-info" style="margin-top: 15px;">
                <strong>{{ __('ledger_gl.summary') }}:</strong>
                {{ __('ledger_gl.total_transactions') }}: {{ $transactions->count() }} |
                {{ __('ledger_gl.total_debit') }}: {{ $m($transactions->sum('debit')) }} {{ $cur }} |
                {{ __('ledger_gl.total_credit') }}: {{ $m($transactions->sum('credit')) }} {{ $cur }} |
                {{ __('ledger_gl.net_movement') }}: {{ $m($closingBalance - $openingBalance) }} {{ $cur }}
            </div>
            @endif
        </div>
    </div>
</section>
@endsection
