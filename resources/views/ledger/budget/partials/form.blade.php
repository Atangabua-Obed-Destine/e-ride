{{-- The departmental budget form (paxhitestbed budget/create and edit: same fields, same order). Expects $row (null on create), $locations, $annualBudgets. --}}
<div class="row">
    <div class="col-md-6 form-group">
        <label for="title">@lang('ledger_budget.field_title') <span class="text-danger">*</span></label>
        <input type="text" class="form-control" name="title" id="title" value="{{ old('title', $row->title ?? '') }}" maxlength="191" required>
    </div>
    <div class="col-md-3 form-group">
        <label for="type">@lang('ledger_budget.field_type') <span class="text-danger">*</span></label>
        <select class="form-control" name="type" id="type" required>
            <option value="">@lang('ledger_budget.select')</option>
            @foreach(['annual', 'departmental', 'project'] as $t)
                <option value="{{ $t }}" {{ old('type', $row->type ?? '') == $t ? 'selected' : '' }}>{{ __('ledger_budget.type_'.$t) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3 form-group">
        <label for="fiscal_year">@lang('ledger_budget.field_fiscal_year') <span class="text-danger">*</span></label>
        <input type="text" class="form-control" name="fiscal_year" id="fiscal_year" value="{{ old('fiscal_year', $row->fiscal_year ?? date('Y')) }}" maxlength="10" required>
    </div>
    <div class="col-md-3 form-group" id="department_div" style="display: none;">
        <label for="location_id">@lang('ledger_budget.field_department') <span class="text-danger" id="dept_required">*</span></label>
        <select class="form-control" name="location_id" id="location_id">
            <option value="">@lang('ledger_budget.select')</option>
            @foreach($locations as $l)
                <option value="{{ $l->id }}" {{ old('location_id', $row->location_id ?? '') == $l->id ? 'selected' : '' }}>{{ $l->name }}</option>
            @endforeach
        </select>
        <p class="help-block">@lang('ledger_budget.department_is_location')</p>
    </div>
    <div class="col-md-3 form-group">
        <label for="parent_id">@lang('ledger_budget.belongs_to')</label>
        <select class="form-control" name="parent_id" id="parent_id">
            <option value="">@lang('ledger_budget.not_part_of_annual')</option>
            @foreach($annualBudgets as $annual)
                <option value="{{ $annual->id }}" {{ old('parent_id', $row->parent_id ?? null) == $annual->id ? 'selected' : '' }}>{{ $annual->title }}</option>
            @endforeach
        </select>
        <p class="help-block">@lang('ledger_budget.belongs_to_help')</p>
    </div>
    <div class="col-md-3 form-group">
        <label for="start_date">@lang('ledger_budget.field_start_date') <span class="text-danger">*</span></label>
        <input type="date" class="form-control" name="start_date" id="start_date" value="{{ old('start_date', optional($row->start_date ?? null)->format('Y-m-d')) }}" required>
    </div>
    <div class="col-md-3 form-group">
        <label for="end_date">@lang('ledger_budget.field_end_date') <span class="text-danger">*</span></label>
        <input type="date" class="form-control" name="end_date" id="end_date" value="{{ old('end_date', optional($row->end_date ?? null)->format('Y-m-d')) }}" required>
    </div>
    <div class="col-md-3 form-group">
        <label for="total_amount">@lang('ledger_budget.field_total_amount') <span class="text-danger">*</span></label>
        <input type="number" step="0.01" min="0" class="form-control" name="total_amount" id="total_amount" value="{{ old('total_amount', isset($row) ? (float) $row->total_amount : '') }}" required>
    </div>
    <div class="col-md-12 form-group">
        <label for="description">@lang('ledger_budget.field_description')</label>
        <textarea class="form-control" name="description" id="description" rows="3">{{ old('description', $row->description ?? '') }}</textarea>
    </div>
    <div class="col-md-12 form-group">
        <label for="note">@lang('ledger_budget.field_note')</label>
        <textarea class="form-control" name="note" id="note" rows="2">{{ old('note', $row->note ?? '') }}</textarea>
    </div>
</div>
