<select class="form-select" name="{{ $name }}">
    <option value="1" @selected($value)>@lang('ledger_payroll.yes')</option>
    <option value="0" @selected(! $value)>@lang('ledger_payroll.no')</option>
</select>
