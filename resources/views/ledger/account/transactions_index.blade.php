@extends('layouts.app')
@section('title', $title)
@section('css')
@include('ledger.partials.style')
@include('ledger.account.theme', ['kind' => $kind])
@endsection

{{--
  The income and expense lists (paxhitestbed admin/account/partials/transactions_index).
  Read top to bottom: what period, how much, how it moved, then the records themselves.
  E-Ride: a column says whether each record is in the ledger (posted / not posted, why).
--}}
@section('content')
@php
    $money = fn ($v) => \App\Ledger\Ledger::money($v);
    $shades = $kind === 'expense' ? ['#eb6834', '#f08a5f', '#f5ac8a', '#f9cdb5', '#fde6da'] : ['#2a78d6', '#5593e0', '#7faeea', '#aac9f3', '#d4e4f9'];
    $total = max((float) $summary['total'], 0.0001);
@endphp
<div class="main-body acct">
    <div class="page-wrapper">

        <div class="acct-band">
            <div class="acct-band-top">
                <div class="acct-band-who">
                    <div class="acct-label">@lang('ledger_accounts.showing')</div>
                    <h4>{{ $window['label'] }}</h4>
                    @if($window['start'] && $window['end'])
                    <div class="acct-band-sub">{{ \Carbon\Carbon::parse($window['start'])->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($window['end'])->format('d/m/Y') }}</div>
                    @endif
                </div>
                <div class="acct-band-figure">
                    <div class="acct-label">{{ $title }}</div>
                    <div class="v acct-num">{{ $money($summary['total']) }}<span class="c acct-cur">FCFA</span></div>
                    <div class="acct-band-sub">{{ number_format($summary['count']) }} @lang('ledger_accounts.records')</div>
                </div>
            </div>
            @if(count($summary['methods']))
            <div class="acct-split">
                <div class="acct-split-bar">
                    @foreach(array_values($summary['methods']) as $i => $method)
                    <span style="width: {{ round($method['total'] / $total * 100, 2) }}%; background: {{ $shades[$i % count($shades)] }};" title="{{ $method['label'] }}"></span>
                    @endforeach
                </div>
                <div class="acct-split-key">
                    @foreach(array_values($summary['methods']) as $i => $method)
                    <span class="acct-split-item">
                        <span class="dot" style="background: {{ $shades[$i % count($shades)] }};"></span>
                        {{ $method['label'] }} <b class="acct-num">{{ $money($method['total']) }}</b> <i>{{ $method['count'] }}</i>
                    </span>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        <div class="acct-bar">
            @foreach($window['ranges'] as $range)
            <a href="{{ route($route.'.index', array_filter(['title' => $selected_title, 'category' => $selected_category ?: null,
                   'start_date' => $range['start'], 'end_date' => $range['end'], 'all' => $range['start'] === null ? 1 : null])) }}"
               class="acct-tab {{ $range['active'] ? 'is-on' : '' }}">{{ $range['label'] }}</a>
            @endforeach
            <div class="acct-bar-end">
                <a href="{{ route($route.'.report', array_filter(['title' => $selected_title, 'category' => $selected_category ?: null,
                       'start_date' => $window['start'], 'end_date' => $window['end'], 'all' => $window['start'] === null ? 1 : null])) }}"
                   class="acct-btn" title="@lang('ledger_accounts.report_hint')"><i class="fas fa-file-pdf"></i> @lang('ledger_accounts.report')</a>
                @can($access.'-create')
                <a href="{{ route($route.'.create') }}" class="acct-btn acct-btn-main"><i class="fas fa-plus"></i> @lang('ledger_accounts.btn_add_new')</a>
                @endcan
                <a href="{{ route($route.'.index') }}" class="acct-btn" title="@lang('ledger_accounts.btn_refresh')"><i class="fas fa-sync-alt"></i></a>
            </div>
        </div>

        <div class="acct-panel">
            <form class="acct-filters" method="get" action="{{ route($route.'.index') }}">
                <div class="row gx-2 gy-2 align-items-end">
                    <div class="col-md-3">
                        <label for="title">@lang('ledger_accounts.field_title')</label>
                        <input type="text" class="form-control" name="title" id="title" value="{{ $selected_title }}" placeholder="@lang('ledger_accounts.search')">
                    </div>
                    <div class="col-md-3">
                        <label for="category">@lang('ledger_accounts.field_category') <span>(@lang('ledger_accounts.select_multiple'))</span></label>
                        <select class="form-control select2" name="category[]" id="category" multiple data-placeholder="@lang('ledger_accounts.all')">
                            @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected(in_array($category->id, (array) $selected_category))>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="start_date">@lang('ledger_accounts.field_from_date')</label>
                        <input type="date" class="form-control" name="start_date" id="start_date" value="{{ $selected_start_date }}">
                    </div>
                    <div class="col-md-2">
                        <label for="end_date">@lang('ledger_accounts.field_to_date')</label>
                        <input type="date" class="form-control" name="end_date" id="end_date" value="{{ $selected_end_date }}">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="acct-btn acct-btn-main w-100 justify-content-center"><i class="fas fa-search"></i> @lang('ledger_accounts.btn_filter')</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="acct-panel">
            @if($rows->isEmpty())
                <div class="acct-empty">
                    <i class="fas fa-folder-open"></i>
                    <p>@lang('ledger_accounts.no_records')</p>
                    <small>@lang('ledger_accounts.no_records_hint')</small>
                </div>
            @else
            <div class="table-responsive">
                <table class="table acct-table" style="width:100%">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>@lang('ledger_accounts.field_date')</th>
                            <th>@lang('ledger_accounts.field_title')</th>
                            <th>@lang('ledger_accounts.field_category')</th>
                            <th>@lang('ledger_accounts.field_payment_method')</th>
                            <th>@lang('ledger_accounts.field_invoice_id')</th>
                            <th class="n">@lang('ledger_accounts.field_amount')</th>
                            <th>@lang('ledger_accounts.ledger')</th>
                            <th class="n">@lang('ledger_accounts.field_action')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $key => $row)
                        @php $st = $states[(string) $row->id] ?? null; @endphp
                        <tr>
                            <td class="acct-meta">{{ $key + 1 }}</td>
                            <td class="acct-when">{{ $row->date->format('d/m/Y') }}</td>
                            <td>
                                <span class="acct-what">{{ $row->title }}</span>
                                @if($row->attach)<i class="fas fa-paperclip acct-clip" title="@lang('ledger_accounts.field_attach')"></i>@endif
                            </td>
                            <td><span class="acct-tag acct-tag-flow">{{ $row->category->name ?? '-' }}</span></td>
                            <td class="acct-meta">{{ \App\Ledger\Services\PaymentBook::methodLabel($row->payment_method) }}@if($row->paymentAccount)<br>{{ $row->paymentAccount->title }}@endif</td>
                            <td class="acct-ref">{{ $row->invoice_id }}</td>
                            <td class="n acct-num">{{ $money($row->amount) }}</td>
                            <td class="acct-posted">
                                @if($st && $st->status === 'posted')
                                    <span class="ap-chip posted">@lang('ledger_accounts.posted')</span>
                                @elseif($st && $st->status === 'failed')
                                    <span class="ap-chip failed" title="{{ $st->error }}">@lang('ledger_accounts.not_posted')</span>
                                @else
                                    <span class="ap-chip draft">@lang('ledger_accounts.not_posted')</span>
                                @endif
                            </td>
                            <td class="acct-acts">
                                <button type="button" class="btn btn-icon btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#showModal-{{ $row->id }}" title="@lang('ledger_accounts.view')"><i class="fas fa-eye"></i></button>
                                @include('ledger.account.record_modal')
                                @can($access.'-edit')
                                <a href="{{ route($route.'.edit', $row->id) }}" class="btn btn-icon btn-light btn-sm" title="@lang('ledger_accounts.btn_edit')"><i class="far fa-edit"></i></a>
                                @endcan
                                @can($access.'-delete')
                                <form method="post" action="{{ route($route.'.destroy', $row->id) }}" class="d-inline"
                                      data-review="@lang('ledger_accounts.check_delete_record')" data-review-label="@lang('ledger_accounts.delete_and_reverse')" data-review-class="btn-danger">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-icon btn-light btn-sm" title="@lang('ledger_accounts.btn_delete')"><i class="fas fa-trash-alt"></i></button>
                                </form>
                                @endcan
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="5"></th>
                            <th>@lang('ledger_accounts.field_total')</th>
                            <th class="n acct-num">{{ $money($rows->sum('amount')) }} FCFA</th>
                            <th colspan="2"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @endif
        </div>

    </div>
</div>
@include('ledger.partials.review_modal')
@endsection

@section('javascript')
@include('ledger.partials.review_script')
<script>$(function () { if ($.fn.select2) { $('#category').select2({ width: '100%', placeholder: $('#category').data('placeholder') }); } });</script>
@endsection
