@extends('layouts.app')
@section('title', __('ledger_je.add_journal_entry'))

@section('content')
@include('ledger.partials.style')
@include('ledger.journal-entries.partials.style')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ __('ledger_je.journal_entries') }}</h1>
</section>
<section class="content">
    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">{{ __('ledger_je.add_journal_entry') }}</h3>
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

            @include('ledger.journal-entries.partials.rules')

            <form action="{{ route('ledger.journal-entries.store') }}" method="POST" id="journalEntryForm">
                @csrf
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group @error('entry_date') has-error @enderror">
                            <label for="entry_date">{{ __('ledger_je.entry_date') }} <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="entry_date" name="entry_date" value="{{ old('entry_date', date('Y-m-d')) }}" required>
                            @error('entry_date')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group @error('journal_type') has-error @enderror">
                            <label for="journal_type">{{ __('ledger_je.journal_type') }} <span class="text-danger">*</span></label>
                            <select class="form-control" id="journal_type" name="journal_type" required>
                                <option value="">{{ __('ledger_je.select_journal_type') }}</option>
                                @foreach(\App\Http\Controllers\Ledger\JournalEntryController::TYPES as $t)
                                <option value="{{ $t }}" {{ old('journal_type') == $t ? 'selected' : '' }}>{{ \App\Http\Controllers\Ledger\JournalEntryController::typeLabel($t) }}</option>
                                @endforeach
                            </select>
                            @error('journal_type')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group @error('reference_number') has-error @enderror">
                            <label for="reference_number">{{ __('ledger_je.reference_number') }}</label>
                            <input type="text" class="form-control" id="reference_number" name="reference_number" value="{{ old('reference_number') }}"
                                   placeholder="{{ __('ledger_je.reference_placeholder') }}">
                            @error('reference_number')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group @error('fiscal_year_id') has-error @enderror">
                            <label for="fiscal_year_id">{{ __('ledger_je.fiscal_year') }}</label>
                            <select class="form-control" id="fiscal_year_id" name="fiscal_year_id" required>
                                @foreach($fiscalYears as $year)
                                <option value="{{ $year->id }}" {{ old('fiscal_year_id') ? (old('fiscal_year_id') == $year->id ? 'selected' : '') : ($activeFiscalYear && $activeFiscalYear->id == $year->id ? 'selected' : '') }}>
                                    {{ $year->name }} @if($year->is_active) ({{ __('ledger_je.active') }}) @endif
                                </option>
                                @endforeach
                            </select>
                            @error('fiscal_year_id')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="form-group @error('description') has-error @enderror">
                            <label for="description">{{ __('ledger_je.description') }} <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="2" required>{{ old('description') }}</textarea>
                            @error('description')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </div>

                @include('ledger.journal-entries.partials.lines')

                <div class="box-footer" style="padding-left: 0; margin-top: 15px;">
                    <button type="submit" class="btn btn-primary" id="save-btn"><i class="fa fa-save"></i> {{ __('ledger_je.save_draft') }}</button>
                    @can('journal-entry-post')
                    <button type="button" class="btn btn-success" id="save-post-btn"
                            data-review-url="{{ route('ledger.journal-entries.preview-post', 'new') }}"
                            data-review-form="journalEntryForm" data-confirm-form="journalEntryForm"
                            data-confirm-label="{{ __('ledger_je.save_and_post') }}"
                            data-confirm-check="{{ __('ledger_je.post_check') }}"><i class="fa fa-check"></i> {{ __('ledger_je.save_and_post') }}</button>
                    @endcan
                    <a href="{{ route('ledger.journal-entries.index') }}" class="btn btn-default"><i class="fa fa-times"></i> {{ __('ledger_je.cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
    @include('ledger.partials.review_modal')
</section>
@endsection

@section('javascript')
@include('ledger.partials.review_script')
@include('ledger.journal-entries.partials.lines_script', [
    'initialLines' => old('lines', [['debit' => 0, 'credit' => 0], ['debit' => 0, 'credit' => 0]]),
])
<script>
$(function () {
    // "Save & Post": the form is checked, then reviewed; confirming saves and posts it.
    var $form = $('#journalEntryForm');
    $('#save-post-btn').on('click', function (e) {
        if (!window.jeCheckForm(true)) { e.preventDefault(); e.stopImmediatePropagation(); e.stopPropagation(); return false; }
        if (!$form.find('input[name="post_immediately"]').length) {
            $form.append('<input type="hidden" name="post_immediately" value="1">');
        }
    });
    $('#save-btn').on('click', function () { $form.find('input[name="post_immediately"]').remove(); });
    $('#reviewModal').on('hidden.bs.modal', function () { $form.find('input[name="post_immediately"]').remove(); });
});
</script>
@endsection
