@extends('layouts.app')
@section('title', __('ledger_reports.accounting_reports'))

@section('content')
@include('ledger.partials.style')
<style>
    .ar-card { border-top: 3px solid #d2d6de; }
    .ar-card h4 { margin-top: 0; font-weight: 600; }
    .ar-card.is-info { border-top-color: #00c0ef; }
    .ar-card.is-warning { border-top-color: #f39c12; }
    .ar-card.is-success { border-top-color: #00a65a; }
    .ar-card.is-primary { border-top-color: #3c8dbc; }
    .ar-card.is-purple { border-top-color: #605ca8; }
    .ar-section { color: #64748b; margin: 10px 0 12px; }
</style>
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ __('ledger_reports.accounting_reports') }}</h1>
</section>
<section class="content">
    {{-- Aging Reports --}}
    <h4 class="ar-section"><i class="fas fa-clock"></i> {{ __('ledger_reports.aging_reports') }}</h4>
    <div class="row">
        <div class="col-lg-4 col-md-6">
            <div class="ap-card ar-card is-info">
                <h4><i class="fas fa-file-invoice-dollar text-aqua"></i> {{ __('ledger_reports.receivables_aging') }}</h4>
                <p>{{ __('ledger_reports.receivables_aging_desc') }}</p>
                <a href="{{ route('ledger.accounting-reports.receivables-aging') }}" class="btn btn-info"><i class="fas fa-chart-bar"></i> {{ __('ledger_reports.view_report') }}</a>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="ap-card ar-card is-warning">
                <h4><i class="fas fa-file-invoice text-yellow"></i> {{ __('ledger_reports.payables_aging') }}</h4>
                <p>{{ __('ledger_reports.payables_aging_desc') }}</p>
                <a href="{{ route('ledger.accounting-reports.payables-aging') }}" class="btn btn-warning"><i class="fas fa-chart-bar"></i> {{ __('ledger_reports.view_report') }}</a>
            </div>
        </div>
    </div>

    {{-- Cash Flow Reports --}}
    <h4 class="ar-section"><i class="fas fa-money-bill-wave"></i> {{ __('ledger_reports.cash_flow_reports') }}</h4>
    <div class="row">
        <div class="col-lg-4 col-md-6">
            <div class="ap-card ar-card is-success">
                <h4><i class="fas fa-stream text-green"></i> {{ __('ledger_reports.cash_flow_statement') }}</h4>
                <p>{{ __('ledger_reports.cash_flow_statement_desc') }}</p>
                <a href="{{ route('ledger.accounting-reports.cash-flow-statement') }}" class="btn btn-success"><i class="fas fa-chart-bar"></i> {{ __('ledger_reports.view_report') }}</a>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="ap-card ar-card is-primary">
                <h4><i class="fas fa-chart-line text-blue"></i> {{ __('ledger_reports.comparative_cash_flow') }}</h4>
                <p>{{ __('ledger_reports.comparative_cash_flow_desc') }}</p>
                <a href="{{ route('ledger.accounting-reports.comparative-cash-flow') }}" class="btn btn-primary"><i class="fas fa-chart-bar"></i> {{ __('ledger_reports.view_report') }}</a>
            </div>
        </div>
    </div>

    {{-- Budget Reports: Budget vs Actual is built with the budgets; shown once its screen exists --}}
    @if(Route::has('ledger.accounting-reports.budget-vs-actual'))
    <h4 class="ar-section"><i class="fas fa-balance-scale"></i> {{ __('ledger_reports.budget_reports') }}</h4>
    <div class="row">
        <div class="col-lg-4 col-md-6">
            <div class="ap-card ar-card is-purple">
                <h4><i class="fas fa-balance-scale-left" style="color:#605ca8"></i> {{ __('ledger_reports.budget_vs_actual') }}</h4>
                <p>{{ __('ledger_reports.budget_vs_actual_desc') }}</p>
                <a href="{{ route('ledger.accounting-reports.budget-vs-actual') }}" class="btn bg-purple"><i class="fas fa-chart-bar"></i> {{ __('ledger_reports.view_report') }}</a>
            </div>
        </div>
    </div>
    @endif

    {{-- Quick Stats --}}
    <h4 class="ar-section"><i class="fas fa-tachometer-alt"></i> {{ __('ledger_reports.quick_stats') }}</h4>
    <div class="row">
        <div class="col-lg-3 col-md-6">
            <div class="info-box bg-aqua">
                <span class="info-box-icon"><i class="fas fa-calendar-alt"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('ledger_reports.current_fiscal_year') }}</span>
                    <span class="info-box-number">{{ $currentFiscalYear->name ?? __('ledger_reports.not_set') }}</span>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="info-box bg-green">
                <span class="info-box-icon"><i class="fas fa-check-circle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ __('ledger_reports.fy_status') }}</span>
                    <span class="info-box-number">
                        @if(! $currentFiscalYear)
                            {{ __('ledger_reports.not_set') }}
                        @else
                            {{ $currentFiscalYear->is_closed ? __('ledger_reports.closed') : __('ledger_reports.open') }}
                        @endif
                    </span>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
