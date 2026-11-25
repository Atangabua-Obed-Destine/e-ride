@extends('layouts.app')
@section('title', $title)
@section('css')
@include('ledger.partials.style')
@endsection

{{-- One supplier bill (paxhitestbed admin/supplier-bill/show): its state, lines, payments, and the reviewed approve / void. --}}
@section('content')
@php
    $money = fn ($v) => \App\Ledger\Ledger::money($v);
    $overdue = $row->isApproved() && $owed > 0.005 && $row->due_date->toDateString() < now()->toDateString();
    $user = fn ($u) => \App\Ledger\Services\PaymentBook::userName($u);
@endphp
@include('ledger.account.errors')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap" style="gap:10px">
        <h5 class="mb-0">{{ $row->isOpeningBalance() ? __('ledger_accounts.opening_balance') : __('ledger_accounts.supplier_bill') }} {{ $row->number }}
            @include('ledger.supplier-bill.chip', ['state' => $state, 'overdue' => $overdue])</h5>
        <div class="ap-toolbar">
            <a href="{{ route($route.'.index') }}" class="btn btn-light btn-sm"><i class="fas fa-arrow-left"></i> @lang('ledger_accounts.btn_back')</a>
            @if($row->isDraft())
                @can('supplier-bill-create')
                <a href="{{ route($route.'.edit', $row->id) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-edit"></i> @lang('ledger_accounts.btn_edit')</a>
                <form method="post" action="{{ route($route.'.destroy', $row->id) }}" class="d-inline" onsubmit="return confirm(@js(__('ledger_accounts.delete_this_draft')))">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash"></i> @lang('ledger_accounts.delete_draft')</button>
                </form>
                @endcan
                @can('supplier-bill-approve')
                <form method="post" action="{{ route($route.'.approve', $row->id) }}" id="approve-form" class="d-none">@csrf</form>
                <button type="button" class="btn btn-success btn-sm" id="approve-trigger"
                        data-review-url="{{ route($route.'.preview-approve', $row->id) }}"
                        data-confirm-form="approve-form" data-confirm-label="@lang('ledger_accounts.approve_and_post')"
                        data-confirm-check="@lang('ledger_accounts.check_approve')">
                    <i class="fas fa-check"></i> @lang('ledger_accounts.review_and_approve')</button>
                @endcan
            @endif
            @if($row->isApproved() && $owed > 0.005)
                @can('supplier-payment-create')
                <a href="{{ route('ledger.supplier-payment.create', ['supplier_id' => $row->supplier_id, 'bill_id' => $row->id]) }}" class="btn btn-primary btn-sm"><i class="fas fa-money-bill-wave"></i> @lang('ledger_accounts.pay_this_bill')</a>
                @endcan
            @endif
            @if($row->isApproved())
                @can('supplier-bill-void')
                <form method="post" action="{{ route($route.'.void', $row->id) }}" id="void-form" class="d-none">@csrf<input type="hidden" name="void_reason"></form>
                <button type="button" class="btn btn-outline-danger btn-sm"
                        data-review-url="{{ route($route.'.preview-void', $row->id) }}"
                        data-confirm-form="void-form" data-confirm-label="@lang('ledger_accounts.void_and_reverse')"
                        data-confirm-class="btn-danger" data-needs-reason="1"
                        data-confirm-check="@lang('ledger_accounts.check_void_bill')">
                    <i class="fas fa-ban"></i> @lang('ledger_accounts.void')</button>
                @endcan
            @endif
        </div>
    </div>
    <div class="card-body">
        @if($row->isDraft())
        <div class="ap-note is-warn mb-3">@lang('ledger_accounts.draft_note')</div>
        @endif
        @if($duplicate)
        <div class="ap-note is-bad mb-3">{{ __('ledger_accounts.duplicate_note', ['ref' => $row->supplier_reference, 'number' => $duplicate->number]) }}
            <a href="{{ route($route.'.show', $duplicate->id) }}">@lang('ledger_accounts.open_it')</a></div>
        @endif
        @if($row->isVoid())
        <div class="ap-note is-bad mb-3">@lang('ledger_accounts.voided') {{ $row->voided_at?->format('d/m/Y H:i') }} @lang('ledger_accounts.by') {{ $user($row->voider) }}.
            @lang('ledger_accounts.reason'): {{ $row->void_reason ?: '-' }}</div>
        @endif
        <div class="row">
            <div class="col-md-6">
                <dl class="ap-dl">
                    <dt>@lang('ledger_accounts.supplier')</dt><dd>{{ $row->supplier->title ?? '-' }}
                        @if($row->supplier && $row->supplier->tax_number)<div class="ap-muted">@lang('ledger_accounts.niu') {{ $row->supplier->tax_number }}</div>@endif</dd>
                    <dt>@lang('ledger_accounts.their_invoice')</dt><dd>{{ $row->supplier_reference ?: '-' }}</dd>
                    <dt>@lang('ledger_accounts.bill_date')</dt><dd>{{ $row->bill_date->format('d/m/Y') }}</dd>
                    <dt>@lang('ledger_accounts.due_date')</dt><dd>{{ $row->due_date->format('d/m/Y') }}</dd>
                    <dt>@lang('ledger_accounts.description')</dt><dd>{{ $row->description ?: '-' }}</dd>
                    @if($row->attach)
                    <dt>@lang('ledger_accounts.scanned_invoice')</dt><dd><a href="{{ \App\Http\Controllers\Ledger\IncomeExpenseController::fileUrl($row->attach) }}" target="_blank"><i class="fas fa-paperclip"></i> @lang('ledger_accounts.open')</a></dd>
                    @endif
                </dl>
            </div>
            <div class="col-md-6">
                <dl class="ap-dl">
                    <dt>@lang('ledger_accounts.total')</dt><dd>{{ $money($row->total) }}@if($row->vat_amount > 0) <span class="ap-muted">(@lang('ledger_accounts.vat_recoverable') {{ $money($row->vat_amount) }})</span>@endif</dd>
                    <dt>@lang('ledger_accounts.paid')</dt><dd>{{ $row->isApproved() ? $money((float) $row->total - $owed) : '-' }}</dd>
                    <dt>@lang('ledger_accounts.still_owed')</dt><dd><strong>{{ $row->isApproved() ? $money($owed) : '-' }}</strong></dd>
                    <dt>@lang('ledger_accounts.entered')</dt><dd>{{ $user($row->creator) }}, {{ $row->created_at?->format('d/m/Y H:i') }}</dd>
                    @if($row->approved_at)
                    <dt>@lang('ledger_accounts.approved')</dt><dd>{{ $user($row->approver) }}, {{ $row->approved_at->format('d/m/Y H:i') }}</dd>
                    @endif
                    @if($row->journalEntry)
                    <dt>@lang('ledger_accounts.ledger')</dt><dd>
                        <a href="{{ route('ledger.journal-entries.show', $row->journalEntry->id) }}">{{ $row->journalEntry->entry_number }}</a>
                        @if($row->voidJournalEntry) · @lang('ledger_accounts.reversed_by') <a href="{{ route('ledger.journal-entries.show', $row->voidJournalEntry->id) }}">{{ $row->voidJournalEntry->entry_number }}</a>@endif
                        @if($row->payableAccount)<div class="ap-muted">@lang('ledger_accounts.owed_on') {{ \App\Ledger\Services\PaymentBook::accountLabel($row->payableAccount) }}</div>@endif
                    </dd>
                    @endif
                </dl>
            </div>
        </div>
        <h6 class="mt-4">@lang('ledger_accounts.lines')</h6>
        <div class="table-responsive">
            <table class="table ap-table">
                <thead><tr><th>#</th><th>{{ $row->isOpeningBalance() ? __('ledger_accounts.set_against') : __('ledger_accounts.expense_category_one') }}</th>
                    <th>@lang('ledger_accounts.description')</th><th>@lang('ledger_accounts.ledger_account')</th><th class="ap-num">@lang('ledger_accounts.amount')</th></tr></thead>
                <tbody>
                    @foreach($row->lines as $line)
                    <tr>
                        <td>{{ $line->line_number }}</td>
                        <td>{{ $line->category->name ?? ($row->isOpeningBalance() ? __('ledger_accounts.opening_balance') : '-') }}</td>
                        <td>{{ $line->description ?: '-' }}</td>
                        <td>{{ $line->account ? \App\Ledger\Services\PaymentBook::accountLabel($line->account) : __('ledger_accounts.decided_on_approval') }}</td>
                        <td class="ap-num">{{ $money($line->amount) }}</td>
                    </tr>
                    @endforeach
                    @if($row->vat_amount > 0)
                    <tr><td></td><td colspan="3">@lang('ledger_accounts.vat_recoverable')</td><td class="ap-num">{{ $money($row->vat_amount) }}</td></tr>
                    @endif
                    <tr class="ap-total-row"><td colspan="4" class="text-end">@lang('ledger_accounts.total')</td><td class="ap-num">{{ $money($row->total) }}</td></tr>
                </tbody>
            </table>
        </div>
        @if($row->allocations->isNotEmpty())
        <h6 class="mt-4">@lang('ledger_accounts.payments')</h6>
        <div class="table-responsive">
            <table class="table ap-table">
                <thead><tr><th>@lang('ledger_accounts.payment')</th><th>@lang('ledger_accounts.date')</th><th>@lang('ledger_accounts.field_reference')</th>
                    <th class="ap-num">@lang('ledger_accounts.applied_to_bill')</th><th>@lang('ledger_accounts.status')</th></tr></thead>
                <tbody>
                    @foreach($row->allocations as $a)
                    <tr>
                        <td><a href="{{ route('ledger.supplier-payment.show', $a->supplier_payment_id) }}">{{ $a->payment->number ?? '#' }}</a></td>
                        <td>{{ $a->payment ? $a->payment->payment_date->format('d/m/Y') : '-' }}</td>
                        <td>{{ $a->payment->reference ?? '-' }}</td>
                        <td class="ap-num">{{ $money($a->amount) }}</td>
                        <td>@if($a->payment && $a->payment->isVoid())<span class="ap-chip void">@lang('ledger_accounts.state_void')</span>@else<span class="ap-chip posted">@lang('ledger_accounts.posted')</span>@endif</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>
@include('ledger.partials.review_modal')
@endsection

@section('javascript')
@include('ledger.partials.review_script')
@if(request('review') === 'approve' && $row->isDraft())
@can('supplier-bill-approve')
<script>$(function () { $('#approve-trigger').trigger('click'); });</script>
@endcan
@endif
@endsection
