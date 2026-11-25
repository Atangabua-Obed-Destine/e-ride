{{-- The tax fields (paxhitestbed admin/tax-setting index create form and edit modal). $t: the tax being edited, or null. --}}
@php
    $val = fn ($k, $d = null) => old($k, $t ? $t->{$k} : $d);
    $sfx = $t ? '-'.$t->id : '';
    $num = fn ($v) => $v === null || $v === '' ? '' : rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.');
@endphp
<div class="mb-3">
    <label class="form-label" for="title{{ $sfx }}">@lang('ledger_payroll.field_tax_title') <span class="text-danger">*</span></label>
    <input type="text" class="form-control" name="title" id="title{{ $sfx }}" value="{{ $val('title') }}" required>
</div>
<div class="mb-3">
    <label class="form-label" for="tax_group_id{{ $sfx }}">@lang('ledger_payroll.field_tax_group')</label>
    <select class="form-select" name="tax_group_id" id="tax_group_id{{ $sfx }}">
        <option value="">@lang('ledger_payroll.standalone_bracket')</option>
        @foreach($all_groups as $g)<option value="{{ $g->id }}" @selected((string) $val('tax_group_id') === (string) $g->id)>{{ $g->title }} ({{ $g->is_progressive ? __('ledger_payroll.progressive_tax') : __('ledger_payroll.flat_rate_tax') }})</option>@endforeach
    </select>
    <small class="text-muted">@lang('ledger_payroll.tax_group_help')</small>
</div>
<div class="mb-3 form-check">
    <input type="hidden" name="is_dependent" value="0">
    <input type="checkbox" class="form-check-input" name="is_dependent" value="1" id="is_dependent{{ $sfx }}" @checked($val('is_dependent'))>
    <label class="form-check-label" for="is_dependent{{ $sfx }}">@lang('ledger_payroll.field_is_dependent')</label>
    <small class="text-muted d-block">@lang('ledger_payroll.dependent_tax_help')</small>
