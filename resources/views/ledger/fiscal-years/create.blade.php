@extends('layouts.app')
@section('title', __('ledger_fy.add_fiscal_year'))

@section('content')
@include('ledger.partials.style')
@php $err = fn ($field) => $errors->has($field) ? ' has-error' : ''; @endphp
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_fy.add_fiscal_year')</h1>
</section>
<section class="content">
    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">@lang('ledger_fy.add_fiscal_year')</h3>
            <div class="box-tools">
                <a href="{{ route('ledger.fiscal-years.index') }}" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> @lang('ledger_fy.back')</a>
            </div>
        </div>
        <form action="{{ route('ledger.fiscal-years.store') }}" method="POST" id="fy_form">
            @csrf
            <div class="box-body">
                <div class="row">
                    {{-- Fiscal Year Name --}}
                    <div class="col-md-12">
                        <div class="form-group{{ $err('name') }}">
                            <label for="name">@lang('ledger_fy.fiscal_year_name') <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}" placeholder="@lang('ledger_fy.example'): {{ date('Y') }}" maxlength="255" required>
                            @error('name')<span class="help-block">{{ $message }}</span>@enderror
                            <p class="help-block">@lang('ledger_fy.enter_fiscal_year_name_example')</p>
                        </div>
                    </div>

                    {{-- Start Date --}}
                    <div class="col-md-6">
                        <div class="form-group{{ $err('start_date') }}">
                            <label for="start_date">@lang('ledger_fy.start_date') <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="{{ old('start_date') }}" required>
                            @error('start_date')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    {{-- End Date --}}
                    <div class="col-md-6">
                        <div class="form-group{{ $err('end_date') }}">
                            <label for="end_date">@lang('ledger_fy.end_date') <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="{{ old('end_date') }}" required>
                            @error('end_date')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    {{-- Is Active --}}
                    <div class="col-md-6">
                        <div class="form-group{{ $err('is_active') }}">
                            <label for="is_active">@lang('ledger_fy.set_as_active_year')</label>
                            <select class="form-control" id="is_active" name="is_active">
                                <option value="0" {{ old('is_active', 0) == 0 ? 'selected' : '' }}>@lang('ledger_fy.no')</option>
                                <option value="1" {{ old('is_active') == 1 ? 'selected' : '' }}>@lang('ledger_fy.yes')</option>
                            </select>
                            @error('is_active')<span class="help-block">{{ $message }}</span>@enderror
                            <p class="help-block">@lang('ledger_fy.only_one_fiscal_year_can_be_active')</p>
                        </div>
                    </div>

                    {{-- Generate Periods --}}
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="generate_periods">@lang('ledger_fy.generate_monthly_periods')</label>
                            <select class="form-control" id="generate_periods" name="generate_periods">
                                <option value="1" {{ old('generate_periods', 1) == 1 ? 'selected' : '' }}>@lang('ledger_fy.yes')</option>
                                <option value="0" {{ old('generate_periods', 1) == 0 ? 'selected' : '' }}>@lang('ledger_fy.no')</option>
                            </select>
                            <p class="help-block">@lang('ledger_fy.automatically_create_12_monthly_periods')</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="box-footer">
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> @lang('ledger_fy.save')</button>
                <a href="{{ route('ledger.fiscal-years.index') }}" class="btn btn-default"><i class="fa fa-times"></i> @lang('ledger_fy.cancel')</a>
            </div>
        </form>
    </div>
</section>
@endsection

@section('javascript')
<script>
$(document).ready(function () {
    // Auto-calculate end date (12 months from start date) and the name
    $('#start_date').change(function () {
        if (!$(this).val()) { return; }
        var startDate = new Date($(this).val() + 'T00:00:00');
        if (isNaN(startDate.getTime())) { return; }
        var endDate = new Date(startDate);
        endDate.setFullYear(endDate.getFullYear() + 1);
        endDate.setDate(endDate.getDate() - 1); // last day of the 12th month

        var year = endDate.getFullYear();
        var month = String(endDate.getMonth() + 1).padStart(2, '0');
        var day = String(endDate.getDate()).padStart(2, '0');
        $('#end_date').val(year + '-' + month + '-' + day);

        var startYear = startDate.getFullYear();
        $('#name').val(startYear === year ? String(startYear) : startYear + '-' + year);
    });
});
</script>
@endsection
