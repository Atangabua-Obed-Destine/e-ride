{{--
    One preview (App\Ledger\Services\Preview), rendered inside the review dialog; the
    confirm button reads data-ok. paxhitestbed supplier-bill/partials/preview.
--}}
<div class="ap-preview" data-ok="{{ $p['ok'] ? '1' : '0' }}">
    @if($p['problems'])
    <div class="ap-note is-bad" style="margin-bottom: 12px;">
        <strong>@lang('ledger.cannot_yet')</strong>
        <ul style="margin-bottom: 0; padding-left: 18px;">
            @foreach($p['problems'] as $problem)<li>{{ $problem }}</li>@endforeach
        </ul>
    </div>
    @endif

    @if($p['warnings'])
    <div class="ap-note is-warn" style="margin-bottom: 12px;">
        <strong>@lang('ledger.check_before')</strong>
        <ul style="margin-bottom: 0; padding-left: 18px;">
            @foreach($p['warnings'] as $warning)<li>{{ $warning }}</li>@endforeach
        </ul>
    </div>
    @endif

    @if($p['summary'])
    <dl class="ap-dl" style="margin-bottom: 12px;">
        @foreach($p['summary'] as [$label, $value])
        <dt>{{ $label }}</dt><dd>{{ $value }}</dd>
        @endforeach
    </dl>
    @endif

    @foreach($p['entries'] as $e)
    @if($e)
    <h5 style="margin: 14px 0 4px; font-weight: 600;">{{ $e['title'] ?? __('ledger.entry_to_post') }}</h5>
    <div class="ap-muted" style="margin-bottom: 6px;">{{ $e['date'] }}@if($e['period']) · {{ $e['period'] }}@endif · {{ $e['description'] }}</div>
    <div class="table-responsive">
        <table class="table table-condensed ap-table" style="margin-bottom: 8px;">
            <thead>
                <tr><th>@lang('ledger.account')</th><th>@lang('ledger.description')</th><th class="ap-num">@lang('ledger.debit')</th><th class="ap-num">@lang('ledger.credit')</th></tr>
            </thead>
            <tbody>
                @foreach($e['lines'] as $l)
                <tr>
                    <td style="white-space: nowrap;">{{ $l['account'] }}</td>
                    <td>{{ $l['description'] }}</td>
                    <td class="ap-num">{{ $l['debit'] > 0 ? \App\Ledger\Ledger::money($l['debit']) : '' }}</td>
                    <td class="ap-num">{{ $l['credit'] > 0 ? \App\Ledger\Ledger::money($l['credit']) : '' }}</td>
                </tr>
                @endforeach
                <tr class="ap-total-row">
                    <td colspan="2">@lang('ledger.total') @if(abs($e['debit'] - $e['credit']) < 0.01)<span class="ap-chip paid">@lang('ledger.balanced')</span>@else<span class="ap-chip unpaid">@lang('ledger.not_balanced')</span>@endif</td>
                    <td class="ap-num">{{ \App\Ledger\Ledger::money($e['debit']) }}</td>
                    <td class="ap-num">{{ \App\Ledger\Ledger::money($e['credit']) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
    @endif
    @endforeach

    @foreach($p['effects'] as $effect)
    <h5 style="margin: 14px 0 6px; font-weight: 600;">{{ $effect['title'] }}</h5>
    <div class="table-responsive">
        <table class="table table-condensed ap-table" style="margin-bottom: 8px;">
            <thead>
                <tr>@foreach($effect['columns'] as $i => $c)<th class="{{ in_array($i, $effect['numeric'] ?? []) ? 'ap-num' : '' }}">{{ $c }}</th>@endforeach</tr>
            </thead>
            <tbody>
                @forelse($effect['rows'] as $row)
                <tr>@foreach($row as $i => $cell)<td class="{{ in_array($i, $effect['numeric'] ?? []) ? 'ap-num' : '' }}">{{ $cell }}</td>@endforeach</tr>
                @empty
                <tr><td colspan="{{ count($effect['columns']) }}" class="ap-muted">@lang('ledger.nothing')</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @endforeach

    @foreach($p['notes'] as $note)
    <div class="ap-muted" style="margin-top: 8px;">{{ $note }}</div>
    @endforeach
</div>
