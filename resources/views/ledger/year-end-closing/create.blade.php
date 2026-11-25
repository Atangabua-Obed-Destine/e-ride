@extends('layouts.app')
@section('title', __('ledger_periodend.create_year_end_closing'))

@section('content')
@include('ledger.partials.style')
@php use App\Ledger\Services\Preview; @endphp
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_periodend.create_year_end_closing')</h1>
</section>
<section class="content">
    <form action="{{ route('ledger.year-end-closing.store') }}" method="POST" id="yearEndForm">
        @csrf
        <div class="row">
            <div class="col-md-8">
                {{-- Fiscal year --}}
                <div class="box box-solid">
                    <div class="box-header with-border"><h3 class="box-title">@lang('ledger_periodend.fiscal_year_information')</h3></div>
                    <div class="box-body">
                        <div class="form-group">
                            <label for="fiscal_year_id">@lang('ledger_periodend.fiscal_year_to_close') <span class="text-danger">*</span></label>
                            <select class="form-control" id="fiscal_year_id" name="fiscal_year_id" required>
                                <option value="">@lang('ledger_periodend.select_fiscal_year')</option>
                                @foreach($fiscalYears as $fy)
                                <option value="{{ $fy->id }}" data-end="{{ $fy->end_date->toDateString() }}" @selected(old('fiscal_year_id') == $fy->id)>
                                    {{ $fy->name }} ({{ $fy->start_date->format('d/m/Y') }} - {{ $fy->end_date->format('d/m/Y') }})
                                </option>
                                @endforeach
                            </select>
                            @error('fiscal_year_id')<span class="text-danger small">{{ $message }}</span>@enderror
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="closing_date">@lang('ledger_periodend.closing_date') <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="closing_date" name="closing_date" value="{{ old('closing_date') }}" required>
                                    @error('closing_date')<span class="text-danger small">{{ $message }}</span>@enderror
                                    <small class="help-block">@lang('ledger_periodend.usually_last_day')</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Accounts --}}
                <div class="box box-solid">
                    <div class="box-header with-border"><h3 class="box-title">@lang('ledger_periodend.closing_accounts_ohada')</h3></div>
                    <div class="box-body">
                        <div class="ap-note" style="margin-bottom: 14px;"><i class="fa fa-info-circle"></i> @lang('ledger_periodend.closing_accounts_note')</div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="retained_earnings_account_id">@lang('ledger_periodend.retained_earnings_account') <span class="text-danger">*</span></label>
                                    <select class="form-control select2" id="retained_earnings_account_id" name="retained_earnings_account_id" required style="width: 100%;">
                                        <option value="">@lang('ledger_periodend.select_account')</option>
                                        @foreach($retainedEarningsAccounts as $account)
                                        <option value="{{ $account->id }}" @selected(old('retained_earnings_account_id', $defaultRetained) == $account->id)>{{ $account->account_code }} - {{ Preview::accountName($account) }}</option>
                                        @endforeach
                                    </select>
                                    @error('retained_earnings_account_id')<span class="text-danger small">{{ $message }}</span>@enderror
                                    <small class="help-block">@lang('ledger_periodend.usually_12')</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="income_summary_account_id">@lang('ledger_periodend.income_summary_account') <span class="text-danger">*</span></label>
                                    <select class="form-control select2" id="income_summary_account_id" name="income_summary_account_id" required style="width: 100%;">
                                        <option value="">@lang('ledger_periodend.select_account')</option>
                                        @foreach($incomeSummaryAccounts as $account)
                                        <option value="{{ $account->id }}" @selected(old('income_summary_account_id', $defaultSummary) == $account->id)>{{ $account->account_code }} - {{ Preview::accountName($account) }}</option>
                                        @endforeach
                                    </select>
                                    @error('income_summary_account_id')<span class="text-danger small">{{ $message }}</span>@enderror
                                    <small class="help-block">@lang('ledger_periodend.usually_13')</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Notes --}}
                <div class="box box-solid">
                    <div class="box-header with-border"><h3 class="box-title">@lang('ledger_periodend.additional_notes')</h3></div>
                    <div class="box-body">
                        <div class="form-group">
                            <label for="notes">@lang('ledger_periodend.notes')</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" maxlength="2000">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                {{-- Pre-closing checklist --}}
                <div class="box box-solid">
                    <div class="box-header with-border"><h3 class="box-title">@lang('ledger_periodend.pre_closing_checklist')</h3></div>
                    <div class="box-body">
                        <p class="text-muted">@lang('ledger_periodend.before_proceeding')</p>
                        <ul class="list-unstyled">
                            @foreach(['all_journal_entries_posted', 'bank_reconciliations_completed', 'stock_counted_valued', 'depreciation_entries_posted', 'accruals_recorded', 'trial_balance_verified', 'financial_statements_reviewed'] as $k)
                            <li style="margin-bottom: 6px;"><i class="fa fa-check-circle text-muted"></i> @lang('ledger_periodend.'.$k)</li>
                            @endforeach
                        </ul>
                        <div class="ap-note is-warn"><i class="fa fa-exclamation-triangle"></i> @lang('ledger_periodend.checklist_after_creating')</div>
                    </div>
                </div>

                {{-- Submit --}}
                <div class="box box-solid">
                    <div class="box-body">
                        <button type="submit" class="btn btn-primary btn-block"><i class="fa fa-plus"></i> @lang('ledger_periodend.create_closing_process')</button>
                        <a href="{{ route('ledger.year-end-closing.index') }}" class="btn btn-default btn-block"><i class="fa fa-times"></i> @lang('ledger_periodend.cancel')</a>
                    </div>
                </div>

                {{-- Help --}}
                <div class="box box-solid">
                    <div class="box-header with-border"><h3 class="box-title">@lang('ledger_periodend.ohada_year_end_process')</h3></div>
                    <div class="box-body">
                        <small class="text-muted">
                            <p>@lang('ledger_periodend.process_involves')</p>
                            <ol style="padding-left: 18px;">
                                <li>@lang('ledger_periodend.step_stock')</li>
                                <li>@lang('ledger_periodend.step_revenue')</li>
                                <li>@lang('ledger_periodend.step_expenses')</li>
                                <li>@lang('ledger_periodend.step_retained')</li>
                                <li>@lang('ledger_periodend.step_opening')</li>
                            </ol>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </form>
</section>
@endsection

@section('javascript')
<script>
$(function () {
    if ($.fn.select2) { $('#yearEndForm .select2').select2({ allowClear: true, width: '100%' }); }
    // The closing date follows the fiscal year chosen: its last day.
    function setDate() {
        var end = $('#fiscal_year_id option:selected').data('end');
        if (end && !$('#closing_date').data('touched')) { $('#closing_date').val(end); }
    }
    $('#closing_date').on('input change', function () { $(this).data('touched', 1); });
    $('#fiscal_year_id').on('change', function () { $('#closing_date').data('touched', 0); setDate(); });
    if (!$('#closing_date').val()) { setDate(); }
});
</script>
@endsection
