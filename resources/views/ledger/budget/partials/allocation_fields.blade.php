{{--
    The fields of an allocation (paxhitestbed: expense category, title, amount, period, department,
    description). PosInnova adds the ledger account after the category: at least one of the two says
    what the ledger counts as spent against it. Expects $a (null when adding), $budget, $expenseCategories,
    $accounts, $locations.
--}}
@php $uid = $a ? $a->id : 'new'; $isNew = ! $a; @endphp
<div class="row">
    <div class="col-md-3 form-group">
        <label for="expense_category_id_{{ $uid }}">@lang('ledger_budget.field_expense_category')</label>
        <select class="form-control" name="expense_category_id" id="expense_category_id_{{ $uid }}">
            <option value="">@lang('ledger_budget.select')</option>
            @foreach($expenseCategories as $category)
                <option value="{{ $category->id }}" {{ (string) ($isNew ? old('expense_category_id') : $a->expense_category_id) === (string) $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3 form-group">
        <label for="account_id_{{ $uid }}">@lang('ledger_budget.ledger_account')</label>
        <select class="form-control" name="account_id" id="account_id_{{ $uid }}">
            <option value="">@lang('ledger_budget.select')</option>
            @foreach($accounts as $acc)
                <option value="{{ $acc['id'] }}" {{ (string) ($isNew ? old('account_id') : $a->account_id) === (string) $acc['id'] ? 'selected' : '' }}>{{ $acc['label'] }}{{ $acc['heading'] ? ' '.__('ledger_budget.heading_marker') : '' }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6 form-group">
        <label for="title_{{ $uid }}">@lang('ledger_budget.field_title') <span class="text-danger">*</span></label>
        <input type="text" class="form-control" name="title" id="title_{{ $uid }}" value="{{ $isNew ? old('title') : $a->title }}" maxlength="191" required>
    </div>
    <div class="col-md-12"><p class="help-block" style="margin-top: -8px;">@lang('ledger_budget.allocation_target_help')</p></div>
    <div class="col-md-3 form-group">
        <label for="allocated_amount_{{ $uid }}">@lang('ledger_budget.field_amount') <span class="text-danger">*</span></label>
        <input type="number" step="0.01" min="0" class="form-control" name="allocated_amount" id="allocated_amount_{{ $uid }}"
               value="{{ $isNew ? old('allocated_amount') : (float) $a->allocated_amount }}" required>
        @if($isNew)<p class="help-block">@lang('ledger_budget.max', ['amount' => \App\Ledger\Ledger::money((float) $budget->total_amount - (float) $budget->allocated_amount)])</p>@endif
    </div>
    <div class="col-md-3 form-group">
        <label for="period_{{ $uid }}">@lang('ledger_budget.field_period') <span class="text-danger">*</span></label>
        <select class="form-control" name="period" id="period_{{ $uid }}" required>
            @foreach(\App\Ledger\Models\BudgetAllocation::PERIODS as $p)
                <option value="{{ $p }}" {{ ($isNew ? old('period', 'yearly') : $a->period) === $p ? 'selected' : '' }}>{{ \App\Ledger\Models\BudgetAllocation::periodLabel($p) }}</option>
            @endforeach
        </select>
    </div>
    @if($budget->type !== 'departmental' || ! $isNew)
    <div class="col-md-3 form-group">
        <label for="location_id_{{ $uid }}">@lang('ledger_budget.field_department')</label>
        <select class="form-control" name="location_id" id="location_id_{{ $uid }}">
            <option value="">{{ $budget->location ? __('ledger_budget.budget_location', ['name' => $budget->location->name]) : __('ledger_budget.select') }}</option>
            @foreach($locations as $l)
                <option value="{{ $l->id }}" {{ (string) ($isNew ? old('location_id') : $a->location_id) === (string) $l->id ? 'selected' : '' }}>{{ $l->name }}</option>
            @endforeach
        </select>
        <p class="help-block">@lang('ledger_budget.subdivide_by_department')</p>
    </div>
    @else
        <input type="hidden" name="location_id" value="">
    @endif
    <div class="col-md-12 form-group">
        <label for="description_{{ $uid }}">@lang('ledger_budget.field_description')</label>
        <textarea class="form-control" name="description" id="description_{{ $uid }}" rows="2">{{ $isNew ? old('description') : $a->description }}</textarea>
    </div>
</div>
