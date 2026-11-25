@extends('layouts.app')
@section('title', __('ledger_assets.edit_fixed_asset'))

{{-- paxhitestbed admin/accounting/fixed-assets/edit: the figures the ledger depends on are shown, not edited. --}}
@section('content')
@include('ledger.partials.style')
@php
    $err = fn ($f) => $errors->has($f) ? ' has-error' : '';
    $money = fn ($n) => \App\Ledger\Ledger::money($n);
    $salvageLocked = (float) $asset->accumulated_depreciation > 0;
@endphp
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_assets.edit_fixed_asset')</h1>
</section>
<section class="content">
    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">@lang('ledger_assets.edit_fixed_asset'): {{ $asset->name }}</h3>
            <div class="box-tools">
                <a href="{{ route('ledger.fixed-assets.index') }}" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> @lang('ledger_assets.back')</a>
            </div>
        </div>

        @include('ledger.fixed-assets.partials.errors')

        <form action="{{ route('ledger.fixed-assets.update', $asset->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="box-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>@lang('ledger_assets.asset_code')</label>
                            <input type="text" class="form-control" value="{{ $asset->asset_code }}" readonly>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group{{ $err('name') }}">
                            <label for="name">@lang('ledger_assets.asset_name') <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $asset->name) }}" required>
                            @error('name')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="form-group{{ $err('name_fr') }}">
                            <label for="name_fr">@lang('ledger_assets.asset_name_fr')</label>
                            <input type="text" class="form-control" id="name_fr" name="name_fr" value="{{ old('name_fr', $asset->name_fr) }}">
                            @error('name_fr')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group{{ $err('category_id') }}">
                            <label for="category_id">@lang('ledger_assets.category') <span class="text-danger">*</span></label>
                            <select class="form-control" id="category_id" name="category_id" required>
                                @foreach($categories as $category)
                                @if(! $inLedger || $category->id == $asset->category_id)
                                <option value="{{ $category->id }}" {{ old('category_id', $asset->category_id) == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                @endif
                                @endforeach
                            </select>
                            @if($inLedger)<small class="ap-muted">@lang('ledger_assets.category_locked')</small>@endif
                            @error('category_id')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group{{ $err('serial_number') }}">
                            <label for="serial_number">@lang('ledger_assets.serial_number')</label>
                            <input type="text" class="form-control" id="serial_number" name="serial_number" value="{{ old('serial_number', $asset->serial_number) }}">
                            @error('serial_number')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group{{ $err('location') }}">
                            <label for="location">@lang('ledger_assets.location')</label>
                            <input type="text" class="form-control" id="location" name="location" value="{{ old('location', $asset->location) }}">
                            @error('location')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    {{-- Financial information (some read-only) --}}
                    <div class="col-md-12"><hr><h4>@lang('ledger_assets.financial_information')</h4></div>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label>@lang('ledger_assets.acquisition_date')</label>
                            <input type="text" class="form-control" value="{{ $asset->acquisition_date->format('d/m/Y') }}" readonly>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>@lang('ledger_assets.acquisition_cost')</label>
                            <input type="text" class="form-control" value="{{ $money($asset->acquisition_cost) }}" readonly>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>@lang('ledger_assets.accumulated_depreciation')</label>
                            <input type="text" class="form-control" value="{{ $money($asset->accumulated_depreciation) }}" readonly>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>@lang('ledger_assets.net_book_value')</label>
                            <input type="text" class="form-control" style="font-weight: 600;" value="{{ $money($asset->net_book_value) }}" readonly>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group{{ $err('salvage_value') }}">
                            <label for="salvage_value">@lang('ledger_assets.salvage_value')</label>
                            <input type="number" class="form-control" id="salvage_value" name="salvage_value" value="{{ old('salvage_value', (float) $asset->salvage_value) }}" min="0" step="any" {{ $salvageLocked ? 'readonly' : '' }}>
                            @if($salvageLocked)<small class="ap-muted">@lang('ledger_assets.salvage_locked')</small>@endif
                            @error('salvage_value')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group{{ $err('vendor') }}">
                            <label for="vendor">@lang('ledger_assets.vendor')</label>
                            <input type="text" class="form-control" id="vendor" name="vendor" value="{{ old('vendor', $asset->vendor) }}">
                            @error('vendor')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>@lang('ledger_assets.depreciation_method')</label>
                            <input type="text" class="form-control" value="{{ \App\Ledger\Models\FixedAssetCategory::methodLabel($asset->depreciation_method) }}" readonly>
                        </div>
                    </div>

                    {{-- Additional information --}}
                    <div class="col-md-12"><hr><h4>@lang('ledger_assets.additional_information')</h4></div>

                    <div class="col-md-4">
                        <div class="form-group{{ $err('warranty_expiry_date') }}">
                            <label for="warranty_expiry_date">@lang('ledger_assets.warranty_expiry_date')</label>
                            <input type="date" class="form-control" id="warranty_expiry_date" name="warranty_expiry_date" value="{{ old('warranty_expiry_date', optional($asset->warranty_expiry)->format('Y-m-d')) }}">
                            @error('warranty_expiry_date')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group{{ $err('insurance_value') }}">
                            <label for="insurance_value">@lang('ledger_assets.insurance_value')</label>
                            <input type="number" class="form-control" id="insurance_value" name="insurance_value" value="{{ old('insurance_value', $asset->insurance_value !== null ? (float) $asset->insurance_value : '') }}" min="0" step="any">
                            @error('insurance_value')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group{{ $err('insurance_policy_number') }}">
                            <label for="insurance_policy_number">@lang('ledger_assets.insurance_policy_number')</label>
                            <input type="text" class="form-control" id="insurance_policy_number" name="insurance_policy_number" value="{{ old('insurance_policy_number', $asset->insurance_policy_number) }}">
                            @error('insurance_policy_number')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <div class="col-md-12">
                        <div class="form-group{{ $err('description') }}">
                            <label for="description">@lang('ledger_assets.description')</label>
                            <textarea class="form-control" id="description" name="description" rows="3">{{ old('description', $asset->description) }}</textarea>
                            @error('description')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </div>
            </div>
            <div class="box-footer">
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> @lang('ledger_assets.update')</button>
                <a href="{{ route('ledger.fixed-assets.show', $asset->id) }}" class="btn btn-default"><i class="fa fa-times"></i> @lang('ledger_assets.cancel')</a>
            </div>
        </form>
    </div>
</section>
@endsection
