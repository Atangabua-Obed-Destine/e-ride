{{-- A band of a tax group (paxhitestbed admin/tax-group/show forms). $b: the band being edited, or null. --}}
@php
    $v = fn ($k, $d = null) => $b ? $b->{$k} : old($k, $d);
    $num = fn ($x) => $x === null || $x === '' ? '' : rtrim(rtrim(number_format((float) $x, 4, '.', ''), '0'), '.');
@endphp
<div class="mb-3"><label class="form-label">@lang('ledger_payroll.field_title') <span class="text-danger">*</span></label>
    <input type="text" class="form-control" name="title" value="{{ $v('title') }}" required></div>
<div class="row">
    <div class="col-6 mb-3"><label class="form-label">@lang('ledger_payroll.field_tax_type') <span class="text-danger">*</span></label>
        <select class="form-select" name="tax_type" required>
            <option value="1" @selected((string) $v('tax_type', 1) === '1')>@lang('ledger_payroll.tax_type_percentage')</option>
            <option value="2" @selected((string) $v('tax_type') === '2')>@lang('ledger_payroll.tax_type_fixed')</option>
        </select></div>
    <div class="col-6 mb-3"><label class="form-label">@lang('ledger_payroll.field_paid_by')</label>
        <select class="form-select" name="paid_by">
            @foreach(['employee', 'employer', 'both'] as $p)<option value="{{ $p }}" @selected($v('paid_by', 'employee') === $p)>@lang('ledger_payroll.paid_by_'.$p)</option>@endforeach
        </select></div>
    <div class="col-6 mb-3"><label class="form-label">@lang('ledger_payroll.field_min_amount') <span class="text-danger">*</span></label>
        <input type="number" step="any" class="form-control" name="min_amount" value="{{ $num($v('min_amount')) }}" required></div>
    <div class="col-6 mb-3"><label class="form-label">@lang('ledger_payroll.field_max_amount') <span class="text-danger">*</span></label>
        <input type="number" step="any" class="form-control" name="max_amount" value="{{ $num($v('max_amount')) }}" required></div>
    <div class="col-6 mb-3"><label class="form-label">@lang('ledger_payroll.field_percentage')</label>
        <input type="number" step="any" class="form-control" name="percentange" value="{{ $num($v('percentange')) }}"></div>
    <div class="col-6 mb-3"><label class="form-label">@lang('ledger_payroll.field_fixed_amount')</label>
        <input type="number" step="any" class="form-control" name="fixed_amount" value="{{ $num($v('fixed_amount')) }}"></div>
    <div class="col-6 mb-3"><label class="form-label">@lang('ledger_payroll.field_employer_percentage')</label>
        <input type="number" step="any" class="form-control" name="employer_percentage" value="{{ $num($v('employer_percentage')) }}"></div>
    <div class="col-6 mb-3"><label class="form-label">@lang('ledger_payroll.field_employer_fixed_amount')</label>
        <input type="number" step="any" class="form-control" name="employer_fixed_amount" value="{{ $num($v('employer_fixed_amount')) }}"></div>
    <div class="col-6 mb-3"><label class="form-label">@lang('ledger_payroll.field_max_no_taxable_amount')</label>
        <input type="number" step="any" class="form-control" name="max_no_taxable_amount" value="{{ $num($v('max_no_taxable_amount')) }}"></div>
    <div class="col-6 mb-3"><label class="form-label">@lang('ledger_payroll.field_bracket_order')</label>
        <input type="number" class="form-control" name="bracket_order" value="{{ $v('bracket_order') }}" placeholder="@lang('ledger_payroll.auto_assign')"></div>
    <div class="col-6 mb-3"><label class="form-label">@lang('ledger_payroll.field_effective_from')</label>
        <input type="date" class="form-control" name="effective_from" value="{{ $b && $b->effective_from ? $b->effective_from->format('Y-m-d') : '' }}"></div>
    <div class="col-6 mb-3"><label class="form-label">@lang('ledger_payroll.field_effective_to')</label>
        <input type="date" class="form-control" name="effective_to" value="{{ $b && $b->effective_to ? $b->effective_to->format('Y-m-d') : '' }}"></div>
    @if($b)
    <div class="col-6 mb-3"><label class="form-label">@lang('ledger_payroll.field_status')</label>
        <select class="form-select" name="status"><option value="1" @selected($b->status)>@lang('ledger_payroll.status_active')</option><option value="0" @selected(! $b->status)>@lang('ledger_payroll.status_inactive')</option></select></div>
    @endif
</div>
