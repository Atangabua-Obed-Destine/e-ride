<ul class="nav nav--tabs p-1 rounded bg-white mb-3 flex-wrap" role="tablist">
    <li class="nav-item">
        <a class="nav-link {{ $active === 'listings' ? 'active' : '' }}" href="{{ route('admin.place.index') }}">{{ translate('Verified_listings') }}</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $active === 'on-duty' ? 'active' : '' }}" href="{{ route('admin.place.on-duty') }}">{{ translate('On-duty_pharmacies') }}</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $active === 'google' ? 'active' : '' }}" href="{{ route('admin.place.google') }}">{{ translate('Google_results') }}</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $active === 'categories' ? 'active' : '' }}" href="{{ route('admin.place.categories') }}">{{ translate('Categories') }}</a>
    </li>
    @can('place_edit')
        <li class="nav-item">
            <a class="nav-link {{ $active === 'settings' ? 'active' : '' }}" href="{{ route('admin.place-setting.edit') }}">{{ translate('Settings') }}</a>
        </li>
    @endcan
</ul>