</div>
<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label" for="depends_on_type{{ $sfx }}">@lang('ledger_payroll.field_depends_on_type')</label>
        <select class="form-select" name="depends_on_type" id="depends_on_type{{ $sfx }}">
            <option value="">@lang('ledger_payroll.select')</option>
            <option value="tax_group" @selected($val('depends_on_type') === 'tax_group')>@lang('ledger_payroll.depends_on_tax_group')</option>
            <option value="tax_setting" @selected($val('depends_on_type') === 'tax_setting')>@lang('ledger_payroll.depends_on_tax_setting')</option>
        </select>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label" for="depends_on_id{{ $sfx }}">@lang('ledger_payroll.field_source_tax')</label>
        <select class="form-select" name="depends_on_id" id="depends_on_id{{ $sfx }}">
            <option value="">@lang('ledger_payroll.select')</option>
            <optgroup label="@lang('ledger_payroll.tax_groups')">@foreach($all_groups as $g)<option value="{{ $g->id }}" @selected($val('depends_on_type') === 'tax_group' && (string) $val('depends_on_id') === (string) $g->id)>{{ $g->title }}</option>@endforeach</optgroup>
            <optgroup label="@lang('ledger_payroll.standalone')">@foreach($standalone_taxes as $s)@if(! $t || $s->id !== $t->id)<option value="{{ $s->id }}" @selected($val('depends_on_type') === 'tax_setting' && (string) $val('depends_on_id') === (string) $s->id)>{{ $s->title }}</option>@endif @endforeach</optgroup>
        </select>
        <small class="text-muted">@lang('ledger_payroll.source_tax_help')</small>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label" for="tax_type{{ $sfx }}">@lang('ledger_payroll.field_tax_type') <span class="text-danger">*</span></label>
        <select class="form-select" name="tax_type" id="tax_type{{ $sfx }}" required>
            <option value="1" @selected((string) $val('tax_type', 1) === '1')>@lang('ledger_payroll.tax_type_percentage')</option>
            <option value="2" @selected((string) $val('tax_type') === '2')>@lang('ledger_payroll.tax_type_fixed')</option>
        </select>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label" for="paid_by{{ $sfx }}">@lang('ledger_payroll.field_paid_by') <span class="text-danger">*</span></label>
        <select class="form-select" name="paid_by" id="paid_by{{ $sfx }}" required>
            @foreach(['employee', 'employer', 'both'] as $p)<option value="{{ $p }}" @selected($val('paid_by', 'employee') === $p)>@lang('ledger_payroll.paid_by_'.$p)</option>@endforeach
        </select>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label" for="min_amount{{ $sfx }}">@lang('ledger_payroll.field_min_amount') <span class="text-danger">*</span></label>
        <input type="number" step="any" class="form-control" name="min_amount" id="min_amount{{ $sfx }}" value="{{ $num($val('min_amount')) }}" required>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label" for="max_amount{{ $sfx }}">@lang('ledger_payroll.field_max_amount') <span class="text-danger">*</span></label>
        <input type="number" step="any" class="form-control" name="max_amount" id="max_amount{{ $sfx }}" value="{{ $num($val('max_amount')) }}" required>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label" for="percentange{{ $sfx }}">@lang('ledger_payroll.field_employee_percentage')</label>
        <input type="number" step="any" class="form-control" name="percentange" id="percentange{{ $sfx }}" value="{{ $num($val('percentange')) }}">
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label" for="fixed_amount{{ $sfx }}">@lang('ledger_payroll.field_employee_fixed_amount')</label>
        <input type="number" step="any" class="form-control" name="fixed_amount" id="fixed_amount{{ $sfx }}" value="{{ $num($val('fixed_amount')) }}">
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label" for="employer_percentage{{ $sfx }}">@lang('ledger_payroll.field_employer_percentage')</label>
        <input type="number" step="any" class="form-control" name="employer_percentage" id="employer_percentage{{ $sfx }}" value="{{ $num($val('employer_percentage')) }}">
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label" for="employer_fixed_amount{{ $sfx }}">@lang('ledger_payroll.field_employer_fixed_amount')</label>
        <input type="number" step="any" class="form-control" name="employer_fixed_amount" id="employer_fixed_amount{{ $sfx }}" value="{{ $num($val('employer_fixed_amount')) }}">
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label" for="max_no_taxable_amount{{ $sfx }}">@lang('ledger_payroll.field_max_no_taxable_amount')</label>
        <input type="number" step="any" class="form-control" name="max_no_taxable_amount" id="max_no_taxable_amount{{ $sfx }}" value="{{ $num($val('max_no_taxable_amount')) }}">
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label" for="bracket_order{{ $sfx }}">@lang('ledger_payroll.field_bracket_order')</label>
        <input type="number" class="form-control" name="bracket_order" id="bracket_order{{ $sfx }}" value="{{ $val('bracket_order') }}" placeholder="@lang('ledger_payroll.auto_assign')">
    </div>
    <div class="col-md-12 mb-3">
        <label class="form-label" for="liability_account_id{{ $sfx }}">@lang('ledger_payroll.field_liability_account')</label>
        <select class="form-select" name="liability_account_id" id="liability_account_id{{ $sfx }}">
            <option value="">@lang('ledger_payroll.liability_default')</option>
            @foreach($accounts as $a)<option value="{{ $a->id }}" @selected((string) $val('liability_account_id') === (string) $a->id)>{{ $a->account_code }} - {{ \App\Ledger\Services\Preview::accountName($a) }}</option>@endforeach
        </select>
        <small class="text-muted">@lang('ledger_payroll.liability_help')</small>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label" for="effective_from{{ $sfx }}">@lang('ledger_payroll.field_effective_from')</label>
        <input type="date" class="form-control" name="effective_from" id="effective_from{{ $sfx }}" value="{{ $t && $t->effective_from ? $t->effective_from->format('Y-m-d') : old('effective_from') }}">
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label" for="effective_to{{ $sfx }}">@lang('ledger_payroll.field_effective_to')</label>
        <input type="date" class="form-control" name="effective_to" id="effective_to{{ $sfx }}" value="{{ $t && $t->effective_to ? $t->effective_to->format('Y-m-d') : old('effective_to') }}">
    </div>
    @if($t)
    <div class="col-md-6 mb-3">
        <label class="form-label" for="status{{ $sfx }}">@lang('ledger_payroll.field_status')</label>
        <select class="form-select" name="status" id="status{{ $sfx }}">
            <option value="1" @selected($t->status)>@lang('ledger_payroll.status_active')</option>
            <option value="0" @selected(! $t->status)>@lang('ledger_payroll.status_inactive')</option>
        </select>
    </div>
    @endif
</div>
