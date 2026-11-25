@extends('layouts.app')
@section('title', __('ledger_periodend.recurring_entries'))

@section('content')
@include('ledger.partials.style')
@php use App\Ledger\Ledger; @endphp
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_periodend.recurring_entries') <small>(@lang('ledger_periodend.ecritures_recurrentes'))</small></h1>
</section>
<section class="content">
    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">@lang('ledger_periodend.recurring_entries')</h3>
            <div class="box-tools ap-toolbar">
                @can('recurring-entry-process')
                <form data-review="@lang('ledger_periodend.process_all_check')" data-review-label="@lang('ledger_periodend.post_them')" action="{{ route('ledger.recurring-entries.process-all') }}" method="POST" style="display: inline-block;">
                    @csrf
                    <button type="submit" class="btn btn-success btn-sm"><i class="fa fa-play"></i> @lang('ledger_periodend.process_all_due')</button>
                </form>
                @endcan
                @can('recurring-entry-create')
                <a href="{{ route('ledger.recurring-entries.create') }}" class="btn btn-primary btn-sm"><i class="fa fa-plus"></i> @lang('ledger_periodend.add_recurring_entry')</a>
                @endcan
            </div>
        </div>
        <div class="box-body">
            {{-- Statistics --}}
            <div class="row" style="margin-bottom: 10px;">
                @foreach([
                    ['total', 'total_templates', 'fa-redo', $statistics['total'] ?? 0],
                    ['active', 'active_templates', 'fa-check-circle', $statistics['active'] ?? 0],
                    ['due_today', 'due_today', 'fa-calendar-day', $statistics['due_today'] ?? 0],
                    ['monthly_total', 'monthly_total', 'fa-coins', Ledger::money($statistics['monthly_total'] ?? 0)],
                ] as [$k, $label, $icon, $value])
                <div class="col-md-3 col-sm-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-aqua"><i class="fa {{ $icon }}"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">@lang('ledger_periodend.'.$label)</span>
                            <span class="info-box-number">{{ $value }}</span>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            {{-- Filters --}}
            <form action="{{ route('ledger.recurring-entries.index') }}" method="GET" class="ap-filters" style="margin-bottom: 12px;">
                <div class="form-group">
                    <label for="frequency">@lang('ledger_periodend.frequency')</label>
                    <select name="frequency" id="frequency" class="form-control input-sm">
                        <option value="">@lang('ledger_periodend.all')</option>
                        @foreach(['daily' => 'daily', 'weekly' => 'weekly', 'monthly' => 'monthly', 'quarterly' => 'quarterly', 'annually' => 'yearly'] as $v => $label)
                        <option value="{{ $v }}" @selected(request('frequency') == $v)>@lang('ledger_periodend.'.$label)</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="status">@lang('ledger_periodend.status')</label>
                    <select name="status" id="status" class="form-control input-sm">
                        <option value="">@lang('ledger_periodend.all')</option>
                        @foreach(['active', 'paused', 'expired'] as $s)
                        <option value="{{ $s }}" @selected(request('status') == $s)>@lang('ledger_periodend.'.$s)</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> @lang('ledger_periodend.filter')</button>
                </div>
            </form>

            {{-- Templates --}}
            <div class="table-responsive">
                <table class="table table-bordered table-hover ap-table">
                    <thead>
                        <tr>
                            <th width="5%">@lang('ledger_periodend.sl')</th>
                            <th>@lang('ledger_periodend.template_name')</th>
                            <th width="10%">@lang('ledger_periodend.frequency')</th>
                            <th width="10%">@lang('ledger_periodend.next_run')</th>
                            <th width="10%" class="ap-num">@lang('ledger_periodend.amount')</th>
                            <th width="8%">@lang('ledger_periodend.occurrences')</th>
                            <th width="8%">@lang('ledger_periodend.status')</th>
                            <th width="17%">@lang('ledger_periodend.action')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($entries as $key => $entry)
                        <tr>
                            <td>{{ $entries->firstItem() + $key }}</td>
                            <td>
                                <strong>{{ $entry->template_name }}</strong> <span class="ap-muted">{{ $entry->template_reference }}</span>
                                <br><small class="text-muted">{{ \Illuminate\Support\Str::limit($entry->description, 50) }}</small>
                            </td>
                            <td><span class="label label-info">{{ $entry->getFrequencyLabel() }}</span></td>
                            <td>
                                @if($entry->next_run_date)
                                {{ $entry->next_run_date->format('d/m/Y') }}
                                @if($entry->status === 'active' && $entry->next_run_date->lte(now()->startOfDay()))
                                <br><span class="ap-chip overdue">@lang('ledger_periodend.overdue')</span>
                                @endif
                                @else
                                -
                                @endif
                            </td>
                            <td class="ap-num"><strong>{{ Ledger::money($entry->lines->sum('debit')) }}</strong></td>
                            <td class="text-center">{{ $entry->occurrences_created }}@if($entry->total_occurrences) / {{ $entry->total_occurrences }}@endif</td>
                            <td>
                                @if($entry->status === 'active')
                                <span class="ap-chip paid">@lang('ledger_periodend.active')</span>
                                @elseif($entry->status === 'paused')
                                <span class="ap-chip part_paid">@lang('ledger_periodend.paused')</span>
                                @else
                                <span class="ap-chip void">@lang('ledger_periodend.expired')</span>
                                @endif
                            </td>
                            <td class="text-nowrap">
                                <a href="{{ route('ledger.recurring-entries.show', $entry->id) }}" class="btn btn-info btn-xs" title="@lang('ledger_periodend.view')"><i class="fa fa-eye"></i></a>
                                @can('recurring-entry-edit')
                                <a href="{{ route('ledger.recurring-entries.edit', $entry->id) }}" class="btn btn-primary btn-xs" title="@lang('ledger_periodend.edit')"><i class="fa fa-edit"></i></a>
                                @endcan

                                @can('recurring-entry-process')
                                @if($entry->status === 'active')
                                <form data-review="@lang('ledger_periodend.process_check')" data-review-label="@lang('ledger_periodend.post_it')" action="{{ route('ledger.recurring-entries.process', $entry->id) }}" method="POST" style="display: inline-block;">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-xs" title="@lang('ledger_periodend.process_now')"><i class="fa fa-play"></i></button>
                                </form>
                                @endif
                                @endcan

                                @can('recurring-entry-edit')
                                @if($entry->status === 'active')
                                <form action="{{ route('ledger.recurring-entries.pause', $entry->id) }}" method="POST" style="display: inline-block;">
                                    @csrf
                                    <button type="submit" class="btn btn-warning btn-xs" title="@lang('ledger_periodend.pause')"><i class="fa fa-pause"></i></button>
                                </form>
                                @elseif($entry->status === 'paused')
                                <form action="{{ route('ledger.recurring-entries.resume', $entry->id) }}" method="POST" style="display: inline-block;">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-xs" title="@lang('ledger_periodend.resume')"><i class="fa fa-play-circle"></i></button>
                                </form>
                                @endif
                                @endcan

                                @can('recurring-entry-create')
                                <form action="{{ route('ledger.recurring-entries.duplicate', $entry->id) }}" method="POST" style="display: inline-block;">
                                    @csrf
                                    <button type="submit" class="btn btn-default btn-xs" title="@lang('ledger_periodend.duplicate')"><i class="fa fa-copy"></i></button>
                                </form>
                                @endcan

                                @can('recurring-entry-delete')
                                <form action="{{ route('ledger.recurring-entries.destroy', $entry->id) }}" method="POST" style="display: inline-block;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-xs" onclick="return confirm({{ json_encode(__('ledger_periodend.are_you_sure')) }})" title="@lang('ledger_periodend.delete')"><i class="fa fa-trash"></i></button>
                                </form>
                                @endcan
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="8" class="text-center ap-muted">@lang('ledger_periodend.no_data_found')</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div>{{ $entries->links() }}</div>
        </div>
    </div>
    @include('ledger.partials.review_modal')
</section>
@endsection

@section('javascript')
@include('ledger.partials.review_script')
@endsection
