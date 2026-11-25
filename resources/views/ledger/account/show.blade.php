@extends('layouts.app')
@section('title', $title)
@section('css')
@include('ledger.partials.style')
@include('ledger.account.theme', ['kind' => $kind])
@endsection

{{-- One income or expense on its own page (paxhitestbed shows it in the list's modal), with the ledger entry it posted. --}}
@section('content')
@php $fileUrl = \App\Http\Controllers\Ledger\IncomeExpenseController::fileUrl($row->attach); @endphp
<div class="main-body acct">
    <div class="page-wrapper">
        <div class="acct-bar">
            <a href="{{ route($route.'.index') }}" class="acct-btn"><i class="fas fa-arrow-left"></i> @lang('ledger_accounts.btn_back')</a>
            <div class="acct-bar-end">
                @can($access.'-edit')
                <a href="{{ route($route.'.edit', $row->id) }}" class="acct-btn acct-btn-main"><i class="far fa-edit"></i> @lang('ledger_accounts.btn_edit')</a>
                @endcan
            </div>
        </div>
        <div class="row">
            <div class="col-lg-6">
                <div class="acct-panel acct-record">
                    <div class="acct-record-head">
                        <div class="acct-label">{{ $title }} · {{ $row->date->format('d/m/Y') }}</div>
                        <div class="v acct-num mt-1">{{ \App\Ledger\Ledger::money($row->amount) }} <span class="acct-cur" style="font-size:.9rem;">FCFA</span></div>
                        <div class="t">{{ $row->title }}</div>
                    </div>
                    <dl class="acct-rows mb-0 pt-2 pb-2">
                        <div class="acct-row"><dt>@lang('ledger_accounts.field_category')</dt><dd>{{ $row->category->name ?? '-' }}</dd></div>
                        <div class="acct-row"><dt>@lang('ledger_accounts.field_payment_method')</dt><dd>{{ \App\Ledger\Services\PaymentBook::methodLabel($row->payment_method) }}</dd></div>
                        <div class="acct-row"><dt>@lang('ledger_accounts.field_payment_account')</dt><dd>{{ $row->paymentAccount->title ?? __('ledger_accounts.not_recorded') }}</dd></div>
                        <div class="acct-row"><dt>@lang('ledger_accounts.field_invoice_id')</dt><dd>{{ $row->invoice_id ?: '-' }}</dd></div>
                        <div class="acct-row"><dt>@lang('ledger_accounts.field_reference')</dt><dd>{{ $row->reference ?: '-' }}</dd></div>
                        <div class="acct-row"><dt>@lang('ledger_accounts.field_recorded_by')</dt><dd>{{ \App\Ledger\Services\PaymentBook::userName($row->recordedBy) }}</dd></div>
                        <div class="acct-row"><dt>@lang('ledger_accounts.field_attach')</dt><dd>@if($fileUrl)<a href="{{ $fileUrl }}" target="_blank">{{ basename($row->attach) }}</a>@else - @endif</dd></div>
                    </dl>
                    @if($row->note)<div class="acct-note">{{ $row->note }}</div>@endif
                </div>
            </div>
            <div class="col-lg-6">
                <div class="acct-panel"><div class="acct-sec">
                    <div class="acct-sec-head">@lang('ledger_accounts.ledger')</div>
                    @if($state && $state->status === 'posted' && $state->journalEntry)
                        <p><span class="ap-chip posted">@lang('ledger_accounts.posted')</span>
                            <a href="{{ route('ledger.journal-entries.show', $state->journal_entry_id) }}">{{ $state->journalEntry->entry_number }}</a></p>
                        <table class="table ap-table">
                            <thead><tr><th>@lang('ledger_accounts.account')</th><th class="ap-num">@lang('ledger_accounts.debit')</th><th class="ap-num">@lang('ledger_accounts.credit')</th></tr></thead>
                            <tbody>
                            @foreach($state->journalEntry->lines()->with('account')->get() as $l)
                                <tr><td>{{ \App\Ledger\Services\PaymentBook::accountLabel($l->account) }}</td>
                                    <td class="ap-num">{{ $l->debit > 0 ? \App\Ledger\Ledger::money($l->debit) : '' }}</td>
                                    <td class="ap-num">{{ $l->credit > 0 ? \App\Ledger\Ledger::money($l->credit) : '' }}</td></tr>
                            @endforeach
                            </tbody>
                        </table>
                    @elseif($state && $state->status === 'failed')
                        <div class="ap-note is-bad">@lang('ledger_accounts.not_posted'): {{ $state->error }}</div>
                    @else
                        <div class="ap-note is-warn">@lang('ledger_accounts.not_posted')</div>
                    @endif
                </div></div>
            </div>
        </div>
    </div>
</div>
@endsection
