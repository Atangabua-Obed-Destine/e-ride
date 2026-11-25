@extends('layouts.app')
@section('title', __('ledger_periodend.new_bank_reconciliation'))

@section('content')
@include('ledger.partials.style')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_periodend.new_bank_reconciliation')</h1>
</section>
<section class="content">
    <div class="row">
        <div class="col-lg-8">
            <div class="box box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title">@lang('ledger_periodend.new_bank_reconciliation')</h3>
                    <div class="box-tools">
                        <a href="{{ route('ledger.bank-reconciliation.index') }}" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> @lang('ledger_periodend.back')</a>
                    </div>
                </div>
                <div class="box-body">
                    <div class="ap-note" style="margin-bottom: 14px;">@lang('ledger_periodend.br_create_note')</div>

                    <form method="post" action="{{ route('ledger.bank-reconciliation.store') }}">
                        @csrf
                        <div class="form-group">
                            <label for="chart_of_account_id">@lang('ledger_periodend.account') <span class="text-danger">*</span></label>
                            <select name="chart_of_account_id" id="chart_of_account_id" class="form-control" required>
                                <option value="">@lang('ledger_periodend.select')</option>
                                @foreach($accounts as $a)
                                <option value="{{ $a->id }}" @selected(old('chart_of_account_id', $selected) == $a->id)>{{ $a->account_code }} {{ \App\Ledger\Services\Preview::accountName($a) }}</option>
                                @endforeach
                            </select>
                            @error('chart_of_account_id')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label for="statement_date">@lang('ledger_periodend.statement_date') <span class="text-danger">*</span></label>
                                <input type="date" name="statement_date" id="statement_date" class="form-control" required max="{{ now()->toDateString() }}"
                                       value="{{ old('statement_date', now()->subMonthNoOverflow()->endOfMonth()->toDateString()) }}">
                                @error('statement_date')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label for="statement_closing_balance">@lang('ledger_periodend.closing_balance_on_statement') <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" name="statement_closing_balance" id="statement_closing_balance" class="form-control" required value="{{ old('statement_closing_balance') }}">
                                @error('statement_closing_balance')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="row">
                            <div class="form-group col-md-6">
                                <label for="statement_reference">@lang('ledger_periodend.statement_reference')</label>
                                <input type="text" name="statement_reference" id="statement_reference" class="form-control" maxlength="190" value="{{ old('statement_reference') }}">
                            </div>
                            <div class="form-group col-md-6">
                                <label for="statement_opening_balance">@lang('ledger_periodend.opening_balance_on_statement')</label>
                                <input type="number" step="0.01" name="statement_opening_balance" id="statement_opening_balance" class="form-control" value="{{ old('statement_opening_balance') }}"
                                       placeholder="@lang('ledger_periodend.taken_from_last')">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="notes">@lang('ledger_periodend.notes')</label>
                            <textarea name="notes" id="notes" rows="2" class="form-control" maxlength="2000">{{ old('notes') }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-success"><i class="fa fa-play"></i> @lang('ledger_periodend.start_reconciliation')</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
