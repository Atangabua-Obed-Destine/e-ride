<div class="mb-4 d-flex flex-wrap justify-content-between gap-3">
    <ul class="nav d-inline-flex nav--tabs p-1 rounded-10 bg-white">
        <li class="nav-item">
            <a href="{{route('admin.business.configuration.notification.index', ['type' => 'schedule-trip'])}}" class="text-capitalize nav-link rounded-8 fs-14
                {{Request::is('admin/business/configuration/notification/schedule-trip') ? 'active' : ''}}
            ">{{translate('schedule_trip')}}</a>
        </li>
        <li class="nav-item">
            <a href="{{route('admin.business.configuration.notification.index', ['type' => 'regular-trip'])}}" class="text-capitalize nav-link rounded-8 fs-14
                {{Request::is('admin/business/configuration/notification/regular-trip') ? 'active' : ''}}
            ">{{translate('regular_trip')}}</a>
        </li>
        <li class="nav-item">
            <a href="{{route('admin.business.configuration.notification.index', ['type' => 'parcel'])}}" class="text-capitalize nav-link rounded-8
                {{Request::is('admin/business/configuration/notification/parcel') ? 'active' : ''}}
            ">{{translate('parcel')}}</a>
        </li>
        <li class="nav-item">
            <a href="{{route('admin.business.configuration.notification.index', ['type' => 'driver-registration'])}}" class="text-capitalize nav-link rounded-8
                {{Request::is('admin/business/configuration/notification/driver-registration') ? 'active' : ''}}
            ">{{translate('driver_registration')}}</a>
        </li>
        <li class="nav-item">
            <a href="{{route('admin.business.configuration.notification.index', ['type' => 'others'])}}" class="text-capitalize nav-link rounded-8
                {{Request::is('admin/business/configuration/notification/others') ? 'active' : ''}}
            ">{{translate('other')}}</a>
        </li>
    </ul>
    <h5 class="d-flex align-items-center gap-2 text-dark fw-medium cursor-pointer read-instruction">{{ translate('Read_Instruction') }}<span><i class="bi bi-info-circle"></i></span></h5>
</div>
