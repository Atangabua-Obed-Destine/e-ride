@extends('layouts.app')
@section('title', __('ledger_assets.add_fixed_asset'))

{{-- paxhitestbed admin/accounting/fixed-assets/create: same fields, same order. --}}
@section('content')
@include('ledger.partials.style')
@php $err = fn ($f) => $errors->has($f) ? ' has-error' : ''; @endphp
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_assets.add_fixed_asset')</h1>
</section>
<section class="content">
    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">@lang('ledger_assets.add_fixed_asset') (@lang('ledger_assets.nouvelle_immobilisation'))</h3>
            <div class="box-tools">
                <a href="{{ route('ledger.fixed-assets.index') }}" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> @lang('ledger_assets.back')</a>
            </div>
        </div>

        @include('ledger.fixed-assets.partials.errors')

        <form action="{{ route('ledger.fixed-assets.store') }}" method="POST">
            @csrf
            <div class="box-body">
                <div class="row">
                    {{-- Basic information --}}
                    <div class="col-md-6">
                        <div class="form-group{{ $err('name') }}">
                            <label for="name">@lang('ledger_assets.asset_name') <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}" required>
                            @error('name')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group{{ $err('name_fr') }}">
                            <label for="name_fr">@lang('ledger_assets.asset_name_fr')</label>
                            <input type="text" class="form-control" id="name_fr" name="name_fr" value="{{ old('name_fr') }}">
                            @error('name_fr')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group{{ $err('category_id') }}">
                            <label for="category_id">@lang('ledger_assets.category') <span class="text-danger">*</span></label>
                            <select class="form-control" id="category_id" name="category_id" required>
                                <option value="">@lang('ledger_assets.select_category')</option>
                                @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}
                                        data-useful-life="{{ $category->useful_life_years }}"
                                        data-depreciation-method="{{ $category->depreciation_method }}">
                                    {{ $category->name }} ({{ $category->useful_life_years }} @lang('ledger_assets.years'))
                                </option>
                                @endforeach
                            </select>
                            @error('category_id')<span class="help-block">{{ $message }}</span>@enderror
                            @if($categories->isEmpty() && auth()->user()->can('fixed-asset-create'))
                            <p class="help-block"><a href="{{ route('ledger.fixed-asset-categories.create') }}">@lang('ledger_assets.add_category')</a></p>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group{{ $err('serial_number') }}">
                            <label for="serial_number">@lang('ledger_assets.serial_number')</label>
                            <input type="text" class="form-control" id="serial_number" name="serial_number" value="{{ old('serial_number') }}">
                            @error('serial_number')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group{{ $err('location') }}">
                            <label for="location">@lang('ledger_assets.location')</label>
                            <input type="text" class="form-control" id="location" name="location" value="{{ old('location') }}">
                            @error('location')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    {{-- Financial information --}}
                    <div class="col-md-12"><hr><h4>@lang('ledger_assets.financial_information')</h4></div>

                    <div class="col-md-3">
                        <div class="form-group{{ $err('acquisition_date') }}">
                            <label for="acquisition_date">@lang('ledger_assets.acquisition_date') <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="acquisition_date" name="acquisition_date" value="{{ old('acquisition_date', date('Y-m-d')) }}" required>
                            @error('acquisition_date')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group{{ $err('acquisition_cost') }}">
                            <label for="acquisition_cost">@lang('ledger_assets.acquisition_cost') <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="acquisition_cost" name="acquisition_cost" value="{{ old('acquisition_cost') }}" required min="0" step="any">
                            @error('acquisition_cost')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group{{ $err('salvage_value') }}">
                            <label for="salvage_value">@lang('ledger_assets.salvage_value')</label>
                            <input type="number" class="form-control" id="salvage_value" name="salvage_value" value="{{ old('salvage_value', 0) }}" min="0" step="any">
                            @error('salvage_value')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group{{ $err('useful_life_years') }}">
                            <label for="useful_life_years">@lang('ledger_assets.useful_life_years') <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="useful_life_years" name="useful_life_years" value="{{ old('useful_life_years') }}" required min="1" step="any">
                            @error('useful_life_years')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group{{ $err('depreciation_method') }}">
                            <label for="depreciation_method">@lang('ledger_assets.depreciation_method') <span class="text-danger">*</span></label>
                            <select class="form-control" id="depreciation_method" name="depreciation_method" required>
                                @foreach(\App\Ledger\Models\FixedAssetCategory::METHODS as $m)
                                <option value="{{ $m }}" {{ old('depreciation_method') == $m ? 'selected' : '' }}>@lang('ledger_assets.'.$m)</option>
                                @endforeach
                            </select>
                            @error('depreciation_method')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group{{ $err('depreciation_start_date') }}">
                            <label for="depreciation_start_date">@lang('ledger_assets.depreciation_start_date')</label>
                            <input type="date" class="form-control" id="depreciation_start_date" name="depreciation_start_date" value="{{ old('depreciation_start_date') }}">
                            <small class="ap-muted">@lang('ledger_assets.leave_blank_to_start_from_acquisition')</small>
                            @error('depreciation_start_date')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group{{ $err('vendor') }}">
                            <label for="vendor">@lang('ledger_assets.vendor')</label>
                            <input type="text" class="form-control" id="vendor" name="vendor" value="{{ old('vendor') }}">
                            @error('vendor')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    {{-- Additional information --}}
                    <div class="col-md-12"><hr><h4>@lang('ledger_assets.additional_information')</h4></div>

                    <div class="col-md-4">
                        <div class="form-group{{ $err('warranty_expiry_date') }}">
                            <label for="warranty_expiry_date">@lang('ledger_assets.warranty_expiry_date')</label>
                            <input type="date" class="form-control" id="warranty_expiry_date" name="warranty_expiry_date" value="{{ old('warranty_expiry_date') }}">
                            @error('warranty_expiry_date')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group{{ $err('insurance_value') }}">
                            <label for="insurance_value">@lang('ledger_assets.insurance_value')</label>
                            <input type="number" class="form-control" id="insurance_value" name="insurance_value" value="{{ old('insurance_value') }}" min="0" step="any">
                            @error('insurance_value')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group{{ $err('insurance_policy_number') }}">
                            <label for="insurance_policy_number">@lang('ledger_assets.insurance_policy_number')</label>
                            <input type="text" class="form-control" id="insurance_policy_number" name="insurance_policy_number" value="{{ old('insurance_policy_number') }}">
                            @error('insurance_policy_number')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="form-group{{ $err('description') }}">
                            <label for="description">@lang('ledger_assets.description')</label>
                            <textarea class="form-control" id="description" name="description" rows="3">{{ old('description') }}</textarea>
                            @error('description')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </div>
            </div>
            <div class="box-footer">
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> @lang('ledger_assets.save')</button>
                <a href="{{ route('ledger.fixed-assets.index') }}" class="btn btn-default"><i class="fa fa-times"></i> @lang('ledger_assets.cancel')</a>
            </div>
        </form>
    </div>
</section>
@endsection

@section('javascript')
<script>
    $(document).ready(function () {
        // the category's useful life and method fill in when it is chosen
        $('#category_id').change(function () {
            var o = $(this).find(':selected');
            if (o.data('useful-life')) { $('#useful_life_years').val(o.data('useful-life')); }
            if (o.data('depreciation-method')) { $('#depreciation_method').val(o.data('depreciation-method')); }
        });
    });
</script>
@endsection
