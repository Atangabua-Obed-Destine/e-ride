<ul class="nav nav--tabs p-1 rounded bg-white mb-3" role="tablist">
    <li class="nav-item">
        <a class="nav-link {{ $active === 'list' ? 'active' : '' }}" href="{{ route('admin.booking-deposit.index') }}">{{ translate('Deposits') }}</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $active === 'settings' ? 'active' : '' }}" href="{{ route('admin.booking-deposit.settings') }}">{{ translate('Settings') }}</a>
    </li>
</ul>
