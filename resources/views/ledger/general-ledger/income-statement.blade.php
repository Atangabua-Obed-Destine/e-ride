@extends('layouts.app')
@section('title', __('ledger_gl.income_statement').' - '.__('ledger_gl.accounting_view'))

@php
    $m = fn ($v) => \App\Ledger\Ledger::money($v);
    $cur = session('currency')['symbol'] ?? 'FCFA';
    $d = fn ($x) => \Carbon\Carbon::parse($x)->format('d/m/Y');
    $FS = \App\Ledger\Services\FinancialStatements::class;
    $dual = fn ($a, $b) => __('ledger_gl.'.$a) === __('ledger_gl.'.$b) ? __('ledger_gl.'.$a) : __('ledger_gl.'.$a).' ('.__('ledger_gl.'.$b).')';
@endphp

@section('content')
@include('ledger.partials.style')
@include('ledger.general-ledger.partials.print')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
        {{ $dual('income_statement', 'compte_de_resultat') }}
        <span class="label label-info" style="font-size: 12px; vertical-align: middle;">{{ __('ledger_gl.accounting_view') }}</span>
    </h1>
    <small class="text-muted">
        @if($startDate)
            {{ $d($startDate) }} - {{ $d($endDate) }}
        @else
            {{ __('ledger_gl.up_to') }} {{ $d($endDate) }}
        @endif
    </small>
