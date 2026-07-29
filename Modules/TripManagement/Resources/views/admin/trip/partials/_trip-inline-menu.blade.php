<div class="col-12">
    <div class="">
        <ul class="nav d-inline-flex nav--tabs p-1 rounded bg-white">
            <li class="nav-item">
                <a href="{{route('admin.trip.index', [ALL])}}" class="nav-link rating-review__info text-capitalize {{Request::is('admin/trip/list/all*') ? 'active' : ''}}">{{translate('all_trips')}}</a>
            </li>
            <li class="nav-item">
                <a href="{{route('admin.trip.index', [PENDING])}}" class="nav-link rating-review__info text-capitalize {{Request::is('admin/trip/list/pending*') ? 'active' : ''}}">{{translate(PENDING)}}</a>
            </li>
            <li class="nav-item">
                <a href="{{route('admin.trip.index', [SCHEDULED])}}" class="nav-link rating-review__info text-capitalize  {{Request::is('admin/trip/list/scheduled*') ? 'active' : ''}}">{{translate(SCHEDULED)}}</a>
            </li>
            <li class="nav-item">
                <a href="{{route('admin.trip.index', [ACCEPTED])}}" class="nav-link rating-review__info text-capitalize {{Request::is('admin/trip/list/accepted*') ? 'active' : ''}}">{{translate(ACCEPTED)}}</a>
            </li>
            <li class="nav-item">
                <a href="{{route('admin.trip.index', [ONGOING])}}" class="nav-link rating-review__info text-capitalize {{Request::is('admin/trip/list/ongoing*') ? 'active' : ''}}">{{translate(ONGOING)}}</a>
            </li>
            <li class="nav-item">
                <a href="{{route('admin.trip.index', [COMPLETED])}}" class="nav-link rating-review__info text-capitalize {{Request::is('admin/trip/list/completed*') ? 'active' : ''}}">{{translate(COMPLETED)}}</a>
            </li>
            <li class="nav-item">
                <a href="{{route('admin.trip.index', [CANCELLED])}}" class="nav-link rating-review__info text-capitalize {{Request::is('admin/trip/list/cancelled*') ? 'active' : ''}}">{{translate(CANCELLED)}}</a>
            </li>
            <li class="nav-item">
                <a href="{{route('admin.trip.index', [RETURNING])}}" class="nav-link rating-review__info text-capitalize {{Request::is('admin/trip/list/returning*') ? 'active' : ''}}">{{translate(RETURNING)}}</a>
            </li>
            <li class="nav-item">
                <a href="{{route('admin.trip.index', [RETURNED])}}" class="nav-link rating-review__info text-capitalize {{Request::is('admin/trip/list/returned*') ? 'active' : ''}}">{{translate(RETURNED)}}</a>
            </li>
        </ul>
    </div>
</div>
