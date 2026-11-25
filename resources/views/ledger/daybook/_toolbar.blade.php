{{--
    Fiscal year, month and cash box pickers, shared by the book, the summary and the
    analysis so the three always agree about what "this month" means (paxhitestbed
    admin/daybook/_toolbar: Budget and Month; PosInnova has no budgets, so the year is the
    fiscal year, and a cash box can be picked).

    Expects: $year, $years, $period, $periods, $boxes, $account, $query, $route
--}}
@php $q = $query; @endphp
<form method="get" action="{{ route($route) }}" class="db-toolbar no-print">
    <div class="db-field">
        <label for="db_year">{{ __('ledger_daybook.fiscal_year') }}</label>
        <select name="fiscal_year_id" id="db_year" class="form-control" onchange="this.form.period_id.value=''; this.form.submit()">
            @foreach($years as $y)
                <option value="{{ $y->id }}" {{ $y->id === $year->id ? 'selected' : '' }}>{{ $y->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="db-field">
        <label for="db_period">{{ __('ledger_daybook.month') }}</label>
        <select name="period_id" id="db_period" class="form-control" onchange="this.form.submit()">
            @foreach($periods as $p)
                <option value="{{ $p->id }}" {{ $p->id === $period->id ? 'selected' : '' }}>{{ \App\Ledger\Models\FiscalYear::periodLabel($p) }}</option>
            @endforeach
        </select>
    </div>

    <div class="db-field">
        <label for="db_account">{{ __('ledger_daybook.cash_box') }}</label>
        <select name="account_id" id="db_account" class="form-control" onchange="this.form.submit()">
            <option value="">{{ __('ledger_daybook.all_cash_boxes') }}</option>
            @foreach($boxes as $b)
                <option value="{{ $b->id }}" {{ $account && $account->id === $b->id ? 'selected' : '' }}>
                    {{ $b->label }}{{ $b->payment_accounts ? ' ('.implode(', ', $b->payment_accounts).')' : '' }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="db-actions">
        <a href="{{ route('ledger.daybook.index', $q) }}"
           class="btn btn-sm {{ $route === 'ledger.daybook.index' ? 'btn-primary' : 'btn-default' }}">
            <i class="fas fa-book"></i> {{ __('ledger_daybook.the_book') }}
        </a>
        <a href="{{ route('ledger.daybook.summary', $q) }}"
           class="btn btn-sm {{ $route === 'ledger.daybook.summary' ? 'btn-primary' : 'btn-default' }}">
            <i class="fas fa-list-ol"></i> {{ __('ledger_daybook.monthly_summary') }}
        </a>
        <a href="{{ route('ledger.daybook.analysis', $q) }}"
           class="btn btn-sm {{ $route === 'ledger.daybook.analysis' ? 'btn-primary' : 'btn-default' }}">
            <i class="fas fa-chart-line"></i> {{ __('ledger_daybook.trends') }}
        </a>
        <a href="{{ route('ledger.daybook.pdf', $q) }}" class="btn btn-sm btn-default"><i class="fas fa-file-pdf"></i> {{ __('ledger_daybook.pdf') }}</a>
        <a href="{{ route('ledger.daybook.excel', $q) }}" class="btn btn-sm btn-default"><i class="fas fa-file-excel"></i> {{ __('ledger_daybook.excel') }}</a>
    </div>
</form>
