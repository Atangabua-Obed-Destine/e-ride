<div class="position-relative nav--tab-wrapper mb-4">
    <ul class="nav d-flex gap-4 flex-nowrap nav--tabs bg-transparent overflow-x-auto text-nowrap">
        <li class="nav-item text-capitalize">
            <a href="{{ route('admin.business.pages-media.additional-data-setup.index', ['userType' => 'customer']) }}" class="nav-link active-rounded-20 {{ $userType === 'customer' ? 'active' : '' }}">{{ translate('Customer Registration Form') }}</a>
        </li>
        <li class="nav-item">
            <a href="{{ route('admin.business.pages-media.additional-data-setup.index', ['userType' => 'driver']) }}" class="nav-link active-rounded-20 {{ $userType === 'driver' ? 'active' : '' }}">{{ translate('Driver Registration Form') }}</a>
        </li>
    </ul>
</div>
