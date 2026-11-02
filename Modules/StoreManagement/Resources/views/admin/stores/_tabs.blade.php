@if($store->exists)
    <ul class="nav nav--tabs p-1 rounded bg-white mb-3" role="tablist">
        @can('store_edit')
            <li class="nav-item"><a class="nav-link {{ $active === 'details' ? 'active' : '' }}" href="{{ route('admin.store.edit', $store) }}">{{ translate('Details') }}</a></li>
            <li class="nav-item"><a class="nav-link {{ $active === 'menu' ? 'active' : '' }}" href="{{ route('admin.store.menu', $store) }}">{{ translate('Menu') }}</a></li>
        @endcan
        <li class="nav-item"><a class="nav-link {{ $active === 'payouts' ? 'active' : '' }}" href="{{ route('admin.store.payouts', $store) }}">{{ translate('Earnings_and_payouts') }}</a></li>
    </ul>
@endif
