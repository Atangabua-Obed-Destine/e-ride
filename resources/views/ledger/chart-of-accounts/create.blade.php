@extends('layouts.app')
@section('title', __('ledger_coa.add_account'))

@section('content')
@include('ledger.partials.style')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_coa.add_account')</h1>
</section>
<section class="content">
    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">@lang('ledger_coa.add_account')</h3>
            <div class="box-tools">
                <a href="{{ route('ledger.chart-of-accounts.index') }}" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> @lang('ledger_coa.back')</a>
            </div>
        </div>
        <form action="{{ route('ledger.chart-of-accounts.store') }}" method="POST" id="coa_form">
            @csrf
            <div class="box-body">
                @include('ledger.chart-of-accounts._form', ['account' => null])
            </div>
            <div class="box-footer">
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> @lang('ledger_coa.save')</button>
                <a href="{{ route('ledger.chart-of-accounts.index') }}" class="btn btn-default"><i class="fa fa-times"></i> @lang('ledger_coa.cancel')</a>
            </div>
        </form>
    </div>
</section>
@endsection

@section('javascript')
<script>
$(document).ready(function () {
    var T = {!! json_encode(['select' => __('ledger_coa.select_parent_account'), 'optional' => __('ledger_coa.optional')], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};
    var fr = @json(app()->getLocale() === 'fr');

    $('#parent_id').select2({ placeholder: T.select, allowClear: true });

    // Auto-suggest normal balance based on account type
    $('#account_type').change(function () {
        var suggested = '';
        switch ($(this).val()) {
            case 'asset':
            case 'expense':
                suggested = 'debit';
                break;
            case 'liability':
            case 'equity':
            case 'revenue':
                suggested = 'credit';
                break;
        }
        if (suggested) {
            $('#normal_balance').val(suggested);
        }
    });

    // Filter parent accounts by class number
    $('#class_number').change(function () {
        var cls = $(this).val();
        if (!cls) { return; }
        $.ajax({
            url: @json(route('ledger.chart-of-accounts.by-class')),
            type: 'GET',
            data: { class_number: cls },
            success: function (accounts) {
                var parent = $('#parent_id');
                parent.empty();
                parent.append($('<option>').val('').text(T.select + ' (' + T.optional + ')'));
                accounts.forEach(function (a) {
                    var name = fr && a.account_name_fr ? a.account_name_fr : a.account_name;
                    parent.append($('<option>').val(a.id).text(a.account_code + ' - ' + name));
                });
                parent.trigger('change');
            }
        });
    });
});
</script>
@endsection
