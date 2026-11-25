@extends('layouts.app')
@section('title', __('ledger_coa.chart_of_accounts'))

@section('content')
@include('ledger.partials.style')
<style>
    .coa-stat { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; margin-bottom: 12px; position: relative; }
    .coa-stat h3 { margin: 0 0 4px; font-size: 22px; font-weight: 700; }
    .coa-stat p { margin: 0; color: #64748b; }
    .coa-stat .fa { position: absolute; right: 16px; top: 18px; font-size: 26px; color: #cbd5e1; }
    .coa-heading { background: #f8f9fa; }
    .coa-row:hover { background: #e3f2fd !important; }
</style>
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_coa.chart_of_accounts') <small>(@lang('ledger_coa.plan_comptable'))</small></h1>
</section>
<section class="content">
    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">@lang('ledger_coa.chart_of_accounts')</h3>
            <div class="box-tools">
                @can('chart-of-accounts-create')
                <a href="{{ route('ledger.chart-of-accounts.create') }}" class="btn btn-primary btn-sm">
                    <i class="fa fa-plus"></i> @lang('ledger_coa.add_account')
                </a>
                @endcan
            </div>
        </div>
        <div class="box-body">
            {{-- Statistics --}}
            <div class="row">
                <div class="col-md-3 col-sm-6">
                    <div class="coa-stat"><h3>{{ $statistics['total_accounts'] }}</h3><p>@lang('ledger_coa.total_accounts')</p><i class="fa fa-list"></i></div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="coa-stat"><h3>{{ $statistics['active_accounts'] }}</h3><p>@lang('ledger_coa.active_accounts')</p><i class="fa fa-check-circle"></i></div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="coa-stat"><h3>{{ $statistics['detail_accounts'] }}</h3><p>@lang('ledger_coa.detail_accounts')</p><i class="fa fa-file-alt"></i></div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="coa-stat"><h3>{{ \App\Ledger\Ledger::money($statistics['total_balance']) }}</h3><p>@lang('ledger_coa.total_balance')</p><i class="fa fa-coins"></i></div>
                </div>
            </div>

            {{-- Filter by class --}}
            <div class="row" style="margin-bottom: 12px;">
                <div class="col-md-12">
                    <div class="btn-group" role="group">
                        <a href="{{ route('ledger.chart-of-accounts.index') }}" class="btn btn-sm {{ ! request('class') ? 'btn-primary' : 'btn-default' }}">@lang('ledger_coa.all_classes')</a>
                        @for($i = 1; $i <= 9; $i++)
                        <a href="{{ route('ledger.chart-of-accounts.index', ['class' => $i]) }}" class="btn btn-sm {{ request('class') == $i ? 'btn-primary' : 'btn-default' }}">@lang('ledger_coa.classe') {{ $i }}</a>
                        @endfor
                    </div>
                </div>
            </div>

            {{-- Accounts tree --}}
            <div class="table-responsive">
                <table class="table table-bordered ap-table" id="coa_table">
                    <thead>
                        <tr>
                            <th width="10%">@lang('ledger_coa.account_code')</th>
                            <th>@lang('ledger_coa.account_name')</th>
                            <th width="12%">@lang('ledger_coa.classe')</th>
                            <th width="12%">@lang('ledger_coa.account_type')</th>
                            <th width="10%">@lang('ledger_coa.normal_balance')</th>
                            <th width="12%" class="ap-num">@lang('ledger_coa.current_balance')</th>
                            <th width="8%">@lang('ledger_coa.status')</th>
                            <th width="15%">@lang('ledger_coa.action')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($accounts as $account)
                        @php
                            $level = $account->level ?? 0;
                            $other = app()->getLocale() === 'fr' ? $account->account_name : $account->account_name_fr;
                        @endphp
                        <tr class="coa-row {{ $account->account_category == 'heading' ? 'coa-heading' : '' }}" data-level="{{ $level }}">
                            <td><strong>{{ $account->account_code }}</strong></td>
                            <td style="padding-left: {{ $level * 20 + 8 }}px;">
                                @if($account->account_category == 'heading')
                                <i class="fa fa-folder text-warning"></i>
                                @else
                                <i class="fa fa-file text-info"></i>
                                @endif
                                <strong>{{ \App\Ledger\Services\Preview::accountName($account) }}</strong>
                                @if($other && $other !== \App\Ledger\Services\Preview::accountName($account))
                                <br><small class="text-muted" style="padding-left: 20px;">{{ $other }}</small>
                                @endif
                            </td>
                            <td><span class="label label-info">@lang('ledger_coa.classe') {{ $account->class_number }}</span></td>
                            <td>{{ __('ledger_coa.type_'.strtolower($account->account_type)) }}</td>
                            <td><span class="ap-chip {{ $account->normal_balance == 'debit' ? 'posted' : 'part_paid' }}">{{ __('ledger_coa.'.$account->normal_balance) }}</span></td>
                            <td class="ap-num"><strong>{{ \App\Ledger\Ledger::money($account->current_balance) }}</strong></td>
                            <td>
                                @if($account->is_active)
                                <span class="ap-chip open">@lang('ledger_coa.active')</span>
                                @else
                                <span class="ap-chip closed">@lang('ledger_coa.inactive')</span>
                                @endif
                            </td>
                            <td class="text-nowrap">
                                @can('chart-of-accounts-view')
                                <a href="{{ route('ledger.chart-of-accounts.show', $account->id) }}" class="btn btn-info btn-xs" title="@lang('ledger_coa.view')"><i class="fa fa-eye"></i></a>
                                @endcan
                                @can('chart-of-accounts-edit')
                                <a href="{{ route('ledger.chart-of-accounts.edit', $account->id) }}" class="btn btn-primary btn-xs" title="@lang('ledger_coa.edit')"><i class="fa fa-edit"></i></a>
                                @endcan
                                @can('chart-of-accounts-delete')
                                @if(! $account->is_system)
                                <form action="{{ route('ledger.chart-of-accounts.destroy', $account->id) }}" method="POST" style="display: inline-block;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-xs" onclick="return confirm({{ json_encode(__('ledger_coa.are_you_sure')) }})" title="@lang('ledger_coa.delete')"><i class="fa fa-trash"></i></button>
                                </form>
                                @endif
                                @endcan
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="8" class="text-center">@lang('ledger_coa.no_data_found')</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
@endsection
