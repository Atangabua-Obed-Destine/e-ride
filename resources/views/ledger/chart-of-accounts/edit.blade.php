@extends('layouts.app')
@section('title', __('ledger_coa.edit_account'))

@section('content')
@include('ledger.partials.style')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_coa.edit_account')</h1>
</section>
<section class="content">
    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">@lang('ledger_coa.edit_account')</h3>
            <div class="box-tools">
                <a href="{{ route('ledger.chart-of-accounts.index') }}" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> @lang('ledger_coa.back')</a>
            </div>
        </div>
        <form action="{{ route('ledger.chart-of-accounts.update', $account->id) }}" method="POST" id="coa_form">
            @csrf
            @method('PUT')
            <div class="box-body">
                @if($account->is_system)
                <div class="ap-note is-warn" style="margin-bottom: 14px;">
                    <i class="fa fa-exclamation-triangle"></i> @lang('ledger_coa.this_is_a_system_account'). @lang('ledger_coa.some_fields_cannot_be_modified').
                </div>
                @endif
                @include('ledger.chart-of-accounts._form', ['account' => $account])
            </div>
            <div class="box-footer">
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> @lang('ledger_coa.update')</button>
                <a href="{{ route('ledger.chart-of-accounts.index') }}" class="btn btn-default"><i class="fa fa-times"></i> @lang('ledger_coa.cancel')</a>
            </div>
        </form>
    </div>
</section>
@endsection

@section('javascript')
<script>
$(document).ready(function () {
    $('#parent_id').select2({ placeholder: @json(__('ledger_coa.select_parent_account')), allowClear: true });
});
</script>
@endsection
