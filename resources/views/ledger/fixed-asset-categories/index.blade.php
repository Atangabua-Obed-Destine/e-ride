@extends('layouts.app')
@section('title', __('ledger_assets.fixed_asset_categories'))

{{-- paxhitestbed admin/accounting/fixed-asset-categories/index. --}}
@section('content')
@include('ledger.partials.style')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_assets.fixed_asset_categories')</h1>
</section>
<section class="content">
    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">@lang('ledger_assets.fixed_asset_categories')</h3>
            <div class="box-tools">
                @can('fixed-asset-create')
                <a href="{{ route('ledger.fixed-asset-categories.create') }}" class="btn btn-primary btn-sm"><i class="fa fa-plus"></i> @lang('ledger_assets.add_category')</a>
                @endcan
            </div>
        </div>
        <div class="box-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover ap-table">
                    <thead>
                        <tr>
                            <th style="width: 5%;">@lang('ledger_assets.sl')</th>
                            <th style="width: 10%;">@lang('ledger_assets.code')</th>
                            <th>@lang('ledger_assets.category_name')</th>
                            <th style="width: 12%;">@lang('ledger_assets.depreciation_method')</th>
                            <th style="width: 10%;">@lang('ledger_assets.useful_life')</th>
                            <th style="width: 12%;">@lang('ledger_assets.asset_account')</th>
                            <th style="width: 8%;">@lang('ledger_assets.assets_count')</th>
                            <th style="width: 12%;">@lang('ledger_assets.action')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($categories as $key => $category)
                        <tr>
                            <td>{{ ($categories->currentPage() - 1) * $categories->perPage() + $key + 1 }}</td>
                            <td><strong>{{ $category->code }}</strong></td>
                            <td>
                                {{ $category->name }}
                                @if($category->name_fr && $category->name_fr !== $category->name)<br><small class="ap-muted">{{ $category->name_fr }}</small>@endif
                            </td>
                            <td><span class="ap-chip draft">{{ $category->getMethodLabel() }}</span></td>
                            <td>{{ $category->useful_life_years }} @lang('ledger_assets.years')</td>
                            <td>
                                @if($category->assetAccount)
                                <span title="{{ \App\Ledger\Services\Preview::accountName($category->assetAccount) }}">{{ $category->assetAccount->account_code }}</span>
                                @else
                                <span class="ap-muted">-</span>
                                @endif
                            </td>
                            <td class="text-center"><span class="ap-chip draft">{{ $category->assets_count ?? 0 }}</span></td>
                            <td>
                                @can('fixed-asset-edit')
                                <a href="{{ route('ledger.fixed-asset-categories.edit', $category->id) }}" class="btn btn-primary btn-xs" title="{{ __('ledger_assets.edit') }}"><i class="fa fa-edit"></i></a>
                                @endcan
                                @can('fixed-asset-delete')
                                @if(($category->assets_count ?? 0) == 0)
                                <form action="{{ route('ledger.fixed-asset-categories.destroy', $category->id) }}" method="POST" style="display: inline-block;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-xs" onclick="return confirm({{ json_encode(__('ledger_assets.are_you_sure')) }})" title="{{ __('ledger_assets.delete') }}"><i class="fa fa-trash"></i></button>
                                </form>
                                @endif
                                @endcan
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="8" class="text-center ap-muted">@lang('ledger_assets.no_data_found')</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($categories->hasPages())<div>{{ $categories->links() }}</div>@endif
        </div>
    </div>
</section>
@endsection
