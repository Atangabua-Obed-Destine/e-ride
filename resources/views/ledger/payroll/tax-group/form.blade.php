@extends('layouts.app')
@section('title', $title)
@section('css')
@include('ledger.partials.style')
@endsection

{{-- paxhitestbed admin/tax-group/create and edit. --}}
@section('content')
@php $v = fn ($k, $d = null) => old($k, $row ? $row->{$k} : $d); @endphp
<section class="content">
    @include('ledger.account.errors')
    <div class="row"><div class="col-lg-8 offset-lg-2">
        <form action="{{ $row ? route($route.'.update', $row->id) : route($route.'.store') }}" method="post">
            @csrf
            @if($row) @method('PUT') @endif
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">{{ $row ? __('ledger_payroll.modal_edit') : __('ledger_payroll.btn_create') }} {{ $title }}</h5>
                    <a href="{{ route($route.'.index') }}" class="btn btn-light btn-sm"><i class="fas fa-arrow-left"></i> @lang('ledger_payroll.btn_back')</a>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8 mb-3"><label class="form-label" for="title">@lang('ledger_payroll.field_title') <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="title" id="title" value="{{ $v('title') }}" required></div>
                        <div class="col-md-4 mb-3"><label class="form-label" for="code">@lang('ledger_payroll.field_code')</label>
                            <input type="text" class="form-control" name="code" id="code" value="{{ $v('code') }}" maxlength="50"></div>
                        <div class="col-md-12 mb-3"><label class="form-label" for="description">@lang('ledger_payroll.field_description')</label>
                            <textarea class="form-control" name="description" id="description" rows="2" maxlength="500">{{ $v('description') }}</textarea></div>
                        <div class="col-md-6 mb-3"><label class="form-label" for="is_progressive">@lang('ledger_payroll.field_type') <span class="text-danger">*</span></label>
                            <select class="form-select" name="is_progressive" id="is_progressive" required>
                                <option value="1" @selected((string) (int) $v('is_progressive', 1) === '1')>@lang('ledger_payroll.progressive_tax')</option>
                                <option value="0" @selected((string) (int) $v('is_progressive', 1) === '0')>@lang('ledger_payroll.flat_rate_tax')</option>
                            </select>
                            <small class="text-muted">@lang('ledger_payroll.step_lookup_help')</small></div>
                        <div class="col-md-6 mb-3"><label class="form-label" for="display_order">@lang('ledger_payroll.field_order')</label>
                            <input type="number" class="form-control" name="display_order" id="display_order" value="{{ $v('display_order') }}" min="0" placeholder="@lang('ledger_payroll.auto_assign')"></div>
                        <div class="col-md-6 mb-3"><label class="form-label" for="effective_from">@lang('ledger_payroll.field_effective_from')</label>
                            <input type="date" class="form-control" name="effective_from" id="effective_from" value="{{ old('effective_from', $row?->effective_from?->format('Y-m-d')) }}"></div>
                        <div class="col-md-6 mb-3"><label class="form-label" for="effective_to">@lang('ledger_payroll.field_effective_to')</label>
                            <input type="date" class="form-control" name="effective_to" id="effective_to" value="{{ old('effective_to', $row?->effective_to?->format('Y-m-d')) }}"></div>
                        <div class="col-md-12 mb-3"><label class="form-label" for="liability_account_id">@lang('ledger_payroll.field_liability_account')</label>
                            <select class="form-select" name="liability_account_id" id="liability_account_id">
                                <option value="">@lang('ledger_payroll.liability_default')</option>
                                @foreach($accounts as $a)<option value="{{ $a->id }}" @selected((string) $v('liability_account_id') === (string) $a->id)>{{ $a->account_code }} - {{ \App\Ledger\Services\Preview::accountName($a) }}</option>@endforeach
                            </select></div>
                        @if($row)
                        <div class="col-md-6 mb-3"><label class="form-label" for="status">@lang('ledger_payroll.field_status')</label>
                            <select class="form-select" name="status" id="status">
                                <option value="1" @selected($row->status)>@lang('ledger_payroll.status_active')</option>
                                <option value="0" @selected(! $row->status)>@lang('ledger_payroll.status_inactive')</option>
                            </select></div>
                        @endif
                    </div>
                </div>
                <div class="card-footer"><button type="submit" class="btn btn-success"><i class="fas fa-check"></i> @lang('ledger_payroll.btn_save')</button></div>
            </div>
        </form>
    </div></div>
</section>
@endsection
