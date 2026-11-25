@extends('layouts.app')
@section('title', __('ledger_gl.trial_balance'))

@php
    $m = fn ($v) => \App\Ledger\Ledger::money($v);
    $cur = session('currency')['symbol'] ?? 'FCFA';
    $d = fn ($x) => \Carbon\Carbon::parse($x)->format('d/m/Y');
    $FS = \App\Ledger\Services\FinancialStatements::class;
@endphp

@section('content')
@include('ledger.partials.style')
@include('ledger.general-ledger.partials.print')
<style>@media print { @page { size: landscape; margin: 1cm; } }</style>
<section class="content-header no-print">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ __('ledger_gl.trial_balance') }}</h1>
</section>
<section class="content">
    <div class="box box-primary">
        <div class="box-header with-border no-print">
            <div class="box-tools pull-right">
                @can('trial-balance-export')
                <a href="{{ route('ledger.general-ledger.export-excel', request()->query()) }}" class="btn btn-success btn-sm"><i class="fas fa-file-excel"></i> {{ __('ledger_gl.export_to_excel') }}</a>
                @endcan
                <button type="button" class="btn btn-primary btn-sm" onclick="window.print()"><i class="fas fa-print"></i> {{ __('ledger_gl.print') }}</button>
                <a href="{{ route('ledger.general-ledger.index') }}" class="btn btn-default btn-sm"><i class="fas fa-arrow-left"></i> {{ __('ledger_gl.back') }}</a>
            </div>
        </div>
        <div class="box-body">
            {{-- Report Header --}}
            <div class="text-center" style="margin-bottom: 20px;">
                <h3><strong>{{ optional(session('business'))->name }}</strong></h3>
                <h4>{{ __('ledger_gl.trial_balance') }}</h4>
                @if($startDate && $endDate)
                <p>{{ __('ledger_gl.period') }}: {{ $d($startDate) }} - {{ $d($endDate) }}</p>
                @else
                <p>{{ __('ledger_gl.as_of') }}: {{ $endDate ? $d($endDate) : now()->format('d/m/Y') }}</p>
                @endif
            </div>

            {{-- Date Range Filter --}}
            <form action="{{ route('ledger.general-ledger.trial-balance') }}" method="GET" class="no-print" style="margin-bottom: 15px;">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="fiscal_year_id">{{ __('ledger_gl.fiscal_year') }}</label>
                            <select class="form-control input-sm" id="fiscal_year_id" name="fiscal_year_id">
                                <option value="">{{ __('ledger_gl.all_years') }}</option>
                                @foreach($fiscalYears as $year)
                                <option value="{{ $year->id }}" {{ request('fiscal_year_id') == $year->id ? 'selected' : '' }}>{{ $year->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="start_date">{{ __('ledger_gl.start_date') }}</label>
                            <input type="date" class="form-control input-sm" id="start_date" name="start_date" value="{{ request('start_date') }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="end_date">{{ __('ledger_gl.end_date') }}</label>
                            <input type="date" class="form-control input-sm" id="end_date" name="end_date" value="{{ request('end_date') }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary btn-sm btn-block"><i class="fas fa-filter"></i> {{ __('ledger_gl.filter') }}</button>
                        </div>
                    </div>
                </div>
            </form>

            {{-- Trial Balance Table --}}
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-condensed ap-table" id="trialBalanceTable">
                    <thead>
                        <tr>
                            <th width="10%">{{ __('ledger_gl.account_code') }}</th>
                            <th width="30%">{{ __('ledger_gl.account_name') }}</th>
                            <th width="10%" class="ap-num">{{ __('ledger_gl.opening_debit') }}</th>
                            <th width="10%" class="ap-num">{{ __('ledger_gl.opening_credit') }}</th>
                            <th width="10%" class="ap-num">{{ __('ledger_gl.period_debit') }}</th>
                            <th width="10%" class="ap-num">{{ __('ledger_gl.period_credit') }}</th>
                            <th width="10%" class="ap-num">{{ __('ledger_gl.closing_debit') }}</th>
                            <th width="10%" class="ap-num">{{ __('ledger_gl.closing_credit') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $totalOpeningDebit = 0;
                            $totalOpeningCredit = 0;
                            $totalPeriodDebit = 0;
                            $totalPeriodCredit = 0;
                            $totalClosingDebit = 0;
                            $totalClosingCredit = 0;
                            $currentClass = null;
                        @endphp

                        @foreach($accounts as $account)
                            @php
                                $accountClass = substr($account->account_code, 0, 1);
                                $openingBalance = $account->opening_balance;
                                if ($account->normal_balance == 'debit') {
                                    $openingDebit = $openingBalance > 0 ? $openingBalance : 0;
                                    $openingCredit = $openingBalance < 0 ? abs($openingBalance) : 0;
                                } else {
                                    $openingCredit = $openingBalance > 0 ? $openingBalance : 0;
                                    $openingDebit = $openingBalance < 0 ? abs($openingBalance) : 0;
                                }
                                $periodDebit = $account->period_debit;
                                $periodCredit = $account->period_credit;
                                if ($account->normal_balance == 'debit') {
                                    $closingBalance = $openingBalance + $periodDebit - $periodCredit;
                                    $closingDebit = $closingBalance > 0 ? $closingBalance : 0;
                                    $closingCredit = $closingBalance < 0 ? abs($closingBalance) : 0;
                                } else {
                                    $closingBalance = $openingBalance + $periodCredit - $periodDebit;
                                    $closingCredit = $closingBalance > 0 ? $closingBalance : 0;
                                    $closingDebit = $closingBalance < 0 ? abs($closingBalance) : 0;
                                }
                                $totalOpeningDebit += $openingDebit;
                                $totalOpeningCredit += $openingCredit;
                                $totalPeriodDebit += $periodDebit;
                                $totalPeriodCredit += $periodCredit;
                                $totalClosingDebit += $closingDebit;
                                $totalClosingCredit += $closingCredit;
                                $other = $FS::otherName($account);
                            @endphp

                            {{-- Class Header --}}
                            @if($currentClass != $accountClass)
                                @php $currentClass = $accountClass; @endphp
                                <tr class="active">
                                    <td colspan="8"><strong>{{ __('ledger_gl.class') }} {{ $accountClass }}: {{ __('ledger_gl.ohada_class_'.$accountClass.'_name') }}</strong></td>
                                </tr>
                            @endif

                            {{-- Account Row --}}
                            <tr class="tb-row" data-code="{{ $account->account_code }}">
                                <td><strong>{{ $account->account_code }}</strong></td>
                                <td>
                                    <a href="{{ route('ledger.general-ledger.account', $account->id) }}">{{ $FS::name($account) }}</a>
                                    @if(! $account->is_postable)
                                    <span class="label label-warning" title="{{ __('ledger_gl.heading_account_tip') }}">{{ __('ledger_gl.heading_account') }}</span>
                                    @endif
                                    @if($other)<br><small class="text-muted">{{ $other }}</small>@endif
                                </td>
                                <td class="ap-num">{{ $openingDebit > 0 ? $m($openingDebit) : '-' }}</td>
                                <td class="ap-num">{{ $openingCredit > 0 ? $m($openingCredit) : '-' }}</td>
                                <td class="ap-num">{{ $periodDebit > 0 ? $m($periodDebit) : '-' }}</td>
                                <td class="ap-num">{{ $periodCredit > 0 ? $m($periodCredit) : '-' }}</td>
                                <td class="ap-num">{{ $closingDebit > 0 ? $m($closingDebit) : '-' }}</td>
                                <td class="ap-num">{{ $closingCredit > 0 ? $m($closingCredit) : '-' }}</td>
                            </tr>
                        @endforeach

                        {{-- Grand Total Row --}}
                        <tr class="success" id="tb_total">
                            <td colspan="2" class="text-right"><strong>{{ __('ledger_gl.total') }}</strong></td>
                            <td class="ap-num"><strong>{{ $m($totalOpeningDebit) }}</strong></td>
                            <td class="ap-num"><strong>{{ $m($totalOpeningCredit) }}</strong></td>
                            <td class="ap-num"><strong>{{ $m($totalPeriodDebit) }}</strong></td>
                            <td class="ap-num"><strong>{{ $m($totalPeriodCredit) }}</strong></td>
                            <td class="ap-num"><strong>{{ $m($totalClosingDebit) }}</strong></td>
                            <td class="ap-num"><strong>{{ $m($totalClosingCredit) }}</strong></td>
                        </tr>

                        {{-- Verification Row --}}
                        <tr class="info">
                            <td colspan="2" class="text-right"><strong>{{ __('ledger_gl.difference') }}</strong></td>
                            <td class="ap-num"><span class="label label-{{ abs($totalOpeningDebit - $totalOpeningCredit) < 0.01 ? 'success' : 'danger' }}">{{ $m(abs($totalOpeningDebit - $totalOpeningCredit)) }}</span></td>
                            <td></td>
                            <td class="ap-num"><span class="label label-{{ abs($totalPeriodDebit - $totalPeriodCredit) < 0.01 ? 'success' : 'danger' }}">{{ $m(abs($totalPeriodDebit - $totalPeriodCredit)) }}</span></td>
                            <td></td>
                            <td class="ap-num"><span class="label label-{{ abs($totalClosingDebit - $totalClosingCredit) < 0.01 ? 'success' : 'danger' }}">{{ $m(abs($totalClosingDebit - $totalClosingCredit)) }}</span></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Verification Alert --}}
            @php $isBalanced = abs($totalClosingDebit - $totalClosingCredit) < 0.01; @endphp
            @if($isBalanced)
            <div class="alert alert-success" id="tb_verdict" data-balanced="1">
                <i class="fas fa-check-circle"></i> <strong>{{ __('ledger_gl.trial_balance_verified') }}:</strong> {{ __('ledger_gl.debits_equal_credits') }}
            </div>
            @else
            <div class="alert alert-danger" id="tb_verdict" data-balanced="0">
                <i class="fas fa-exclamation-triangle"></i> <strong>{{ __('ledger_gl.trial_balance_error') }}:</strong> {{ __('ledger_gl.debits_do_not_equal_credits') }}
                ({{ __('ledger_gl.difference') }}: {{ $m(abs($totalClosingDebit - $totalClosingCredit)) }} {{ $cur }})
            </div>
            @endif

            {{-- Summary Information --}}
            <div class="alert alert-info">
                <strong>{{ __('ledger_gl.report_information') }}:</strong><br>
                {{ __('ledger_gl.total_accounts_included') }}: {{ $accounts->count() }}<br>
                {{ __('ledger_gl.report_generated_at') }}: {{ now()->format('d/m/Y H:i:s') }}<br>
                @if($startDate && $endDate)
                {{ __('ledger_gl.reporting_period') }}: {{ $d($startDate) }} - {{ $d($endDate) }}
                @endif
            </div>
        </div>
    </div>
</section>
@endsection
