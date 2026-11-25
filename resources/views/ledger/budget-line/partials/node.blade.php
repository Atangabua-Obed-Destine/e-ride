{{--
    One row of the sheet, plus the lines filed under it (paxhitestbed budget-line/partials/node).
    The drag reads the structure from the markup: data-id on each li.bl-item, and a nested
    ol.bl-children under each heading (always there, so a line can be dropped into an empty heading).
    Expects: $line, $children, $accountsByLine, $usage
--}}
@php
    $children = $children ?? collect();
    $payload = $line->only(['id', 'code', 'name', 'name_fr', 'description', 'section', 'parent_id', 'account_id',
        'expense_category_id', 'location_id', 'sort_order', 'is_header', 'is_local', 'status']);
    $inUse = $usage[$line->id] ?? null;
    $targeted = ! $line->is_header && ! empty($accountsByLine[$line->id]);
@endphp
<li class="bl-item {{ $line->is_header ? 'bl-is-header' : '' }}" data-id="{{ $line->id }}">
    <div class="bl-row {{ $line->status ? '' : 'bl-retired' }}">
        @can('budget-line-edit')
            <span class="bl-grip" title="{{ __('ledger_budget.drag_to_move') }}"><i class="fa fa-arrows-v"></i></span>
        @else
            <span class="bl-grip" style="cursor: default;"><i class="fa fa-ellipsis-v"></i></span>
        @endcan
        <span class="bl-code">{{ $line->code }}</span>
        <span class="bl-main">
            <span class="bl-name">{{ $line->display_name }}</span>
            @if($line->is_header)
                <span class="bl-account">{{ trans_choice('ledger_budget.lines_filed_here', $children->count(), ['count' => $children->count()]) }}
                    · @lang('ledger_budget.subtotalled')</span>
            @elseif($targeted)
                <span class="bl-account"><strong>@lang('ledger_budget.reads')</strong> {{ implode(' · ', $accountsByLine[$line->id]) }}</span>
                @can('budget-line-edit')
                    <button type="button" class="bl-change-btn bl-nodrag" data-toggle="modal" data-target="#lineModal" onclick='budgetLineForm(@json($payload))'>
                        <i class="fa fa-pencil"></i> @lang('ledger_budget.change_what_it_reads')</button>
                @endcan
            @else
                @can('budget-line-edit')
                    <button type="button" class="bl-unmapped bl-unmapped-btn bl-nodrag" data-toggle="modal" data-target="#lineModal" onclick='budgetLineForm(@json($payload))'>
                        <i class="fa fa-exclamation-triangle"></i> @lang('ledger_budget.no_account_yet')
                        <span class="bl-unmapped-cta">@lang('ledger_budget.fix_this') &rarr;</span>
                    </button>
                @else
                    <span class="bl-unmapped"><i class="fa fa-exclamation-triangle"></i> @lang('ledger_budget.no_account_yet')</span>
                @endcan
            @endif
        </span>
        <span class="bl-meta">
            @if($line->is_local)
                <span class="bl-flag bl-flag-local" title="{{ __('ledger_budget.local_title') }}">@lang('ledger_budget.local')</span>
            @else
                <span class="bl-flag bl-flag-standard" title="{{ __('ledger_budget.standard_title') }}">@lang('ledger_budget.standard')</span>
            @endif
            @if($line->status)
                <span class="ap-chip open">@lang('ledger_budget.on_the_sheet')</span>
            @else
                <span class="ap-chip void">@lang('ledger_budget.retired')</span>
            @endif
        </span>
        <span class="bl-actions bl-nodrag">
            @can('budget-line-edit')
                <button type="button" class="btn btn-xs btn-default" data-toggle="modal" data-target="#lineModal" title="{{ __('ledger_budget.edit') }}"
                        onclick='budgetLineForm(@json($payload))'><i class="fa fa-pencil"></i></button>
                <form action="{{ route('ledger.budget-line.toggle', $line->id) }}" method="post" style="display: inline;"
                      onsubmit="return confirm(@json($line->status ? __('ledger_budget.confirm_retire') : __('ledger_budget.confirm_restore')))">
                    @csrf
                    <button type="submit" class="btn btn-xs btn-default" title="{{ $line->status ? __('ledger_budget.retire') : __('ledger_budget.restore') }}">
                        <i class="fa {{ $line->status ? 'fa-eye-slash' : 'fa-eye' }}"></i></button>
                </form>
            @endcan
            @can('budget-line-delete')
                @if(empty($inUse))
                    <form action="{{ route('ledger.budget-line.delete', $line->id) }}" method="post" style="display: inline;"
                          onsubmit="return confirm(@json(__('ledger_budget.confirm_delete_line')))">
                        @csrf
                        <button type="submit" class="btn btn-xs btn-danger" title="{{ __('ledger_budget.delete') }}"><i class="fa fa-trash"></i></button>
                    </form>
                @else
                    <button type="button" class="btn btn-xs btn-danger" disabled title="{{ __('ledger_budget.in_use_retire', ['reasons' => implode(', ', $inUse)]) }}"><i class="fa fa-trash"></i></button>
                @endif
            @endcan
        </span>
    </div>
    @if($line->is_header)
        <ol class="bl-children" data-section="{{ $line->section }}">
            @foreach($children as $child)
                @include('ledger.budget-line.partials.node', ['line' => $child, 'children' => collect()])
            @endforeach
        </ol>
    @endif
</li>
