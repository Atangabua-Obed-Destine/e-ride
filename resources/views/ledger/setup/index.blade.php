@extends('layouts.app')
@section('title', __('ledger.setup_title'))

@section('content')
@include('ledger.partials.style')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger.setup_title')</h1>
</section>
<section class="content">
    <div class="ap-card" style="max-width: 860px;">
        <p style="font-size: 15px;">@lang('ledger.setup_intro')</p>
        <ol style="font-size: 14px; line-height: 1.8;">
            <li>@lang('ledger.setup_step_chart', ['n' => $accounts])</li>
            <li>@lang('ledger.setup_step_year', ['year' => $year])</li>
            <li>@lang('ledger.setup_step_mappings')</li>
            <li>@lang('ledger.setup_step_opening')</li>
        </ol>

        <form id="ledger_setup_form" method="POST" action="{{ route('ledger.setup.store') }}">
            @csrf
            <div class="form-group">
                <label>@lang('ledger.setup_start_q')</label>
                <div class="radio"><label><input type="radio" name="start" value="today" checked> @lang('ledger.setup_start_today', ['date' => \Carbon\Carbon::parse($today)->format('d/m/Y')])</label></div>
                <div class="radio"><label><input type="radio" name="start" value="year"> @lang('ledger.setup_start_year', ['date' => \Carbon\Carbon::parse($yearStart)->format('d/m/Y')])</label></div>
                <p class="help-block">@lang('ledger.setup_start_help')</p>
            </div>
            <button type="button" class="tw-dw-btn tw-dw-btn-primary tw-text-white" id="ledger_setup_btn"
                data-review-url="{{ route('ledger.setup', ['preview' => 1]) }}"
                data-confirm-form="ledger_setup_form"
                data-confirm-label="@lang('ledger.setup_button')"
                data-confirm-check="@lang('ledger.setup_check')">@lang('ledger.setup_button')</button>
        </form>
    </div>
    @include('ledger.partials.review_modal')
</section>
@endsection

@section('javascript')
@include('ledger.partials.review_script')
<script>
// the preview reads the same choice as the form it confirms
$('#ledger_setup_btn').on('mousedown keydown', function () {
    var v = $('#ledger_setup_form input[name=start]:checked').val();
    $(this).attr('data-review-url', @json(route('ledger.setup', ['preview' => 1])) + '&start=' + v);
});
</script>
@endsection
