@extends('layouts.app')
@section('title', __('ledger_assets.add_asset_category'))

{{-- paxhitestbed admin/accounting/fixed-asset-categories/create. --}}
@section('content')
@include('ledger.partials.style')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_assets.add_asset_category')</h1>
</section>
<section class="content">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <div class="box box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title">@lang('ledger_assets.add_asset_category')</h3>
                    <div class="box-tools">
                        <a href="{{ route('ledger.fixed-asset-categories.index') }}" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> @lang('ledger_assets.back')</a>
                    </div>
                </div>
                @include('ledger.fixed-assets.partials.errors')
                <form action="{{ route('ledger.fixed-asset-categories.store') }}" method="POST">
                    @csrf
                    <div class="box-body">
                        @include('ledger.fixed-asset-categories._form', ['category' => null])
                    </div>
                    <div class="box-footer">
                        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> @lang('ledger_assets.save')</button>
                        <a href="{{ route('ledger.fixed-asset-categories.index') }}" class="btn btn-default"><i class="fa fa-times"></i> @lang('ledger_assets.cancel')</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
@endsection

@section('javascript')
<script>
    $(document).ready(function () { $('.select2').select2({ width: '100%' }); });
</script>
@endsection
