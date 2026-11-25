@extends('layouts.app')
@section('title', $row->title)
@section('css')
@include('ledger.partials.style')
@endsection

{{-- paxhitestbed admin/tax-group/show: the group, its bands (add, edit, remove) and the ranges they leave uncovered. --}}
@section('content')
@php $money = fn ($v) => \App\Ledger\Ledger::money($v); $pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ','); $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.'); @endphp
<section class="content">
    @include('ledger.account.errors')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">{{ $row->title }} @if($row->code)<small class="text-muted">({{ $row->code }})</small>@endif</h5>
            <div>
                <a href="{{ route($route.'.index') }}" class="btn btn-light btn-sm"><i class="fas fa-arrow-left"></i> @lang('ledger_payroll.btn_back')</a>
                @can('tax-setting-edit')<a href="{{ route($route.'.edit', $row->id) }}" class="btn btn-primary btn-sm"><i class="far fa-edit"></i> @lang('ledger_payroll.modal_edit')</a>@endcan
            </div>
        </div>
        <div class="card-body">
            <dl class="ap-dl">
                <dt>@lang('ledger_payroll.field_type')</dt><dd>{{ $row->is_progressive ? __('ledger_payroll.progressive_tax') : __('ledger_payroll.flat_rate_tax') }}</dd>
                <dt>@lang('ledger_payroll.field_status')</dt><dd>{{ $row->status ? __('ledger_payroll.status_active') : __('ledger_payroll.status_inactive') }}</dd>
                <dt>@lang('ledger_payroll.field_effective_dates')</dt><dd>{{ $row->effective_from?->format('d/m/Y') ?? '...' }} - {{ $row->effective_to?->format('d/m/Y') ?? '...' }}</dd>
                <dt>@lang('ledger_payroll.field_liability_account')</dt><dd>{{ $row->liabilityAccount ? $row->liabilityAccount->account_code.' - '.\App\Ledger\Services\Preview::accountName($row->liabilityAccount) : __('ledger_payroll.liability_default') }}</dd>
                @if($row->description)<dt>@lang('ledger_payroll.field_description')</dt><dd>{{ $row->description }}</dd>@endif
            </dl>
            <p class="ap-muted mt-2 mb-0"><i class="fas fa-info-circle"></i> @lang('ledger_payroll.step_lookup_help')</p>
        </div>
    </div>

    @if($coverage['gaps'] || $coverage['open_top'])
    <div class="alert alert-warning">
        <strong>@lang('ledger_payroll.uncovered_ranges')</strong>
        <ul class="mb-0">
            @foreach($coverage['gaps'] as $gap)<li>{{ $money($gap['from']) }} - {{ $money($gap['to']) }} @lang('ledger_payroll.charged_as') "{{ $gap['charged_as'] }}"</li>@endforeach
            @if($coverage['open_top'])<li>@lang('ledger_payroll.above') {{ $money($coverage['open_top']['from']) }} @lang('ledger_payroll.every_salary_charged_as') "{{ $coverage['open_top']['band'] }}"</li>@endif
        </ul>
    </div>
    @endif

    <div class="row">
        @can('tax-setting-create')
        <div class="col-lg-4">
            <form action="{{ route($route.'.bracket.store', $row->id) }}" method="post">
                @csrf
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">@lang('ledger_payroll.add_bracket')</h5></div>
                    <div class="card-body">@include('ledger.payroll.tax-group.bracket_fields', ['b' => null])</div>
                    <div class="card-footer"><button type="submit" class="btn btn-success"><i class="fas fa-check"></i> @lang('ledger_payroll.btn_save')</button></div>
                </div>
            </form>
        </div>
        @endcan
        <div class="col-lg-{{ auth()->user()->can('tax-setting-create') ? '8' : '12' }}">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">@lang('ledger_payroll.brackets')</h5></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover ap-table">
                            <thead><tr><th>#</th><th>@lang('ledger_payroll.field_title')</th><th class="ap-num">@lang('ledger_payroll.field_min_amount')</th><th class="ap-num">@lang('ledger_payroll.field_max_amount')</th><th>@lang('ledger_payroll.field_tax_value')</th><th>@lang('ledger_payroll.field_effective_dates')</th><th>@lang('ledger_payroll.field_status')</th><th></th></tr></thead>
                            <tbody>
                                @forelse($row->brackets as $b)
                                <tr>
                                    <td>{{ $b->bracket_order }}</td>
                                    <td>{{ $b->title }}</td>
                                    <td class="ap-num">{{ $money($b->min_amount) }}</td>
                                    <td class="ap-num">{{ $money($b->max_amount) }}</td>
                                    <td class="text-nowrap">
                                        {{ $b->tax_type == 1 ? $pct($b->percentange).'%' : $money($b->fixed_amount) }}
                                        @if($b->max_no_taxable_amount > 0)<small class="text-muted">@lang('ledger_payroll.above_threshold', ['amount' => $money($b->max_no_taxable_amount)])</small>@endif
                                        @if(in_array($b->paid_by, ['employer', 'both']))<br><small>@lang('ledger_payroll.employer'): {{ $b->tax_type == 1 ? $pct($b->employer_percentage).'%' : $money($b->employer_fixed_amount) }}</small>@endif
                                    </td>
                                    <td><small>{{ $b->effective_from?->format('d/m/Y') ?? '...' }} - {{ $b->effective_to?->format('d/m/Y') ?? '...' }}</small></td>
                                    <td>@if($b->status)<span class="badge bg-success">@lang('ledger_payroll.status_active')</span>@else<span class="badge bg-danger">@lang('ledger_payroll.status_inactive')</span>@endif</td>
                                    <td class="text-nowrap">
                                        @can('tax-setting-edit')<button type="button" class="btn btn-icon btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#bracketModal-{{ $b->id }}"><i class="far fa-edit"></i></button>@endcan
                                        @can('tax-setting-delete')
                                        <form action="{{ route($route.'.bracket.destroy', [$row->id, $b->id]) }}" method="post" class="d-inline" onsubmit="return confirm(@js(__('ledger_payroll.modal_are_you_sure')))">
                                            @csrf @method('DELETE')<button type="submit" class="btn btn-icon btn-danger btn-sm"><i class="fas fa-trash-alt"></i></button>
                                        </form>
                                        @endcan
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="8" class="text-center text-muted">@lang('ledger_payroll.no_brackets')</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @can('tax-setting-edit')
    @foreach($row->brackets as $b)
    <div id="bracketModal-{{ $b->id }}" class="modal fade" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog"><div class="modal-content">
            <form action="{{ route($route.'.bracket.update', [$row->id, $b->id]) }}" method="post">
                @csrf @method('PUT')
                <div class="modal-header"><h5 class="modal-title">@lang('ledger_payroll.modal_edit') {{ $b->title }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">@include('ledger.payroll.tax-group.bracket_fields', ['b' => $b])</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> @lang('ledger_payroll.btn_close')</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> @lang('ledger_payroll.btn_update')</button>
                </div>
            </form>
        </div></div>
    </div>
    @endforeach
    @endcan
</section>
@endsection
