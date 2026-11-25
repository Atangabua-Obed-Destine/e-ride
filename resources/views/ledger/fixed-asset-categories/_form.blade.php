{{--
    The category form (paxhitestbed fixed-asset-categories create / edit: same fields, same order).
    $category: null on create.
--}}
@php
    $c = $category ?? null;
    $v = fn ($field, $default = null) => old($field, $c ? $c->{$field} : $default);
    $err = fn ($f) => $errors->has($f) ? ' has-error' : '';
    $label = fn ($a) => $a->account_code.' - '.\App\Ledger\Services\Preview::accountName($a);
@endphp
<div class="row">
    <div class="col-md-4">
        <div class="form-group{{ $err('code') }}">
            <label for="code">@lang('ledger_assets.category_code') <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="code" name="code" value="{{ $v('code') }}" required maxlength="10">
            @error('code')<span class="help-block">{{ $message }}</span>@enderror
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group{{ $err('name') }}">
            <label for="name">@lang('ledger_assets.category_name') <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="name" name="name" value="{{ $v('name') }}" required>
            @error('name')<span class="help-block">{{ $message }}</span>@enderror
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group{{ $err('name_fr') }}">
            <label for="name_fr">@lang('ledger_assets.category_name_fr')</label>
            <input type="text" class="form-control" id="name_fr" name="name_fr" value="{{ $v('name_fr') }}">
            @error('name_fr')<span class="help-block">{{ $message }}</span>@enderror
        </div>
    </div>

    <div class="col-md-6">
        <div class="form-group{{ $err('depreciation_method') }}">
            <label for="depreciation_method">@lang('ledger_assets.depreciation_method') <span class="text-danger">*</span></label>
            <select class="form-control" id="depreciation_method" name="depreciation_method" required>
                @foreach($methods as $m)
                <option value="{{ $m }}" {{ $v('depreciation_method', 'straight_line') == $m ? 'selected' : '' }}>@lang('ledger_assets.'.$m)</option>
                @endforeach
            </select>
            @error('depreciation_method')<span class="help-block">{{ $message }}</span>@enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group{{ $err('default_useful_life') }}">
            <label for="default_useful_life">@lang('ledger_assets.default_useful_life') (@lang('ledger_assets.years')) <span class="text-danger">*</span></label>
            <input type="number" class="form-control" id="default_useful_life" name="default_useful_life"
                   value="{{ old('default_useful_life', $c ? $c->useful_life_years : 5) }}" required min="1" max="100">
            @error('default_useful_life')<span class="help-block">{{ $message }}</span>@enderror
        </div>
    </div>

    <div class="col-md-12"><hr><h4>@lang('ledger_assets.linked_gl_accounts')</h4></div>

    @foreach([
        ['asset_account_id', 'asset_account', $assetAccounts, true, 'class_2_accounts'],
        ['accumulated_depreciation_account_id', 'accumulated_depreciation_account', $accumulatedDepreciationAccounts, true, 'class_28_accounts'],
        ['depreciation_account_id', 'depreciation_expense_account', $depreciationExpenseAccounts, true, 'class_68_accounts'],
        ['disposal_account_id', 'disposal_gain_loss_account', $disposalAccounts, false, 'disposal_account_help'],
    ] as [$field, $labelKey, $list, $required, $help])
    <div class="col-md-6">
        <div class="form-group{{ $err($field) }}">
            <label for="{{ $field }}">@lang('ledger_assets.'.$labelKey)@if($required) <span class="text-danger">*</span>@endif</label>
            <select class="form-control select2" id="{{ $field }}" name="{{ $field }}" {{ $required ? 'required' : '' }} style="width: 100%;">
                <option value="">@lang('ledger_assets.select_account')</option>
                @foreach($list as $account)
                <option value="{{ $account->id }}" {{ $v($field) == $account->id ? 'selected' : '' }}>{{ $label($account) }}</option>
                @endforeach
            </select>
            <small class="ap-muted">@lang('ledger_assets.'.$help)</small>
            @error($field)<span class="help-block">{{ $message }}</span>@enderror
        </div>
    </div>
    @endforeach

    <div class="col-md-12">
        <div class="form-group{{ $err('description') }}">
            <label for="description">@lang('ledger_assets.description')</label>
            <textarea class="form-control" id="description" name="description" rows="2">{{ $v('description') }}</textarea>
            @error('description')<span class="help-block">{{ $message }}</span>@enderror
        </div>
    </div>
</div>
