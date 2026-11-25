{{-- The report tables, shared by the branch and the head office. Params: $tab, $data, $by, $ledgerUrl (fn(account) => url|null) --}}
@php use Modules\BusManagement\Lib\AccFormat as F; $link = $ledgerUrl ?? fn($a) => null; @endphp
@if($tab === 'income')
    <div class="acc-scroll"><table class="acc-t">
        <tbody>
        <tr class="sub"><td colspan="2">{{ translate('Income') }}</td></tr>
        @foreach($data['income'] as $r)
            <tr><td>@if($u = $link($r['account']))<a href="{{ $u }}">{{ $r['account']->label() }}</a>@else{{ $r['account']->label() }}@endif</td><td class="n {{ F::cls($r['amount']) }}">{{ F::n($r['amount']) }}</td></tr>
        @endforeach
        <tr class="tot"><td>{{ translate('Total income') }}</td><td class="n">{{ F::n($data['total_income']) }}</td></tr>
        <tr class="sub"><td colspan="2">{{ translate('Spending') }}</td></tr>
        @foreach($data['spending'] as $r)
            <tr><td>@if($u = $link($r['account']))<a href="{{ $u }}">{{ $r['account']->label() }}</a>@else{{ $r['account']->label() }}@endif</td><td class="n">{{ F::n($r['amount']) }}</td></tr>
        @endforeach
        <tr class="tot"><td>{{ translate('Total spending') }}</td><td class="n">{{ F::n($data['total_spending']) }}</td></tr>
        <tr class="tot"><td>{{ $data['result'] >= 0 ? translate('Profit') : translate('Loss') }}</td><td class="n {{ F::cls($data['result']) }}">{{ F::n($data['result']) }}</td></tr>
        </tbody>
    </table></div>
@elseif($tab === 'profit')
    <div class="acc-scroll"><table class="acc-t">
        <thead><tr><th>{{ translate($by === 'bus' ? 'Bus' : ($by === 'route' ? 'Route' : 'Trip')) }}</th><th class="n">{{ translate('Income') }}</th><th class="n">{{ translate('Fuel') }}</th>
            <th class="n">{{ translate('Other spending') }}</th><th class="n">{{ translate('E-Ride commission') }}</th><th class="n">{{ translate('Profit') }}</th></tr></thead>
        <tbody>
        @forelse($data as $r)
            <tr><td>{{ $r['label'] ?? translate('Not linked to one') }}@if($r['key'] === '')<div class="acc-tag">{{ translate('Office costs, and the commission not yet billed or already shared out') }}</div>@endif</td>
                <td class="n">{{ F::n($r['income']) }}</td><td class="n">{{ F::n($r['fuel']) }}</td><td class="n">{{ F::n($r['other']) }}</td><td class="n">{{ F::n($r['commission']) }}</td>
                <td class="n fw-bold {{ F::cls($r['profit']) }}">{{ F::n($r['profit']) }}</td></tr>
        @empty
            <tr><td colspan="6" class="acc-empty">{{ translate('Nothing in this period.') }}</td></tr>
        @endforelse
        </tbody>
        @if(count($data))
            <tfoot><tr class="tot"><td>{{ translate('Total') }}</td><td class="n">{{ F::n(collect($data)->sum('income')) }}</td><td class="n">{{ F::n(collect($data)->sum('fuel')) }}</td>
                <td class="n">{{ F::n(collect($data)->sum('other')) }}</td><td class="n">{{ F::n(collect($data)->sum('commission')) }}</td><td class="n">{{ F::n(collect($data)->sum('profit')) }}</td></tr></tfoot>
        @endif
    </table></div>
    <div class="b-hint mt-2">{{ translate('Income and spending count where they are tagged with a bus, route or trip. E-Ride commission per trip comes from its tickets. The total equals the result of the income statement.') }}</div>
@elseif($tab === 'cash')
    <div class="acc-scroll"><table class="acc-t" style="max-width: 720px">
        <tbody>
        <tr class="sub"><td>{{ translate('Money at the start') }}</td><td class="n">{{ F::n($data['opening']) }}</td></tr>
        @forelse($data['rows'] as $r)
            <tr><td>{{ translate($r['label']) }}</td><td class="n {{ F::cls($r['amount']) }}">{{ F::n($r['amount']) }}</td></tr>
        @empty
            <tr><td colspan="2" class="acc-empty">{{ translate('No money moved in this period.') }}</td></tr>
        @endforelse
        <tr class="tot"><td>{{ translate('Money in less money out') }}</td><td class="n {{ F::cls($data['net']) }}">{{ F::n($data['net']) }}</td></tr>
        <tr class="tot"><td>{{ translate('Money at the end') }} @if($data['agrees'])<span class="b-tag ok">{{ translate('Agrees with the accounts') }}</span>@else<span class="b-tag bad">{{ translate('Does not agree') }}</span>@endif</td><td class="n">{{ F::n($data['closing']) }}</td></tr>
        </tbody>
    </table></div>
    <div class="b-hint mt-2">{{ translate('Money accounts: counter cash, safe, bank, Mobile Money, Fapshi, and money the head office holds. Money moved between them is not counted:') }} {{ F::n($data['internal']) }}.</div>