</section>
<section class="content">
    <div class="box box-primary">
        <div class="box-header with-border no-print">
            <div class="box-tools pull-right">
                <a href="{{ route('ledger.general-ledger.index') }}" class="btn btn-default btn-sm"><i class="fas fa-arrow-left"></i> {{ __('ledger_gl.back') }}</a>
                <button type="button" class="btn btn-info btn-sm" onclick="window.print()"><i class="fas fa-print"></i> {{ __('ledger_gl.print') }}</button>
                @can('income-statement-export')
                <a href="{{ route('ledger.general-ledger.income-statement-pdf', request()->query()) }}" class="btn btn-danger btn-sm" target="_blank"><i class="fas fa-file-pdf"></i> {{ __('ledger_gl.export_pdf') }}</a>
                <a href="{{ route('ledger.general-ledger.income-statement-excel', request()->query()) }}" class="btn btn-success btn-sm"><i class="fas fa-file-excel"></i> {{ __('ledger_gl.export_excel') }}</a>
                @endcan
            </div>
        </div>
        <div class="box-body">
            {{-- Filters --}}
            <form action="{{ route('ledger.general-ledger.income-statement') }}" method="GET" class="form-inline no-print" style="margin-bottom: 20px;">
                <div class="form-group" style="margin-right: 15px;">
                    <label for="start_date" style="margin-right: 6px;">{{ __('ledger_gl.start_date') }}:</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="{{ $startDate ?? '' }}">
                </div>
                <div class="form-group" style="margin-right: 15px;">
                    <label for="end_date" style="margin-right: 6px;">{{ __('ledger_gl.end_date') }}:</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="{{ $endDate }}" required>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> {{ __('ledger_gl.apply_filter') }}</button>
            </form>

            <div class="alert alert-info no-print">
                <i class="fas fa-info-circle"></i>
                <strong>{{ __('ledger_gl.accounting_view') }}:</strong> {{ __('ledger_gl.this_report_uses_posted_journal_entries') }}
            </div>

            {{-- Income Statement Table --}}
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="is_table">
                    <thead class="gl-thead-dark">
                        <tr>
                            <th width="60%"><strong>{{ __('ledger_gl.account') }}</strong></th>
                            <th width="40%" class="ap-num"><strong>{{ __('ledger_gl.amount') }} ({{ $cur }})</strong></th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- REVENUE SECTION (Class 7) --}}
                        <tr class="success">
                            <td colspan="2"><h4 style="margin: 0;"><i class="fas fa-arrow-circle-up"></i> <strong>{{ __('ledger_gl.revenue') }} ({{ __('ledger_gl.class') }} 7)</strong></h4></td>
                        </tr>
                        @forelse($revenueByAccount as $account)
                        <tr data-code="{{ $account->account_code }}">
                            <td style="padding-left: 24px;"><i class="fas fa-angle-right text-muted"></i> {{ $account->account_code }} - {{ $FS::name($account) }}</td>
                            <td class="ap-num">{{ $m($account->amount) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="2" class="text-center text-muted">{{ __('ledger_gl.no_revenue_recorded') }}</td></tr>
                        @endforelse
                        <tr class="success" id="is_total_revenue">
                            <td><strong>{{ __('ledger_gl.total_revenue') }}</strong></td>
                            <td class="ap-num"><strong>{{ $m($totalRevenue) }}</strong></td>
                        </tr>

                        {{-- EXPENSES SECTION (Class 6) --}}
                        <tr class="danger">
                            <td colspan="2"><h4 style="margin: 0;"><i class="fas fa-arrow-circle-down"></i> <strong>{{ __('ledger_gl.expenses') }} ({{ __('ledger_gl.class') }} 6)</strong></h4></td>
                        </tr>
                        @forelse($expensesByAccount as $account)
                        <tr data-code="{{ $account->account_code }}">
                            <td style="padding-left: 24px;"><i class="fas fa-angle-right text-muted"></i> {{ $account->account_code }} - {{ $FS::name($account) }}</td>
                            <td class="ap-num">{{ $m($account->amount) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="2" class="text-center text-muted">{{ __('ledger_gl.no_expenses_recorded') }}</td></tr>
                        @endforelse
                        <tr class="danger" id="is_total_expenses">
                            <td><strong>{{ __('ledger_gl.total_expenses') }}</strong></td>
                            <td class="ap-num"><strong>{{ $m($totalExpenses) }}</strong></td>
                        </tr>

                        {{-- OPERATING PROFIT/LOSS --}}
                        <tr class="{{ $operatingProfit >= 0 ? 'info' : 'warning' }}">
                            <td><strong>{{ $dual('operating_result', 'resultat_exploitation') }}</strong></td>
                            <td class="ap-num"><strong>{{ $m($operatingProfit) }}</strong></td>
                        </tr>

                        {{-- OTHER RESULTS SECTION (Class 8) --}}
                        @if($otherResultsByAccount->isNotEmpty())
                        <tr class="info">
                            <td colspan="2"><h4 style="margin: 0;"><i class="fas fa-plus-minus"></i> <strong>{{ __('ledger_gl.other_results') }} ({{ __('ledger_gl.class') }} 8)</strong></h4></td>
                        </tr>
                        @foreach($otherResultsByAccount as $account)
                        <tr data-code="{{ $account->account_code }}">
                            <td style="padding-left: 24px;"><i class="fas fa-angle-right text-muted"></i> {{ $account->account_code }} - {{ $FS::name($account) }}</td>
                            <td class="ap-num">{{ $m($account->amount) }}</td>
                        </tr>
                        @endforeach
                        <tr class="info">
                            <td><strong>{{ __('ledger_gl.total_other_results') }}</strong></td>
                            <td class="ap-num"><strong>{{ $m($totalOtherResults) }}</strong></td>
                        </tr>
                        @endif

                        {{-- NET PROFIT/LOSS --}}
                        <tr class="{{ $netProfit >= 0 ? 'success' : 'danger' }}" style="font-size: 16px;" id="is_net" data-net="{{ $netProfit }}">
                            <td>
                                <strong>
                                    @if($netProfit >= 0)
                                        <i class="fas fa-check-circle text-success"></i> {{ __('ledger_gl.net_profit') }} ({{ __('ledger_gl.benefice_net') }})
                                    @else
                                        <i class="fas fa-times-circle text-danger"></i> {{ __('ledger_gl.net_loss') }} ({{ __('ledger_gl.perte_nette') }})
                                    @endif
                                </strong>
                            </td>
                            <td class="ap-num"><strong>{{ $m(abs($netProfit)) }}</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Financial Metrics --}}
            <div class="row" style="margin-top: 15px;">
                <div class="col-md-6">
                    <div class="ap-card">
                        <h4 style="margin-top: 0;">{{ __('ledger_gl.financial_metrics') }}</h4>
                        <table class="table table-condensed" style="margin: 0;">
                            <tr>
                                <td>{{ __('ledger_gl.operating_margin') }}:</td>
                                <td class="ap-num"><strong>{{ number_format($operatingMargin, 2) }}%</strong></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
