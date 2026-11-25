@extends('layouts.app')
@section('title', __('ledger_je.edit_journal_entry'))

@section('content')
@include('ledger.partials.style')
@include('ledger.journal-entries.partials.style')
@php($posted = $entry->is_posted)
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ __('ledger_je.journal_entries') }}</h1>
</section>
<section class="content">
    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">{{ __('ledger_je.edit_journal_entry') }} - {{ $entry->entry_number }}</h3>
            <div class="box-tools">
                <a href="{{ route('ledger.journal-entries.index') }}" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> {{ __('ledger_je.back') }}</a>
            </div>
        </div>

        <div class="box-body">
            @if ($errors->any())
            <div class="alert alert-danger alert-dismissible">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                <h4><i class="icon fa fa-ban"></i> {{ __('ledger_je.validation_errors') }}</h4>
                <ul style="margin-bottom: 0;">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
            @endif

            @if(!$posted)
            @include('ledger.journal-entries.partials.rules')
            @endif

            <form action="{{ route('ledger.journal-entries.update', $entry->id) }}" method="POST" id="journalEntryForm">
                @csrf
                @method('PUT')
                @if($posted)
                <div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> {{ __('ledger_je.this_entry_is_posted') }}. {{ __('ledger_je.unpost_to_edit') }}.</div>
                @endif

                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>{{ __('ledger_je.entry_number') }}</label>
                            <input type="text" class="form-control" value="{{ $entry->entry_number }}" readonly>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group @error('entry_date') has-error @enderror">
                            <label for="entry_date">{{ __('ledger_je.entry_date') }} <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="entry_date" name="entry_date"
                                   value="{{ old('entry_date', $entry->entry_date->format('Y-m-d')) }}" {{ $posted ? 'readonly' : '' }} required>
                            @error('entry_date')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group @error('journal_type') has-error @enderror">
                            <label for="journal_type">{{ __('ledger_je.journal_type') }} <span class="text-danger">*</span></label>
                            <select class="form-control" id="journal_type" name="journal_type" {{ $posted ? 'disabled' : '' }} required>
                                @foreach(\App\Http\Controllers\Ledger\JournalEntryController::TYPES as $t)
                                <option value="{{ $t }}" {{ old('journal_type', $entry->journal_type) == $t ? 'selected' : '' }}>{{ \App\Http\Controllers\Ledger\JournalEntryController::typeLabel($t) }}</option>
                                @endforeach
                                @if(!in_array($entry->journal_type, \App\Http\Controllers\Ledger\JournalEntryController::TYPES))
                                <option value="{{ $entry->journal_type }}" selected>{{ \App\Http\Controllers\Ledger\JournalEntryController::typeLabel($entry->journal_type) }}</option>
                                @endif
                            </select>
                            @if($posted)<input type="hidden" name="journal_type" value="{{ $entry->journal_type }}">@endif
                            @error('journal_type')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group @error('reference_number') has-error @enderror">
                            <label for="reference_number">{{ __('ledger_je.reference_number') }}</label>
                            <input type="text" class="form-control" id="reference_number" name="reference_number"
                                   value="{{ old('reference_number', $entry->reference_number) }}" {{ $posted ? 'readonly' : '' }}>
                            @error('reference_number')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="form-group @error('description') has-error @enderror">
                            <label for="description">{{ __('ledger_je.description') }} <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="2" {{ $posted ? 'readonly' : '' }} required>{{ old('description', $entry->description) }}</textarea>
                            @error('description')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </div>

                @include('ledger.journal-entries.partials.lines', ['posted' => $posted])

                <div class="box-footer" style="padding-left: 0; margin-top: 15px;">
                    @if(!$posted)
                    <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> {{ __('ledger_je.update') }}</button>
                    @endif
                    <a href="{{ route('ledger.journal-entries.index') }}" class="btn btn-default"><i class="fa fa-times"></i> {{ __('ledger_je.cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</section>
@endsection

@section('javascript')
@if(!$entry->is_posted)
@include('ledger.journal-entries.partials.lines_script', [
    'initialLines' => old('lines', $entry->lines->map(fn ($l) => [
        'account_id' => $l->account_id, 'description' => $l->description, 'debit' => (float) $l->debit, 'credit' => (float) $l->credit,
    ])->all()),
])
@endif
@endsection