@elseif($tab === 'budget')
    <div class="acc-scroll"><table class="acc-t">
        <thead><tr><th>{{ translate('Account') }}</th><th class="n">{{ translate('Budget') }}</th><th class="n">{{ translate('Actual') }}</th><th class="n">{{ translate('Difference') }}</th><th class="n">{{ translate('Used') }}</th></tr></thead>
        <tbody>
        @foreach([['income', translate('Income')], ['spending', translate('Spending')]] as [$k, $label])
            <tr class="sub"><td colspan="5">{{ $label }}</td></tr>
            @forelse($data['rows'][$k] as $r)
                <tr><td>{{ $r['account']->label() }} @if($r['flag'])<span class="b-tag bad">{{ $k === 'income' ? translate('Under budget') : translate('Over budget') }}</span>@endif</td>
                    <td class="n">{{ F::n($r['budget']) }}</td><td class="n">{{ F::n($r['actual']) }}</td><td class="n {{ $r['flag'] ? 'neg' : '' }}">{{ F::n($r['diff']) }}</td>
                    <td class="n">{{ $r['used'] !== null ? $r['used'] . '%' : '-' }}</td></tr>
            @empty
                <tr><td colspan="5" class="acc-empty">{{ translate('No budget or activity.') }}</td></tr>
            @endforelse
            <tr class="tot"><td>{{ translate('Total') }}</td><td class="n">{{ F::n($data[$k . '_budget']) }}</td><td class="n">{{ F::n($data[$k . '_actual']) }}</td>
                <td class="n">{{ F::n($data[$k . '_actual'] - $data[$k . '_budget']) }}</td><td></td></tr>
        @endforeach
        </tbody>
    </table></div>
    <div class="b-hint mt-2">{{ translate('Whole months') }}: {{ \Illuminate\Support\Carbon::parse($data['from'])->format('d/m/Y') }} - {{ \Illuminate\Support\Carbon::parse($data['to'])->format('d/m/Y') }}</div>
@elseif($tab === 'trial')
    <div class="acc-scroll"><table class="acc-t">
        <thead><tr><th>{{ translate('Account') }}</th><th class="n">{{ translate('Opening debit') }}</th><th class="n">{{ translate('Opening credit') }}</th><th class="n">{{ translate('Debit') }}</th>
            <th class="n">{{ translate('Credit') }}</th><th class="n">{{ translate('Closing debit') }}</th><th class="n">{{ translate('Closing credit') }}</th></tr></thead>
        <tbody>
        @foreach($data['rows'] as $r)
            <tr><td>@if($u = $link($r['account']))<a href="{{ $u }}">{{ $r['account']->label() }}</a>@else{{ $r['account']->label() }}@endif</td>
                @foreach(['od', 'oc', 'd', 'c', 'cd', 'cc'] as $k)<td class="n">{{ F::n($r[$k], true) }}</td>@endforeach</tr>
        @endforeach
        </tbody>
        <tfoot><tr class="tot"><td>{{ translate('Total') }} @if($data['balanced'])<span class="b-tag ok">{{ translate('Balanced') }}</span>@else<span class="b-tag bad">{{ translate('Not balanced') }}</span>@endif</td>
            @foreach(['od', 'oc', 'd', 'c', 'cd', 'cc'] as $k)<td class="n">{{ F::n($data['totals'][$k]) }}</td>@endforeach</tr></tfoot>
    </table></div>
@else
    <div class="row g-3">
        <div class="col-lg-6">
            <table class="acc-t"><tbody>
                <tr class="sub"><td colspan="2">{{ ($isCompanyBooks ?? false) ? translate('What the company has') : translate('What the branch has') }}</td></tr>
                @foreach($data['has'] as $r)<tr><td>{{ $r['account']->label() }}</td><td class="n {{ F::cls($r['amount']) }}">{{ F::n($r['amount']) }}</td></tr>@endforeach
                <tr class="tot"><td>{{ translate('Total') }}</td><td class="n">{{ F::n($data['total_has']) }}</td></tr>
            </tbody></table>
        </div>
        <div class="col-lg-6">
            <table class="acc-t"><tbody>
                <tr class="sub"><td colspan="2">{{ ($isCompanyBooks ?? false) ? translate('What the company owes') : translate('What the branch owes') }}</td></tr>
                @foreach($data['owes'] as $r)<tr><td>{{ $r['account']->label() }}</td><td class="n {{ F::cls($r['amount']) }}">{{ F::n($r['amount']) }}</td></tr>@endforeach
                <tr class="tot"><td>{{ translate('Total') }}</td><td class="n">{{ F::n($data['total_owes']) }}</td></tr>
                <tr class="sub"><td colspan="2">{{ translate('Owner') }}</td></tr>
                @foreach($data['equity'] as $r)<tr><td>{{ $r['account']->label() }}</td><td class="n {{ F::cls($r['amount']) }}">{{ F::n($r['amount']) }}</td></tr>@endforeach
                <tr><td>{{ translate('Result since the start') }}</td><td class="n {{ F::cls($data['result']) }}">{{ F::n($data['result']) }}</td></tr>
                <tr class="tot"><td>{{ translate('Total owed and owner') }}</td><td class="n">{{ F::n($data['total_owes'] + $data['total_equity']) }}</td></tr>
            </tbody></table>
        </div>
    </div>
    <div class="mt-2">@if($data['balanced'])<span class="b-tag ok">{{ translate('Balanced') }}</span>@else<span class="b-tag bad">{{ translate('Not balanced') }}</span>@endif</div>
@endif
