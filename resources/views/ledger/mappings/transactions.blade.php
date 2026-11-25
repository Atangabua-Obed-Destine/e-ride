@extends('layouts.app')
@section('title', __('ledger_map.title_transactions'))

@section('content')
@include('ledger.partials.style')
<style>
    .transaction-row { border-left: 4px solid transparent; }
    .transaction-row.sell { border-left-color: #3c8dbc; }
    .transaction-row.sell_return { border-left-color: #00c0ef; }
    .transaction-row.purchase { border-left-color: #00a65a; }
    .transaction-row.purchase_return { border-left-color: #8bc34a; }
    .transaction-row.expense { border-left-color: #dd4b39; }
    .transaction-row.payment { border-left-color: #f39c12; }
    .transaction-row.opening_balance, .transaction-row.ledger_discount { border-left-color: #94a3b8; }
    .ap-chip.unposted { background: #fffbeb; color: #92400e; border-color: #fde68a; }
    .ap-chip.none { background: #f1f5f9; color: #475569; border-color: #cbd5e1; }
    .ap-chip.history { background: #f8fafc; color: #64748b; border-color: #e2e8f0; }
    .sync-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; padding: 10px 12px; background: #f8f9fb; border: 1px solid #e6e9ef; border-radius: 6px; margin-bottom: 12px; }
    .sync-toolbar .checkbox { margin: 0; }
    .ap-filters label { font-weight: 500; }
</style>
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_map.title_transactions')
        <small>@lang('ledger_map.sub_transactions')</small></h1>
</section>
<section class="content">
    <div class="ap-card">
        <div class="ap-toolbar" style="justify-content: flex-end; margin-bottom: 12px;">
            @can('transaction-mapping-settings')
            <a href="{{ route('ledger.mappings.settings') }}" class="btn btn-default btn-sm"><i class="fa fa-cog"></i> @lang('ledger_map.mapping_settings')</a>
            @endcan
            <a href="{{ route('ledger.ledger-health') }}" class="btn btn-default btn-sm"><i class="fa fa-heartbeat"></i> @lang('ledger_map.title_health')</a>
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('ledger.mappings.transactions') }}" class="ap-filters" style="margin-bottom: 15px;">
            <div class="form-group">
                <label for="f_type">@lang('ledger_map.type'):</label>
                <select name="type" id="f_type" class="form-control input-sm" onchange="this.form.submit()">
                    <option value="all">@lang('ledger_map.all')</option>
                    @foreach($types as $t)
                    <option value="{{ $t }}" @selected($f['type'] === $t)>{{ __('ledger.doc_'.$t) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="f_status">@lang('ledger_map.status'):</label>
                <select name="status" id="f_status" class="form-control input-sm" onchange="this.form.submit()">
                    <option value="all">@lang('ledger_map.all')</option>
                    @foreach(['posted', 'failed', 'unposted', 'none', 'history'] as $s)
                    <option value="{{ $s }}" @selected($f['status'] === $s)>{{ __('ledger_map.st_'.$s) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="f_from">@lang('ledger_map.from'):</label>
                <input type="date" name="date_from" id="f_from" class="form-control input-sm" value="{{ $f['date_from'] }}">
            </div>
            <div class="form-group">
                <label for="f_to">@lang('ledger_map.to'):</label>
                <input type="date" name="date_to" id="f_to" class="form-control input-sm" value="{{ $f['date_to'] }}">
            </div>
            <div class="form-group" style="min-width: 0;">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> @lang('ledger_map.filter')</button>
                <a href="{{ route('ledger.mappings.transactions') }}" class="btn btn-default btn-sm"><i class="fa fa-redo"></i> @lang('ledger_map.reset')</a>
            </div>
        </form>

        {{-- Stats --}}
        <div class="row">
            <div class="col-md-3 col-sm-6">
                <div class="small-box bg-aqua"><div class="inner"><h3>{{ number_format((int) $stats->n) }}</h3><p>@lang('ledger_map.total_transactions')</p></div>
                    <div class="icon"><i class="fa fa-list"></i></div></div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="small-box bg-green"><div class="inner"><h3>{{ number_format((int) $stats->posted) }}</h3><p>@lang('ledger_map.stat_posted')</p></div>
                    <div class="icon"><i class="fa fa-check-circle"></i></div></div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="small-box bg-yellow"><div class="inner"><h3>{{ number_format((int) $stats->failed + (int) $stats->unposted) }}</h3><p>@lang('ledger_map.stat_open')</p></div>
                    <div class="icon"><i class="fa fa-exclamation-circle"></i></div></div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="small-box bg-blue"><div class="inner"><h3 style="font-size: 26px;">{{ \App\Ledger\Ledger::money($stats->amount) }}</h3><p>@lang('ledger_map.total_amount')</p></div>
                    <div class="icon"><i class="fa fa-money-bill-alt"></i></div></div>
            </div>
        </div>

        @php($selectable = collect($rows->items())->filter(fn ($r) => $r->state !== 'history')->count())
        @if($canManage)
        {{-- Posting a selection. The server judges every row again when it posts, and the
             review shows the entries before anything is written. --}}
        <form id="bulk_sync_form" method="POST" action="{{ route('ledger.mappings.bulk-sync', request()->only(['type', 'status', 'date_from', 'date_to', 'page'])) }}">@csrf</form>
        <div class="sync-toolbar">
            <div class="checkbox"><label>
                <input type="checkbox" id="syncSelectAll" @disabled(! $selectable)> @lang('ledger_map.select_all') ({{ $selectable }})
            </label></div>
            <button type="button" class="btn btn-success btn-sm" id="syncSelectedBtn" disabled
                data-review-url="{{ route('ledger.mappings.bulk-sync', ['preview' => 1]) }}"
                data-review-form="bulk_sync_form"
                data-confirm-form="bulk_sync_form"
                data-confirm-label="@lang('ledger_map.sync_selected')"
                data-confirm-check="@lang('ledger_map.sync_check')">
                <i class="fa fa-sync"></i> @lang('ledger_map.sync_selected') (<span id="syncCount">0</span>)
            </button>
            <small class="ap-muted" style="flex: 1 1 300px;">@lang('ledger_map.sync_help')</small>
        </div>
        @endif

        <div class="table-responsive">
            <table class="table table-hover ap-table" id="mapping_transactions_table">
                <thead>
                    <tr>
                        @if($canManage)<th style="width: 3%;" class="text-center"><span class="sr-only">@lang('ledger_map.select_for_posting')</span></th>@endif
                        <th style="width: 3%;">#</th>
                        <th style="width: 9%;">@lang('ledger_map.col_date')</th>
                        <th style="width: 10%;">@lang('ledger_map.col_type')</th>
                        <th>@lang('ledger_map.col_description')</th>
                        <th>@lang('ledger_map.col_reference')</th>
                        <th class="ap-num">@lang('ledger_map.col_amount')</th>
                        <th class="text-center">@lang('ledger_map.col_status')</th>
                        <th>@lang('ledger_map.col_entry')</th>
                        <th class="text-center">@lang('ledger_map.col_actions')</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $r)
                    <tr class="transaction-row {{ $r->type }}" data-doc="{{ $r->type }}:{{ $r->id }}">
                        @if($canManage)
                        <td class="text-center">
                            @if($r->state !== 'history')
                            <input type="checkbox" class="sync-select" name="items[]" form="bulk_sync_form" value="{{ $r->type }}:{{ $r->id }}"
                                aria-label="@lang('ledger_map.select_for_posting')">
                            @else
                            <input type="checkbox" disabled title="@lang('ledger_map.st_history_tip')" aria-label="@lang('ledger_map.st_history_tip')">
                            @endif
                        </td>
                        @endif
                        <td>{{ ($rows->currentPage() - 1) * $rows->perPage() + $loop->iteration }}</td>
                        <td style="white-space: nowrap;">{{ \Carbon\Carbon::parse($r->date)->format('d/m/Y') }}</td>
                        <td><span class="label label-{{ ['sell' => 'primary', 'sell_return' => 'info', 'purchase' => 'success', 'purchase_return' => 'success', 'expense' => 'danger', 'payment' => 'warning'][$r->type] ?? 'default' }}">{{ __('ledger.doc_'.$r->type) }}</span></td>
                        <td>
                            @if($r->link && $r->link['modal'])
                            <a href="#" class="btn-modal" data-href="{{ $r->link['url'] }}" data-container=".view_modal">{{ $r->label }}</a>
                            @elseif($r->link)
                            <a href="{{ $r->link['url'] }}" target="_blank">{{ $r->label }}</a>
                            @else
                            {{ $r->label }}
                            @endif
                        </td>
                        <td><small class="ap-muted">{{ $r->reference }}</small></td>
                        <td class="ap-num">{{ \App\Ledger\Ledger::money($r->amount) }}</td>
                        <td class="text-center">
                            <span class="ap-chip {{ $r->state }}" @if(in_array($r->state, ['none', 'unposted', 'history'])) title="{{ __('ledger_map.st_'.$r->state.'_tip') }}" @endif>{{ __('ledger_map.st_'.$r->state) }}</span>
                            @if($r->state === 'failed' && $r->error)
                            <div class="text-danger" style="font-size: 0.75rem; max-width: 260px; white-space: normal; margin: 4px auto 0;">{{ $r->error }}</div>
                            @endif
                        </td>
                        <td style="white-space: nowrap;">
                            @if($r->entry)
                            <a href="{{ route('ledger.journal-entries.show', $r->entry->id) }}" target="_blank">{{ $r->entry->entry_number }}</a>
                            @endif
                        </td>
                        <td class="text-center" style="white-space: nowrap;">
                            @if($r->map_id && $canRemap)
                            <form id="remap_form_{{ $r->map_id }}" method="POST" action="{{ route('ledger.mappings.remap', ['mappingId' => $r->map_id] + request()->only(['type', 'status', 'date_from', 'date_to', 'page'])) }}" style="display: inline;">@csrf</form>
                            <button type="button" class="btn btn-xs btn-warning" title="@lang('ledger_map.repost_tip')"
                                data-review-url="{{ route('ledger.mappings.remap', ['mappingId' => $r->map_id, 'preview' => 1]) }}"
                                data-review-form="remap_form_{{ $r->map_id }}"
                                data-confirm-form="remap_form_{{ $r->map_id }}"
                                data-confirm-label="@lang('ledger_map.repost')"
                                data-confirm-class="btn-warning"
                                data-confirm-check="@lang('ledger_map.repost_check')">
                                <i class="fa fa-redo"></i> @lang('ledger_map.repost')
                            </button>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="{{ $canManage ? 10 : 9 }}" class="text-center ap-muted" style="padding: 30px;">
                        <i class="fa fa-inbox fa-3x"></i><p>@lang('ledger_map.no_transactions')</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($rows->total() > 0)
        <div class="row">
            <div class="col-md-6"><p class="ap-muted">@lang('ledger_map.showing', ['from' => $rows->firstItem(), 'to' => $rows->lastItem(), 'total' => $rows->total()])</p></div>
            <div class="col-md-6 text-right">{{ $rows->links() }}</div>
        </div>
        @endif
    </div>
    @include('ledger.partials.review_modal')
</section>
@endsection

@section('javascript')
@include('ledger.partials.review_script')
<script>
(function () {
    var button = document.getElementById('syncSelectedBtn');
    if (!button) { return; }
    var selectAll = document.getElementById('syncSelectAll');
    var counter = document.getElementById('syncCount');
    function boxes() { return Array.prototype.slice.call(document.querySelectorAll('.sync-select')); }
    function chosen() { return boxes().filter(function (b) { return b.checked; }); }
    function refresh() {
        var n = chosen().length, all = boxes().length;
        counter.textContent = n;
        button.disabled = n === 0;
        if (selectAll) {
            selectAll.checked = all > 0 && n === all;
            selectAll.indeterminate = n > 0 && n < all;
        }
    }
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            boxes().forEach(function (b) { b.checked = selectAll.checked; });
            refresh();
        });
    }
    document.addEventListener('change', function (e) {
        if (e.target.classList && e.target.classList.contains('sync-select')) { refresh(); }
    });
    refresh();
})();
</script>
@endsection
