@extends('layouts.app')
@section('title', __('ledger_assets.fixed_assets'))

{{-- paxhitestbed admin/accounting/fixed-assets/index: statistics, filters, the month's depreciation actions, the list, the dispose dialog. --}}
@section('content')
@include('ledger.partials.style')
@include('ledger.fixed-assets.partials.style')
@php $money = fn ($n) => \App\Ledger\Ledger::money($n); @endphp
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_assets.fixed_assets') <small>(@lang('ledger_assets.immobilisations'))</small></h1>
</section>
<section class="content">
    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">@lang('ledger_assets.fixed_assets') (@lang('ledger_assets.immobilisations'))</h3>
            <div class="box-tools">
                @can('fixed-asset-list')
                <a href="{{ route('ledger.fixed-assets.depreciation-report') }}" class="btn btn-info btn-sm" style="margin-right: 6px;">
                    <i class="fa fa-chart-bar"></i> @lang('ledger_assets.depreciation_reports')
                </a>
                @endcan
                @can('fixed-asset-create')
                <a href="{{ route('ledger.fixed-assets.create') }}" class="btn btn-primary btn-sm">
                    <i class="fa fa-plus"></i> @lang('ledger_assets.add_fixed_asset')
                </a>
                @endcan
            </div>
        </div>
        <div class="box-body">
            {{-- Statistics --}}
            <div class="row">
                <div class="col-md-3 col-sm-6">
                    <div class="fa-stat"><i class="fa fa-building fa-stat-icon"></i>
                        <div><div class="fa-stat-value">{{ $statistics['total_assets'] ?? 0 }}</div><div class="fa-stat-label">@lang('ledger_assets.total_assets')</div></div></div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="fa-stat"><i class="fa fa-money-bill-wave fa-stat-icon"></i>
                        <div><div class="fa-stat-value">{{ $money($statistics['total_cost'] ?? 0) }}</div><div class="fa-stat-label">@lang('ledger_assets.total_acquisition_cost')</div></div></div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="fa-stat"><i class="fa fa-chart-line fa-stat-icon"></i>
                        <div><div class="fa-stat-value">{{ $money($statistics['total_depreciation'] ?? 0) }}</div><div class="fa-stat-label">@lang('ledger_assets.accumulated_depreciation')</div></div></div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="fa-stat"><i class="fa fa-balance-scale fa-stat-icon"></i>
                        <div><div class="fa-stat-value">{{ $money($statistics['total_book_value'] ?? 0) }}</div><div class="fa-stat-label">@lang('ledger_assets.net_book_value')</div></div></div>
                </div>
            </div>

            {{-- Filters --}}
            <form action="{{ route('ledger.fixed-assets.index') }}" method="GET">
                <div class="row">
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="category_id">@lang('ledger_assets.category')</label>
                            <select name="category_id" id="category_id" class="form-control input-sm">
                                <option value="">@lang('ledger_assets.all')</option>
                                @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ request('category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="status">@lang('ledger_assets.status_title')</label>
                            <select name="status" id="status" class="form-control input-sm">
                                <option value="">@lang('ledger_assets.all')</option>
                                @foreach(['active', 'disposed', 'fully_depreciated'] as $s)
                                <option value="{{ $s }}" {{ request('status') == $s ? 'selected' : '' }}>@lang('ledger_assets.'.$s)</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="location">@lang('ledger_assets.location')</label>
                            <input type="text" name="location" id="location" class="form-control input-sm" value="{{ request('location') }}" placeholder="{{ __('ledger_assets.location') }}">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary btn-sm btn-block"><i class="fa fa-filter"></i> @lang('ledger_assets.filter')</button>
                        </div>
                    </div>
                </div>
            </form>

            {{-- Depreciation actions (the current month) --}}
            @can('fixed-asset-edit')
            <div class="alert alert-info fa-actions" style="background: #eff6ff !important; color: #1e3a8a !important; border-color: #bfdbfe;">
                <strong><i class="fa fa-calculator"></i> @lang('ledger_assets.depreciation_actions'):</strong>
                <form action="{{ route('ledger.fixed-assets.calculate-depreciation') }}" method="POST" id="calculate_depreciation_form">
                    @csrf
                    <input type="hidden" name="month" value="{{ date('Y-m') }}">
                    <button type="submit" class="btn btn-warning btn-sm"><i class="fa fa-calculator"></i> @lang('ledger_assets.calculate_monthly_depreciation')</button>
                </form>
                <form data-review="{{ __('ledger_assets.review_depreciation') }}" data-review-label="{{ __('ledger_assets.Post depreciation') }}"
                      action="{{ route('ledger.fixed-assets.post-depreciation') }}" method="POST" id="post_depreciation_form">
                    @csrf
                    <input type="hidden" name="month" value="{{ date('Y-m') }}">
                    <button type="submit" class="btn btn-success btn-sm"><i class="fa fa-check"></i> @lang('ledger_assets.post_depreciation_entries')</button>
                </form>
            </div>
            @endcan

            {{-- Assets --}}
            <div class="table-responsive">
                <table class="table table-bordered table-hover ap-table">
                    <thead>
                        <tr>
                            <th style="width: 5%;">@lang('ledger_assets.sl')</th>
                            <th style="width: 10%;">@lang('ledger_assets.asset_code')</th>
                            <th>@lang('ledger_assets.asset_name')</th>
                            <th style="width: 10%;">@lang('ledger_assets.category')</th>
                            <th style="width: 10%;">@lang('ledger_assets.acquisition_date')</th>
                            <th style="width: 10%;" class="ap-num">@lang('ledger_assets.acquisition_cost')</th>
                            <th style="width: 10%;" class="ap-num">@lang('ledger_assets.net_book_value')</th>
                            <th style="width: 8%;">@lang('ledger_assets.status_title')</th>
                            <th style="width: 12%;">@lang('ledger_assets.action')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($assets as $key => $asset)
                        <tr>
                            <td>{{ $assets->firstItem() + $key }}</td>
                            <td><strong>{{ $asset->asset_code }}</strong></td>
                            <td>
                                {{ $asset->name }}
                                @if($asset->serial_number)<br><small class="ap-muted">S/N: {{ $asset->serial_number }}</small>@endif
                                @if($asset->bus_label)<br><small class="ap-muted"><i class="fa fa-bus"></i> {{ $asset->bus_label }}</small>@endif{{-- E-Ride: the bus --}}
                            </td>
                            <td><span class="ap-chip draft">{{ $asset->category->name ?? '—' }}</span></td>
                            <td>{{ $asset->acquisition_date->format('d/m/Y') }}</td>
                            <td class="ap-num"><strong>{{ $money($asset->acquisition_cost) }}</strong></td>
                            <td class="ap-num">
                                <strong>{{ $money($asset->book_value ?? 0) }}</strong>
                                @if(($asset->book_value ?? 0) <= 0)<br><small class="text-danger">@lang('ledger_assets.fully_depreciated')</small>@endif
                            </td>
                            <td><span class="ap-chip {{ \App\Ledger\Models\FixedAsset::statusChip($asset->status) }}">{{ $asset->getStatusLabel() }}</span></td>
                            <td class="fa-table-actions">
                                @can('fixed-asset-list')
                                <a href="{{ route('ledger.fixed-assets.show', $asset->id) }}" class="btn btn-info btn-xs" title="{{ __('ledger_assets.view') }}"><i class="fa fa-eye"></i></a>
                                @endcan
                                @can('fixed-asset-edit')
                                @if($asset->status == 'active')
                                <a href="{{ route('ledger.fixed-assets.edit', $asset->id) }}" class="btn btn-primary btn-xs" title="{{ __('ledger_assets.edit') }}"><i class="fa fa-edit"></i></a>
                                <button type="button" class="btn btn-warning btn-xs" data-toggle="modal" data-target="#disposeModal{{ $asset->id }}" title="{{ __('ledger_assets.dispose') }}"><i class="fa fa-sign-out-alt"></i></button>
                                @endif
                                @endcan
                                @can('fixed-asset-delete')
                                @if($asset->status == 'active' && (float) $asset->accumulated_depreciation == 0 && ! $asset->purchase_journal_entry_id)
                                <form action="{{ route('ledger.fixed-assets.destroy', $asset->id) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-xs" onclick="return confirm({{ json_encode(__('ledger_assets.are_you_sure')) }})" title="{{ __('ledger_assets.delete') }}"><i class="fa fa-trash"></i></button>
                                </form>
                                @endif
                                @endcan
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="9" class="text-center ap-muted">@lang('ledger_assets.no_data_found')</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div>{{ $assets->appends(request()->query())->links() }}</div>
        </div>
    </div>

    {{-- Dispose dialogs --}}
    @can('fixed-asset-edit')
    @foreach($assets as $asset)
    @if($asset->status == 'active')
    <div class="modal fade" id="disposeModal{{ $asset->id }}" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form data-review="{{ __('ledger_assets.review_disposal') }}" data-review-label="{{ __('ledger_assets.Dispose and post') }}" data-review-class="btn-danger"
                      action="{{ route('ledger.fixed-assets.dispose', $asset->id) }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('ledger_assets.cancel') }}"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title">@lang('ledger_assets.dispose_asset'): {{ $asset->name }}</h4>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>@lang('ledger_assets.disposal_date') <span class="text-danger">*</span></label>
                            <input type="date" name="disposal_date" class="form-control" required value="{{ date('Y-m-d') }}">
                        </div>
                        <div class="form-group">
                            <label>@lang('ledger_assets.disposal_method') <span class="text-danger">*</span></label>
                            <select name="disposal_method" class="form-control" required>
                                @foreach(\App\Ledger\Models\FixedAsset::DISPOSAL_METHODS as $m)
                                <option value="{{ $m }}">@lang('ledger_assets.'.$m)</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>@lang('ledger_assets.disposal_proceeds')</label>
                            <input type="number" name="disposal_proceeds" class="form-control" value="0" min="0" step="any">
                            <small class="ap-muted">@lang('ledger_assets.current_nbv'): {{ $money($asset->book_value ?? 0) }}</small>
                        </div>
                        <div class="form-group">
                            <label>@lang('ledger_assets.Paid into')</label>
                            <select name="payment_account_id" class="form-control">
                                <option value="">@lang('ledger_assets.Nothing received')</option>
                                @foreach($paymentAccounts as $pa)
                                <option value="{{ $pa->id }}">{{ $pa->name }}</option>
                                @endforeach
                            </select>
                            <small class="ap-muted">@lang('ledger_assets.needed_when_proceeds')</small>
                        </div>
                        <div class="form-group">
                            <label>@lang('ledger_assets.notes')</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">@lang('ledger_assets.cancel')</button>
                        <button type="submit" class="btn btn-warning">@lang('ledger_assets.dispose_asset')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
    @endforeach
    @endcan
    @include('ledger.partials.review_modal')
</section>
@endsection

@section('javascript')
@include('ledger.partials.review_script')
@endsection
