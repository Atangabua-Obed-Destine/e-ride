@extends('layouts.app')
@section('title', __('ledger_periodend.edit_recurring_entry'))

@section('content')
@include('ledger.partials.style')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_periodend.edit_recurring_entry') <small>{{ $recurringEntry->name }}</small></h1>
</section>
<section class="content">
    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">@lang('ledger_periodend.edit_recurring_entry') <span class="ap-muted">{{ $recurringEntry->template_reference }}</span></h3>
            <div class="box-tools">
                <a href="{{ route('ledger.recurring-entries.show', $recurringEntry->id) }}" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> @lang('ledger_periodend.back')</a>
            </div>
        </div>
        <form action="{{ route('ledger.recurring-entries.update', $recurringEntry->id) }}" method="POST" id="recurringEntryForm">
            @csrf
            @method('PUT')
            <div class="box-body">
                @include('ledger.recurring-entries._form', ['recurringEntry' => $recurringEntry])
            </div>
            <div class="box-footer">
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> @lang('ledger_periodend.save')</button>
                <a href="{{ route('ledger.recurring-entries.show', $recurringEntry->id) }}" class="btn btn-default"><i class="fa fa-times"></i> @lang('ledger_periodend.cancel')</a>
            </div>
        </form>
    </div>
</section>
@endsection
