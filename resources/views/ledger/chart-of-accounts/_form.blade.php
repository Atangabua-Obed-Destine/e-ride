{{--
    The account form (paxhitestbed chart-of-accounts create / edit: same fields, same order).
    $account: null on create; on edit, a system account keeps its code, class, parent, type,
    category and normal balance.
--}}
@php
    $a = $account ?? null;
    $locked = $a && $a->is_system;
    $v = fn ($field, $default = null) => old($field, $a ? $a->{$field} : $default);
    $err = fn ($field) => $errors->has($field) ? ' has-error' : '';
@endphp
<div class="row">
    {{-- Account Code --}}
    <div class="col-md-6">
        <div class="form-group{{ $err('account_code') }}">
            <label for="account_code">@lang('ledger_coa.account_code') <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="account_code" name="account_code" value="{{ $v('account_code') }}"
                   placeholder="@lang('ledger_coa.enter_account_code')" maxlength="20" {{ $locked ? 'readonly' : '' }} required>
            @error('account_code')<span class="help-block">{{ $message }}</span>@enderror
            @if(! $a)<p class="help-block">@lang('ledger_coa.example'): 411001, 53001, etc.</p>@endif
        </div>
    </div>

    {{-- Class Number --}}
    <div class="col-md-6">
        <div class="form-group{{ $err('class_number') }}">
            <label for="class_number">@lang('ledger_coa.classe') <span class="text-danger">*</span></label>
            <select class="form-control" id="class_number" name="class_number" {{ $locked ? 'disabled' : '' }} required>
                @if(! $a)<option value="">@lang('ledger_coa.select_class')</option>@endif
                @for($i = 1; $i <= 9; $i++)
                <option value="{{ $i }}" {{ $v('class_number') == $i ? 'selected' : '' }}>@lang('ledger_coa.classe') {{ $i }} - @lang('ledger_coa.ohada_class_'.$i.'_name')</option>
                @endfor
            </select>
            @if($locked)<input type="hidden" name="class_number" value="{{ $a->class_number }}">@endif
            @error('class_number')<span class="help-block">{{ $message }}</span>@enderror
        </div>
    </div>

    {{-- Account Name (English) --}}
    <div class="col-md-6">
        <div class="form-group{{ $err('account_name') }}">
            <label for="account_name">@lang('ledger_coa.account_name') @lang('ledger_coa.in_english') <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="account_name" name="account_name" value="{{ $v('account_name') }}"
                   placeholder="@lang('ledger_coa.enter_account_name')" maxlength="255" required>
            @error('account_name')<span class="help-block">{{ $message }}</span>@enderror
        </div>
    </div>

    {{-- Account Name (French) --}}
    <div class="col-md-6">
        <div class="form-group{{ $err('account_name_fr') }}">
            <label for="account_name_fr">@lang('ledger_coa.account_name') @lang('ledger_coa.in_french')</label>
            <input type="text" class="form-control" id="account_name_fr" name="account_name_fr" value="{{ $v('account_name_fr') }}"
                   placeholder="@lang('ledger_coa.french_placeholder')" maxlength="255">
            @error('account_name_fr')<span class="help-block">{{ $message }}</span>@enderror
        </div>
    </div>

    {{-- Parent Account --}}
    <div class="col-md-6">
        <div class="form-group{{ $err('parent_id') }}">
            <label for="parent_id">@lang('ledger_coa.parent_account')</label>
            <select class="form-control select2" id="parent_id" name="parent_id" style="width: 100%;" {{ $locked ? 'disabled' : '' }}>
                <option value="">@lang('ledger_coa.select_parent_account') (@lang('ledger_coa.optional'))</option>
                @foreach($parentAccounts as $parent)
                @if(! $a || $parent->id != $a->id)
                <option value="{{ $parent->id }}" {{ $v('parent_id') == $parent->id ? 'selected' : '' }}>{{ $parent->account_code }} - {{ \App\Ledger\Services\Preview::accountName($parent) }}</option>
                @endif
                @endforeach
            </select>
            @if($locked)<input type="hidden" name="parent_id" value="{{ $a->parent_id }}">@endif
            @error('parent_id')<span class="help-block">{{ $message }}</span>@enderror
        </div>
    </div>

    {{-- Account Type --}}
    <div class="col-md-6">
        <div class="form-group{{ $err('account_type') }}">
            <label for="account_type">@lang('ledger_coa.account_type') <span class="text-danger">*</span></label>
            <select class="form-control" id="account_type" name="account_type" {{ $locked ? 'disabled' : '' }} required>
                @if(! $a)<option value="">@lang('ledger_coa.select_account_type')</option>@endif
                @foreach(['asset', 'liability', 'equity', 'revenue', 'expense', 'other'] as $type)
                <option value="{{ $type }}" {{ $v('account_type') == $type ? 'selected' : '' }}>@lang('ledger_coa.type_'.$type)</option>
                @endforeach
                @if($a && $a->account_type === 'analytical')
                <option value="analytical" selected>@lang('ledger_coa.type_analytical')</option>
                @endif
            </select>
            @if($locked)<input type="hidden" name="account_type" value="{{ $a->account_type }}">@endif
            @error('account_type')<span class="help-block">{{ $message }}</span>@enderror
        </div>
    </div>

    {{-- Account Category --}}
    <div class="col-md-6">
        <div class="form-group{{ $err('account_category') }}">
            <label for="account_category">@lang('ledger_coa.account_category') <span class="text-danger">*</span></label>
            <select class="form-control" id="account_category" name="account_category" {{ $locked ? 'disabled' : '' }} required>
                @if(! $a)<option value="">@lang('ledger_coa.select_account_category')</option>@endif
                @foreach(['detail', 'heading', 'total', 'subtotal'] as $cat)
                <option value="{{ $cat }}" {{ $v('account_category') == $cat ? 'selected' : '' }}>@lang('ledger_coa.cat_'.$cat)</option>
                @endforeach
            </select>
            @if($locked)<input type="hidden" name="account_category" value="{{ $a->account_category }}">@endif
            @error('account_category')<span class="help-block">{{ $message }}</span>@enderror
            @if(! $a)<p class="help-block">@lang('ledger_coa.only_detail_accounts_can_post_transactions')</p>@endif
        </div>
    </div>

    {{-- Normal Balance --}}
    <div class="col-md-6">
        <div class="form-group{{ $err('normal_balance') }}">
            <label for="normal_balance">@lang('ledger_coa.normal_balance') <span class="text-danger">*</span></label>
            <select class="form-control" id="normal_balance" name="normal_balance" {{ $locked ? 'disabled' : '' }} required>
                @if(! $a)<option value="">@lang('ledger_coa.select_normal_balance')</option>@endif
                <option value="debit" {{ $v('normal_balance') == 'debit' ? 'selected' : '' }}>@lang('ledger_coa.debit')</option>
                <option value="credit" {{ $v('normal_balance') == 'credit' ? 'selected' : '' }}>@lang('ledger_coa.credit')</option>
            </select>
            @if($locked)<input type="hidden" name="normal_balance" value="{{ $a->normal_balance }}">@endif
            @error('normal_balance')<span class="help-block">{{ $message }}</span>@enderror
        </div>
    </div>

    {{-- Opening Balance --}}
    <div class="col-md-6">
        <div class="form-group{{ $err('opening_balance') }}">
            <label for="opening_balance">@lang('ledger_coa.opening_balance')</label>
            @if($a)
            {{-- paxhitestbed shows it on edit but never saves it: shown read-only here --}}
            <input type="text" class="form-control" id="opening_balance" value="{{ \App\Ledger\Ledger::money($a->opening_balance) }}" readonly>
            <p class="help-block">@lang('ledger_coa.opening_balance_locked')</p>
            @else
            <input type="number" step="0.01" min="0" class="form-control" id="opening_balance" name="opening_balance" value="{{ old('opening_balance', 0) }}" placeholder="0.00">
            @endif
            @error('opening_balance')<span class="help-block">{{ $message }}</span>@enderror
        </div>
    </div>

    {{-- Display Order --}}
    <div class="col-md-6">
        <div class="form-group{{ $err('display_order') }}">
            <label for="display_order">@lang('ledger_coa.display_order')</label>
            <input type="number" class="form-control" id="display_order" name="display_order" value="{{ $v('display_order', 0) }}" placeholder="0">
            @error('display_order')<span class="help-block">{{ $message }}</span>@enderror
        </div>
    </div>

    {{-- Status --}}
    <div class="col-md-6">
        <div class="form-group{{ $err('is_active') }}">
            <label for="is_active">@lang('ledger_coa.status')</label>
            @php $active = (int) old('is_active', $a ? (int) $a->is_active : 1); @endphp
            <select class="form-control" id="is_active" name="is_active">
                <option value="1" {{ $active === 1 ? 'selected' : '' }}>@lang('ledger_coa.active')</option>
                <option value="0" {{ $active === 0 ? 'selected' : '' }}>@lang('ledger_coa.inactive')</option>
            </select>
            @error('is_active')<span class="help-block">{{ $message }}</span>@enderror
        </div>
    </div>
</div>
