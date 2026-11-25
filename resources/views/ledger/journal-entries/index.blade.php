@extends('layouts.app')
@section('title', __('ledger_je.journal_entries'))

@section('content')
@include('ledger.partials.style')
@include('ledger.journal-entries.partials.style')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_je.journal_entries')</h1>
</section>
<section class="content">
    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">@lang('ledger_je.index_heading')</h3>
            <div class="box-tools">
                @can('journal-entry-delete')
                <a href="{{ route('ledger.journal-entries.trash') }}" class="btn btn-warning btn-sm" style="margin-right: 6px;">
                    <i class="fa fa-trash"></i> @lang('ledger_je.trash') / @lang('ledger_je.deleted_entries')
                </a>
                @endcan
                @can('journal-entry-create')
                <a href="{{ route('ledger.journal-entries.create') }}" class="btn btn-primary btn-sm">
                    <i class="fa fa-plus"></i> @lang('ledger_je.add_journal_entry')
                </a>
                @endcan
            </div>
        </div>
        <div class="box-body">
            {{-- Filters --}}
            <form action="{{ route('ledger.journal-entries.index') }}" method="GET">
                <div class="row">
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="fiscal_year">@lang('ledger_je.fiscal_year')</label>
                            <select name="fiscal_year" id="fiscal_year" class="form-control input-sm">
                                <option value="">@lang('ledger_je.all')</option>
                                @foreach($fiscalYears as $year)
                                <option value="{{ $year->id }}" {{ request('fiscal_year') == $year->id ? 'selected' : '' }}>{{ $year->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="journal_type">@lang('ledger_je.journal_type')</label>
                            <select name="journal_type" id="journal_type" class="form-control input-sm">
                                <option value="">@lang('ledger_je.all')</option>
                                @foreach(\App\Http\Controllers\Ledger\JournalEntryController::TYPES as $t)
                                <option value="{{ $t }}" {{ request('journal_type') == $t ? 'selected' : '' }}>{{ \App\Http\Controllers\Ledger\JournalEntryController::typeLabel($t) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="is_posted">@lang('ledger_je.status_title')</label>
                            <select name="is_posted" id="is_posted" class="form-control input-sm">
                                <option value="">@lang('ledger_je.all')</option>
                                <option value="1" {{ request('is_posted') === '1' ? 'selected' : '' }}>@lang('ledger_je.posted')</option>
                                <option value="0" {{ request('is_posted') === '0' ? 'selected' : '' }}>@lang('ledger_je.draft')</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="start_date">@lang('ledger_je.start_date')</label>
                            <input type="date" name="start_date" id="start_date" class="form-control input-sm" value="{{ request('start_date') }}">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label for="end_date">@lang('ledger_je.end_date')</label>
                            <input type="date" name="end_date" id="end_date" class="form-control input-sm" value="{{ request('end_date') }}">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary btn-sm btn-block"><i class="fa fa-filter"></i> @lang('ledger_je.filter')</button>
                        </div>
                    </div>
                </div>
            </form>

            {{-- Entries --}}
            <div class="table-responsive">
                <table class="table table-bordered table-hover ap-table">
                    <thead>
                        <tr>
                            <th width="5%">@lang('ledger_je.sl')</th>
                            <th width="12%">@lang('ledger_je.entry_number')</th>
                            <th width="10%">@lang('ledger_je.date')</th>
                            <th width="10%">@lang('ledger_je.journal_type')</th>
                            <th>@lang('ledger_je.description')</th>
                            <th width="10%" class="ap-num">@lang('ledger_je.total_debit')</th>
                            <th width="10%" class="ap-num">@lang('ledger_je.total_credit')</th>
                            <th width="8%">@lang('ledger_je.status_title')</th>
                            <th width="15%">@lang('ledger_je.action')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($entries as $key => $entry)
                        @php($locked = \App\Http\Controllers\Ledger\JournalEntryController::lockReason($entry))
                        <tr>
                            <td>{{ $entries->firstItem() + $key }}</td>
                            <td><strong>{{ $entry->entry_number }}</strong></td>
                            <td>{{ $entry->entry_date->format('d/m/Y') }}</td>
                            <td><span class="label label-info">{{ \App\Http\Controllers\Ledger\JournalEntryController::typeLabel($entry->journal_type) }}</span></td>
                            <td>{{ $entry->description }}</td>
                            <td class="ap-num"><strong>{{ \App\Ledger\Ledger::money($entry->total_debit) }}</strong></td>
                            <td class="ap-num"><strong>{{ \App\Ledger\Ledger::money($entry->total_credit) }}</strong></td>
                            <td>@include('ledger.journal-entries.partials.status', ['entry' => $entry])</td>
                            <td style="white-space: nowrap;">
                                @can('journal-entry-view')
                                <a href="{{ route('ledger.journal-entries.show', $entry->id) }}" class="btn btn-info btn-xs" title="{{ __('ledger_je.view') }}"><i class="fa fa-eye"></i></a>
                                @endcan

                                @can('journal-entry-edit')
                                @if(!$entry->is_posted && !$locked)
                                <a href="{{ route('ledger.journal-entries.edit', $entry->id) }}" class="btn btn-primary btn-xs" title="{{ __('ledger_je.edit') }}"><i class="fa fa-edit"></i></a>
                                @endif
                                @endcan

                                @if(!$entry->is_posted)
                                @can('journal-entry-post')
                                <form action="{{ route('ledger.journal-entries.post', $entry->id) }}" method="POST" id="post-form-{{ $entry->id }}" style="display: inline-block;">
                                    @csrf
                                    <button type="button" class="btn btn-success btn-xs" title="{{ __('ledger_je.post_entry') }}"
                                            data-review-url="{{ route('ledger.journal-entries.preview-post', $entry->id) }}"
                                            data-confirm-form="post-form-{{ $entry->id }}"
                                            data-confirm-label="{{ __('ledger_je.post_entry') }}"
                                            data-confirm-check="{{ __('ledger_je.post_check') }}"><i class="fa fa-check"></i></button>
                                </form>
                                @endcan
                                @elseif($locked)
                                <span class="btn btn-default btn-xs disabled" title="{{ $locked }}"><i class="fa fa-lock"></i></span>
                                @elseif(!$entry->is_reversed)
                                @can('journal-entry-unpost')
                                <form action="{{ route('ledger.journal-entries.unpost', $entry->id) }}" method="POST" data-review="{{ __('ledger_je.confirm_unpost_entry') }}" data-review-label="{{ __('ledger_je.unpost_entry') }}" data-review-class="btn-warning" style="display: inline-block;">
                                    @csrf
                                    <button type="submit" class="btn btn-warning btn-xs" title="{{ __('ledger_je.unpost_entry') }}"><i class="fa fa-undo"></i></button>
                                </form>
                                @endcan
                                @endif

                                @can('journal-entry-create')
                                <form action="{{ route('ledger.journal-entries.duplicate', $entry->id) }}" method="POST" style="display: inline-block;">
                                    @csrf
                                    <button type="submit" class="btn btn-default btn-xs" title="{{ __('ledger_je.duplicate') }}" onclick="return confirm({{ json_encode(__('ledger_je.confirm_duplicate_entry')) }})"><i class="fa fa-copy"></i></button>
                                </form>
                                @endcan

                                @can('journal-entry-delete')
                                @if(!$entry->is_posted && !$entry->is_system_generated)
                                <form action="{{ route('ledger.journal-entries.destroy', $entry->id) }}" method="POST" style="display: inline-block;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-xs" title="{{ __('ledger_je.delete') }}" onclick="return confirm({{ json_encode(__('ledger_je.are_you_sure')) }})"><i class="fa fa-trash"></i></button>
                                </form>
                                @endif
                                @endcan
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="9" class="text-center">@lang('ledger_je.no_data_found')</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>{{ $entries->appends(request()->query())->links() }}</div>
        </div>
    </div>
    @include('ledger.partials.review_modal')
</section>
@endsection

@section('javascript')
@include('ledger.partials.review_script')
@endsection
