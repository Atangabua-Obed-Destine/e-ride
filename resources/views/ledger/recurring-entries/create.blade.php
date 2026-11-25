@extends('layouts.app')
@section('title', __('ledger_periodend.add_recurring_entry'))

@section('content')
@include('ledger.partials.style')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_periodend.add_recurring_entry')</h1>
</section>
<section class="content">
    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">@lang('ledger_periodend.add_recurring_entry')</h3>
            <div class="box-tools">
                <a href="{{ route('ledger.recurring-entries.index') }}" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> @lang('ledger_periodend.back')</a>
            </div>
        </div>
        <form action="{{ route('ledger.recurring-entries.store') }}" method="POST" id="recurringEntryForm">
            @csrf
            <div class="box-body">
                @include('ledger.recurring-entries._form', ['recurringEntry' => null])
            </div>
            <div class="box-footer">
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> @lang('ledger_periodend.save')</button>
                <a href="{{ route('ledger.recurring-entries.index') }}" class="btn btn-default"><i class="fa fa-times"></i> @lang('ledger_periodend.cancel')</a>
            </div>
        </form>
    </div>
</section>
@endsection
