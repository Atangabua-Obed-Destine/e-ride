<ul class="nav nav--tabs p-1 rounded bg-white mb-3">
    <li class="nav-item"><a class="nav-link {{ $active === 'moderation' ? 'active' : '' }}" href="{{ route('admin.market.moderation') }}">{{ translate('Moderation') }}</a></li>
    <li class="nav-item"><a class="nav-link {{ $active === 'shops' ? 'active' : '' }}" href="{{ route('admin.market.shops') }}">{{ translate('Shops and verification') }}</a></li>
    <li class="nav-item"><a class="nav-link {{ $active === 'orders' ? 'active' : '' }}" href="{{ route('admin.market.orders') }}">{{ translate('Orders') }}</a></li>
    <li class="nav-item"><a class="nav-link {{ $active === 'auctions' ? 'active' : '' }}" href="{{ route('admin.market.auctions') }}">{{ translate('Auctions and private sellers') }}</a></li>
    <li class="nav-item"><a class="nav-link {{ $active === 'settings' ? 'active' : '' }}" href="{{ route('admin.market.settings') }}">{{ translate('Settings') }}</a></li>
</ul>
