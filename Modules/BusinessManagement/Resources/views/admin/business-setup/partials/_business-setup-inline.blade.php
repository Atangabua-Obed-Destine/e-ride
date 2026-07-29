<div class="position-relative nav--tab-wrapper mb-4">
    <ul class="nav d-flex gap-4 flex-nowrap nav--tabs bg-transparent overflow-x-auto text-nowrap">
        <li class="nav-item text-capitalize">
            <a href="{{route('admin.business.setup.info.index')}}"
               class="nav-link active-rounded-20 {{Request::is('admin/business/setup/info') ? 'active' : ''}}">{{translate('business_info')}}</a>
        </li>
         <li class="nav-item">
            <a href="{{route('admin.business.setup.info.settings')}}"
               class="nav-link active-rounded-20 {{Request::is('admin/business/setup/info/settings') ? 'active' : ''}}">{{translate('settings')}}</a>
        </li>
        <li class="nav-item">
            <a href="{{route('admin.business.setup.driver.index')}}"
               class="nav-link active-rounded-20 {{Request::is('admin/business/setup/driver') ? 'active' : ''}}">{{translate('driver')}}</a>
        </li>
        <li class="nav-item">
            <a href="{{route('admin.business.setup.customer.index')}}"
               class="nav-link active-rounded-20 {{Request::is('admin/business/setup/customer') ? 'active' : ''}}">{{translate('customer')}}</a>
        </li>
        <li class="nav-item text-capitalize">
            <a href="{{route('admin.business.setup.trip-fare.penalty')}}"
               class="nav-link active-rounded-20 {{Request::is('admin/business/setup/trip-fare/penalty') ? 'active' : ''}}">{{translate('fare_&_penalty_settings')}}</a>
        </li>
        <li class="nav-item">
            <a href="{{route('admin.business.setup.trip-fare.trips')}}"
               class="nav-link active-rounded-20 {{Request::is('admin/business/setup/trip-fare/trips') ? 'active' : ''}}">{{translate('trips')}}</a>
        </li>
        <li class="nav-item">
            <a href="{{route('admin.business.setup.parcel.index')}}"
               class="nav-link active-rounded-20 {{Request::is('admin/business/setup/parcel') ? 'active' : ''}}">{{translate('parcel')}}</a>
        </li>
        <li class="nav-item">
            <a href="{{route('admin.business.setup.parcel-refund.index')}}"
               class="nav-link active-rounded-20 {{Request::is('admin/business/setup/parcel-refund') ? 'active' : ''}}">{{translate('refund')}}</a>
        </li>
        <li class="nav-item">
            <a href="{{route('admin.business.setup.safety-precaution.index', SAFETY_ALERT)}}"
               class="nav-link active-rounded-20 {{Request::is('admin/business/setup/safety-precaution/*') ? 'active' : ''}}">{{translate('safety_&_Precautions')}}</a>
        </li>
        <li class="nav-item">
            <a href="{{route('admin.business.setup.referral-earning.index')}}"
               class="nav-link active-rounded-20 {{Request::is('admin/business/setup/referral-earning') ? 'active' : ''}}">{{translate('referral_earning')}}</a>
        </li>
        <li class="nav-item">
            <a href="{{route('admin.business.setup.chatting-setup.index',DRIVER)}}"
               class="nav-link active-rounded-20 {{Request::is('admin/business/setup/chatting-setup/*') ? 'active' : ''}}">{{translate('chatting_setup')}}</a>
        </li>
    </ul>
    <div class="nav--tab__prev">
        <button type="button" class="btn btn-circle fs-16">
            <i class="bi bi-chevron-left"></i>
        </button>
    </div>
    <div class="nav--tab__next">
        <button type="button" class="btn btn-circle fs-16">
            <i class="bi bi-chevron-right"></i>
        </button>
    </div>
</div>
