@extends('layouts.app')
@section('title', __('ledger_gl.accounts_by_class'))

@php
    $m = fn ($v) => \App\Ledger\Ledger::money($v);
    $cur = session('currency')['symbol'] ?? 'FCFA';
@endphp

@section('content')
@include('ledger.partials.style')
@include('ledger.general-ledger.partials.print')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ __('ledger_gl.ohada_class') }} {{ $classNumber }}: {{ $className }}</h1>
</section>
<section class="content">
    <div class="box box-primary">
        <div class="box-header with-border no-print">
            <div class="box-tools pull-right">
                <button type="button" class="btn btn-primary btn-sm" onclick="window.print()"><i class="fas fa-print"></i> {{ __('ledger_gl.print') }}</button>
                <a href="{{ route('ledger.general-ledger.index') }}" class="btn btn-default btn-sm"><i class="fas fa-arrow-left"></i> {{ __('ledger_gl.back') }}</a>
            </div>
        </div>
        <div class="box-body">
            {{-- Class Information --}}
            <div class="alert alert-info">
                <h4 style="margin-top: 0;">{{ $className }}@if($className_fr !== $className) ({{ $className_fr }})@endif</h4>
                <p style="margin: 0;">{{ __('ledger_gl.class_description', ['class' => $classNumber]) }}</p>
            </div>

            {{-- Date Range Filter --}}
            <form action="{{ route('ledger.general-ledger.by-class', $classNumber) }}" method="GET" class="no-print" style="margin-bottom: 15px;">
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

            {{-- Accounts Table --}}
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-condensed ap-table" id="by_class_table">
                    <thead>
                        <tr>
                            <th width="12%">{{ __('ledger_gl.account_code') }}</th>
                            <th>{{ __('ledger_gl.account_name') }}</th>
                            <th width="10%" class="text-center">{{ __('ledger_gl.type') }}</th>
                            <th width="12%" class="ap-num">{{ __('ledger_gl.debit') }}</th>
                            <th width="12%" class="ap-num">{{ __('ledger_gl.credit') }}</th>
                            <th width="12%" class="ap-num">{{ __('ledger_gl.balance') }}</th>
                            <th width="8%" class="no-print">{{ __('ledger_gl.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $classDebitTotal = 0;
                            $classCreditTotal = 0;
                        @endphp
                        @foreach($accounts as $account)
                            @php
                                $balance = $account->normal_balance == 'debit'
                                    ? $account->total_debit - $account->total_credit
                                    : $account->total_credit - $account->total_debit;
                                $classDebitTotal += $account->total_debit;
                                $classCreditTotal += $account->total_credit;
                                $other = \App\Ledger\Services\FinancialStatements::otherName($account);
                            @endphp
                            <tr class="{{ $account->account_category == 'heading' ? 'active' : '' }}" data-code="{{ $account->account_code }}">
                                <td><strong>{{ $account->account_code }}</strong></td>
                                <td>
                                    {{ \App\Ledger\Services\Preview::accountName($account) }}
                                    @if($other)<br><small class="text-muted">{{ $other }}</small>@endif
                                </td>
                                <td class="text-center">
                                    @if($account->account_category == 'heading')
                                    <span class="label label-default">{{ __('ledger_gl.cat_header') }}</span>
                                    @elseif(in_array($account->account_category, ['total', 'subtotal']))
                                    <span class="label label-info">{{ __('ledger_gl.cat_summary') }}</span>
                                    @else
                                    <span class="label label-success">{{ __('ledger_gl.cat_detail') }}</span>
                                    @endif
                                </td>
                                <td class="ap-num">{{ $account->total_debit > 0 ? $m($account->total_debit) : '-' }}</td>
                                <td class="ap-num">{{ $account->total_credit > 0 ? $m($account->total_credit) : '-' }}</td>
                                <td class="ap-num"><strong class="text-{{ $balance >= 0 ? 'success' : 'danger' }}">{{ $m($balance) }}</strong></td>
                                <td class="text-center no-print" style="white-space: nowrap;">
                                    @if($account->account_category == 'detail')
                                    <a href="{{ route('ledger.general-ledger.account', $account->id) }}" class="btn btn-info btn-xs" title="{{ __('ledger_gl.view_ledger') }}"><i class="fas fa-book"></i></a>
                                    @endif
                                    <a href="{{ route('ledger.chart-of-accounts.show', $account->id) }}" class="btn btn-default btn-xs" title="{{ __('ledger_gl.view') }}"><i class="fas fa-eye"></i></a>
                                </td>
                            </tr>
                        @endforeach

                        {{-- Class Total Row --}}
                        <tr class="success">
                            <td colspan="3" class="text-right"><strong>{{ __('ledger_gl.class_total') }}</strong></td>
                            <td class="ap-num"><strong>{{ $m($classDebitTotal) }}</strong></td>
                            <td class="ap-num"><strong>{{ $m($classCreditTotal) }}</strong></td>
                            <td class="ap-num"><strong>{{ $m(abs($classDebitTotal - $classCreditTotal)) }}</strong></td>
                            <td class="no-print"></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Summary --}}
            @if($accounts->count() > 0)
            <div class="alert alert-info" style="margin-top: 15px;">
                <strong>{{ __('ledger_gl.summary') }}:</strong>
                {{ __('ledger_gl.total_accounts') }}: {{ $accounts->count() }} |
                {{ __('ledger_gl.total_debit') }}: {{ $m($classDebitTotal) }} {{ $cur }} |
                {{ __('ledger_gl.total_credit') }}: {{ $m($classCreditTotal) }} {{ $cur }} |
                {{ __('ledger_gl.difference') }}: {{ $m(abs($classDebitTotal - $classCreditTotal)) }} {{ $cur }}
            </div>
            @else
            <div class="alert alert-warning">{{ __('ledger_gl.no_accounts_found_in_class') }}</div>
            @endif

            {{-- Quick Navigation --}}
            <div class="btn-group btn-group-sm no-print" style="margin-top: 10px;">
                @for($i = 1; $i <= 8; $i++)
                <a href="{{ route('ledger.general-ledger.by-class', $i) }}" class="btn {{ $i == $classNumber ? 'btn-primary' : 'btn-default' }}">{{ __('ledger_gl.class') }} {{ $i }}</a>
                @endfor
            </div>
        </div>
    </div>
</section>
@endsection
