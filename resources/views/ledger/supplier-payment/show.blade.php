@extends('layouts.app')
@section('title', $title)
@section('css')
@include('ledger.partials.style')
@if($print)<style>.ledger-nav, .ledger-top, .no-print { display: none !important; }</style>@endif
@endsection

{{-- One supplier payment as a payment voucher (paxhitestbed admin/supplier-payment/show), with the reviewed void. --}}
@section('content')
@php
    $money = fn ($v) => \App\Ledger\Ledger::money($v);
    $user = fn ($u) => \App\Ledger\Services\PaymentBook::userName($u);
@endphp
@include('ledger.account.errors')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap no-print" style="gap:10px">
        <h5 class="mb-0">@lang('ledger_accounts.supplier_payment') {{ $row->number }}
            @if($row->isVoid())<span class="ap-chip void">@lang('ledger_accounts.state_void')</span>@else<span class="ap-chip posted">@lang('ledger_accounts.posted')</span>@endif</h5>
        <div class="ap-toolbar">
            <a href="{{ route($route.'.index') }}" class="btn btn-light btn-sm"><i class="fas fa-arrow-left"></i> @lang('ledger_accounts.btn_back')</a>
            <a href="{{ route($route.'.show', ['id' => $row->id, 'print' => 1]) }}" target="_blank" class="btn btn-outline-primary btn-sm"><i class="fas fa-print"></i> @lang('ledger_accounts.print_voucher')</a>
            @if(! $row->isVoid())
            @can('supplier-payment-void')
            <form method="post" action="{{ route($route.'.void', $row->id) }}" id="void-form" class="d-none">@csrf<input type="hidden" name="void_reason"></form>
            <button type="button" class="btn btn-outline-danger btn-sm" data-review-url="{{ route($route.'.preview-void', $row->id) }}"
                    data-confirm-form="void-form" data-confirm-label="@lang('ledger_accounts.void_and_reverse')" data-confirm-class="btn-danger" data-needs-reason="1"
                    data-confirm-check="@lang('ledger_accounts.check_void_payment')"><i class="fas fa-ban"></i> @lang('ledger_accounts.void')</button>
            @endcan
            @endif
        </div>
    </div>
    <div class="card-body">
        @if($row->isVoid())
        <div class="ap-note is-bad mb-3">@lang('ledger_accounts.voided') {{ $row->voided_at->format('d/m/Y H:i') }} @lang('ledger_accounts.by') {{ $user($row->voider) }}.
            @lang('ledger_accounts.reason'): {{ $row->void_reason ?: '-' }}. @lang('ledger_accounts.bills_owed_again')</div>
        @endif
        <h4 class="text-center mb-3">@lang('ledger_accounts.payment_voucher') @if($row->isVoid())<span class="text-danger">(@lang('ledger_accounts.void_caps'))</span>@endif</h4>
        <div class="row">
            <div class="col-md-6">
                <dl class="ap-dl">
                    <dt>@lang('ledger_accounts.paid_to')</dt><dd>{{ $row->supplier->title ?? '-' }}
                        @if($row->supplier && $row->supplier->tax_number)<div class="ap-muted">@lang('ledger_accounts.niu') {{ $row->supplier->tax_number }}</div>@endif</dd>
                    <dt>@lang('ledger_accounts.date')</dt><dd>{{ $row->payment_date->format('d/m/Y') }}</dd>
                    <dt>@lang('ledger_accounts.amount')</dt><dd><strong>{{ $money($row->amount) }} FCFA</strong></dd>
                    <dt>@lang('ledger_accounts.method')</dt><dd>{{ \App\Ledger\Services\PaymentBook::methodLabel($row->payment_method) }}</dd>
                </dl>
            </div>
            <div class="col-md-6">
                <dl class="ap-dl">
                    <dt>@lang('ledger_accounts.paid_from')</dt><dd>{{ \App\Ledger\Services\PaymentBook::accountLabel($row->cashAccount) }}@if($row->paymentAccount)<div class="ap-muted">{{ $row->paymentAccount->title }}</div>@endif</dd>
                    <dt>@lang('ledger_accounts.cheque_or_transfer')</dt><dd>{{ $row->reference ?: '-' }}</dd>
                    <dt>@lang('ledger_accounts.field_note')</dt><dd>{{ $row->note ?: '-' }}</dd>
                    <dt>@lang('ledger_accounts.recorded')</dt><dd>{{ $user($row->creator) }}, {{ $row->created_at?->format('d/m/Y H:i') }}</dd>
                    @if($row->journalEntry)
                    <dt>@lang('ledger_accounts.ledger')</dt><dd><a href="{{ route('ledger.journal-entries.show', $row->journalEntry->id) }}">{{ $row->journalEntry->entry_number }}</a>
                        @if($row->voidJournalEntry) · @lang('ledger_accounts.reversed_by') <a href="{{ route('ledger.journal-entries.show', $row->voidJournalEntry->id) }}">{{ $row->voidJournalEntry->entry_number }}</a>@endif</dd>
                    @endif
                </dl>
            </div>
        </div>
        <h6 class="mt-4">@lang('ledger_accounts.bills_paid')</h6>
        <div class="table-responsive">
            <table class="table ap-table">
                <thead><tr><th>@lang('ledger_accounts.bill')</th><th>@lang('ledger_accounts.their_invoice')</th><th>@lang('ledger_accounts.bill_date')</th>
                    <th class="ap-num">@lang('ledger_accounts.bill_total')</th><th class="ap-num">@lang('ledger_accounts.paid_by_this_payment')</th></tr></thead>
                <tbody>
                    @foreach($row->allocations as $a)
                    <tr>
                        <td>@if($a->bill)<a href="{{ route('ledger.supplier-bill.show', $a->bill->id) }}">{{ $a->bill->number }}</a>@endif</td>
                        <td>{{ $a->bill->supplier_reference ?? '-' }}</td>
                        <td>{{ $a->bill ? $a->bill->bill_date->format('d/m/Y') : '-' }}</td>
                        <td class="ap-num">{{ $a->bill ? $money($a->bill->total) : '-' }}</td>
                        <td class="ap-num">{{ $money($a->amount) }}</td>
                    </tr>
                    @endforeach
                    <tr class="ap-total-row"><td colspan="4" class="text-end">@lang('ledger_accounts.total')</td><td class="ap-num">{{ $money($row->amount) }}</td></tr>
                </tbody>
            </table>
        </div>
        <div class="row mt-5 text-center">
            <div class="col-4"><div style="border-top:1px solid #94a3b8;padding-top:4px">@lang('ledger_accounts.prepared_by')</div></div>
            <div class="col-4"><div style="border-top:1px solid #94a3b8;padding-top:4px">@lang('ledger_accounts.approved_by')</div></div>
            <div class="col-4"><div style="border-top:1px solid #94a3b8;padding-top:4px">@lang('ledger_accounts.received_by')</div></div>
        </div>
    </div>
</div>
@include('ledger.partials.review_modal')
@endsection

@section('javascript')
@include('ledger.partials.review_script')
@if($print)<script>window.addEventListener('load', function () { window.print(); });</script>@endif
@endsection
