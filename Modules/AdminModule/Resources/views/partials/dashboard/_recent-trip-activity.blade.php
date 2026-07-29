<ul class="common-list">
    @php($count = 0)
    @forelse($trips as $trip)
        @if ($count = 0)
            <li class="pt-0 d-flex flex-wrap gap-2 align-items-center justify-content-between">
        @endif
        @php($count++)
        <li class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <div class="media align-items-center gap-3">
                <a href="{{ route('admin.customer.show', ['id' => $trip->customer_id]) }}">
                    <div class="avatar avatar-lg rounded position-relative">
                        <img src="{{ onErrorImage(
                                                $trip?->vehicleCategory?->image,
                                                dynamicStorage('storage/app/public/vehicle/category') . '/' . $trip?->vehicleCategory?->image,
                                                dynamicAsset('public/assets/admin-module/img/media/car.png'),
                                                'vehicle/category/',
                                            ) }}"
                             class="dark-support rounded custom-box-size"
                             alt="" style="--size: 42px">
                        @if($trip->ride_request_type == SCHEDULED)
                            <img src="{{dynamicAsset('public/assets/admin-module/img/svg/schedule_clock_blue.svg')}}" class="svg position-absolute top-minus4 right-minus4" alt="">
                        @endif
                    </div>
                </a>

                <div class="media-body ">
                    <a href="{{ route('admin.trip.show', ['id' => $trip->id, 'page' => 'summary']) }}">
                        <h5 class="">{{ translate('trip') }}# {{ $trip->ref_id }}</h5>
                    </a>
                    @php($time_format = getSession('time_format'))
                    <p>{{ date(DASHBOARD_DATE_FORMAT, strtotime($trip->created_at)) }}</p>
                </div>
            </div>
            @if ($trip->current_status == PENDING || $trip->current_status == 'completed')
                <span
                    class="badge rounded-pill text-capitalize py-2 px-3 badge-success">{{ translate($trip->current_status) }}</span>
            @elseif($trip->current_status == 'cancelled' || $trip->current_status == 'failed' || $trip->current_status == 'rejected')
                <span
                    class="badge rounded-pill text-capitalize py-2 px-3 badge-danger">{{ translate($trip->current_status) }}</span>
            @else
                <span class="badge rounded-pill text-capitalize py-2 px-3 badge-info">{{ translate($trip->current_status) }}</span>
            @endif
        </li>
    @empty
    @endforelse
</ul>
