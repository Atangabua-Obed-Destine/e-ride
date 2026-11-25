{{-- The payables screens, one row of links, so each is a click from the others (paxhitestbed supplier-bill/partials/nav). --}}
<div class="ap-toolbar">
    @can('supplier-bill-view')
    <a href="{{ route('ledger.supplier-bill.index') }}" class="btn btn-sm {{ request()->routeIs('ledger.supplier-bill.index') ? 'btn-primary' : 'btn-outline-primary' }}"><i class="fas fa-file-invoice"></i> @lang('ledger_accounts.supplier_bills')</a>
    @endcan
    @can('supplier-payment-view')
    <a href="{{ route('ledger.supplier-payment.index') }}" class="btn btn-sm {{ request()->routeIs('ledger.supplier-payment.index') ? 'btn-primary' : 'btn-outline-primary' }}"><i class="fas fa-money-check-alt"></i> @lang('ledger_accounts.supplier_payments')</a>
    @endcan
    @can('supplier-bill-view')
    <a href="{{ route('ledger.supplier-bill.aging') }}" class="btn btn-sm {{ request()->routeIs('ledger.supplier-bill.aging') ? 'btn-primary' : 'btn-outline-primary' }}"><i class="fas fa-hourglass-half"></i> @lang('ledger_accounts.payables_aging')</a>
    <a href="{{ route('ledger.supplier-bill.statement') }}" class="btn btn-sm {{ request()->routeIs('ledger.supplier-bill.statement') ? 'btn-primary' : 'btn-outline-primary' }}"><i class="fas fa-file-alt"></i> @lang('ledger_accounts.supplier_statement')</a>
    @endcan
</div>
