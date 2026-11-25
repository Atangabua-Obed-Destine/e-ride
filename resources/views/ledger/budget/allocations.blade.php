@extends('layouts.app')
@section('title', __('ledger_budget.text_budget_allocations'))

@section('content')
@include('ledger.partials.style')
@php
    $M = fn ($v) => \App\Ledger\Ledger::money($v);
    $unallocated = (float) $budget->total_amount - (float) $budget->allocated_amount;
    $open = ! in_array($budget->status, ['active', 'closed', 'cancelled']);
@endphp
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_budget.text_budget_allocations') - {{ $budget->budget_code }}
        <small>{{ $budget->title }}</small></h1>
</section>
<section class="content">
    @include('ledger.budget.partials.messages')
    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">@lang('ledger_budget.text_budget_allocations')</h3>
            <div class="box-tools"><a href="{{ route('ledger.budget.show', $budget->id) }}" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> @lang('ledger_budget.btn_back')</a></div>
        </div>
        <div class="box-body">
            <div class="row">
                <div class="col-md-3"><div class="callout callout-info"><strong>@lang('ledger_budget.field_total_amount'):</strong><br>{{ $M($budget->total_amount) }}</div></div>
                <div class="col-md-3"><div class="callout callout-info"><strong>@lang('ledger_budget.field_allocated_amount'):</strong><br>{{ $M($budget->allocated_amount) }}</div></div>
                <div class="col-md-3"><div class="callout callout-warning"><strong>@lang('ledger_budget.field_spent_amount'):</strong><br>{{ $M($budget->spent_amount) }}</div></div>
                <div class="col-md-3"><div class="callout callout-success"><strong>@lang('ledger_budget.field_unallocated'):</strong><br>{{ $M($unallocated) }}</div></div>
            </div>

            @if($open)
            @can('budget-edit')
            <div class="ap-card">
                <h4 style="margin-top: 0;">@lang('ledger_budget.text_add_allocation')</h4>
                <form method="post" action="{{ route('ledger.budget.allocations.store', $budget->id) }}" id="allocationForm">
                    @csrf
                    @include('ledger.budget.partials.allocation_fields', ['a' => null])
                    <button type="submit" class="btn btn-primary"><i class="fa fa-plus"></i> @lang('ledger_budget.btn_add_allocation')</button>
                </form>
            </div>
            @endcan
            @endif

            <div class="table-responsive">
                <table class="table table-striped table-hover ap-table">
                    <thead><tr>
                        <th>@lang('ledger_budget.field_category')</th><th>@lang('ledger_budget.field_title')</th><th>@lang('ledger_budget.field_department')</th>
                        <th>@lang('ledger_budget.field_period')</th><th class="ap-num">@lang('ledger_budget.field_allocated')</th><th class="ap-num">@lang('ledger_budget.field_spent')</th>
                        <th class="ap-num">@lang('ledger_budget.field_remaining')</th><th style="min-width: 140px;">@lang('ledger_budget.field_utilization')</th><th>@lang('ledger_budget.field_action')</th>
                    </tr></thead>
                    <tbody>
                    @forelse($budget->allocations as $allocation)
                        <tr>
                            <td>{{ $allocation->target_label }}
                                @if($allocation->expenseCategory && $allocation->account)<br><small class="ap-muted">{{ $allocation->account->account_code }} {{ \App\Ledger\Services\Preview::accountName($allocation->account) }}</small>@endif</td>
                            <td>{{ $allocation->title }}</td>
                            <td>{{ optional($allocation->location)->name ?? (optional($budget->location)->name ?? '-') }}</td>
                            <td><span class="ap-chip draft">{{ \App\Ledger\Models\BudgetAllocation::periodLabel($allocation->period) }}</span></td>
                            <td class="ap-num">{{ $M($allocation->allocated_amount) }}</td>
                            <td class="ap-num">{{ $M($allocation->spent_amount) }}</td>
                            <td class="ap-num">{{ $M($allocation->remaining_amount) }}</td>
                            <td>@include('ledger.budget.partials.progress', ['pct' => $allocation->utilization_percentage, 'color' => $allocation->status_color])</td>
                            <td style="white-space: nowrap;">
                                @if($open)
                                @can('budget-edit')
                                    <button type="button" class="btn btn-primary btn-xs" data-toggle="modal" data-target="#editModal{{ $allocation->id }}" title="{{ __('ledger_budget.btn_edit') }}"><i class="fa fa-pencil"></i></button>
                                    <form action="{{ route('ledger.budget.allocations.delete', [$budget->id, $allocation->id]) }}" method="POST" style="display: inline;" onsubmit="return confirm(@json(__('ledger_budget.msg_confirm_delete')))">
                                        @csrf
                                        <button type="submit" class="btn btn-danger btn-xs" title="{{ __('ledger_budget.btn_delete') }}"><i class="fa fa-trash"></i></button>
                                    </form>
                                @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center ap-muted">@lang('ledger_budget.msg_no_allocations')</td></tr>
                    @endforelse
                    </tbody>
                    @if($budget->allocations->count() > 0)
                    <tfoot>
                        <tr class="ap-total-row">
                            <td colspan="4">@lang('ledger_budget.text_total')</td>
                            <td class="ap-num">{{ $M($budget->allocated_amount) }}</td>
                            <td class="ap-num">{{ $M($budget->spent_amount) }}</td>
                            <td class="ap-num">{{ $M((float) $budget->allocated_amount - (float) $budget->spent_amount) }}</td>
                            <td></td><td></td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
            <p class="ap-muted">@lang('ledger_budget.spent_from_ledger')</p>
        </div>
    </div>
</section>

{{-- The edit dialogs sit outside the table (paxhitestbed put them inside tbody, which is not valid HTML). --}}
@if($open)
@foreach($budget->allocations as $allocation)
<div class="modal fade" id="editModal{{ $allocation->id }}" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form method="post" action="{{ route('ledger.budget.allocations.update', [$budget->id, $allocation->id]) }}">
                @csrf
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('ledger_budget.btn_cancel') }}"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">@lang('ledger_budget.text_edit_allocation')</h4>
                </div>
                <div class="modal-body">
                    @include('ledger.budget.partials.allocation_fields', ['a' => $allocation])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">@lang('ledger_budget.btn_cancel')</button>
                    <button type="submit" class="btn btn-primary">@lang('ledger_budget.btn_update')</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach
@endif
@endsection

@section('javascript')
<script>
    $(function () {
        var max = {{ json_encode(round($unallocated, 2)) }};
        $('#allocationForm [name="allocated_amount"]').on('input', function () {
            var v = parseFloat(this.value) || 0;
            this.setCustomValidity(v > max ? {!! json_encode(__('ledger_budget.amount_exceeds_available', ['amount' => \App\Ledger\Ledger::money($unallocated)])) !!} : '');
            this.style.borderColor = v > max ? '#dc3545' : '';
        });
    });
</script>
@endsection
