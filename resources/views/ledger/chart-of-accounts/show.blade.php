@extends('layouts.app')
@section('title', __('ledger_coa.account_details'))

@section('content')
@include('ledger.partials.style')
@php
    $money = fn ($n) => \App\Ledger\Ledger::money($n);
    $name = fn ($acc) => \App\Ledger\Services\Preview::accountName($acc);
@endphp
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_coa.account_details') <small>{{ $account->account_code }} {{ $name($account) }}</small></h1>
</section>
<section class="content">
    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">@lang('ledger_coa.account_details')</h3>
            <div class="box-tools">
                @can('chart-of-accounts-edit')
                <a href="{{ route('ledger.chart-of-accounts.edit', $account->id) }}" class="btn btn-primary btn-sm"><i class="fa fa-edit"></i> @lang('ledger_coa.edit')</a>
                @endcan
                <a href="{{ route('ledger.chart-of-accounts.index') }}" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> @lang('ledger_coa.back')</a>
            </div>
        </div>
        <div class="box-body">
            <div class="row">
                {{-- Account information --}}
                <div class="col-md-6">
                    <table class="table table-bordered ap-table">
                        <tr><th width="40%">@lang('ledger_coa.account_code')</th><td><strong>{{ $account->account_code }}</strong></td></tr>
                        <tr><th>@lang('ledger_coa.account_name')</th><td>{{ $account->account_name }}</td></tr>
                        @if($account->account_name_fr)
                        <tr><th>@lang('ledger_coa.account_name') @lang('ledger_coa.french_short')</th><td>{{ $account->account_name_fr }}</td></tr>
                        @endif
                        <tr>
                            <th>@lang('ledger_coa.classe')</th>
                            <td><span class="label label-info">@lang('ledger_coa.classe') {{ $account->class_number }}</span><br><small>@lang('ledger_coa.class_'.$account->class_number.'_name')</small></td>
                        </tr>
                        <tr>
                            <th>@lang('ledger_coa.parent_account')</th>
                            <td>
                                @if($account->parent)
                                <a href="{{ route('ledger.chart-of-accounts.show', $account->parent->id) }}">{{ $account->parent->account_code }} - {{ $name($account->parent) }}</a>
                                @else
                                <span class="text-muted">@lang('ledger_coa.none')</span>
                                @endif
                            </td>
                        </tr>
                        <tr><th>@lang('ledger_coa.account_type')</th><td>{{ __('ledger_coa.type_'.strtolower($account->account_type)) }}</td></tr>
                        <tr><th>@lang('ledger_coa.account_category')</th><td>{{ __('ledger_coa.cat_'.strtolower($account->account_category)) }}</td></tr>
                        <tr><th>@lang('ledger_coa.normal_balance')</th><td><span class="ap-chip {{ $account->normal_balance == 'debit' ? 'posted' : 'part_paid' }}">{{ __('ledger_coa.'.$account->normal_balance) }}</span></td></tr>
                    </table>
                </div>

                {{-- Balance information --}}
                <div class="col-md-6">
                    <table class="table table-bordered ap-table">
                        <tr><th width="40%">@lang('ledger_coa.opening_balance')</th><td class="ap-num"><strong>{{ $money($account->opening_balance) }}</strong></td></tr>
                        <tr><th>@lang('ledger_coa.current_balance')</th><td class="ap-num"><strong class="text-{{ $account->current_balance >= 0 ? 'success' : 'danger' }}">{{ $money($account->current_balance) }}</strong></td></tr>
                        <tr><th>@lang('ledger_coa.display_order')</th><td>{{ $account->display_order }}</td></tr>
                        <tr>
                            <th>@lang('ledger_coa.status')</th>
                            <td>
                                @if($account->is_active)
                                <span class="ap-chip open">@lang('ledger_coa.active')</span>
                                @else
                                <span class="ap-chip closed">@lang('ledger_coa.inactive')</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>@lang('ledger_coa.system_account')</th>
                            <td><span class="ap-chip {{ $account->is_system ? 'part_paid' : 'draft' }}">{{ $account->is_system ? __('ledger_coa.yes') : __('ledger_coa.no') }}</span></td>
                        </tr>
                        <tr><th>@lang('ledger_coa.created_at')</th><td>{{ optional($account->created_at)->format('d/m/Y H:i') }}</td></tr>
                        <tr><th>@lang('ledger_coa.updated_at')</th><td>{{ optional($account->updated_at)->format('d/m/Y H:i') }}</td></tr>
                    </table>
                </div>
            </div>

            {{-- Child accounts --}}
            @if($account->children->count() > 0)
            <div class="row" style="margin-top: 16px;">
                <div class="col-md-12">
                    <h4>@lang('ledger_coa.child_accounts') ({{ $account->children->count() }})</h4>
                    <div class="table-responsive">
                        <table class="table table-bordered table-condensed ap-table">
                            <thead>
                                <tr>
                                    <th>@lang('ledger_coa.account_code')</th>
                                    <th>@lang('ledger_coa.account_name')</th>
                                    <th>@lang('ledger_coa.account_type')</th>
                                    <th class="ap-num">@lang('ledger_coa.current_balance')</th>
                                    <th>@lang('ledger_coa.action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($account->children as $child)
                                <tr>
                                    <td>{{ $child->account_code }}</td>
                                    <td>{{ $name($child) }}</td>
                                    <td>{{ __('ledger_coa.type_'.strtolower($child->account_type)) }}</td>
                                    <td class="ap-num">{{ $money($child->current_balance) }}</td>
                                    <td><a href="{{ route('ledger.chart-of-accounts.show', $child->id) }}" class="btn btn-info btn-xs" title="@lang('ledger_coa.view')"><i class="fa fa-eye"></i></a></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif

            {{-- Recent transactions --}}
            <div class="row" style="margin-top: 16px;">
                <div class="col-md-12">
                    <h4>@lang('ledger_coa.recent_transactions')</h4>
                    @if($transactions->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-bordered table-condensed ap-table">
                            <thead>
                                <tr>
                                    <th>@lang('ledger_coa.date')</th>
                                    <th>@lang('ledger_coa.entry_number')</th>
                                    <th>@lang('ledger_coa.description')</th>
                                    <th class="ap-num">@lang('ledger_coa.debit')</th>
                                    <th class="ap-num">@lang('ledger_coa.credit')</th>
                                    <th class="ap-num">@lang('ledger_coa.balance')</th>
                                    <th>@lang('ledger_coa.action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $runningBalance = (float) $account->opening_balance; @endphp
                                @foreach($transactions as $transaction)
                                @php
                                    if ($account->normal_balance == 'debit') {
                                        $runningBalance += $transaction->debit - $transaction->credit;
                                    } else {
                                        $runningBalance += $transaction->credit - $transaction->debit;
                                    }
                                @endphp
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($transaction->journalEntry->entry_date)->format('d/m/Y') }}</td>
                                    <td>{{ $transaction->journalEntry->entry_number }}</td>
                                    <td>{{ $transaction->description }}</td>
                                    <td class="ap-num">{{ $transaction->debit > 0 ? $money($transaction->debit) : '-' }}</td>
                                    <td class="ap-num">{{ $transaction->credit > 0 ? $money($transaction->credit) : '-' }}</td>
                                    <td class="ap-num"><strong>{{ $money($runningBalance) }}</strong></td>
                                    <td><a href="{{ route('ledger.journal-entries.show', $transaction->journal_entry_id) }}" class="btn btn-info btn-xs" title="@lang('ledger_coa.view')"><i class="fa fa-eye"></i></a></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div style="margin-top: 8px;">
                        <a href="{{ route('ledger.general-ledger.account', $account->id) }}" class="btn btn-primary btn-sm">@lang('ledger_coa.view_full_ledger')</a>
                    </div>
                    @else
                    <p class="text-muted">@lang('ledger_coa.no_transactions_found')</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
