@extends('layouts.app')
@section('title', $title)
@section('css')
@include('ledger.partials.style')
@endsection

{{--
  paxhitestbed admin/tax-setting/index (+ edit and exemptions modals): the add form beside the list
  of every band and standalone tax, the coverage warnings above it, and each tax's exempt staff.
--}}
@section('content')
@php $money = fn ($v) => \App\Ledger\Ledger::money($v); $pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ','); @endphp
<section class="content">
    @include('ledger.account.errors')
    <div class="row">
        @can('tax-setting-create')
        <div class="col-lg-4">
            <form action="{{ route($route.'.store') }}" method="post">
                @csrf
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">@lang('ledger_payroll.btn_create') {{ $title }}</h5></div>
                    <div class="card-body">@include('ledger.payroll.tax-setting.fields', ['t' => null])</div>
                    <div class="card-footer"><button type="submit" class="btn btn-success"><i class="fas fa-check"></i> @lang('ledger_payroll.btn_save')</button></div>
                </div>
            </form>
        </div>
        @endcan
        <div class="col-lg-{{ auth()->user()->can('tax-setting-create') ? '8' : '12' }}">
            @if($misfiled_dependents->isNotEmpty())
            <div class="alert alert-danger">
                <strong>@lang('ledger_payroll.wrong_taxes')</strong> @lang('ledger_payroll.misfiled_help')
                <ul class="mb-0">@foreach($misfiled_dependents as $d)<li>{{ $d->title }}: @lang('ledger_payroll.misfiled_line', ['group' => $d->taxGroup->title ?? '']) @lang('ledger_payroll.clear_group')</li>@endforeach</ul>
            </div>
            @endif
            @php $bad = collect($coverage)->filter(fn ($c) => $c['biting_gaps'] || $c['top_exceeded']); @endphp
            @if($bad->isNotEmpty())
            <div class="alert alert-warning">
                <strong>@lang('ledger_payroll.uncovered_ranges')</strong> @lang('ledger_payroll.uncovered_help')
                <small class="d-block">@lang('ledger_payroll.only_biting', ['salary' => $money($highest_salary)])</small>
                <ul class="mb-0">
                    @foreach($bad as $groupId => $c)
                    @php $g = $tax_groups->firstWhere('id', $groupId); @endphp
                    @foreach($c['biting_gaps'] as $gap)<li>{{ $g->title ?? '' }}: {{ $money($gap['from']) }} - {{ $money($gap['to']) }} @lang('ledger_payroll.charged_as') "{{ $gap['charged_as'] }}"</li>@endforeach
                    @if($c['top_exceeded'])<li>{{ $g->title ?? '' }}: @lang('ledger_payroll.above') {{ $money($c['open_top']['from']) }} @lang('ledger_payroll.every_salary_charged_as') "{{ $c['open_top']['band'] }}" @lang('ledger_payroll.however_high')</li>@endif
                    @endforeach
                </ul>
                <small>@lang('ledger_payroll.fix_bands')</small>
            </div>
            @endif
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">{{ $title }} @lang('ledger_payroll.list')</h5>
                    @can('tax-setting-view')<a href="{{ route('ledger.tax-group.index') }}" class="btn btn-light btn-sm"><i class="fas fa-layer-group"></i> @lang('ledger_payroll.tax_groups')</a>@endcan
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover ap-table" style="width:100%">
                            <thead>
                                <tr>
                                    <th>@lang('ledger_payroll.field_order')</th>
                                    <th>@lang('ledger_payroll.field_tax_title')</th>
                                    <th>@lang('ledger_payroll.field_tax_group')</th>
                                    <th>@lang('ledger_payroll.field_tax_type')</th>
                                    <th class="ap-num">@lang('ledger_payroll.field_min_amount')</th>
                                    <th class="ap-num">@lang('ledger_payroll.field_max_amount')</th>
                                    <th>@lang('ledger_payroll.field_tax_value')</th>
                                    <th>@lang('ledger_payroll.field_liability_account')</th>
                                    <th>@lang('ledger_payroll.field_effective_dates')</th>
                                    <th>@lang('ledger_payroll.field_status')</th>
                                    <th>@lang('ledger_payroll.field_action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($rows as $row)
                                <tr>
                                    <td>{{ $row->bracket_order }}</td>
                                    <td>{{ $row->title }}@if($row->is_dependent)<br><small class="text-info">@lang('ledger_payroll.dependent'): @lang('ledger_payroll.calculated_from') {{ $row->dependency_label }}</small>@endif</td>
                                    <td>@if($row->taxGroup){{ $row->taxGroup->title }} <span class="badge bg-info text-dark">{{ $row->taxGroup->is_progressive ? __('ledger_payroll.progressive') : __('ledger_payroll.flat') }}</span>@else<span class="badge bg-secondary">@lang('ledger_payroll.standalone')</span>@endif</td>
                                    <td>{{ $row->tax_type == 1 ? __('ledger_payroll.tax_type_percentage') : __('ledger_payroll.tax_type_fixed') }}</td>
                                    <td class="ap-num">{{ $money($row->min_amount) }}</td>
                                    <td class="ap-num">{{ $money($row->max_amount) }}</td>
                                    <td class="text-nowrap">
                                        @if(in_array($row->paid_by, ['employee', 'both']))@lang('ledger_payroll.employee'): {{ $row->tax_type == 1 ? $pct($row->percentange).'%' : $money($row->fixed_amount) }}@endif
                                        @if(in_array($row->paid_by, ['employer', 'both']))<br>@lang('ledger_payroll.employer'): {{ $row->tax_type == 1 ? $pct($row->employer_percentage).'%' : $money($row->employer_fixed_amount) }}@endif
                                        @if($row->paid_by === 'both')<br><span class="badge bg-warning text-dark">@lang('ledger_payroll.shared')</span>@endif
                                        @if($row->max_no_taxable_amount > 0)<br><small class="text-muted">@lang('ledger_payroll.above_threshold', ['amount' => $money($row->max_no_taxable_amount)])</small>@endif
                                    </td>
                                    <td>{{ $row->liabilityAccount->account_code ?? '' }}</td>
                                    <td><small>{{ $row->effective_from?->format('d/m/Y') ?? '...' }} - {{ $row->effective_to?->format('d/m/Y') ?? '...' }}</small></td>
                                    <td>@if($row->status)<span class="badge bg-success">@lang('ledger_payroll.status_active')</span>@else<span class="badge bg-danger">@lang('ledger_payroll.status_inactive')</span>@endif</td>
                                    <td class="text-nowrap">
                                        @can('tax-setting-edit')
                                        <button type="button" class="btn btn-icon btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal-{{ $row->id }}"><i class="far fa-edit"></i></button>
                                        <button type="button" class="btn btn-icon btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#exemptModal-{{ $row->id }}" title="@lang('ledger_payroll.exemptions')"><i class="fas fa-user-shield"></i>@if($row->exemptions->count()) {{ $row->exemptions->count() }}@endif</button>
                                        @endcan
                                        @can('tax-setting-delete')
                                        <form action="{{ route($route.'.destroy', $row->id) }}" method="post" class="d-inline" onsubmit="return confirm(@js(__('ledger_payroll.modal_are_you_sure')))">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-icon btn-danger btn-sm"><i class="fas fa-trash-alt"></i></button>
                                        </form>
                                        @endcan
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="11" class="text-center text-muted">@lang('ledger_payroll.no_data')</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @can('tax-setting-edit')
    @foreach($rows as $row)
    <div id="editModal-{{ $row->id }}" class="modal fade" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg"><div class="modal-content">
            <form action="{{ route($route.'.update', $row->id) }}" method="post">
                @csrf @method('PUT')
                <div class="modal-header"><h5 class="modal-title">@lang('ledger_payroll.modal_edit') {{ $title }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">@include('ledger.payroll.tax-setting.fields', ['t' => $row])</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> @lang('ledger_payroll.btn_close')</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> @lang('ledger_payroll.btn_update')</button>
                </div>
            </form>
        </div></div>
    </div>
    <div id="exemptModal-{{ $row->id }}" class="modal fade" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">@lang('ledger_payroll.exemptions'): {{ $row->title }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p class="small text-muted">@lang('ledger_payroll.exemption_rule')</p>
                <table class="table table-sm ap-table">
                    <thead><tr><th>@lang('ledger_payroll.staff')</th><th>@lang('ledger_payroll.reason')</th><th>@lang('ledger_payroll.custom_rate')</th><th>@lang('ledger_payroll.expires_at')</th><th></th></tr></thead>
                    <tbody>
                        @forelse($row->exemptions as $e)
                        <tr>
                            <td>{{ $e->staff->name ?? '' }}</td>
                            <td>{{ $e->reason }}</td>
                            <td>{{ $e->custom_percentage !== null ? $pct($e->custom_percentage).'%' : ($e->custom_fixed_amount !== null ? $money($e->custom_fixed_amount) : __('ledger_payroll.fully_exempt')) }}</td>
                            <td>{{ $e->expires_at?->format('d/m/Y') ?? '-' }}@if($e->isExpired()) <span class="badge bg-secondary">@lang('ledger_payroll.expired')</span>@endif</td>
                            <td>
                                <form action="{{ route('ledger.tax-exemptions.destroy', [$row->id, $e->staff_id]) }}" method="post" onsubmit="return confirm(@js(__('ledger_payroll.modal_are_you_sure')))">
                                    @csrf @method('DELETE')<button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-times"></i></button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-muted text-center">@lang('ledger_payroll.no_exemptions')</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <form action="{{ route('ledger.tax-exemptions.store') }}" method="post" class="row g-2 align-items-end">
                    @csrf
                    <input type="hidden" name="tax_setting_id" value="{{ $row->id }}">
                    <div class="col-md-4"><label class="form-label">@lang('ledger_payroll.staff') *</label>
                        <select class="form-select" name="staff_id" required><option value="">@lang('ledger_payroll.select')</option>@foreach($staff as $s)<option value="{{ $s->id }}">{{ $s->name }} ({{ $s->staff_no }})</option>@endforeach</select></div>
                    <div class="col-md-8"><label class="form-label">@lang('ledger_payroll.reason')</label><input type="text" class="form-control" name="reason" maxlength="500"></div>
                    <div class="col-md-4"><label class="form-label">@lang('ledger_payroll.custom_percentage')</label><input type="number" step="any" class="form-control" name="custom_percentage" min="0" max="100"></div>
                    <div class="col-md-4"><label class="form-label">@lang('ledger_payroll.custom_fixed_amount')</label><input type="number" step="any" class="form-control" name="custom_fixed_amount" min="0"></div>
                    <div class="col-md-4"><label class="form-label">@lang('ledger_payroll.expires_at')</label><input type="date" class="form-control" name="expires_at" min="{{ date('Y-m-d') }}"></div>
                    <div class="col-12"><button type="submit" class="btn btn-success btn-sm"><i class="fas fa-plus"></i> @lang('ledger_payroll.add_exemption')</button></div>
                </form>
            </div>
        </div></div>
    </div>
    @endforeach
    @endcan
</section>
@endsection
