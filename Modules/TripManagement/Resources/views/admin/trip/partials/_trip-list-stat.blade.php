<div class="row g-2">
    @foreach([PENDING, ACCEPTED, ONGOING, COMPLETED, CANCELLED, RETURNING, RETURNED, SCHEDULED] as $key => $tripStatus)
        <?php
        $icon = dynamicAsset('public/assets/admin-module/img/media/car' . ($key + 1) . '.png');
        if(in_array($tripStatus, [ONGOING, RETURNING])) {
            $icon = dynamicAsset('public/assets/admin-module/img/media/car3.png');
        }

        if (in_array($tripStatus, [COMPLETED, RETURNED])) {
            $icon = dynamicAsset('public/assets/admin-module/img/media/car4.png');
        }

        if ($tripStatus == SCHEDULED) {
            $icon = dynamicAsset('public/assets/admin-module/img/media/shedule-cars.png');
        }
        ?>
        <a href="{{ route('admin.trip.index', $tripStatus) }}" class="col-lg-3 col-md-4 col-sm-6">
            <div class="card border analytical_data flex-grow-1">
                <div class="card-body">
                    <div class="d-flex justify-content-end mb-2">
                        <img width="40" src="{{ $icon }}" class="dark-support" alt="">
                    </div>
                    <h6 class="text-primary mb-2 text-capitalize">{{translate($tripStatus)}}</h6>
                    <h3 class="fs-27">{{$trip_counts->firstWhere('current_status', $tripStatus)?->total_records + 0}}</h3>
                </div>
            </div>
        </a>
    @endforeach
</div>
