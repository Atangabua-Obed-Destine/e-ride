@extends('layouts.app')
@section('title', __('ledger_map.title_health'))

@section('content')
@include('ledger.partials.style')
<style>
    /* Status wears an icon and a word as well as a colour (paxhitestbed ledger-health). */
    .lh { --ok: #0ca30c; --warning: #b07d10; --critical: #d03b3b; --muted: #8a97ab; --rule: #e3e7ee; }
    .lh-head { background: #fff; border: 1px solid var(--rule); border-radius: 10px; padding: 1.1rem 1.25rem; margin-bottom: 1rem; border-left: 4px solid var(--ok); }
    .lh-head.is-warning { border-left-color: var(--warning); }
    .lh-head.is-critical { border-left-color: var(--critical); }
    .lh-head h4 { margin: 0 0 .25rem; font-size: 1.1rem; font-weight: 700; color: #182b49; }
    .lh-head p { margin: 0; color: #55637a; font-size: .88rem; line-height: 1.6; }
    .lh-scale { margin-top: .6rem; font-size: .78rem; color: var(--muted); }
    .lh-facts { display: flex; flex-wrap: wrap; gap: 8px 24px; margin-top: .75rem; padding-top: .75rem; border-top: 1px solid #f1f3f7; font-size: .85rem; align-items: center; }
    .lh-facts dt { color: var(--muted); font-weight: 500; display: inline; }
    .lh-facts dd { display: inline; margin: 0 0 0 4px; color: #182b49; font-weight: 600; }
    .lh-filters { display: flex; gap: .35rem; flex-wrap: wrap; margin-bottom: 1rem; }
    .lh-filters .lh-right { margin-left: auto; }
    .lh-card { background: #fff; border: 1px solid var(--rule); border-radius: 10px; margin-bottom: .85rem; border-left: 4px solid var(--rule); }
    .lh-card.is-critical { border-left-color: var(--critical); }
    .lh-card.is-warning { border-left-color: var(--warning); }
    .lh-card.is-ok { border-left-color: var(--ok); }
    .lh-row { display: flex; align-items: flex-start; gap: .75rem; padding: .9rem 1.15rem; }
    .lh-ico { font-size: 1rem; line-height: 1.5; flex-shrink: 0; }
    .lh-ico.is-ok { color: var(--ok); }
    .lh-ico.is-warning { color: var(--warning); }
    .lh-ico.is-critical { color: var(--critical); }
    .lh-body { flex-grow: 1; min-width: 0; }
    .lh-label { font-weight: 700; color: #182b49; font-size: .93rem; }
    .lh-group { font-size: .66rem; font-weight: 800; letter-spacing: .8px; text-transform: uppercase; color: var(--muted); margin-left: .45rem; }
    .lh-detail { font-size: .85rem; color: #55637a; margin: .25rem 0 0; line-height: 1.55; }
    .lh-action { font-size: .83rem; color: #33507d; margin: .35rem 0 0; font-weight: 600; }
    .lh-figure { text-align: right; flex-shrink: 0; font-variant-numeric: tabular-nums; }
    .lh-count { font-size: 1.25rem; font-weight: 800; color: #182b49; line-height: 1.1; }
    .lh-value { font-size: .78rem; color: var(--muted); }
    .lh-rows { border-top: 1px solid #f1f3f7; padding: 0; margin: 0; }
    .lh-rows summary { padding: .55rem 1.15rem; cursor: pointer; font-size: .8rem; font-weight: 700; color: #33507d; list-style: none; }
    .lh-rows summary::-webkit-details-marker { display: none; }
    .lh-rows summary::before { content: '▸ '; }
    .lh-rows[open] summary::before { content: '▾ '; }
    .lh-rows table { width: 100%; font-size: .8rem; margin-bottom: .5rem; }
    .lh-rows th, .lh-rows td { padding: .3rem .6rem; border-top: 1px solid #f1f3f7; }
    .lh-rows th { font-size: .66rem; text-transform: uppercase; letter-spacing: .7px; color: var(--muted); font-weight: 800; }
    .lh-rows td.n, .lh-rows th.n { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .lh-passing .lh-row { padding: .5rem 1.15rem; }
    .lh-passing .lh-label { font-weight: 600; font-size: .87rem; color: #55637a; }
</style>
@php
    $icons = ['ok' => 'fa-check-circle', 'warning' => 'fa-exclamation-triangle', 'critical' => 'fa-times-circle'];
    $moneyCols = ['amount', 'debit', 'credit', 'difference', 'posinnova', 'ledger', 'stored_balance', 'lines_balance',
        'header_total', 'line_total', 'document_amount', 'entry_amount'];
    $o = $status['opening'];
@endphp
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('ledger_map.title_health')</h1>
</section>
<section class="content lh">
    <div class="lh-head {{ $worst === 'ok' ? '' : 'is-'.$worst }}">
        <h4>
            <i class="fa {{ $icons[$worst] }}" style="color: var(--{{ $worst }})"></i>
            @if($worst === 'ok')
                @lang('ledger_map.h_all_good')
            @else
                {{ trans_choice('ledger_map.h_found', $concerns, ['count' => $concerns]) }}
            @endif
        </h4>
        <p>
            @lang('ledger_map.h_intro')
            @if($worst === 'critical')<br><strong>@lang('ledger_map.h_critical_note')</strong>@endif
        </p>
        <div class="lh-scale">
            @lang('ledger_map.h_scale', ['mappings' => number_format($status['mappings']), 'entries' => number_format($status['entries']),
                'live' => number_format($status['live']), 'at' => $checkedAt->format('d/m/Y H:i')])
        </div>
        <dl class="lh-facts">
            <div><dt>@lang('ledger_map.h_books_start'):</dt>
                <dd>{{ $status['post_from'] ? \Carbon\Carbon::parse($status['post_from'])->format('d/m/Y') : '—' }}</dd></div>
            <div><dt>@lang('ledger_map.h_opening'):</dt>
                <dd>
                    @if($o)
                        <a href="{{ route('ledger.journal-entries.show', $o->id) }}">{{ $o->entry_number }}</a>
                        @if($o->is_posted && ! $o->is_reversed)<span class="ap-chip posted">@lang('ledger_map.st_posted')</span>@else<span class="ap-chip failed">@lang('ledger_map.st_failed')</span>@endif
                    @elseif($status['opening_id'] !== null)
                        @lang('ledger_map.h_opening_none')
                    @else
                        @lang('ledger_map.h_opening_not_made')
                    @endif
                </dd></div>
            <div><dt>@lang('ledger_map.h_last_sync'):</dt>
                <dd id="last_sync">{{ $status['last_sweep_at'] ? $status['last_sweep_at']->format('d/m/Y H:i') : __('ledger_map.h_never') }}</dd></div>
            @if($canSync)
            <div>
                <form id="sync_form" method="POST" action="{{ route('ledger.mappings.bulk-sync') }}" style="display: inline;">
                    @csrf
                    <input type="hidden" name="sweep" value="1">
                </form>
                <button type="button" class="btn btn-success btn-sm" id="sync_now_btn"
                    data-review-url="{{ route('ledger.mappings.bulk-sync', ['preview' => 1, 'sweep' => 1]) }}"
                    data-review-form="sync_form"
                    data-confirm-form="sync_form"
                    data-confirm-label="@lang('ledger_map.sync_now')"
                    data-confirm-check="@lang('ledger_map.h_sync_check')"><i class="fa fa-sync"></i> @lang('ledger_map.sync_now')</button>
            </div>
            @endif
        </dl>
    </div>

    <div class="lh-filters">
        <a href="{{ route('ledger.ledger-health') }}" class="btn btn-sm {{ $group ? 'btn-default' : 'btn-primary' }}">@lang('ledger_map.h_everything')</a>
        @foreach($groups as $name)
            <a href="{{ route('ledger.ledger-health', ['group' => $name]) }}" class="btn btn-sm {{ $group === $name ? 'btn-primary' : 'btn-default' }}">{{ __('ledger_map.h_group_'.$name) }}</a>
        @endforeach
        <a href="{{ route('ledger.journal-entries.index') }}" class="btn btn-sm btn-default lh-right"><i class="fa fa-book"></i> @lang('ledger_map.h_journal_entries')</a>
        <a href="{{ route('ledger.mappings.transactions') }}" class="btn btn-sm btn-default"><i class="fa fa-link"></i> @lang('ledger_map.h_transaction_mappings')</a>
    </div>

    @foreach($findings as $check)
    <div class="lh-card is-{{ $check['state'] }}" data-check="{{ $check['key'] }}">
        <div class="lh-row">
            <div class="lh-ico is-{{ $check['state'] }}"><i class="fa {{ $icons[$check['state']] }}"></i></div>
            <div class="lh-body">
                <span class="lh-label">{{ $check['label'] }}</span>
                <span class="lh-group">{{ __('ledger_map.h_group_'.$check['group']) }}</span>
                <p class="lh-detail">{{ $check['detail'] }}</p>
                @if($check['action'])<p class="lh-action">{{ $check['action'] }}</p>@endif
            </div>
            <div class="lh-figure">
                <div class="lh-count">{{ number_format($check['count']) }}</div>
                @if($check['value'] > 0)<div class="lh-value">{{ \App\Ledger\Ledger::money($check['value']) }}</div>@endif
            </div>
        </div>

        @if(!empty($check['rows']))
        <details class="lh-rows">
            <summary>{{ trans_choice('ledger_map.h_show_rows', count($check['rows']), ['count' => count($check['rows'])]) }}</summary>
            <div class="table-responsive">
                <table>
                    <thead><tr>
                        @foreach(array_keys($check['rows'][0]) as $col)
                        <th class="{{ in_array($col, $moneyCols) ? 'n' : '' }}">{{ __('ledger_map.col_'.$col) }}</th>
                        @endforeach
                    </tr></thead>
                    <tbody>
                    @foreach($check['rows'] as $row)
                        <tr>
                        @foreach($row as $key => $value)
                            <td class="{{ in_array($key, $moneyCols) ? 'n' : '' }}">
                                @if(is_array($value))
                                    @if(!empty($value['modal']))
                                    <a href="#" class="btn-modal" data-href="{{ $value['url'] }}" data-container=".view_modal">{{ $value['text'] }}</a>
                                    @elseif(!empty($value['url']))
                                    <a href="{{ $value['url'] }}" target="_blank">{{ $value['text'] }}</a>
                                    @else
                                    {{ $value['text'] }}
                                    @endif
                                @elseif(in_array($key, $moneyCols) && is_numeric($value))
                                    {{ \App\Ledger\Ledger::money($value) }}
                                @else
                                    {{ $value }}
                                @endif
                            </td>
                        @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if($check['count'] > count($check['rows']))<div class="ap-muted" style="padding: 0 1.15rem .5rem;">@lang('ledger_map.h_rows_capped', ['n' => count($check['rows'])])</div>@endif
        </details>
        @endif
    </div>
    @endforeach

    @if(!empty($passing))
    <div class="lh-card is-ok lh-passing">
        <div class="lh-row">
            <div class="lh-ico is-ok"><i class="fa fa-check-circle"></i></div>
            <div class="lh-body">
                <span class="lh-label" style="font-weight: 700; color: #182b49;">{{ trans_choice('ledger_map.h_passed', count($passing), ['count' => count($passing)]) }}</span>
                <p class="lh-detail">@lang('ledger_map.h_passed_note')</p>
            </div>
        </div>
        @foreach($passing as $check)
        <div class="lh-row" style="border-top: 1px solid #f1f3f7;" data-check="{{ $check['key'] }}">
            <div class="lh-ico is-ok"><i class="fa fa-check"></i></div>
            <div class="lh-body">
                <span class="lh-label">{{ $check['label'] }}</span>
                <span class="lh-group">{{ __('ledger_map.h_group_'.$check['group']) }}</span>
            </div>
        </div>
        @endforeach
    </div>
    @endif
    @include('ledger.partials.review_modal')
</section>
@endsection

@section('javascript')
@include('ledger.partials.review_script')
@endsection
