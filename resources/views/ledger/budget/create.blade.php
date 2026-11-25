@extends('layouts.app')
@section('title', __('ledger_budget.text_create_budget'))

@section('content')
@include('ledger.partials.style')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_budget.text_create_budget')</h1>
</section>
<section class="content">
    @include('ledger.budget.partials.messages')
    <div class="box box-solid">
        <div class="box-header with-border"><h3 class="box-title">@lang('ledger_budget.text_create_budget')</h3></div>
        <div class="box-body">
            <form method="post" action="{{ route('ledger.budget.store') }}">
                @csrf
                @include('ledger.budget.partials.form')
                <button type="submit" class="btn btn-primary">@lang('ledger_budget.btn_save')</button>
                <a href="{{ isset($row) ? route('ledger.budget.show', $row->id) : route('ledger.budget.index') }}" class="btn btn-default">@lang('ledger_budget.btn_cancel')</a>
            </form>
        </div>
    </div>
</section>
@endsection

@section('javascript')
@include('ledger.budget.partials.form_script')
@endsection
