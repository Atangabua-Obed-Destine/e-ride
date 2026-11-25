@extends('layouts.app')
@section('title', $title)
@section('css')
@include('ledger.partials.style')
@endsection

{{-- paxhitestbed admin/user/create and edit, as cards: basic information, employment, pay and tax. E-Ride: payroll fields only. --}}
@section('content')
@php
    $v = fn ($k, $d = null) => old($k, $row ? $row->{$k} : $d);
    $date = fn ($k) => old($k, $row && $row->{$k} ? $row->{$k}->format('Y-m-d') : null);
@endphp
<section class="content">
    @include('ledger.account.errors')
    <form action="{{ $row ? route('ledger.staff.update', $row->id) : route('ledger.staff.store') }}" method="post">
        @csrf
        @if($row) @method('PUT') @endif
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ $row ? __('ledger_payroll.modal_edit') : __('ledger_payroll.btn_create') }} {{ $title }}</h5>
                <a href="{{ route('ledger.staff.index') }}" class="btn btn-light btn-sm"><i class="fas fa-arrow-left"></i> @lang('ledger_payroll.btn_back')</a>
            </div>
            <div class="card-body">
                <fieldset class="row gx-3">
                    <legend class="fs-6 fw-bold mb-2">@lang('ledger_payroll.basic_info')</legend>
                    <div class="col-md-3 mb-3">
                        <label class="form-label" for="staff_no">@lang('ledger_payroll.field_staff_id') <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="staff_no" id="staff_no" value="{{ $v('staff_no', $next_no) }}" required maxlength="50">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label" for="first_name">@lang('ledger_payroll.field_first_name') <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="first_name" id="first_name" value="{{ $v('first_name') }}" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label" for="last_name">@lang('ledger_payroll.field_last_name')</label>
                        <input type="text" class="form-control" name="last_name" id="last_name" value="{{ $v('last_name') }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label" for="gender">@lang('ledger_payroll.field_gender')</label>
                        <select class="form-select" name="gender" id="gender">
                            <option value="">@lang('ledger_payroll.select')</option>
                            @foreach([1 => 'gender_male', 2 => 'gender_female', 3 => 'gender_other'] as $k => $l)
                            <option value="{{ $k }}" @selected((string) $v('gender') === (string) $k)>@lang('ledger_payroll.'.$l)</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label" for="phone">@lang('ledger_payroll.field_phone')</label>
                        <input type="text" class="form-control" name="phone" id="phone" value="{{ $v('phone') }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label" for="email">@lang('ledger_payroll.field_email')</label>
                        <input type="email" class="form-control" name="email" id="email" value="{{ $v('email') }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="user_id">@lang('ledger_payroll.field_eride_user')</label>
                        <select class="form-select" name="user_id" id="user_id">
                            <option value="">@lang('ledger_payroll.not_an_eride_user')</option>
                            @foreach($people as $id => $label)<option value="{{ $id }}" @selected((string) $v('user_id') === (string) $id)>{{ $label }}</option>@endforeach
                        </select>
                        <small class="text-muted">@lang('ledger_payroll.eride_user_help')</small>
                    </div>
                </fieldset>
                <hr>
                <fieldset class="row gx-3">
                    <legend class="fs-6 fw-bold mb-2">@lang('ledger_payroll.employment_info')</legend>
                    <div class="col-md-3 mb-3">
                        <label class="form-label" for="designation_id">@lang('ledger_payroll.field_designation')</label>
                        <select class="form-select" name="designation_id" id="designation_id">
                            <option value="">@lang('ledger_payroll.select')</option>
                            @foreach($designations as $d)<option value="{{ $d->id }}" @selected((string) $v('designation_id') === (string) $d->id)>{{ $d->title }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label" for="department_id">@lang('ledger_payroll.field_department')</label>
                        <select class="form-select" name="department_id" id="department_id">
                            <option value="">@lang('ledger_payroll.select')</option>
                            @foreach($departments as $d)<option value="{{ $d->id }}" @selected((string) $v('department_id') === (string) $d->id)>{{ $d->title }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label" for="contract_type">@lang('ledger_payroll.field_contract_type')</label>
                        <select class="form-select" name="contract_type" id="contract_type">
                            <option value="">@lang('ledger_payroll.select')</option>
                            <option value="1" @selected((string) $v('contract_type') === '1')>@lang('ledger_payroll.contract_type_full_time')</option>
                            <option value="2" @selected((string) $v('contract_type') === '2')>@lang('ledger_payroll.contract_type_part_time')</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label" for="salary_type">@lang('ledger_payroll.field_salary_type')</label>
                        <select class="form-select" name="salary_type" id="salary_type">
                            <option value="1" @selected((string) $v('salary_type', 1) === '1')>@lang('ledger_payroll.salary_type_fixed')</option>
                            <option value="2" @selected((string) $v('salary_type', 1) === '2')>@lang('ledger_payroll.salary_type_hourly')</option>
                        </select>
                        <small class="text-muted">@lang('ledger_payroll.hourly_help')</small>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label" for="basic_salary">@lang('ledger_payroll.field_basic_salary') (FCFA <span id="salaryUnit">/ {{ (string) $v('salary_type', 1) === '2' ? __('ledger_payroll.hour') : __('ledger_payroll.month') }}</span>) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="basic_salary" id="basic_salary" value="{{ $v('basic_salary') !== null ? round((float) $v('basic_salary')) : '' }}" min="0" step="1" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label" for="joining_date">@lang('ledger_payroll.field_joining_date')</label>
                        <input type="date" class="form-control" name="joining_date" id="joining_date" value="{{ $date('joining_date') }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label" for="ending_date">@lang('ledger_payroll.field_ending_date')</label>
                        <input type="date" class="form-control" name="ending_date" id="ending_date" value="{{ $date('ending_date') }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label" for="status">@lang('ledger_payroll.field_status')</label>
                        <select class="form-select" name="status" id="status">
                            <option value="1" @selected((string) $v('status', 1) === '1')>@lang('ledger_payroll.status_active')</option>
                            <option value="0" @selected((string) $v('status', 1) === '0')>@lang('ledger_payroll.status_inactive')</option>
                        </select>
                    </div>
                </fieldset>
                <hr>
                <fieldset class="row gx-3">
                    <legend class="fs-6 fw-bold mb-2">@lang('ledger_payroll.pay_and_tax_info')</legend>
                    <div class="col-md-3 mb-3">
                        <label class="form-label" for="pay_channel">@lang('ledger_payroll.field_pay_channel')</label>
                        <select class="form-select" name="pay_channel" id="pay_channel">
                            <option value="">@lang('ledger_payroll.select')</option>
                            @foreach(\App\Ledger\Models\Staff::PAY_CHANNELS as $c)<option value="{{ $c }}" @selected($v('pay_channel') === $c)>@lang('ledger_payroll.channel_'.$c)</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label" for="momo_provider">@lang('ledger_payroll.field_momo_provider')</label>
                        <select class="form-select" name="momo_provider" id="momo_provider">
                            <option value="">@lang('ledger_payroll.select')</option>
                            <option value="mtn" @selected($v('momo_provider') === 'mtn')>MTN Mobile Money</option>
                            <option value="orange" @selected($v('momo_provider') === 'orange')>Orange Money</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label" for="momo_number">@lang('ledger_payroll.field_momo_number')</label>
                        <input type="text" class="form-control" name="momo_number" id="momo_number" value="{{ $v('momo_number') }}">
                    </div>
                    <div class="col-md-3 mb-3"></div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label" for="bank_name">@lang('ledger_payroll.field_bank_name')</label>
                        <input type="text" class="form-control" name="bank_name" id="bank_name" value="{{ $v('bank_name') }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label" for="bank_branch">@lang('ledger_payroll.field_bank_branch')</label>
                        <input type="text" class="form-control" name="bank_branch" id="bank_branch" value="{{ $v('bank_branch') }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label" for="account_name">@lang('ledger_payroll.field_account_name')</label>
                        <input type="text" class="form-control" name="account_name" id="account_name" value="{{ $v('account_name') }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label" for="account_number">@lang('ledger_payroll.field_account_number')</label>
                        <input type="text" class="form-control" name="account_number" id="account_number" value="{{ $v('account_number') }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label" for="bank_code">@lang('ledger_payroll.field_bank_code')</label>
                        <input type="text" class="form-control" name="bank_code" id="bank_code" value="{{ $v('bank_code') }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label" for="cnps_no">@lang('ledger_payroll.field_cnps_no')</label>
                        <input type="text" class="form-control" name="cnps_no" id="cnps_no" value="{{ $v('cnps_no') }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label" for="niu">@lang('ledger_payroll.field_niu')</label>
                        <input type="text" class="form-control" name="niu" id="niu" value="{{ $v('niu') }}">
                    </div>
                    <div class="col-md-12 mb-3">
                        <label class="form-label" for="note">@lang('ledger_payroll.field_note')</label>
                        <textarea class="form-control" name="note" id="note" rows="2">{{ $v('note') }}</textarea>
                    </div>
                    @if($row)
                    <div class="col-md-12">
                        <small class="text-muted"><i class="fas fa-info-circle"></i> @lang('ledger_payroll.exemptions_where')
                        @can('tax-setting-view')<a href="{{ route('ledger.tax-setting.index') }}">@lang('ledger_payroll.tax_settings')</a>@endcan</small>
                    </div>
                    @endif
                </fieldset>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> @lang('ledger_payroll.btn_save')</button>
            </div>
        </div>
    </form>
</section>
@endsection

@section('javascript')
<script>
(function () {
    var t = document.getElementById('salary_type'), u = document.getElementById('salaryUnit');
    var L = {!! json_encode(['1' => '/ '.__('ledger_payroll.month'), '2' => '/ '.__('ledger_payroll.hour')], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!};
    if (t && u) { t.addEventListener('change', function () { u.textContent = L[t.value] || ''; }); }
})();
</script>
@endsection
