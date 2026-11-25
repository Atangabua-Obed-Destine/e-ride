@extends('layouts.app')
@section('title', $title)
@section('css')
@include('ledger.partials.style')
@endsection

{{-- paxhitestbed admin/user/index (Staff list): the filter card, then the list. E-Ride: the payroll fields only. --}}
@section('content')
@php $money = fn ($v) => \App\Ledger\Ledger::money($v); @endphp
<section class="content">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">{{ $title }}</h5>
            @can('staff-create')
            <a href="{{ route('ledger.staff.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> @lang('ledger_payroll.btn_add_new')</a>
            @endcan
        </div>
        <div class="card-body">
            <form method="get" action="{{ route('ledger.staff.index') }}">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label" for="department">@lang('ledger_payroll.field_department')</label>
                        <select class="form-select" name="department" id="department">
                            <option value="">@lang('ledger_payroll.all')</option>
                            @foreach($departments as $d)<option value="{{ $d->id }}" @selected($selected_department == $d->id)>{{ $d->title }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="designation">@lang('ledger_payroll.field_designation')</label>
                        <select class="form-select" name="designation" id="designation">
                            <option value="">@lang('ledger_payroll.all')</option>
                            @foreach($designations as $d)<option value="{{ $d->id }}" @selected($selected_designation == $d->id)>{{ $d->title }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="status">@lang('ledger_payroll.field_status')</label>
                        <select class="form-select" name="status" id="status">
                            <option value="1" @selected($selected_status === '1')>@lang('ledger_payroll.status_active')</option>
                            <option value="0" @selected($selected_status === '0')>@lang('ledger_payroll.status_inactive')</option>
                            <option value="all" @selected($selected_status === 'all')>@lang('ledger_payroll.all')</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-info"><i class="fas fa-search"></i> @lang('ledger_payroll.btn_filter')</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover ap-table" style="width:100%">
                    <thead>
                        <tr>
                            <th>@lang('ledger_payroll.field_staff_id')</th>
                            <th>@lang('ledger_payroll.field_name')</th>
                            <th>@lang('ledger_payroll.field_designation')</th>
                            <th>@lang('ledger_payroll.field_department')</th>
                            <th class="ap-num">@lang('ledger_payroll.field_basic_salary')</th>
                            <th>@lang('ledger_payroll.field_pay_to')</th>
                            <th>@lang('ledger_payroll.field_cnps_no')</th>
                            <th>@lang('ledger_payroll.field_phone')</th>
                            <th>@lang('ledger_payroll.field_status')</th>
                            <th>@lang('ledger_payroll.field_action')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                        <tr>
                            <td>#{{ $row->staff_no }}</td>
                            <td>{{ $row->name }}@if($row->user)<br><small class="text-muted"><i class="fas fa-user-check"></i> @lang('ledger_payroll.eride_user')</small>@endif</td>
                            <td>{{ $row->designation->title ?? '' }}</td>
                            <td>{{ $row->department->title ?? '' }}</td>
                            <td class="ap-num">{{ $money($row->basic_salary) }}</td>
                            <td>{{ $row->payTo() }}</td>
                            <td>{{ $row->cnps_no }}</td>
                            <td>{{ $row->phone }}</td>
                            <td>@if($row->status)<span class="badge bg-success">@lang('ledger_payroll.status_active')</span>@else<span class="badge bg-danger">@lang('ledger_payroll.status_inactive')</span>@endif</td>
                            <td class="text-nowrap">
                                @can('staff-edit')
                                <a href="{{ route('ledger.staff.edit', $row->id) }}" class="btn btn-icon btn-primary btn-sm"><i class="far fa-edit"></i></a>
                                @endcan
                                @can('staff-delete')
                                <form action="{{ route('ledger.staff.destroy', $row->id) }}" method="post" class="d-inline" onsubmit="return confirm(@js(__('ledger_payroll.modal_are_you_sure')))">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-icon btn-danger btn-sm"><i class="fas fa-trash-alt"></i></button>
                                </form>
                                @endcan
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="10" class="text-center text-muted">@lang('ledger_payroll.no_staff')</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
@endsection
