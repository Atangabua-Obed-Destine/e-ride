@extends('layouts.app')
@section('title', $title)
@section('css')
@include('ledger.partials.style')
@endsection

{{-- paxhitestbed admin/tax-group/index: the groups, their bands and their state. --}}
@section('content')
<section class="content">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">{{ $title }}</h5>
            @can('tax-setting-create')<a href="{{ route($route.'.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> @lang('ledger_payroll.btn_add_new')</a>@endcan
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover ap-table" style="width:100%">
                    <thead>
                        <tr>
                            <th>@lang('ledger_payroll.field_order')</th>
                            <th>@lang('ledger_payroll.field_title')</th>
                            <th>@lang('ledger_payroll.field_code')</th>
                            <th>@lang('ledger_payroll.field_type')</th>
                            <th>@lang('ledger_payroll.brackets')</th>
                            <th>@lang('ledger_payroll.field_liability_account')</th>
                            <th>@lang('ledger_payroll.field_effective_dates')</th>
                            <th>@lang('ledger_payroll.field_status')</th>
                            <th>@lang('ledger_payroll.field_action')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                        <tr>
                            <td>{{ $row->display_order }}</td>
                            <td><a href="{{ route($route.'.show', $row->id) }}">{{ $row->title }}</a>@if($row->description)<br><small class="text-muted">{{ $row->description }}</small>@endif</td>
                            <td>{{ $row->code }}</td>
                            <td>{{ $row->is_progressive ? __('ledger_payroll.progressive_tax') : __('ledger_payroll.flat_rate_tax') }}</td>
                            <td>{{ $row->brackets->where('status', 1)->count() }} / {{ $row->brackets->count() }}</td>
                            <td>{{ $row->liabilityAccount->account_code ?? '' }}</td>
                            <td><small>{{ $row->effective_from?->format('d/m/Y') ?? '...' }} - {{ $row->effective_to?->format('d/m/Y') ?? '...' }}</small></td>
                            <td>@if($row->status)<span class="badge bg-success">@lang('ledger_payroll.status_active')</span>@else<span class="badge bg-danger">@lang('ledger_payroll.status_inactive')</span>@endif</td>
                            <td class="text-nowrap">
                                <a href="{{ route($route.'.show', $row->id) }}" class="btn btn-icon btn-info btn-sm" title="@lang('ledger_payroll.view_brackets')"><i class="fas fa-eye"></i></a>
                                @can('tax-setting-edit')<a href="{{ route($route.'.edit', $row->id) }}" class="btn btn-icon btn-primary btn-sm"><i class="far fa-edit"></i></a>@endcan
                                @can('tax-setting-delete')
                                <form action="{{ route($route.'.destroy', $row->id) }}" method="post" class="d-inline" onsubmit="return confirm(@js(__('ledger_payroll.delete_group_confirm')))">
                                    @csrf @method('DELETE')<button type="submit" class="btn btn-icon btn-danger btn-sm"><i class="fas fa-trash-alt"></i></button>
                                </form>
                                @endcan
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="9" class="text-center text-muted">@lang('ledger_payroll.no_data')</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
@endsection
