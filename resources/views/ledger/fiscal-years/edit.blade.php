@extends('layouts.app')
@section('title', __('ledger_fy.edit_fiscal_year'))

@section('content')
@include('ledger.partials.style')
@php
    $err = fn ($field) => $errors->has($field) ? ' has-error' : '';
    $closed = $fiscalYear->is_closed;
@endphp
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_fy.edit_fiscal_year')</h1>
</section>
<section class="content">
    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">@lang('ledger_fy.edit_fiscal_year')</h3>
            <div class="box-tools">
                <a href="{{ route('ledger.fiscal-years.index') }}" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> @lang('ledger_fy.back')</a>
            </div>
        </div>
        <form action="{{ route('ledger.fiscal-years.update', $fiscalYear->id) }}" method="POST" id="fy_form">
            @csrf
            @method('PUT')
            <div class="box-body">
                @if($closed)
                <div class="ap-note is-warn" style="margin-bottom: 14px;">
                    <i class="fa fa-exclamation-triangle"></i> @lang('ledger_fy.this_fiscal_year_is_closed'). @lang('ledger_fy.reopen_to_make_changes').
                </div>
                @endif

                <div class="row">
                    {{-- Fiscal Year Name --}}
                    <div class="col-md-12">
                        <div class="form-group{{ $err('name') }}">
                            <label for="name">@lang('ledger_fy.fiscal_year_name') <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $fiscalYear->name) }}" maxlength="255" {{ $closed ? 'readonly' : '' }} required>
                            @error('name')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    {{-- Start Date --}}
                    <div class="col-md-6">
                        <div class="form-group{{ $err('start_date') }}">
                            <label for="start_date">@lang('ledger_fy.start_date') <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="{{ old('start_date', $fiscalYear->start_date->format('Y-m-d')) }}" {{ $closed ? 'readonly' : '' }} required>
                            @error('start_date')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    {{-- End Date --}}
                    <div class="col-md-6">
                        <div class="form-group{{ $err('end_date') }}">
                            <label for="end_date">@lang('ledger_fy.end_date') <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="{{ old('end_date', $fiscalYear->end_date->format('Y-m-d')) }}" {{ $closed ? 'readonly' : '' }} required>
                            @error('end_date')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    {{-- Is Active --}}
                    <div class="col-md-6">
                        <div class="form-group{{ $err('is_active') }}">
                            <label for="is_active">@lang('ledger_fy.set_as_active_year')</label>
                            @php $active = (int) old('is_active', (int) $fiscalYear->is_active); @endphp
                            <select class="form-control" id="is_active" name="is_active" {{ $closed ? 'disabled' : '' }}>
                                <option value="0" {{ $active === 0 ? 'selected' : '' }}>@lang('ledger_fy.no')</option>
                                <option value="1" {{ $active === 1 ? 'selected' : '' }}>@lang('ledger_fy.yes')</option>
                            </select>
                            @if($closed)<input type="hidden" name="is_active" value="{{ (int) $fiscalYear->is_active }}">@endif
                            @error('is_active')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </div>
            </div>
            <div class="box-footer">
                @if(! $closed)
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> @lang('ledger_fy.update')</button>
                @endif
                <a href="{{ route('ledger.fiscal-years.index') }}" class="btn btn-default"><i class="fa fa-times"></i> @lang('ledger_fy.cancel')</a>
            </div>
        </form>
    </div>
</section>
@endsection
