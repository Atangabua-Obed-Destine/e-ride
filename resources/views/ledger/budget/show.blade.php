@extends('layouts.app')
@section('title', __('ledger_budget.text_budget_details'))

@section('content')
@include('ledger.partials.style')
@php $M = fn ($v) => \App\Ledger\Ledger::money($v); @endphp
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ $row->budget_code }} — {{ $row->title }}</h1>
</section>
<section class="content">
    @include('ledger.budget.partials.messages')

    {{-- What this status means, and what comes next. --}}
    @php $tone = ['draft' => 'info', 'pending_approval' => 'warning', 'approved' => 'success', 'active' => 'info', 'closed' => 'default', 'cancelled' => 'danger'][$row->status] ?? 'info'; @endphp
    <div class="ap-note {{ in_array($row->status, ['pending_approval']) ? 'is-warn' : ($row->status === 'cancelled' ? 'is-bad' : '') }}" style="margin-bottom: 16px;">
        <strong>{{ __('ledger_budget.state_'.$row->status.'_title') }}</strong><br>{{ __('ledger_budget.state_'.$row->status.'_text') }}
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="box box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title">@lang('ledger_budget.text_budget_details')</h3>
                    <div class="box-tools">
                        @can('budget-edit')
                            @if($row->is_institutional)
                                <a href="{{ route('ledger.budget-sheet.show', $row->id) }}" class="btn btn-primary btn-sm"><i class="fa fa-file-text-o"></i> @lang('ledger_budget.open_the_sheet')</a>
                            @elseif(in_array($row->status, ['draft', 'pending_approval']))
                                <a href="{{ route('ledger.budget.edit', $row->id) }}" class="btn btn-primary btn-sm"><i class="fa fa-pencil"></i> @lang('ledger_budget.btn_edit')</a>
                            @elseif(in_array($row->status, ['approved', 'active']))
                                <button class="btn btn-default btn-sm" disabled title="{{ __('ledger_budget.msg_cannot_edit_active_budget') }}"><i class="fa fa-lock"></i> @lang('ledger_budget.edit_locked')</button>
                            @endif
                        @endcan
                        <a href="{{ route('ledger.budget.index') }}" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> @lang('ledger_budget.btn_back')</a>
                    </div>
                </div>
                <div class="box-body">
                    <dl class="ap-dl">
                        <dt>@lang('ledger_budget.belongs_to')</dt>
                        <dd>@if($row->parent)<a href="{{ route('ledger.budget-sheet.show', $row->parent->id) }}">{{ $row->parent->title }}</a>@else<span class="ap-muted">@lang('ledger_budget.not_part_of_annual')</span>@endif</dd>
                        <dt>@lang('ledger_budget.field_budget_code')</dt><dd><strong>{{ $row->budget_code }}</strong></dd>
                        <dt>@lang('ledger_budget.field_title')</dt><dd>{{ $row->title }}</dd>
                        <dt>@lang('ledger_budget.field_type')</dt><dd><span class="ap-chip posted">{{ __('ledger_budget.type_'.$row->type) }}</span></dd>
                        <dt>@lang('ledger_budget.field_fiscal_year')</dt><dd>{{ $row->fiscal_year }}</dd>
                        <dt>@lang('ledger_budget.field_department')</dt><dd>{{ optional($row->location)->name ?? '-' }}</dd>
                        <dt>@lang('ledger_budget.field_period')</dt><dd>{{ $row->start_date->format('d/m/Y') }} - {{ $row->end_date->format('d/m/Y') }}</dd>
                        <dt>@lang('ledger_budget.field_status')</dt><dd>@include('ledger.budget.partials.status', ['status' => $row->status])
                            @if($row->approvedBy)<span class="ap-muted">@lang('ledger_budget.approved_by_on', ['name' => $row->approvedBy->user_full_name ?? $row->approvedBy->username, 'date' => optional($row->approved_at)->format('d/m/Y')])</span>@endif</dd>
                        @if($row->description)<dt>@lang('ledger_budget.field_description')</dt><dd>{{ $row->description }}</dd>@endif
                        @if($row->note)<dt>@lang('ledger_budget.field_note')</dt><dd>{{ $row->note }}</dd>@endif
                    </dl>
                </div>
            </div>

            @if($row->children->count())
            <div class="box box-solid">
                <div class="box-header with-border"><h3 class="box-title">@lang('ledger_budget.sub_budgets')</h3></div>
                <div class="box-body table-responsive">
                    <table class="table table-striped ap-table">
                        <thead><tr>
                            <th>@lang('ledger_budget.field_title')</th><th>@lang('ledger_budget.field_department')</th>
                            <th class="ap-num">@lang('ledger_budget.field_total_amount')</th><th class="ap-num">@lang('ledger_budget.spent')</th>
                            <th>@lang('ledger_budget.field_status')</th><th></th>
                        </tr></thead>
                        <tbody>
                            @foreach($row->children as $child)
                                <tr>
                                    <td>{{ $child->title }}</td>
                                    <td>{{ optional($child->location)->name ?? '—' }}</td>
                                    <td class="ap-num">{{ $M($child->total_amount) }}</td>
                                    <td class="ap-num">{{ $M($child->spent_amount) }}</td>
                                    <td>@include('ledger.budget.partials.status', ['status' => $child->status])</td>
                                    <td class="text-right"><a href="{{ route('ledger.budget.show', $child->id) }}" class="btn btn-xs btn-default">@lang('ledger_budget.open')</a></td>
                                </tr>
                            @endforeach
                            <tr class="ap-total-row">
                                <td colspan="2">@lang('ledger_budget.total_delegated')</td>
                                <td class="ap-num">{{ $M($row->children->sum('total_amount')) }}</td>
                                <td class="ap-num">{{ $M($row->children->sum('spent_amount')) }}</td>
                                <td colspan="2"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            <div class="box box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title">@lang('ledger_budget.text_budget_allocations')</h3>
                    <div class="box-tools">
                        @can('budget-edit')
                            @if($row->is_institutional)
                                <a href="{{ route('ledger.budget-sheet.show', $row->id) }}" class="btn btn-primary btn-sm"><i class="fa fa-pencil"></i> @lang('ledger_budget.enter_figures_on_sheet')</a>
                            @elseif(in_array($row->status, ['draft', 'pending_approval', 'approved']))
                                <a href="{{ route('ledger.budget.allocations', $row->id) }}" class="btn btn-primary btn-sm"><i class="fa fa-plus"></i> @lang('ledger_budget.text_manage_allocations')</a>
                            @endif
                        @endcan
                    </div>
                </div>
                <div class="box-body table-responsive">
                    <table class="table table-striped table-hover ap-table">
                        <thead><tr>
                            <th>@lang('ledger_budget.field_category')</th><th>@lang('ledger_budget.field_title')</th><th>@lang('ledger_budget.field_period')</th>
                            <th class="ap-num">@lang('ledger_budget.field_allocated')</th><th class="ap-num">@lang('ledger_budget.field_spent')</th>
                            <th class="ap-num">@lang('ledger_budget.field_remaining')</th><th style="min-width: 140px;">@lang('ledger_budget.field_utilization')</th>
                        </tr></thead>
                        <tbody>
                        @forelse($row->allocations as $allocation)
                            <tr>
                                <td>{{ $allocation->target_label }}
                                    @if($allocation->expenseCategory && $allocation->account)<br><small class="ap-muted">{{ $allocation->account->account_code }} {{ \App\Ledger\Services\Preview::accountName($allocation->account) }}</small>@endif</td>
                                <td>{{ $allocation->title }}</td>
                                <td><span class="ap-chip draft">{{ \App\Ledger\Models\BudgetAllocation::periodLabel($allocation->period) }}</span></td>
                                <td class="ap-num">{{ $M($allocation->allocated_amount) }}</td>
                                <td class="ap-num">{{ $M($allocation->spent_amount) }}</td>
                                <td class="ap-num">{{ $M($allocation->remaining_amount) }}</td>
                                <td>@include('ledger.budget.partials.progress', ['pct' => $allocation->utilization_percentage, 'color' => $allocation->status_color])</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center ap-muted">@lang('ledger_budget.msg_no_allocations')</td></tr>
                        @endforelse
                        </tbody>
                        @if($row->allocations->count() > 0)
                        <tfoot>
                            <tr class="ap-total-row">
                                <td colspan="3">@lang('ledger_budget.text_total')</td>
                                <td class="ap-num">{{ $M($row->allocated_amount) }}</td>
                                <td class="ap-num">{{ $M($row->spent_amount) }}</td>
                                <td class="ap-num">{{ $M((float) $row->allocated_amount - (float) $row->spent_amount) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                        @endif
                    </table>
                    <p class="ap-muted" style="margin: 6px 0 0;">@lang('ledger_budget.spent_from_ledger')</p>
                </div>
            </div>

            {{-- The revisions are the log of every change to a locked total: who, when, why, from and to. --}}
            @if($row->revisions->count())
            <div class="box box-solid">
                <div class="box-header with-border"><h3 class="box-title">@lang('ledger_budget.revisions')</h3></div>
                <div class="box-body table-responsive">
                    <table class="table table-condensed ap-table">
                        <thead><tr>
                            <th>#</th><th>@lang('ledger_budget.date')</th><th class="ap-num">@lang('ledger_budget.previous_amount')</th>
                            <th class="ap-num">@lang('ledger_budget.new_amount')</th><th class="ap-num">@lang('ledger_budget.change')</th>
                            <th>@lang('ledger_budget.reason')</th><th>@lang('ledger_budget.by')</th>
                        </tr></thead>
                        <tbody>
                            @foreach($row->revisions as $rev)
                                <tr>
                                    <td>{{ $rev->revision_number }}</td>
                                    <td>{{ optional($rev->approved_at ?? $rev->created_at)->format('d/m/Y') }}</td>
                                    <td class="ap-num">{{ $M($rev->previous_amount) }}</td>
                                    <td class="ap-num">{{ $M($rev->new_amount) }}</td>
                                    <td class="ap-num">{{ (float) $rev->change_amount >= 0 ? '+' : '' }}{{ $M($rev->change_amount) }}</td>
                                    <td>{{ $rev->reason }}</td>
                                    <td>{{ optional($rev->requestedBy)->user_full_name ?? optional($rev->requestedBy)->username }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        </div>

        <div class="col-md-4">
            <div class="box box-solid">
                <div class="box-header with-border"><h3 class="box-title">@lang('ledger_budget.text_budget_summary')</h3></div>
                <div class="box-body">
                    <h5 class="ap-muted">@lang('ledger_budget.field_total_amount')</h5><h3 class="text-primary" style="margin-top: 0;">{{ $M($row->total_amount) }}</h3>
                    <h5 class="ap-muted">@lang('ledger_budget.field_allocated_amount')</h5><h3 class="text-info" style="margin-top: 0;">{{ $M($row->allocated_amount) }}</h3>
                    <h5 class="ap-muted">@lang('ledger_budget.field_spent_amount')</h5><h3 class="text-warning" style="margin-top: 0;">{{ $M($row->spent_amount) }}</h3>
                    <h5 class="ap-muted">@lang('ledger_budget.field_remaining_amount')</h5><h3 class="text-success" style="margin-top: 0;">{{ $M($row->remaining_amount) }}</h3>
                    <h5 class="ap-muted">@lang('ledger_budget.field_utilization')</h5>
                    @include('ledger.budget.partials.progress', ['pct' => $row->utilization_percentage, 'color' => $row->utilization_color])
                </div>
            </div>

            @unless($row->is_institutional)
            <div class="box box-solid">
                <div class="box-header with-border"><h3 class="box-title">@lang('ledger_budget.text_actions')</h3></div>
                <div class="box-body">
                    @can('budget-edit')
                    @if($row->status == 'draft')
                        <form action="{{ route('ledger.budget.submit', $row->id) }}" method="POST" style="margin-bottom: 8px;">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-block"><i class="fa fa-send"></i> @lang('ledger_budget.btn_submit_for_approval')</button>
                        </form>
                    @endif
                    @endcan
                    @can('budget-approve')
                    @if($row->status == 'pending_approval')
                        <form action="{{ route('ledger.budget.approve', $row->id) }}" method="POST" style="margin-bottom: 8px;"
                              data-review="{{ __('ledger_budget.review_approve_budget') }}" data-review-label="{{ __('ledger_budget.btn_approve') }}" data-review-class="btn-success">
                            @csrf
                            <button type="submit" class="btn btn-success btn-block"><i class="fa fa-check-circle"></i> @lang('ledger_budget.btn_approve')</button>
                        </form>
                    @endif
                    @endcan
                    @can('budget-activate')
                    @if($row->status == 'approved')
                        <form action="{{ route('ledger.budget.activate', $row->id) }}" method="POST" style="margin-bottom: 8px;">
                            @csrf
                            <button type="submit" class="btn btn-info btn-block"><i class="fa fa-play"></i> @lang('ledger_budget.btn_activate')</button>
                        </form>
                    @endif
                    @endcan
                    @can('budget-close')
                    @if($row->status == 'active')
                        <form action="{{ route('ledger.budget.close', $row->id) }}" method="POST" style="margin-bottom: 8px;" onsubmit="return confirm(@json(__('ledger_budget.msg_confirm_close_budget')))">
                            @csrf
                            <button type="submit" class="btn btn-default btn-block"><i class="fa fa-lock"></i> @lang('ledger_budget.btn_close')</button>
                        </form>
                    @endif
                    @endcan
                    @can('budget-cancel')
                    @if(in_array($row->status, ['draft', 'pending_approval', 'approved']))
                        <form action="{{ route('ledger.budget.cancel', $row->id) }}" method="POST" onsubmit="return confirm(@json(__('ledger_budget.msg_confirm_cancel_budget')))">
                            @csrf
                            <button type="submit" class="btn btn-danger btn-block"><i class="fa fa-times-circle"></i> @lang('ledger_budget.btn_cancel_budget')</button>
                        </form>
                    @endif
                    @endcan
                    {{-- Revise the total of an otherwise locked (approved or active) budget; logs a revision. --}}
                    @can('budget-edit')
                    @if(in_array($row->status, ['approved', 'active']))
                        <hr>
                        <form action="{{ route('ledger.budget.revise', $row->id) }}" method="POST"
                              data-review="{{ __('ledger_budget.review_revise') }}" data-review-label="{{ __('ledger_budget.apply_revision') }}" data-review-class="btn-warning">
                            @csrf
                            <label for="new_amount">@lang('ledger_budget.revise_total')</label>
                            <input type="number" step="0.01" min="0" name="new_amount" id="new_amount" class="form-control input-sm" style="margin-bottom: 4px;" value="{{ old('new_amount', (float) $row->total_amount) }}" required>
                            <textarea name="reason" class="form-control input-sm" rows="2" style="margin-bottom: 4px;" placeholder="{{ __('ledger_budget.reason_for_revision') }}" maxlength="1000" required>{{ old('reason') }}</textarea>
                            <button type="submit" class="btn btn-warning btn-block btn-sm"><i class="fa fa-pencil-square-o"></i> @lang('ledger_budget.apply_revision')</button>
                        </form>
                    @endif
                    @endcan
                </div>
            </div>
            @endunless
        </div>
    </div>
</section>
@include('ledger.partials.review_modal')
@endsection

@section('javascript')
@include('ledger.partials.review_script')
@endsection
