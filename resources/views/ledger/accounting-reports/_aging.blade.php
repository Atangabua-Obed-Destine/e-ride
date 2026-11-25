{{--
    Receivables / payables aging (paxhitestbed's receivables-aging and payables-aging
    screens, one view for both). $kind: receivables | payables.
    Below paxhitestbed's table: the tie-out to the ledger and the documents behind
    the figures (paxhitestbed's supplier aging), as the figures come from the documents (E-Ride: supplier bills and E-Ride's statements).
--}}
@php
    $L = fn ($k, $r = []) => __('ledger_reports.'.$k, $r);
    $m = fn ($v) => \App\Ledger\Ledger::money($v);
    $cur = session('currency')['symbol'] ?? 'FCFA';
    $d = fn ($x) => \Carbon\Carbon::parse($x)->format('d/m/Y');
    $isR = $kind === 'receivables';
    $route = 'ledger.accounting-reports.'.$kind.'-aging';
    $report_param = $kind.'-aging';
    $s = $report['summary'] ?? [];
@endphp

<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ $L($kind.'_aging_report') }}</h1>
    <small class="text-muted"><a href="{{ route('ledger.accounting-reports.index') }}">{{ $L('reports') }}</a> › {{ $L($kind.'_aging') }}</small>
</section>
<section class="content">
    {{-- Filters --}}
    <div class="box {{ $isR ? 'box-primary' : 'box-warning' }}">
        <div class="box-header with-border"><h3 class="box-title"><i class="fas fa-filter"></i> {{ $L('report_filters') }}</h3></div>
        <div class="box-body">
            <form method="GET" action="{{ route($route) }}">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="as_of_date">{{ $L('as_of_date') }}</label>
                            <input type="date" class="form-control" id="as_of_date" name="as_of_date" value="{{ $asOfDate }}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn {{ $isR ? 'btn-primary' : 'btn-warning' }} btn-block"><i class="fas fa-search"></i> {{ $L('generate_report') }}</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Summary --}}
    @if(isset($report['summary']))
    <div class="row">
        @foreach([['current', 'current_0_30_days', 'bg-green', 'fa-check-circle'], ['days_31_60', 'days_31_60', 'bg-aqua', 'fa-clock'],
                  ['days_61_90', 'days_61_90', 'bg-yellow', 'fa-exclamation-triangle'], ['days_91_120', 'days_91_120', 'bg-orange', 'fa-exclamation-circle'],
                  ['over_120', 'over_120_days', 'bg-red', 'fa-times-circle'], ['total', 'total_outstanding', 'bg-gray', 'fa-calculator']] as [$key, $label, $bg, $icon])
        <div class="col-lg-2 col-md-4 col-sm-6">
            <div class="small-box {{ $bg }}">
                <div class="inner">
                    <h4 style="font-weight:600" data-band="{{ $key }}">{{ $m($s[$key] ?? 0) }} {{ $cur }}</h4>
                    <p>{{ $L($label) }}</p>
                </div>
                <div class="icon"><i class="fas {{ $icon }}"></i></div>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    @foreach($report['problems'] ?? [] as $pb)
    <div class="ap-note is-bad" style="margin-bottom:12px">{{ $L('documents_not_worked_out', ['n' => $pb['count']]) }} {{ $pb['message'] }}</div>
    @endforeach

    {{-- Detailed Report --}}
    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">{{ $L($isR ? 'aging_details_by_customer' : 'aging_details_by_vendor') }}</h3>
            <div class="box-tools pull-right">
                <button type="button" class="btn btn-success btn-sm" onclick="exportToExcel()"><i class="fas fa-file-excel"></i> {{ $L('export_excel') }}</button>
                <button type="button" class="btn btn-danger btn-sm" onclick="exportToPdf()"><i class="fas fa-file-pdf"></i> {{ $L('export_pdf') }}</button>
            </div>
        </div>
        <div class="box-body">
            <p class="ap-muted">{{ $L($isR ? 'counted_from_due_date_sales' : 'counted_from_due_date_bills') }}</p>
            <div class="table-responsive">
                <table class="table table-hover table-striped ap-table" id="aging_table">
                    <thead>
                        <tr>
                            <th>{{ $L($isR ? 'customer' : 'vendor') }}</th>
                            <th class="ap-num">{{ $L('current') }}</th>
                            <th class="ap-num">{{ $L('days_31_60') }}</th>
                            <th class="ap-num">{{ $L('days_61_90') }}</th>
                            <th class="ap-num">{{ $L('days_91_120') }}</th>
                            <th class="ap-num">{{ $L('over_120_days') }}</th>
                            <th class="ap-num">{{ $L('total') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($report['details'] ?? [] as $item)
                        <tr data-contact="{{ $item['contact_id'] }}">
                            <td>
                                {{-- E-Ride: a supplier opens on its statement; there is no contact page --}}
                                @if(! empty($item['link']))<a href="{{ $item['link'] }}">{{ $item[$isR ? 'customer_name' : 'vendor_name'] }}</a>@else{{ $item[$isR ? 'customer_name' : 'vendor_name'] }}@endif
                                <div class="ap-muted">{{ count($item['documents']) }} {{ count($item['documents']) === 1 ? $L('document_one') : $L('document_many') }}</div>
                            </td>
                            <td class="ap-num">{{ $m($item['current'] ?? 0) }}</td>
                            <td class="ap-num">{{ $m($item['days_31_60'] ?? 0) }}</td>
                            <td class="ap-num">{{ $m($item['days_61_90'] ?? 0) }}</td>
                            <td class="ap-num">{{ $m($item['days_91_120'] ?? 0) }}</td>
                            <td class="ap-num text-danger">{{ $m($item['over_120'] ?? 0) }}</td>
                            <td class="ap-num"><strong>{{ $m($item['total'] ?? 0) }}</strong></td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="text-center ap-muted">{{ $L($isR ? 'no_receivables_data_found' : 'no_payables_data_found') }}</td></tr>
                        @endforelse
                    </tbody>
                    @if(isset($report['summary']))
                    <tfoot>
                        <tr class="ap-total-row">
                            <td>{{ $L('grand_total') }}</td>
                            <td class="ap-num">{{ $m($s['current'] ?? 0) }}</td>
                            <td class="ap-num">{{ $m($s['days_31_60'] ?? 0) }}</td>
                            <td class="ap-num">{{ $m($s['days_61_90'] ?? 0) }}</td>
                            <td class="ap-num">{{ $m($s['days_91_120'] ?? 0) }}</td>
                            <td class="ap-num text-danger">{{ $m($s['over_120'] ?? 0) }}</td>
                            <td class="ap-num">{{ $m($s['total'] ?? 0) }}</td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>

            {{-- The tie-out an auditor asks for first. --}}
            <table class="table ap-table" style="max-width:620px; margin-top:15px" id="aging_tie_out">
                <tbody>
                    <tr><td>{{ $L('owed_on_the_documents_above') }}</td><td class="ap-num">{{ $m($s['total'] ?? 0) }}</td></tr>
                    <tr><td>{{ $L($isR ? 'balance_of_the_customer_account' : 'balance_of_the_supplier_account') }}
                        <div class="ap-muted">{{ implode(', ', $report['ledger_accounts'] ?? []) }}</div></td>
                        <td class="ap-num">{{ $m($report['ledger_balance'] ?? 0) }}</td></tr>
                    <tr class="ap-total-row"><td>{{ $L('difference') }}</td><td class="ap-num" data-difference="{{ $report['difference'] ?? 0 }}">{{ $m($report['difference'] ?? 0) }}</td></tr>
                </tbody>
            </table>
            @if(abs($report['difference'] ?? 0) > 0.005)
            <div class="ap-note is-warn">{{ $L($isR ? 'ledger_and_documents_differ_customers' : 'ledger_and_documents_differ_suppliers') }}</div>
            @if(! empty($report['contacts_differing']))
            <div class="table-responsive" style="margin-top:10px">
                <table class="table ap-table" style="max-width:820px" id="aging_differences">
                    <thead><tr>
                        <th>{{ $L($isR ? 'customer' : 'supplier') }}</th>
                        <th class="ap-num">{{ $L('on_the_documents') }}</th>
                        <th class="ap-num">{{ $L('in_the_ledger') }}</th>
                        <th class="ap-num">{{ $L('difference') }}</th>
                    </tr></thead>
                    <tbody>
                        @foreach($report['contacts_differing'] as $c)
                        <tr>
                            <td>@if($c['contact_id'] > 0 && ! $isR && \Route::has('ledger.supplier-bill.statement'))<a href="{{ route('ledger.supplier-bill.statement', ['supplier_id' => $c['contact_id']]) }}">{{ $c['name'] }}</a>@else{{ $c['name'] }}@endif</td>{{-- E-Ride: the supplier's statement --}}
                            <td class="ap-num">{{ $m($c['documents']) }}</td>
                            <td class="ap-num">{{ $m($c['ledger']) }}</td>
                            <td class="ap-num"><strong>{{ $m($c['difference']) }}</strong></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
            @else
            <div class="ap-note">{{ $L('documents_agree_with_ledger') }}</div>
            @endif

            {{-- Documents behind these figures --}}
            <h4 style="margin-top:22px">{{ $L('documents_behind_these_figures') }}</h4>
            <div class="table-responsive">
                <table class="table table-striped ap-table" id="aging_documents" style="width:100%">
                    <thead>
                        <tr>
                            <th>{{ $L($isR ? 'customer' : 'supplier') }}</th>
                            <th>{{ $L('document') }}</th>
                            <th>{{ $L('date') }}</th>
                            <th>{{ $L('due') }}</th>
                            <th class="ap-num">{{ $L('days_overdue') }}</th>
                            <th class="ap-num">{{ $L('owed') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($report['details'] ?? [] as $item)
                        @foreach($item['documents'] as $doc)
                        <tr>
                            <td>{{ $item['customer_name'] }}</td>
                            <td>
                                @if($doc['journal_entry_id'])
                                <a href="{{ route('ledger.journal-entries.show', $doc['journal_entry_id']) }}">{{ $doc['number'] }}</a>
                                @else
                                {{ $doc['number'] }}
                                @endif
                                <span class="ap-muted">{{ __('ledger.doc_'.$doc['type']) }}</span>
                            </td>
                            <td data-order="{{ $doc['date'] }}">{{ $d($doc['date']) }}</td>
                            <td data-order="{{ $doc['due_date'] }}">{{ $d($doc['due_date']) }}
                                @if($doc['days_overdue'] > 0 && $doc['owed'] > 0)<span class="ap-chip overdue">{{ $L('overdue') }}</span>@endif</td>
                            <td class="ap-num">{{ $doc['days_overdue'] }}</td>
                            <td class="ap-num" data-order="{{ $doc['owed'] }}">{{ $m($doc['owed']) }}</td>
                        </tr>
                        @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

@section('javascript')
<script>
function exportToExcel() {
    window.location.href = "{{ route('ledger.accounting-reports.export-excel') }}?report={{ $report_param }}&as_of_date={{ $asOfDate }}";
}

function exportToPdf() {
    window.location.href = "{{ route('ledger.accounting-reports.export-pdf') }}?report={{ $report_param }}&as_of_date={{ $asOfDate }}";
}

$(function () {
    if ($.fn.DataTable && $('#aging_documents tbody tr').length) {
        $('#aging_documents').DataTable({ pageLength: 25, order: [] });
    }
});
</script>
@endsection
