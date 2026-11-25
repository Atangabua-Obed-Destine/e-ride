@extends('layouts.app')
@section('title', __('ledger_je.journal_entry_details'))

@section('content')
@include('ledger.partials.style')
@include('ledger.journal-entries.partials.style')
@php
    $JE = \App\Http\Controllers\Ledger\JournalEntryController::class;
    $cur = session('currency')['symbol'] ?? '';
    $money = fn ($n) => \App\Ledger\Ledger::money($n);
@endphp
<section class="content-header hidden-print">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ __('ledger_je.journal_entries') }}</h1>
</section>
<section class="content">
    <div class="box box-solid je-show">
        <div class="box-header with-border hidden-print">
            <h3 class="box-title">{{ __('ledger_je.journal_entry_details') }} - {{ $entry->entry_number }}</h3>
            <div class="box-tools">
                @can('journal-entry-edit')
                @if(!$entry->is_posted && !$lockReason)
                <a href="{{ route('ledger.journal-entries.edit', $entry->id) }}" class="btn btn-primary btn-sm"><i class="fa fa-edit"></i> {{ __('ledger_je.edit') }}</a>
                @endif
                @endcan
                <a href="{{ route('ledger.journal-entries.index') }}" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> {{ __('ledger_je.back') }}</a>
            </div>
        </div>
        <div class="box-body">
            {{-- printed heading --}}
            <div class="visible-print-block text-center" style="margin-bottom: 15px;">
                <div style="font-size: 20pt; font-weight: bold; letter-spacing: 3px;">{{ session('business.name') }}</div>
                <div style="display: inline-block; border: 2px solid #000; padding: 6px 12px; font-weight: bold; margin-top: 8px;">{{ __('ledger_je.print_title', ['number' => $entry->entry_number]) }}</div>
            </div>

            <div class="row">
                <div class="col-md-6 col-xs-6">
                    <table class="table table-bordered">
                        <tr><th width="40%">{{ __('ledger_je.entry_number') }}</th><td><strong>{{ $entry->entry_number }}</strong></td></tr>
                        <tr><th>{{ __('ledger_je.entry_date') }}</th><td>{{ $entry->entry_date->format('d/m/Y') }}</td></tr>
                        <tr><th>{{ __('ledger_je.fiscal_year') }}</th><td>{{ $entry->fiscalYear->name ?? '-' }}</td></tr>
                        <tr><th>{{ __('ledger_je.accounting_period') }}</th>
                            <td>@if($entry->accountingPeriod){{ $entry->accountingPeriod->name }}@if($entry->accountingPeriod->french_name && $entry->accountingPeriod->french_name !== $entry->accountingPeriod->name) ({{ $entry->accountingPeriod->french_name }})@endif @else - @endif</td></tr>
                        <tr><th>{{ __('ledger_je.journal_type') }}</th><td><span class="label label-info">{{ $JE::typeLabel($entry->journal_type) }}</span></td></tr>
                        <tr><th>{{ __('ledger_je.reference_number') }}</th><td>{{ $entry->reference_number ?? '-' }}</td></tr>
                        <tr><th>{{ __('ledger_je.description') }}</th><td>{{ $entry->description }}</td></tr>
                    </table>
                </div>
                <div class="col-md-6 col-xs-6">
                    <table class="table table-bordered">
                        <tr><th width="40%">{{ __('ledger_je.status_title') }}</th><td>@include('ledger.journal-entries.partials.status', ['entry' => $entry])</td></tr>
                        <tr><th>{{ __('ledger_je.total_debit') }}</th><td class="text-right"><strong>{{ $money($entry->total_debit) }}</strong> {{ $cur }}</td></tr>
                        <tr><th>{{ __('ledger_je.total_credit') }}</th><td class="text-right"><strong>{{ $money($entry->total_credit) }}</strong> {{ $cur }}</td></tr>
                        <tr><th>{{ __('ledger_je.balanced') }}</th>
                            <td>@if($entry->isBalanced())<span class="ap-chip paid">{{ __('ledger_je.yes') }}</span>
                                @else<span class="ap-chip unpaid">{{ __('ledger_je.no') }} ({{ __('ledger_je.difference') }}: {{ $money(abs($entry->total_debit - $entry->total_credit)) }} {{ $cur }})</span>@endif</td></tr>
                        @if($entry->is_posted)
                        <tr><th>{{ __('ledger_je.posted_by') }}</th><td>{{ $entry->postedBy ? trim($entry->postedBy->first_name.' '.$entry->postedBy->last_name) : '-' }}</td></tr>
                        <tr><th>{{ __('ledger_je.posted_at') }}</th><td>{{ $entry->posted_at ? $entry->posted_at->format('d/m/Y H:i') : '-' }}</td></tr>
                        @endif
                        @if($entry->is_system_generated)
                        <tr><th>{{ __('ledger_je.system_generated') }}</th><td><span class="label label-info">{{ __('ledger_je.yes') }}</span> {{ $JE::sourceLabel($entry) }}</td></tr>
                        @endif
                        @if($entry->originalEntry)
                        <tr><th>{{ __('ledger_je.reverses') }}</th><td><a href="{{ route('ledger.journal-entries.show', $entry->originalEntry->id) }}">{{ $entry->originalEntry->entry_number }}</a></td></tr>
                        @endif
                        @if($entry->reversingEntry)
                        <tr><th>{{ __('ledger_je.reversed_by') }}</th><td><a href="{{ route('ledger.journal-entries.show', $entry->reversingEntry->id) }}">{{ $entry->reversingEntry->entry_number }}</a> · {{ $entry->reversingEntry->entry_date->format('d/m/Y') }}</td></tr>
                        @endif
                        <tr><th>{{ __('ledger_je.created_at') }}</th><td>{{ $entry->created_at ? $entry->created_at->format('d/m/Y H:i') : '-' }}</td></tr>
                    </table>
                </div>
            </div>

            <div class="row" style="margin-top: 10px;">
                <div class="col-md-12">
                    <h4>{{ __('ledger_je.journal_entry_lines') }} ({{ $entry->lines->count() }})</h4>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover ap-table">
                            <thead>
                                <tr>
                                    <th width="5%">{{ __('ledger_je.line') }}</th>
                                    <th width="15%">{{ __('ledger_je.account_code') }}</th>
                                    <th width="30%">{{ __('ledger_je.account_name') }}</th>
                                    <th width="25%">{{ __('ledger_je.description') }}</th>
                                    <th width="12%" class="ap-num">{{ __('ledger_je.debit') }}</th>
                                    <th width="12%" class="ap-num">{{ __('ledger_je.credit') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($entry->lines as $line)
                                <tr>
                                    <td class="text-center">{{ $line->line_number }}</td>
                                    <td><strong>{{ $line->account->account_code ?? '#'.$line->account_id }}</strong></td>
                                    <td>
                                        @if($line->account)
                                        <a href="{{ route('ledger.chart-of-accounts.show', $line->account_id) }}">{{ \App\Ledger\Services\Preview::accountName($line->account) }}</a>
                                        @php($other = app()->getLocale() === 'fr' ? $line->account->account_name : $line->account->account_name_fr)
                                        @if($other && $other !== \App\Ledger\Services\Preview::accountName($line->account))<br><small class="text-muted">{{ $other }}</small>@endif
                                        @endif
                                    </td>
                                    <td>{{ $line->description ?? '-' }}</td>
                                    <td class="ap-num">@if($line->debit > 0)<strong>{{ $money($line->debit) }}</strong>@else - @endif</td>
                                    <td class="ap-num">@if($line->credit > 0)<strong>{{ $money($line->credit) }}</strong>@else - @endif</td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="info">
                                    <td colspan="4" class="text-right"><strong>{{ __('ledger_je.total') }}:</strong></td>
                                    <td class="ap-num"><strong>{{ $money($entry->total_debit) }}</strong> {{ $cur }}</td>
                                    <td class="ap-num"><strong>{{ $money($entry->total_credit) }}</strong> {{ $cur }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Quick actions --}}
            <div class="row hidden-print" style="margin-top: 10px;">
                <div class="col-md-12">
                    <h4>{{ __('ledger_je.quick_actions') }}</h4>
                    @if($lockReason)
                    <div class="ap-note is-warn" style="margin-bottom: 10px;"><i class="fa fa-lock"></i> {{ $lockReason }}</div>
                    @endif
                    <div class="ap-toolbar" style="align-items: center;">
                        @can('journal-entry-edit')
                        @if(!$entry->is_posted && !$lockReason)
                        <a href="{{ route('ledger.journal-entries.edit', $entry->id) }}" class="btn btn-primary"><i class="fa fa-edit"></i> {{ __('ledger_je.edit') }}</a>
                        @endif
                        @endcan

                        @if(!$entry->is_posted)
                        @can('journal-entry-post')
                        <form action="{{ route('ledger.journal-entries.post', $entry->id) }}" method="POST" id="post-form" style="display: inline-block;">
                            @csrf
                            <button type="button" class="btn btn-success"
                                    data-review-url="{{ route('ledger.journal-entries.preview-post', $entry->id) }}"
                                    data-confirm-form="post-form"
                                    data-confirm-label="{{ __('ledger_je.post_entry') }}"
                                    data-confirm-check="{{ __('ledger_je.post_check') }}"><i class="fa fa-check"></i> {{ __('ledger_je.post_entry') }}</button>
                        </form>
                        @endcan
                        @elseif(!$lockReason && !$entry->is_reversed)
                        @can('journal-entry-unpost')
                        <form action="{{ route('ledger.journal-entries.unpost', $entry->id) }}" method="POST" data-review="{{ __('ledger_je.confirm_unpost_entry') }}" data-review-label="{{ __('ledger_je.unpost_entry') }}" data-review-class="btn-warning" style="display: inline-block;">
                            @csrf
                            <button type="submit" class="btn btn-warning"><i class="fa fa-undo"></i> {{ __('ledger_je.unpost_entry') }}</button>
                        </form>
                        @endcan
                        @endif

                        @can('journal-entry-reverse')
                        @if($entry->is_posted && !$entry->is_reversed && !$entry->reversed_entry_id && !$entry->is_system_generated)
                        <form action="{{ route('ledger.journal-entries.reverse', $entry->id) }}" method="POST" id="reverse-form" style="display: inline-flex; gap: 6px; align-items: center;">
                            @csrf
                            <label for="reversal_date" class="small" style="margin: 0;">{{ __('ledger_je.reverse_on') }}</label>
                            <input type="date" name="reversal_date" id="reversal_date" class="form-control input-sm" style="width: 150px;"
                                   min="{{ $entry->entry_date->toDateString() }}" value="{{ max(now()->toDateString(), $entry->entry_date->toDateString()) }}" required>
                            <button type="button" class="btn btn-danger" style="background: #fff; color: #d73925;"
                                    data-review-url="{{ route('ledger.journal-entries.preview-reverse', $entry->id) }}"
                                    data-review-form="reverse-form" data-confirm-form="reverse-form"
                                    data-confirm-label="{{ __('ledger_je.post_the_reversal') }}" data-confirm-class="btn-danger" data-needs-reason="1"
                                    data-confirm-check="{{ __('ledger_je.reverse_check') }}"><i class="fa fa-exchange-alt"></i> {{ __('ledger_je.reverse') }}</button>
                        </form>
                        @endif
                        @endcan

                        @can('journal-entry-create')
                        <form action="{{ route('ledger.journal-entries.duplicate', $entry->id) }}" method="POST" style="display: inline-block;">
                            @csrf
                            <button type="submit" class="btn btn-default" onclick="return confirm({{ json_encode(__('ledger_je.confirm_duplicate_entry')) }})"><i class="fa fa-copy"></i> {{ __('ledger_je.duplicate') }}</button>
                        </form>
                        @endcan

                        <button type="button" class="btn btn-info" onclick="window.print()"><i class="fa fa-print"></i> {{ __('ledger_je.print') }}</button>
                    </div>
                </div>
            </div>

            <div class="visible-print-block text-right" style="font-size: 8pt; margin-top: 15px; padding-top: 8px; border-top: 1px solid #ccc; color: #666; font-style: italic;">
                {{ __('ledger_je.printed_on', ['date' => now()->format('d/m/Y H:i')]) }}
            </div>
        </div>
    </div>
    @include('ledger.partials.review_modal')
</section>
<style>
    @media print {
        .main-header, .main-sidebar, .main-footer, .content-header, nav, footer, .btn, form, #reviewModal { display: none !important; }
        .content-wrapper { margin: 0 !important; padding: 0 !important; }
        .je-show { border: none !important; box-shadow: none !important; }
        .je-show .table th, .je-show .table td { border: 1px solid #000 !important; padding: 5px 6px !important; font-size: 9pt !important; }
        .je-show .table th { background: #f5f5f5 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .je-show a { color: #000 !important; text-decoration: none !important; }
        .je-show .col-xs-6 { width: 50% !important; float: left !important; }
    }
    @page { size: A4 portrait; margin: 12mm 10mm; }
</style>
@endsection

@section('javascript')
@include('ledger.partials.review_script')
@endsection
