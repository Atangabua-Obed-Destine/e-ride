<div class="col-12">
    <div class="">
        <ul class="nav d-inline-flex nav--tabs p-1 rounded bg-white">
            <li class="nav-item">
                <a href="{{route('admin.safety-alert.index', CUSTOMER)}}" class="nav-link text-capitalize {{Request::is('admin/safety-alert/list/customer*') ? 'active' : ''}}">{{translate(CUSTOMER)}}</a>
            </li>
            <li class="nav-item">
                <a href="{{route('admin.safety-alert.index', DRIVER)}}" class="nav-link text-capitalize {{Request::is('admin/safety-alert/list/driver*') ? 'active' : ''}}">{{translate(DRIVER)}}</a>
            </li>
        </ul>
    </div>
</div>
