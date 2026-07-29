<ul class="nav d-inline-flex nav--tabs nav--tabs__style2 p-1 rounded bg-transparent mb-3">
    <li class="nav-item text-capitalize">
        <a href="{{route('admin.business.setup.safety-precaution.index',SAFETY_ALERT)}}"
           class="nav-link {{Request::is('admin/business/setup/safety-precaution/safety-alert') ? 'active' : ''}}">{{translate("Safety Alert")}}</a>
    </li>
    <li class="nav-item">
        <a href="{{route('admin.business.setup.safety-precaution.index',PRECAUTION)}}"
           class="nav-link {{Request::is('admin/business/setup/safety-precaution/precaution') ? 'active' : ''}}">{{translate("Precautions")}}</a>
    </li>
</ul>
