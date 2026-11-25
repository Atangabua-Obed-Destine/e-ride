@extends('layouts.app')
@section('title', __('ledger_gl.general_ledger'))

@php
    $dual = fn ($a, $b) => __('ledger_gl.'.$a) === __('ledger_gl.'.$b) ? __('ledger_gl.'.$a) : __('ledger_gl.'.$a).' ('.__('ledger_gl.'.$b).')';
@endphp

@section('content')
@include('ledger.partials.style')
<style>
    .gl-quick { border-radius: 6px; padding: 16px; color: #fff; display: flex; align-items: center; gap: 16px; margin-bottom: 15px; }
    .gl-quick h4 { margin: 0 0 4px; font-weight: 600; }
    .gl-quick p { margin: 0 0 8px; opacity: .9; }
    .gl-class-btn { display: block; text-align: left; padding: 12px 14px; margin-bottom: 15px; white-space: normal; }
</style>
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ $dual('general_ledger', 'grand_livre') }}</h1>
</section>
<section class="content">
    <div class="box box-primary">
        <div class="box-body">
            {{-- Quick Links --}}
            <h4>{{ __('ledger_gl.quick_access') }}</h4>
            <div class="row">
                @can('trial-balance-view')
                <div class="col-md-4">
                    <div class="gl-quick bg-aqua">
                        <i class="fas fa-book fa-3x"></i>
                        <div>
                            <h4>{{ __('ledger_gl.trial_balance') }}</h4>
                            <p><small>{{ __('ledger_gl.balance_de_verification') }}</small></p>
                            <a href="{{ route('ledger.general-ledger.trial-balance') }}" class="btn btn-default btn-sm">{{ __('ledger_gl.view_report') }} <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
                @endcan
                @can('income-statement-view')
                <div class="col-md-4">
                    <div class="gl-quick bg-green">
                        <i class="fas fa-chart-line fa-3x"></i>
                        <div>
                            <h4>{{ __('ledger_gl.income_statement') }}</h4>
                            <p><small>{{ __('ledger_gl.compte_de_resultat') }}</small></p>
                            <a href="{{ route('ledger.general-ledger.income-statement') }}" class="btn btn-default btn-sm">{{ __('ledger_gl.view_report') }} <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
                @endcan
                @can('balance-sheet-view')
                <div class="col-md-4">
                    <div class="gl-quick bg-yellow">
                        <i class="fas fa-balance-scale fa-3x"></i>
                        <div>
                            <h4>{{ __('ledger_gl.balance_sheet') }}</h4>
                            <p><small>{{ __('ledger_gl.bilan') }}</small></p>
                            <a href="{{ route('ledger.general-ledger.balance-sheet') }}" class="btn btn-default btn-sm">{{ __('ledger_gl.view_report') }} <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
                @endcan
            </div>

            {{-- Account Selector --}}
            <h4 style="margin-top: 10px;">{{ __('ledger_gl.account_ledger') }}</h4>
            <div class="ap-card">
                <form action="{{ route('ledger.general-ledger.account', ':account_id') }}" method="GET" id="account-form">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="account_id">{{ __('ledger_gl.select_account') }}</label>
                                <select class="form-control select2" id="account_id" name="account_id" required style="width: 100%;">
                                    <option value="">{{ __('ledger_gl.select_account') }}</option>
                                    @foreach($accounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->account_code }} - {{ \App\Ledger\Services\Preview::accountName($account) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="start_date">{{ __('ledger_gl.start_date') }}</label>
                                <input type="date" class="form-control" id="start_date" name="start_date">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="end_date">{{ __('ledger_gl.end_date') }}</label>
                                <input type="date" class="form-control" id="end_date" name="end_date">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-search"></i> {{ __('ledger_gl.view') }}</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            {{-- Browse by Class --}}
            <h4>{{ __('ledger_gl.browse_by_class') }}</h4>
            <div class="row">
                @for($i = 1; $i <= 9; $i++)
                <div class="col-lg-3 col-md-4 col-sm-6">
                    <a href="{{ route('ledger.general-ledger.by-class', $i) }}" class="btn btn-default btn-block gl-class-btn">
                        <i class="fas fa-folder-open"></i>
                        <strong>{{ __('ledger_gl.classe') }} {{ $i }}:</strong><br>
                        <small>{{ __('ledger_gl.class_'.$i.'_name') }}</small>
                    </a>
                </div>
                @endfor
            </div>
        </div>
    </div>
</section>
@endsection

@section('javascript')
<script>
$(document).ready(function () {
    $('#account_id').select2({ placeholder: @json(__('ledger_gl.select_account')) });

    $('#account-form').submit(function (e) {
        e.preventDefault();
        var accountId = $('#account_id').val();
        if (!accountId) {
            alert(@json(__('ledger_gl.please_select_account')));
            return;
        }
        var url = $(this).attr('action').replace(':account_id', accountId);
        var startDate = $('#start_date').val();
        var endDate = $('#end_date').val();
        if (startDate) url += '?start_date=' + startDate;
        if (endDate) url += (startDate ? '&' : '?') + 'end_date=' + endDate;
        window.location.href = url;
    });
});
</script>
@endsection
