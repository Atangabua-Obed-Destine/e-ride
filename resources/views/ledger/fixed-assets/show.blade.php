@extends('layouts.app')
@section('title', __('ledger_assets.fixed_asset_details'))

{{-- paxhitestbed admin/accounting/fixed-assets/show, plus the ledger entries of the asset and "Record the purchase in the ledger". --}}
@section('content')
@include('ledger.partials.style')
@include('ledger.fixed-assets.partials.style')
@php
    $money = fn ($n) => \App\Ledger\Ledger::money($n);
    $depreciable = (float) $asset->acquisition_cost - (float) $asset->salvage_value;
    $percentage = $depreciable > 0 ? min(100, (float) $asset->accumulated_depreciation / $depreciable * 100) : 0;
    $hasInsurance = $asset->insurance_value || $asset->insurance_policy_number || $asset->warranty_expiry;
    $entryLink = fn ($e) => $e ? '<a href="'.e(route('ledger.journal-entries.show', $e->id)).'">'.e($e->entry_number).'</a>' : '—';
    $recent = $asset->depreciationSchedules->sortByDesc('depreciation_date')->take(12);
@endphp
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_assets.fixed_asset_details')</h1>
</section>
<section class="content">
    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">@lang('ledger_assets.fixed_asset_details'): {{ $asset->asset_code }}</h3>
            <div class="box-tools">
                @can('fixed-asset-edit')
                @if($asset->status == 'active' && ! $asset->purchase_journal_entry_id)
                <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#purchaseModal" style="margin-right: 6px;">
                    <i class="fa fa-book"></i> @lang('ledger_assets.record_purchase')
                </button>
                @endif
                @if($asset->status == 'active')
                <a href="{{ route('ledger.fixed-assets.edit', $asset->id) }}" class="btn btn-primary btn-sm" style="margin-right: 6px;"><i class="fa fa-edit"></i> @lang('ledger_assets.edit')</a>
                @endif
                @endcan
                <a href="{{ route('ledger.fixed-assets.index') }}" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> @lang('ledger_assets.back')</a>
            </div>
        </div>
        <div class="box-body">
            <div class="row">
                {{-- Basic information --}}
                <div class="col-md-6">
                    <div class="fa-panel">
                        <div class="fa-panel-head"><i class="fa fa-info-circle"></i> @lang('ledger_assets.basic_information')</div>
                        <div class="fa-panel-body">
                            <dl class="ap-dl">
                                <dt>@lang('ledger_assets.asset_code')</dt><dd><strong>{{ $asset->asset_code }}</strong></dd>
                                <dt>@lang('ledger_assets.asset_name')</dt><dd>{{ $asset->name }}</dd>
                                @if($asset->name_fr && $asset->name_fr !== $asset->name)
                                <dt>@lang('ledger_assets.asset_name_fr')</dt><dd>{{ $asset->name_fr }}</dd>
                                @endif
                                <dt>@lang('ledger_assets.category')</dt><dd><span class="ap-chip draft">{{ $asset->category->name ?? '—' }}</span></dd>
                                <dt>@lang('ledger_assets.serial_number')</dt><dd>{{ $asset->serial_number ?: '-' }}</dd>
                                <dt>@lang('ledger_assets.location')</dt><dd>{{ $asset->location ?: '-' }}</dd>
                                <dt>@lang('ledger_assets.vendor')</dt><dd>{{ $asset->vendor ?: '-' }}</dd>
                                <dt>@lang('ledger_assets.status_title')</dt><dd><span class="ap-chip {{ \App\Ledger\Models\FixedAsset::statusChip($asset->status) }}">{{ $asset->getStatusLabel() }}</span></dd>
                            </dl>
                        </div>
                    </div>
                </div>

                {{-- Financial information --}}
                <div class="col-md-6">
                    <div class="fa-panel">
                        <div class="fa-panel-head"><i class="fa fa-money-bill-wave"></i> @lang('ledger_assets.financial_information')</div>
                        <div class="fa-panel-body">
                            <dl class="ap-dl">
                                <dt>@lang('ledger_assets.acquisition_date')</dt><dd>{{ $asset->acquisition_date->format('d/m/Y') }}</dd>
                                <dt>@lang('ledger_assets.acquisition_cost')</dt><dd><strong>{{ $money($asset->acquisition_cost) }}</strong></dd>
                                <dt>@lang('ledger_assets.salvage_value')</dt><dd>{{ $money($asset->salvage_value) }}</dd>
                                <dt>@lang('ledger_assets.accumulated_depreciation')</dt><dd class="text-danger">{{ $money($asset->accumulated_depreciation) }}</dd>
                                <dt>@lang('ledger_assets.net_book_value')</dt><dd><strong>{{ $money($asset->net_book_value) }}</strong></dd>
                                <dt>@lang('ledger_assets.depreciation_method')</dt><dd>{{ \App\Ledger\Models\FixedAssetCategory::methodLabel($asset->depreciation_method) }}</dd>
                                <dt>@lang('ledger_assets.useful_life')</dt><dd>{{ $asset->useful_life_years }} @lang('ledger_assets.years')</dd>
                                <dt>@lang('ledger_assets.depreciation_start_date')</dt><dd>{{ $asset->depreciation_start_date ? $asset->depreciation_start_date->format('d/m/Y') : '-' }}</dd>
                                <dt>@lang('ledger_assets.purchase_entry')</dt>
                                <dd>{!! $entryLink($asset->purchaseJournalEntry) !!}@if(! $asset->purchase_journal_entry_id) <span class="ap-muted">@lang('ledger_assets.purchase_not_posted')</span>@endif</dd>
                            </dl>
                        </div>
                    </div>
                </div>

                {{-- Depreciation progress --}}
                <div class="col-md-12">
                    <div class="fa-panel">
                        <div class="fa-panel-head"><i class="fa fa-chart-line"></i> @lang('ledger_assets.depreciation_progress')</div>
                        <div class="fa-panel-body">
                            <div class="progress fa-progress">
                                <div class="progress-bar progress-bar-warning" role="progressbar" style="width: {{ $percentage }}%; min-width: 3em;"
                                     aria-valuenow="{{ $percentage }}" aria-valuemin="0" aria-valuemax="100">
                                    {{ number_format($percentage, 1, ',', ' ') }}% @lang('ledger_assets.depreciated')
                                </div>
                            </div>
                            <div class="row" style="margin-top: 12px;">
                                <div class="col-md-4 text-center"><small class="ap-muted">@lang('ledger_assets.depreciable_amount')</small><h4>{{ $money($depreciable) }}</h4></div>
                                <div class="col-md-4 text-center"><small class="ap-muted">@lang('ledger_assets.depreciated_amount')</small><h4 class="text-danger">{{ $money($asset->accumulated_depreciation) }}</h4></div>
                                <div class="col-md-4 text-center"><small class="ap-muted">@lang('ledger_assets.remaining_to_depreciate')</small><h4 class="text-success">{{ $money($asset->net_book_value - (float) $asset->salvage_value) }}</h4></div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Insurance and warranty --}}
                @if($hasInsurance)
                <div class="col-md-6">
                    <div class="fa-panel">
                        <div class="fa-panel-head"><i class="fa fa-shield-alt"></i> @lang('ledger_assets.insurance_warranty')</div>
                        <div class="fa-panel-body">
                            <dl class="ap-dl">
                                @if($asset->insurance_value)<dt>@lang('ledger_assets.insurance_value')</dt><dd>{{ $money($asset->insurance_value) }}</dd>@endif
                                @if($asset->insurance_policy_number)<dt>@lang('ledger_assets.policy_number')</dt><dd>{{ $asset->insurance_policy_number }}</dd>@endif
                                @if($asset->warranty_expiry)
                                <dt>@lang('ledger_assets.warranty_expiry')</dt>
                                <dd>{{ $asset->warranty_expiry->format('d/m/Y') }}
                                    @if($asset->warranty_expiry->isPast())<span class="ap-chip unpaid">@lang('ledger_assets.expired')</span>@else<span class="ap-chip paid">@lang('ledger_assets.valid')</span>@endif
                                </dd>
                                @endif
                            </dl>
                        </div>
                    </div>
                </div>
                @endif

                {{-- Description --}}
                @if($asset->description)
                <div class="col-md-{{ $hasInsurance ? '6' : '12' }}">
                    <div class="fa-panel">
                        <div class="fa-panel-head"><i class="fa fa-file-alt"></i> @lang('ledger_assets.description')</div>
                        <div class="fa-panel-body">{{ $asset->description }}</div>
                    </div>
                </div>
                @endif

                {{-- Disposal information --}}
                @if($asset->status == 'disposed')
                @php $gainLoss = (float) ($asset->disposal_value ?? 0) - $asset->net_book_value; @endphp
                <div class="col-md-12">
                    <div class="fa-panel is-danger">
                        <div class="fa-panel-head"><i class="fa fa-sign-out-alt"></i> @lang('ledger_assets.disposal_information')</div>
                        <div class="fa-panel-body">
                            <div class="row">
                                <div class="col-md-3"><strong>@lang('ledger_assets.disposal_date'):</strong><br>{{ $asset->disposal_date ? $asset->disposal_date->format('d/m/Y') : '-' }}</div>
                                <div class="col-md-3"><strong>@lang('ledger_assets.disposal_method'):</strong><br>{{ $asset->disposal_method ? __('ledger_assets.'.$asset->disposal_method) : ($asset->disposal_reason ?: '-') }}</div>
                                <div class="col-md-2"><strong>@lang('ledger_assets.disposal_proceeds'):</strong><br>{{ $asset->disposal_value ? $money($asset->disposal_value) : '-' }}</div>
                                <div class="col-md-2"><strong>@lang('ledger_assets.gain_loss'):</strong><br><span class="{{ $gainLoss >= 0 ? 'text-success' : 'text-danger' }}">{{ $money($gainLoss) }}</span></div>
                                <div class="col-md-2"><strong>@lang('ledger_assets.disposal_entry'):</strong><br>{!! $entryLink($asset->disposalJournalEntry) !!}</div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                {{-- Depreciation schedule --}}
                <div class="col-md-12">
                    <div class="fa-panel">
                        <div class="fa-panel-head"><i class="fa fa-calendar-alt"></i> @lang('ledger_assets.depreciation_schedule')</div>
                        <div class="fa-panel-body">
                            @if($asset->depreciationSchedules->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-bordered table-condensed ap-table">
                                    <thead>
                                        <tr>
                                            <th>@lang('ledger_assets.period')</th>
                                            <th class="ap-num">@lang('ledger_assets.depreciation_amount')</th>
                                            <th class="ap-num">@lang('ledger_assets.accumulated')</th>
                                            <th class="ap-num">@lang('ledger_assets.net_book_value')</th>
                                            <th>@lang('ledger_assets.status_title')</th>
                                            <th>@lang('ledger_assets.posted_date')</th>
                                            <th>@lang('ledger_assets.entry')</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($recent as $schedule)
                                        <tr>
                                            <td>{{ $schedule->depreciation_date->translatedFormat('M Y') }}</td>
                                            <td class="ap-num">{{ $money($schedule->depreciation_amount) }}</td>
                                            <td class="ap-num">{{ $money($schedule->accumulated_depreciation_after) }}</td>
                                            <td class="ap-num">{{ $money($schedule->book_value_after) }}</td>
                                            <td><span class="ap-chip {{ \App\Ledger\Models\DepreciationSchedule::statusChip($schedule->status) }}">{{ $schedule->getStatusLabel() }}</span></td>
                                            <td>{{ $schedule->posted_at ? $schedule->posted_at->format('d/m/Y') : '-' }}</td>
                                            <td>{!! $entryLink($schedule->journalEntry) !!}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @if($asset->depreciationSchedules->count() > 12)
                            <p class="ap-muted">@lang('ledger_assets.showing_recent_12_entries'). @lang('ledger_assets.total'): {{ $asset->depreciationSchedules->count() }} @lang('ledger_assets.entries').</p>
                            @endif
                            @else
                            <p class="ap-muted">@lang('ledger_assets.no_depreciation_recorded')</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Record the purchase in the ledger --}}
    @can('fixed-asset-edit')
    @if($asset->status == 'active' && ! $asset->purchase_journal_entry_id)
    <div class="modal fade" id="purchaseModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form data-review="{{ __('ledger_assets.review_purchase') }}" data-review-label="{{ __('ledger_assets.record_purchase') }}"
                      action="{{ route('ledger.fixed-assets.post-purchase', $asset->id) }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('ledger_assets.cancel') }}"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title">@lang('ledger_assets.record_purchase'): {{ $asset->name }}</h4>
                    </div>
                    <div class="modal-body">
                        <dl class="ap-dl" style="margin-bottom: 12px;">
                            <dt>@lang('ledger_assets.acquisition_date')</dt><dd>{{ $asset->acquisition_date->format('d/m/Y') }}</dd>
                            <dt>@lang('ledger_assets.acquisition_cost')</dt><dd>{{ $money($asset->acquisition_cost) }}</dd>
                            <dt>@lang('ledger_assets.asset_account')</dt><dd>{{ $asset->category && $asset->category->assetAccount ? $asset->category->assetAccount->account_code.' '.\App\Ledger\Services\Preview::accountName($asset->category->assetAccount) : '—' }}</dd>
                        </dl>
                        <div class="form-group">
                            <label for="paid_from">@lang('ledger_assets.paid_from') <span class="text-danger">*</span></label>
                            <select name="paid_from" id="paid_from" class="form-control" required>
                                <option value="">@lang('ledger_assets.select_account')</option>
                                @foreach($paymentAccounts as $pa)
                                <option value="{{ $pa->id }}">{{ $pa->name }}</option>
                                @endforeach
                                <option value="supplier">@lang('ledger_assets.owed_to_supplier_option')</option>
                            </select>
                        </div>
                        <div class="ap-note">@lang('ledger_assets.record_purchase_note')</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">@lang('ledger_assets.cancel')</button>
                        <button type="submit" class="btn btn-success">@lang('ledger_assets.record_purchase')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
    @endcan
    @include('ledger.partials.review_modal')
</section>
@endsection

@section('javascript')
@include('ledger.partials.review_script')
@endsection
