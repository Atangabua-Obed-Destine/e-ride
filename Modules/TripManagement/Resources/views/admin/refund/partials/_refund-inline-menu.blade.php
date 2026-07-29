<div class="col-12">
    <div class="">
        <ul class="nav d-inline-flex nav--tabs p-1 rounded bg-white">
            <li class="nav-item">
                <a href="{{route('admin.trip.refund.index', [PENDING])}}"
                   class="nav-link text-capitalize {{Request::is('admin/trip/refund/list/pending*') ? 'active' : ''}}">{{translate(PENDING)}}</a>
            </li>
            <li class="nav-item">
                <a href="{{route('admin.trip.refund.index', [APPROVED])}}"
                   class="nav-link text-capitalize {{Request::is('admin/trip/refund/list/approved*') ? 'active' : ''}}">{{translate(APPROVED)}}</a>
            </li>
            <li class="nav-item">
                <a href="{{route('admin.trip.refund.index', [DENIED])}}"
                   class="nav-link text-capitalize {{Request::is('admin/trip/refund/list/denied*') ? 'active' : ''}}">{{translate(DENIED)}}</a>
            </li>
            <li class="nav-item">
                <a href="{{route('admin.trip.refund.index', [REFUNDED])}}"
                   class="nav-link text-capitalize {{Request::is('admin/trip/refund/list/refunded*') ? 'active' : ''}}">{{translate(REFUNDED)}}</a>
            </li>
        </ul>
    </div>
</div>
