@extends('layouts.app')
@section('title', __('ledger_periodend.year_end_closing'))

@section('content')
@include('ledger.partials.style')
@php use App\Ledger\Ledger; @endphp
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_periodend.year_end_closing') <small>(@lang('ledger_periodend.cloture_exercice'))</small></h1>
</section>
<section class="content">
    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">@lang('ledger_periodend.year_end_closing')</h3>
            <div class="box-tools">
                @can('year-end-closing-create')
                <a href="{{ route('ledger.year-end-closing.create') }}" class="btn btn-primary btn-sm"><i class="fa fa-plus"></i> @lang('ledger_periodend.new_year_end_closing')</a>
                @endcan
            </div>
        </div>
        <div class="box-body">
            @if($activeFiscalYear)
            <div class="ap-note" style="margin-bottom: 14px;">
                <i class="fa fa-info-circle"></i>
                <strong>@lang('ledger_periodend.active_fiscal_year'):</strong> {{ $activeFiscalYear->name }}
                ({{ $activeFiscalYear->start_date->format('d/m/Y') }} - {{ $activeFiscalYear->end_date->format('d/m/Y') }})
            </div>
            @endif

            <div class="table-responsive">
                <table class="table table-bordered table-hover ap-table">
                    <thead>
                        <tr>
                            <th width="5%">@lang('ledger_periodend.sl')</th>
                            <th>@lang('ledger_periodend.fiscal_year')</th>
                            <th width="12%">@lang('ledger_periodend.closing_date')</th>
                            <th width="12%">@lang('ledger_periodend.checklist_progress')</th>
                            <th width="12%" class="ap-num">@lang('ledger_periodend.net_income')</th>
                            <th width="12%">@lang('ledger_periodend.status')</th>
                            <th width="15%">@lang('ledger_periodend.action')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($closings as $key => $closing)
                        @php
                            $completed = $closing->getChecklistCompletedCount();
                            $total = count(\App\Ledger\Models\YearEndClosing::CHECKLIST_ITEMS);
                            $percentage = $closing->getChecklistCompletionPercentage();
                        @endphp
                        <tr>
                            <td>{{ $closings->firstItem() + $key }}</td>
                            <td>
                                <strong>{{ $closing->fiscalYear->name ?? 'N/A' }}</strong>
                                <br><small class="text-muted">{{ $closing->fiscalYear ? $closing->fiscalYear->start_date->format('d/m/Y').' - '.$closing->fiscalYear->end_date->format('d/m/Y') : '' }}</small>
                            </td>
                            <td>{{ $closing->closing_date->format('d/m/Y') }}</td>
                            <td>
                                <div class="progress" style="height: 18px; margin-bottom: 0;">
                                    <div class="progress-bar progress-bar-{{ $percentage == 100 ? 'success' : ($percentage >= 50 ? 'warning' : 'danger') }}" role="progressbar" style="width: {{ max($percentage, 12) }}%; color: #fff;">{{ $completed }}/{{ $total }}</div>
                                </div>
                            </td>
                            <td class="ap-num"><strong class="{{ (float) $closing->net_income >= 0 ? 'text-success' : 'text-danger' }}">{{ Ledger::money($closing->net_income) }}</strong></td>
                            <td><span class="ap-chip {{ $closing->getStatusChip() }}">{{ $closing->getStatusLabel() }}</span></td>
                            <td class="text-nowrap">
                                <a href="{{ route('ledger.year-end-closing.show', $closing->id) }}" class="btn btn-info btn-xs" title="@lang('ledger_periodend.view')"><i class="fa fa-eye"></i></a>

                                @can('year-end-closing-edit')
                                @if($closing->status == 'draft')
                                <form action="{{ route('ledger.year-end-closing.start', $closing->id) }}" method="POST" style="display: inline-block;">
                                    @csrf
                                    <button type="submit" class="btn btn-warning btn-xs" title="@lang('ledger_periodend.start_closing')"><i class="fa fa-play"></i></button>
                                </form>
                                @endif
                                @if($closing->status == 'in_progress' && $percentage == 100)
                                <form data-review="@lang('ledger_periodend.generate_check')" data-review-label="@lang('ledger_periodend.generate_the_entries')" action="{{ route('ledger.year-end-closing.generate-entries', $closing->id) }}" method="POST" style="display: inline-block;">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-xs" title="@lang('ledger_periodend.generate_closing_entries')"><i class="fa fa-cogs"></i></button>
                                </form>
                                @endif
                                @endcan

                                @can('year-end-closing-approve')
                                @if($closing->status == 'pending_approval')
                                <form data-review="@lang('ledger_periodend.approve_check')" data-review-label="@lang('ledger_periodend.approve_the_closing')" data-review-class="btn-danger" action="{{ route('ledger.year-end-closing.approve', $closing->id) }}" method="POST" style="display: inline-block;">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-xs" title="@lang('ledger_periodend.approve')"><i class="fa fa-check"></i></button>
                                </form>
                                @endif
                                @if($closing->status == 'completed')
                                <form method="post" action="{{ route('ledger.year-end-closing.reverse', $closing->id) }}" id="reverse-closing-{{ $closing->id }}" class="hidden">@csrf</form>
                                <button type="button" class="btn btn-danger btn-xs" title="@lang('ledger_periodend.reverse')"
                                        data-review-url="{{ route('ledger.year-end-closing.preview-reverse', $closing->id) }}"
                                        data-confirm-form="reverse-closing-{{ $closing->id }}" data-confirm-label="@lang('ledger_periodend.reverse_the_closing')" data-confirm-class="btn-danger"
                                        data-needs-reason="1" data-confirm-check="@lang('ledger_periodend.reverse_check')"><i class="fa fa-undo"></i></button>
                                @endif
                                @endcan

                                @can('year-end-closing-delete')
                                @if($closing->status == 'draft')
                                <form action="{{ route('ledger.year-end-closing.destroy', $closing->id) }}" method="POST" style="display: inline-block;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-xs" onclick="return confirm({{ json_encode(__('ledger_periodend.are_you_sure')) }})" title="@lang('ledger_periodend.delete')"><i class="fa fa-trash"></i></button>
                                </form>
                                @endif
                                @endcan
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="text-center ap-muted">@lang('ledger_periodend.no_data_found')</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div>{{ $closings->links() }}</div>
        </div>
    </div>
    @include('ledger.partials.review_modal')
</section>
@endsection

@section('javascript')
@include('ledger.partials.review_script')
@endsection
