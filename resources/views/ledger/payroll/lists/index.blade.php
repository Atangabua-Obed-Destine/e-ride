@extends('layouts.app')
@section('title', $title)
@section('css')
@include('ledger.partials.style')
@endsection

{{--
  paxhitestbed admin/designation, department, allowance-type and deduction-type (index + edit):
  the add form beside the list, edit in a modal, delete with a confirmation. $columns says which of
  description and "Taxed on payroll" the list has.
--}}
@section('content')
<section class="content">
    @include('ledger.account.errors')
    <div class="row">
        @can($access.'-create')
        <div class="col-md-4">
            <form action="{{ route($route.'.store') }}" method="post">
                @csrf
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">@lang('ledger_payroll.btn_create') {{ $title }}</h5></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="title" class="form-label">@lang('ledger_payroll.field_title') <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="title" id="title" value="{{ old('title') }}" required maxlength="191">
                        </div>
                        @if(in_array('description', $columns))
                        <div class="mb-3">
                            <label for="description" class="form-label">@lang('ledger_payroll.field_description')</label>
                            <textarea class="form-control" name="description" id="description" rows="3">{{ old('description') }}</textarea>
                        </div>
                        @endif
                        @if(in_array('is_taxable', $columns))
                        <div class="mb-3">
                            <label for="is_taxable" class="form-label">@lang('ledger_payroll.taxed_on_payroll') <span class="text-danger">*</span></label>
                            <select class="form-select" name="is_taxable" id="is_taxable" required>
                                <option value="0" @selected(old('is_taxable', '0') == '0')>@lang('ledger_payroll.not_taxed_long')</option>
                                <option value="1" @selected(old('is_taxable') == '1')>@lang('ledger_payroll.taxed_long')</option>
                            </select>
                            <small class="text-muted">@lang('ledger_payroll.taxed_help')</small>
                        </div>
                        @endif
                        <div class="mb-0">
                            <label for="status" class="form-label">@lang('ledger_payroll.field_status') <span class="text-danger">*</span></label>
                            <select class="form-select" name="status" id="status" required>
                                <option value="1" selected>@lang('ledger_payroll.status_active')</option>
                                <option value="0">@lang('ledger_payroll.status_inactive')</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> @lang('ledger_payroll.btn_save')</button>
                    </div>
                </div>
            </form>
        </div>
        @endcan
        <div class="col-md-{{ auth()->user()->can($access.'-create') ? '8' : '12' }}">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">{{ $title }} @lang('ledger_payroll.list')</h5></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover ap-table" style="width:100%">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>@lang('ledger_payroll.field_title')</th>
                                    @if(in_array('description', $columns))<th>@lang('ledger_payroll.field_description')</th>@endif
                                    @if(in_array('is_taxable', $columns))<th>@lang('ledger_payroll.taxed_on_payroll')</th>@endif
                                    <th>@lang('ledger_payroll.field_status')</th>
                                    <th>@lang('ledger_payroll.field_action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($rows as $key => $row)
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    <td>{{ $row->title }}</td>
                                    @if(in_array('description', $columns))<td>{{ $row->description }}</td>@endif
                                    @if(in_array('is_taxable', $columns))
                                    <td>
                                        @if($row->is_taxable)<span class="badge bg-warning text-dark">@lang('ledger_payroll.taxed')</span>
                                        @else<span class="badge bg-secondary">@lang('ledger_payroll.not_taxed')</span>@endif
                                    </td>
                                    @endif
                                    <td>
                                        @if($row->status)<span class="badge bg-success">@lang('ledger_payroll.status_active')</span>
                                        @else<span class="badge bg-danger">@lang('ledger_payroll.status_inactive')</span>@endif
                                    </td>
                                    <td class="text-nowrap">
                                        @can($access.'-edit')
                                        <button type="button" class="btn btn-icon btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal-{{ $row->id }}"><i class="far fa-edit"></i></button>
                                        <div id="editModal-{{ $row->id }}" class="modal fade" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog"><div class="modal-content">
                                                <form action="{{ route($route.'.update', $row->id) }}" method="post">
                                                    @csrf @method('PUT')
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">@lang('ledger_payroll.modal_edit') {{ $title }}</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="@lang('ledger_payroll.btn_close')"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="form-label">@lang('ledger_payroll.field_title') <span class="text-danger">*</span></label>
                                                            <input type="text" class="form-control" name="title" value="{{ $row->title }}" required maxlength="191">
                                                        </div>
                                                        @if(in_array('description', $columns))
                                                        <div class="mb-3">
                                                            <label class="form-label">@lang('ledger_payroll.field_description')</label>
                                                            <textarea class="form-control" name="description" rows="3">{{ $row->description }}</textarea>
                                                        </div>
                                                        @endif
                                                        @if(in_array('is_taxable', $columns))
                                                        <div class="mb-3">
                                                            <label class="form-label">@lang('ledger_payroll.taxed_on_payroll')</label>
                                                            <select class="form-select" name="is_taxable">
                                                                <option value="0" @selected(! $row->is_taxable)>@lang('ledger_payroll.not_taxed_long')</option>
                                                                <option value="1" @selected($row->is_taxable)>@lang('ledger_payroll.taxed_long')</option>
                                                            </select>
                                                        </div>
                                                        @endif
                                                        <div class="mb-0">
                                                            <label class="form-label">@lang('ledger_payroll.select_status')</label>
                                                            <select class="form-select" name="status">
                                                                <option value="1" @selected($row->status)>@lang('ledger_payroll.status_active')</option>
                                                                <option value="0" @selected(! $row->status)>@lang('ledger_payroll.status_inactive')</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> @lang('ledger_payroll.btn_close')</button>
                                                        <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> @lang('ledger_payroll.btn_update')</button>
                                                    </div>
                                                </form>
                                            </div></div>
                                        </div>
                                        @endcan
                                        @can($access.'-delete')
                                        <form action="{{ route($route.'.destroy', $row->id) }}" method="post" class="d-inline" onsubmit="return confirm(@js(__('ledger_payroll.modal_are_you_sure')))">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-icon btn-danger btn-sm"><i class="fas fa-trash-alt"></i></button>
                                        </form>
                                        @endcan
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="6" class="text-center text-muted">@lang('ledger_payroll.no_data')</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
