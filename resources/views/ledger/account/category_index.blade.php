@extends('layouts.app')
@section('title', $title)
@section('css')
@include('ledger.partials.style')
@include('ledger.account.theme', ['kind' => $kind])
@endsection

{{--
  The income and expense category lists (paxhitestbed admin/account/partials/category_index and
  category_edit_modal): the add form beside the list, rename / take out of use in a modal.
  E-Ride: each category's ledger account is set under Mapping settings; a category with records
  is taken out of use rather than deleted.
--}}
@section('content')
<div class="main-body acct acct-cat">
    <div class="page-wrapper">
        @include('ledger.account.errors')
        <div class="row">
            @can($access.'-create')
            <div class="col-lg-4">
                <form method="post" action="{{ route($route.'.store') }}">
                    @csrf
                    <div class="acct-panel">
                        <div class="acct-sec">
                            <div class="acct-sec-head">@lang('ledger_accounts.cat_new')</div>
                            <div class="form-group mb-0">
                                <label for="title" class="form-label">@lang('ledger_accounts.field_title') <span>*</span></label>
                                <input type="text" class="form-control" name="title" id="title" value="{{ old('title') }}" required maxlength="191">
                            </div>
                        </div>
                        <div class="acct-foot">
                            <button type="submit" class="acct-btn acct-btn-main"><i class="fas fa-plus"></i> @lang('ledger_accounts.btn_save')</button>
                        </div>
                    </div>
                </form>
                @can('transaction-mapping-settings')
                <p class="ap-muted"><i class="fas fa-link"></i> @lang('ledger_accounts.cat_mapping_hint') <a href="{{ route('ledger.mappings.settings') }}">@lang('ledger_accounts.mapping_settings')</a></p>
                @endcan
            </div>
            @endcan

            <div class="col-lg-{{ auth()->user()->can($access.'-create') ? '8' : '12' }}">
                <div class="acct-panel">
                    <div class="acct-sec" style="padding-bottom:.5rem;">
                        <div class="acct-sec-head" style="margin-bottom:0;">
                            @lang('ledger_accounts.cat_list')
                            <span class="acct-label" style="letter-spacing:.04em;">{{ number_format($rows->count()) }} @lang('ledger_accounts.cat_count')</span>
                        </div>
                    </div>
                    @if($rows->isEmpty())
                        <div class="acct-empty">
                            <i class="fas fa-tags"></i>
                            <p>@lang('ledger_accounts.cat_none')</p>
                            <small>@lang('ledger_accounts.cat_none_hint')</small>
                        </div>
                    @else
                    <div class="table-responsive">
                        <table class="table acct-table" style="width:100%">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>@lang('ledger_accounts.field_title')</th>
                                    <th>@lang('ledger_accounts.field_status')</th>
                                    <th class="n">@lang('ledger_accounts.field_action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rows as $key => $row)
                                <tr>
                                    <td class="acct-meta">{{ $key + 1 }}</td>
                                    <td class="acct-what">{{ $row->name }}</td>
                                    <td><span class="acct-state"><span class="acct-dot {{ $row->status ? 'is-on' : 'is-off' }}"></span>{{ $row->status ? __('ledger_accounts.status_active') : __('ledger_accounts.status_inactive') }}</span></td>
                                    <td class="acct-acts">
                                        @can($access.'-edit')
                                        <button type="button" class="btn btn-icon btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#editModal-{{ $row->id }}" title="@lang('ledger_accounts.btn_edit')"><i class="far fa-edit"></i></button>
                                        <div id="editModal-{{ $row->id }}" class="modal fade acct-record text-start" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
                                                <form method="post" action="{{ route($route.'.update', $row->id) }}">
                                                    @csrf @method('PUT')
                                                    <div class="modal-header">
                                                        <div><div class="acct-label">{{ $title }}</div><h5 class="modal-title mt-1" style="font-size:.95rem;font-weight:700;">@lang('ledger_accounts.modal_edit')</h5></div>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="@lang('ledger_accounts.btn_close')"></button>
                                                    </div>
                                                    <div class="modal-body" style="padding:1.35rem;">
                                                        <div class="form-group mb-3">
                                                            <label for="title-{{ $row->id }}" class="form-label">@lang('ledger_accounts.field_title') <span style="color:var(--bad);">*</span></label>
                                                            <input type="text" class="form-control" name="title" id="title-{{ $row->id }}" value="{{ $row->name }}" required maxlength="191">
                                                        </div>
                                                        <div class="form-group mb-0">
                                                            <label for="status-{{ $row->id }}" class="form-label">@lang('ledger_accounts.select_status')</label>
                                                            <select class="form-control" name="status" id="status-{{ $row->id }}">
                                                                <option value="1" @selected($row->status)>@lang('ledger_accounts.status_active')</option>
                                                                <option value="0" @selected(! $row->status)>@lang('ledger_accounts.status_inactive')</option>
                                                            </select>
                                                            <small class="form-text" style="font-size:.74rem;color:var(--muted);">@lang('ledger_accounts.cat_status_help')</small>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="acct-btn" data-bs-dismiss="modal">@lang('ledger_accounts.btn_close')</button>
                                                        <button type="submit" class="acct-btn acct-btn-main"><i class="fas fa-check"></i> @lang('ledger_accounts.btn_update')</button>
                                                    </div>
                                                </form>
                                            </div></div>
                                        </div>
                                        @endcan
                                        @can($access.'-delete')
                                        <form method="post" action="{{ route($route.'.destroy', $row->id) }}" class="d-inline" onsubmit="return confirm(@js(__('ledger_accounts.are_you_sure')))">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-icon btn-light btn-sm" title="@lang('ledger_accounts.btn_delete')"><i class="fas fa-trash-alt"></i></button>
                                        </form>
                                        @endcan
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
