@extends('layouts.app')
@section('title', $title)

@php
    $m = fn ($v) => \App\Ledger\Ledger::money($v);
    $acc = fn ($id) => isset($lines[$id]) ? $lines[$id]->account_code.' '.\App\Ledger\Services\Preview::accountName($lines[$id]) : '#'.$id;
    $periodName = \App\Ledger\Models\FiscalYear::periodLabel($period);
    $many = ! $account;
@endphp

@section('content')
@include('ledger.partials.style')
@include('ledger.daybook.partials.style')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ __('ledger_daybook.daybook') }}</h1>
</section>
<section class="content">

    @include('ledger.daybook._toolbar', ['route' => 'ledger.daybook.index'])

    <div class="db-guide">
        <button class="db-guide-toggle" type="button" data-toggle="collapse" data-target="#dbBookGuide">
            <span class="grow"><i class="fas fa-lightbulb"></i> {{ __('ledger_daybook.guide_book_title') }}</span>
            <i class="fas fa-chevron-up"></i>
        </button>
        <div class="collapse in" id="dbBookGuide">
            <div class="db-guide-body">
                <h6>{{ __('ledger_daybook.guide_what_it_is') }}</h6>
                <p>{{ __('ledger_daybook.guide_what_it_is_p') }}</p>

                <h6>{{ __('ledger_daybook.guide_nothing_typed') }}</h6>
                <p>{{ __('ledger_daybook.guide_nothing_typed_p') }}</p>

                <h6>{{ __('ledger_daybook.guide_relates') }}</h6>
                <p>{{ __('ledger_daybook.guide_relates_p') }}</p>

                <h6>{{ __('ledger_daybook.guide_watch') }}</h6>
                <p>{{ __('ledger_daybook.guide_watch_p') }}</p>
            </div>
        </div>
    </div>

    {{-- Money with no analysis column is stated before the book rather than left to be spotted. --}}
    @if(count($unanalysed))
        <div class="alert alert-warning" id="db-unanalysed">
            <strong>
                <i class="fas fa-exclamation-triangle"></i>
                {{ trans_choice('ledger_daybook.unanalysed_count', count($unanalysed), ['count' => count($unanalysed)]) }}
            </strong>
            <p style="margin: 4px 0 0;"><small>{{ __('ledger_daybook.unanalysed_help') }}</small></p>
        </div>
    @endif

    <div class="db-figures">
        <div class="db-figure">
            <span class="label">{{ __('ledger_daybook.opening_balance') }}</span>
            <div class="value" id="db-opening">{{ $m($opening) }}</div>
        </div>
        <div class="db-figure in">
            <span class="label">{{ __('ledger_daybook.money_in') }}</span>
            <div class="value" id="db-in">{{ $m($totalIn) }}</div>
        </div>
        <div class="db-figure out">
            <span class="label">{{ __('ledger_daybook.money_out') }}</span>
            <div class="value" id="db-out">{{ $m($totalOut) }}</div>
        </div>
        <div class="db-figure">
            <span class="label">{{ __('ledger_daybook.net_movement') }}</span>
            <div class="value" id="db-net">{{ $m($totalIn - $totalOut) }}</div>
        </div>
        <div class="db-figure">
            <span class="label">{{ __('ledger_daybook.closing_balance') }}</span>
            <div class="value" id="db-closing">{{ $m($closing) }}</div>
        </div>
        <div class="db-figure">
            <span class="label">{{ __('ledger_daybook.entries') }}</span>
            <div class="value" id="db-count">{{ number_format(count($rows)) }}</div>
        </div>
    </div>

    <div class="db-card">
        <div class="db-card-head">
            <h5>{{ $periodName }}{{ $account ? ' — '.$account->label : '' }}</h5>
            {{-- Said plainly: nothing here was typed into this screen. --}}
            <small>{{ __('ledger_daybook.generated_from') }}</small>
        </div>
        <div class="table-responsive">
            <table class="table db-table" id="db-book">
                <thead>
                    <tr>
                        <th style="width:92px">{{ __('ledger_daybook.date') }}</th>
                        <th style="width:120px">{{ __('ledger_daybook.ref') }}</th>
                        <th>{{ __('ledger_daybook.description') }}</th>
                        @if($many)<th style="width:170px">{{ __('ledger_daybook.cash_box') }}</th>@endif
                        <th style="width:210px">{{ __('ledger_daybook.analysis_column') }}</th>
                        <th style="width:120px" class="db-num">{{ __('ledger_daybook.debit_in') }}</th>
                        <th style="width:120px" class="db-num">{{ __('ledger_daybook.credit_out') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @if(! $many)
                        <tr class="db-open">
                            <td colspan="4">{{ __('ledger_daybook.opening_balance') }}</td>
                            <td class="db-num" colspan="2">{{ $m($opening) }}</td>
                        </tr>
                    @endif
                    @forelse($rows as $row)
                        <tr data-entry="{{ $row['entry_id'] }}">
                            <td>{{ \Carbon\Carbon::parse($row['date'])->format('d/m/Y') }}</td>
                            <td class="db-ref">
                                {{ $row['ref'] }}
                                @if($row['ref'] !== $row['entry_number'])
                                    <br><a href="{{ route('ledger.journal-entries.show', $row['entry_id']) }}">{{ $row['entry_number'] }}</a>
                                @endif
                            </td>
                            <td>
                                @if($row['link'] && $row['link']['modal'])
                                    <a href="#" class="btn-modal" data-href="{{ $row['link']['url'] }}" data-container=".view_modal">{{ $row['description'] }}</a>
                                @elseif($row['link'])
                                    <a href="{{ $row['link']['url'] }}" target="_blank">{{ $row['description'] }}</a>
                                @elseif($row['ref'] === $row['entry_number'])
                                    <a href="{{ route('ledger.journal-entries.show', $row['entry_id']) }}">{{ $row['description'] }}</a>
                                @else
                                    {{ $row['description'] }}
                                @endif
                                <span class="db-source">{{ __('ledger_daybook.src_'.$row['source']) }}</span>
                                @if($row['is_reversal'])<span class="db-source rev">{{ __('ledger_daybook.reversal') }}</span>@endif
                            </td>
                            @if($many)<td class="db-line">{{ $acc($row['cash_account_id']) }}</td>@endif
                            <td>
                                @if(! $row['unanalysed'])
                                    <span class="db-line">{{ $acc($row['line_id']) }}</span>
                                    @if(count($row['splits']) > 1)
                                        <span class="db-line" title="{{ collect($row['splits'])->map(fn ($v, $id) => $acc($id).': '.$m($v))->implode(' · ') }}">
                                            {{ __('ledger_daybook.and_n_more', ['n' => count($row['splits']) - 1]) }}
                                        </span>
                                    @endif
                                @else
                                    <span class="db-line db-line-none">
                                        <i class="fas fa-exclamation-triangle"></i> {{ __('ledger_daybook.none') }}{{ $row['line_id'] ? ' ('.$acc($row['line_id']).')' : '' }}
                                    </span>
                                @endif
                            </td>
                            <td class="db-num db-in">{{ $row['direction'] === 'in' ? $m($row['amount']) : '' }}</td>
                            <td class="db-num db-out">{{ $row['direction'] === 'out' ? $m($row['amount']) : '' }}</td>
                        </tr>
                    @empty
                        {{-- A quiet month is a fact, not a failure. --}}
                        <tr>
                            <td colspan="{{ $many ? 7 : 6 }}" class="text-center text-muted" style="padding: 24px !important;">
                                {{ __('ledger_daybook.no_cash_moved', ['period' => $periodName]) }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if(count($rows) || ! $many)
                    <tfoot>
                        <tr class="db-foot">
                            <td colspan="{{ $many ? 5 : 4 }}">{{ __('ledger_daybook.total_for') }} {{ mb_strtoupper($periodName) }}</td>
                            <td class="db-num">{{ $m($totalIn) }}</td>
                            <td class="db-num">{{ $m($totalOut) }}</td>
                        </tr>
                        @if(! $many)
                            <tr class="db-foot">
                                <td colspan="4">{{ __('ledger_daybook.closing_balance') }}</td>
                                <td class="db-num" colspan="2">{{ $m($closing) }}</td>
                            </tr>
                        @endif
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- Each cash box on its own: where it opened, what moved, where it closed. --}}
    <div class="db-card">
        <div class="db-card-head">
            <h5>{{ __('ledger_daybook.by_cash_box') }}</h5>
            <small>{{ __('ledger_daybook.by_cash_box_help') }}</small>
        </div>
        <div class="table-responsive">
            <table class="table db-table" id="db-boxes">
                <thead>
                    <tr>
                        <th>{{ __('ledger_daybook.cash_box') }}</th>
                        <th class="db-num">{{ __('ledger_daybook.opening_balance') }}</th>
                        <th class="db-num">{{ __('ledger_daybook.debit_in') }}</th>
                        <th class="db-num">{{ __('ledger_daybook.credit_out') }}</th>
                        <th class="db-num">{{ __('ledger_daybook.closing_balance') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($balances as $id => $b)
                        <tr data-code="{{ $b['account']->account_code }}">
                            <td>
                                <a href="{{ route('ledger.general-ledger.account', ['id' => $id, 'start_date' => $period->start_date->toDateString(), 'end_date' => $period->end_date->toDateString()]) }}">{{ $b['account']->label }}</a>
                                @if($b['account']->payment_accounts)<br><small class="ap-muted">{{ implode(', ', $b['account']->payment_accounts) }}</small>@endif
                            </td>
                            <td class="db-num">{{ $m($b['opening']) }}</td>
                            <td class="db-num db-in">{{ $m($b['in']) }}</td>
                            <td class="db-num db-out">{{ $m($b['out']) }}</td>
                            <td class="db-num"><strong>{{ $m($b['closing']) }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="db-foot">
                        <td>{{ __('ledger_daybook.total') }}</td>
                        <td class="db-num">{{ $m($opening) }}</td>
                        <td class="db-num">{{ $m(array_sum(array_column($balances, 'in'))) }}</td>
                        <td class="db-num">{{ $m(array_sum(array_column($balances, 'out'))) }}</td>
                        <td class="db-num">{{ $m($closing) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</section>
@endsection
