@extends('layouts.app')
@section('title', __('ledger_periodend.recurring_entry_details'))

@section('content')
@include('ledger.partials.style')
@php use App\Ledger\Ledger; use App\Ledger\Services\Preview; $e = $recurringEntry; @endphp
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_periodend.recurring_entry_details') <small>{{ $e->name }}</small></h1>
</section>
<section class="content">
    <div class="row">
        <div class="col-md-8">
            {{-- Entry information --}}
            <div class="box box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title">@lang('ledger_periodend.entry_information') <span class="ap-muted">{{ $e->template_reference }}</span></h3>
                    <div class="box-tools">
                        @can('recurring-entry-edit')
                        <a href="{{ route('ledger.recurring-entries.edit', $e->id) }}" class="btn btn-warning btn-sm"><i class="fa fa-edit"></i> @lang('ledger_periodend.edit')</a>
                        @endcan
                        <a href="{{ route('ledger.recurring-entries.index') }}" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> @lang('ledger_periodend.back')</a>
                    </div>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-condensed ap-table">
                                <tr><th style="width: 40%;">@lang('ledger_periodend.name'):</th><td>{{ $e->name }}</td></tr>
                                <tr><th>@lang('ledger_periodend.journal_type'):</th><td>{{ \App\Http\Controllers\Ledger\JournalEntryController::typeLabel($e->journal_type) }}</td></tr>
                                <tr><th>@lang('ledger_periodend.frequency'):</th><td><span class="label label-info">{{ $e->getFrequencyLabel() }}</span></td></tr>
                                <tr><th>@lang('ledger_periodend.status'):</th><td>
                                    @if($e->status === 'active')<span class="ap-chip paid">@lang('ledger_periodend.active')</span>
                                    @elseif($e->status === 'paused')<span class="ap-chip part_paid">@lang('ledger_periodend.paused')</span>
                                    @else<span class="ap-chip void">@lang('ledger_periodend.expired')</span>@endif
                                </td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-condensed ap-table">
                                <tr><th style="width: 40%;">@lang('ledger_periodend.next_run_date'):</th><td>
                                    @if($e->next_run_date)
                                    {{ $e->next_run_date->format('d/m/Y') }}
                                    @if($e->status === 'active' && $e->next_run_date->lte(now()->startOfDay()))<span class="ap-chip overdue">@lang('ledger_periodend.overdue')</span>@endif
                                    @else - @endif
                                </td></tr>
                                <tr><th>@lang('ledger_periodend.last_run_date'):</th><td>{{ $e->last_run_date ? $e->last_run_date->format('d/m/Y') : '-' }}</td></tr>
                                <tr><th>@lang('ledger_periodend.end_date'):</th><td>{{ $e->end_date ? $e->end_date->format('d/m/Y') : __('ledger_periodend.no_end_date') }}</td></tr>
                                <tr><th>@lang('ledger_periodend.times_executed'):</th><td>{{ $e->occurrences_created }}</td></tr>
                            </table>
                        </div>
                    </div>
                    @if($e->description)
                    <div><strong>@lang('ledger_periodend.description'):</strong><p class="text-muted">{{ $e->description }}</p></div>
                    @endif
                </div>
            </div>

            {{-- The template's lines --}}
            <div class="box box-solid">
                <div class="box-header with-border"><h3 class="box-title">@lang('ledger_periodend.journal_entry_template')</h3></div>
                <div class="box-body table-responsive">
                    <table class="table table-hover ap-table">
                        <thead>
                            <tr>
                                <th>@lang('ledger_periodend.account_code')</th>
                                <th>@lang('ledger_periodend.account_name')</th>
                                <th class="ap-num">@lang('ledger_periodend.debit')</th>
                                <th class="ap-num">@lang('ledger_periodend.credit')</th>
                                <th>@lang('ledger_periodend.description')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($e->lines as $line)
                            <tr>
                                <td>{{ $line->account->account_code ?? '-' }}</td>
                                <td>{{ $line->account ? Preview::accountName($line->account) : '-' }}</td>
                                <td class="ap-num">{{ $line->debit > 0 ? Ledger::money($line->debit) : '' }}</td>
                                <td class="ap-num">{{ $line->credit > 0 ? Ledger::money($line->credit) : '' }}</td>
                                <td>{{ $line->description ?? '-' }}</td>
                            </tr>
                            @endforeach
                            <tr class="ap-total-row">
                                <td colspan="2">@lang('ledger_periodend.total')
                                    @if($e->isBalanced())<span class="ap-chip paid">@lang('ledger.balanced')</span>@else<span class="ap-chip unpaid">@lang('ledger.not_balanced')</span>@endif</td>
                                <td class="ap-num">{{ Ledger::money($e->lines->sum('debit')) }}</td>
                                <td class="ap-num">{{ Ledger::money($e->lines->sum('credit')) }}</td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Recent executions --}}
            <div class="box box-solid">
                <div class="box-header with-border"><h3 class="box-title">@lang('ledger_periodend.recent_executions')</h3></div>
                <div class="box-body table-responsive">
                    <table class="table table-hover ap-table">
                        <thead>
                            <tr>
                                <th>@lang('ledger_periodend.date')</th>
                                <th>@lang('ledger_periodend.entry')</th>
                                <th>@lang('ledger_periodend.reference')</th>
                                <th class="ap-num">@lang('ledger_periodend.amount')</th>
                                <th>@lang('ledger_periodend.status')</th>
                                <th>@lang('ledger_periodend.actions')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($e->journalEntries as $entry)
                            <tr>
                                <td>{{ $entry->entry_date->format('d/m/Y') }}</td>
                                <td>{{ $entry->entry_number }}</td>
                                <td>{{ $entry->reference_number }}</td>
                                <td class="ap-num">{{ Ledger::money($entry->lines->sum('debit')) }}</td>
                                <td>
                                    @if($entry->is_reversed)<span class="ap-chip void">@lang('ledger_periodend.reversed')</span>
                                    @elseif($entry->is_posted)<span class="ap-chip posted">@lang('ledger_periodend.posted')</span>
                                    @else<span class="ap-chip draft">@lang('ledger_periodend.draft')</span>@endif
                                </td>
                                <td><a href="{{ route('ledger.journal-entries.show', $entry->id) }}" class="btn btn-xs btn-info" title="@lang('ledger_periodend.view')"><i class="fa fa-eye"></i></a></td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center ap-muted">@lang('ledger_periodend.no_executions_yet')</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            {{-- Actions --}}
            <div class="box box-solid">
                <div class="box-header with-border"><h3 class="box-title">@lang('ledger_periodend.actions')</h3></div>
                <div class="box-body">
                    @if($e->status === 'active')
                    @can('recurring-entry-process')
                    <form data-review="@lang('ledger_periodend.process_check')" data-review-label="@lang('ledger_periodend.post_it')" action="{{ route('ledger.recurring-entries.process', $e->id) }}" method="POST" style="margin-bottom: 8px;">
                        @csrf
                        <button type="submit" class="btn btn-success btn-block"><i class="fa fa-play"></i> @lang('ledger_periodend.process_now')</button>
                    </form>
                    @endcan
                    @can('recurring-entry-edit')
                    <form action="{{ route('ledger.recurring-entries.pause', $e->id) }}" method="POST" style="margin-bottom: 8px;">
                        @csrf
                        <button type="submit" class="btn btn-warning btn-block"><i class="fa fa-pause"></i> @lang('ledger_periodend.pause')</button>
                    </form>
                    <form action="{{ route('ledger.recurring-entries.skip-next', $e->id) }}" method="POST" style="margin-bottom: 8px;">
                        @csrf
                        <button type="submit" class="btn btn-info btn-block"><i class="fa fa-forward"></i> @lang('ledger_periodend.skip_next_run')</button>
                    </form>
                    @endcan
                    @elseif($e->status === 'paused')
                    @can('recurring-entry-edit')
                    <form action="{{ route('ledger.recurring-entries.resume', $e->id) }}" method="POST" style="margin-bottom: 8px;">
                        @csrf
                        <button type="submit" class="btn btn-success btn-block"><i class="fa fa-play"></i> @lang('ledger_periodend.resume')</button>
                    </form>
                    @endcan
                    @endif

                    @can('recurring-entry-create')
                    <form action="{{ route('ledger.recurring-entries.duplicate', $e->id) }}" method="POST" style="margin-bottom: 8px;">
                        @csrf
                        <button type="submit" class="btn btn-default btn-block"><i class="fa fa-copy"></i> @lang('ledger_periodend.duplicate')</button>
                    </form>
                    @endcan

                    @can('recurring-entry-delete')
                    <hr>
                    <form action="{{ route('ledger.recurring-entries.destroy', $e->id) }}" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-block" onclick="return confirm({{ json_encode(__('ledger_periodend.delete_recurring_confirm')) }})"><i class="fa fa-trash"></i> @lang('ledger_periodend.delete')</button>
                    </form>
                    @endcan
                </div>
            </div>

            {{-- Schedule --}}
            <div class="box box-solid">
                <div class="box-header with-border"><h3 class="box-title">@lang('ledger_periodend.schedule_information')</h3></div>
                <div class="box-body">
                    <ul class="list-unstyled">
                        <li style="margin-bottom: 6px;"><i class="fa fa-calendar-alt text-primary"></i> <strong>@lang('ledger_periodend.frequency'):</strong> {{ $e->getFrequencyLabel() }}</li>
                        @if($e->day_of_month)
                        <li style="margin-bottom: 6px;"><i class="fa fa-calendar-day text-info"></i> <strong>@lang('ledger_periodend.day_of_month'):</strong> {{ $e->day_of_month }}</li>
                        @endif
                        <li style="margin-bottom: 6px;"><i class="fa fa-clock text-success"></i> <strong>@lang('ledger_periodend.auto_post'):</strong> {{ $e->auto_post ? __('ledger_periodend.yes') : __('ledger_periodend.no') }}</li>
                        @if($e->occurrences)
                        <li style="margin-bottom: 6px;"><i class="fa fa-hashtag text-warning"></i> <strong>@lang('ledger_periodend.max_occurrences'):</strong> {{ $e->occurrences_created }}/{{ $e->occurrences }}</li>
                        @endif
                    </ul>
                </div>
            </div>

            {{-- Meta --}}
            <div class="box box-solid">
                <div class="box-header with-border"><h3 class="box-title">@lang('ledger_periodend.meta_information')</h3></div>
                <div class="box-body">
                    <small class="text-muted">
                        <p style="margin-bottom: 4px;"><strong>@lang('ledger_periodend.created_by'):</strong> {{ trim(optional($e->creator)->first_name.' '.optional($e->creator)->last_name) ?: '-' }}</p> {{-- E-Ride: first and last name (no user_full_name) --}}
                        <p style="margin-bottom: 4px;"><strong>@lang('ledger_periodend.created_at'):</strong> {{ optional($e->created_at)->format('d/m/Y H:i') }}</p>
                        <p style="margin-bottom: 0;"><strong>@lang('ledger_periodend.last_updated'):</strong> {{ optional($e->updated_at)->format('d/m/Y H:i') }}</p>
                    </small>
                    @if($e->notes)
                    <pre class="ap-muted" style="margin-top: 8px; white-space: pre-wrap;">{{ $e->notes }}</pre>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @include('ledger.partials.review_modal')
</section>
@endsection

@section('javascript')
@include('ledger.partials.review_script')
@endsection
