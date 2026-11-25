@extends('layouts.app')
@section('title', $title)
@section('css')
@include('ledger.partials.style')
@endsection

{{-- paxhitestbed admin/payroll-setting/index: how payroll runs, the bank file, and loans and advances. --}}
@section('content')
@php $yn = fn ($name, $value) => view('ledger.payroll.payroll-setting.yesno', ['name' => $name, 'value' => $value])->render(); @endphp
<section class="content">
    @include('ledger.account.errors')
    <form action="{{ route('ledger.payroll-setting.update') }}" method="post">
        @csrf
        <div class="row">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">@lang('ledger_payroll.how_payroll_runs')</h5></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">@lang('ledger_payroll.require_approval')</label>{!! $yn('require_approval', $settings->require_approval) !!}
                            <small class="text-muted">@lang('ledger_payroll.require_approval_help')</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">@lang('ledger_payroll.carry_over_lines')</label>{!! $yn('carry_over_lines', $settings->carry_over_lines) !!}
                            <small class="text-muted">@lang('ledger_payroll.carry_over_help')</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">@lang('ledger_payroll.prorate_partial_months')</label>{!! $yn('prorate_partial_months', $settings->prorate_partial_months) !!}
                            <small class="text-muted">@lang('ledger_payroll.prorate_help')</small>
                        </div>
                        <div class="mb-0">
                            <label class="form-label" for="proration_basis">@lang('ledger_payroll.proration_basis')</label>
                            <select class="form-select" name="proration_basis" id="proration_basis">
                                @foreach($bases as $b)<option value="{{ $b }}" @selected($settings->proration_basis === $b)>@lang('ledger_payroll.basis_'.$b)</option>@endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">@lang('ledger_payroll.loans_and_advances')</h5></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label" for="advance_account_id">@lang('ledger_payroll.map_staff_advances')</label>
                            <select class="form-select" name="advance_account_id" id="advance_account_id">
                                @foreach($accounts as $a)<option value="{{ $a->id }}" @selected($advance_account_id == $a->id)>{{ $a->account_code }} - {{ \App\Ledger\Services\Preview::accountName($a) }}</option>@endforeach
                            </select>
                            <small class="text-muted">@lang('ledger_payroll.advance_account_help')</small>
                        </div>
                        <div class="mb-0">
                            <label class="form-label" for="advance_payment_account_id">@lang('ledger_payroll.advance_paid_from')</label>
                            <select class="form-select" name="advance_payment_account_id" id="advance_payment_account_id">
                                <option value="">@lang('ledger_payroll.choose_each_time')</option>
                                @foreach($payment_accounts as $a)<option value="{{ $a->id }}" @selected($settings->advance_payment_account_id == $a->id)>{{ $a->title }}</option>@endforeach
                            </select>
                            <small class="text-muted">@lang('ledger_payroll.advance_paid_from_help')</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">@lang('ledger_payroll.bank_file')</h5></div>
                    <div class="card-body">
                        <label class="form-label">@lang('ledger_payroll.bank_file_columns')</label>
                        <table class="table table-sm ap-table">
                            <thead><tr><th></th><th>@lang('ledger_payroll.column')</th><th style="width:90px">@lang('ledger_payroll.position')</th></tr></thead>
                            <tbody>
                                @php $chosen = $settings->bankColumns(); @endphp
                                @foreach($columns as $c)
                                <tr>
                                    <td><input type="checkbox" name="bank_file_columns[]" value="{{ $c }}" id="col-{{ $c }}" @checked(in_array($c, $chosen))></td>
                                    <td><label for="col-{{ $c }}" class="mb-0">@lang('ledger_payroll.bank_col_'.$c)</label></td>
                                    <td><input type="number" class="form-control form-control-sm" name="bank_file_order[{{ $c }}]" value="{{ in_array($c, $chosen) ? array_search($c, $chosen) + 1 : '' }}" min="1" max="20"></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="bank_file_delimiter">@lang('ledger_payroll.delimiter')</label>
                                <select class="form-select" name="bank_file_delimiter" id="bank_file_delimiter">
                                    @foreach([',' => ', (comma)', ';' => '; (semicolon)', 'tab' => 'Tab', '|' => '| (pipe)'] as $k => $l)<option value="{{ $k }}" @selected($settings->bank_file_delimiter === $k)>{{ $l }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">@lang('ledger_payroll.header_row')</label>{!! $yn('bank_file_header', $settings->bank_file_header) !!}
                            </div>
                            <div class="col-md-8 mb-3">
                                <label class="form-label" for="bank_file_reference">@lang('ledger_payroll.payment_reference')</label>
                                <input type="text" class="form-control" name="bank_file_reference" id="bank_file_reference" value="{{ $settings->bank_file_reference }}" maxlength="120" required>
                                <small class="text-muted">@lang('ledger_payroll.reference_help')</small>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label" for="bank_file_decimals">@lang('ledger_payroll.decimals')</label>
                                <select class="form-select" name="bank_file_decimals" id="bank_file_decimals">
                                    @foreach([0, 1, 2] as $d)<option value="{{ $d }}" @selected($settings->bank_file_decimals == $d)>{{ $d }}</option>@endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="mb-4"><button type="submit" class="btn btn-success"><i class="fas fa-check"></i> @lang('ledger_payroll.btn_save')</button></div>
    </form>
</section>
@endsection
