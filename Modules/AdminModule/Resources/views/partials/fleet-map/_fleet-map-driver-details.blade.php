<button type="button" class="btn customer-back-btn">
    <img src="{{dynamicAsset('public/assets/admin-module/img/maps/left-arrow.svg')}}"
         class="svg" alt=""> {{ translate('Driver List') }}
</button>
<div class="customer-details-media">
    <img src="{{onErrorImage(
                $driver?->profile_image,
                dynamicStorage('storage/app/public/driver/profile') . '/' . $driver?->profile_image,
                dynamicAsset('public/assets/admin-module/img/avatar/avatar.png'),
                'driver/profile/',
            )}}"
         alt="">
    <div class="customer-details-media-content">
        <div class="d-flex gap-2">
            <h6>
                <a href="{{route('admin.driver.show', ['id' => $driver->id])}}">
                    {{$driver?->first_name . ' ' . $driver?->last_name}}
                </a>
            </h6>
            <div
                class="badge badge-pill badge-info ms-2">{{Carbon\Carbon::parse($driver->created_at)->diffInMonths(Carbon\Carbon::now())<6 ? translate("New") : ""}}</div>

        </div>
        <div class="my-2 d-flex gap-2">
            <div
                class="badge badge-success">{{translate("Level")}}
                - {{$driver->level->name ?? translate('no_level_found')}}</div>
            <div class="gap-1 ms-2">
                <i class="bi bi-star-fill text-warning"></i>
                {{ number_format($driver->receivedReviews->avg('rating'), 1) }}
            </div>
        </div>
        <small>{{$driver?->phone ?? "N/A"}}</small>
    </div>
</div>
<hr>
<div class="overflow-y-auto max-h-100vh-360">
    <div class="customer-details-media-info-card-body p-0 ">
        <div class="border rounded p-3">
            <ul class="customer-details-media-info-card-body-list">
                <li>
                    <span class="key">{{translate("Joining Date")}}</span>
                    <span>:</span>
                    <span class="value title-color">{{ date('d M Y',strtotime($driver->created_at)) }}</span>
                </li>
                <li>
                    <span class="key">{{translate("Service")}}</span>
                    <span>:</span>
                    <span class="value title-color">
                     @if($driver?->driverDetails?->service)
                            @if(in_array('ride_request',$driver?->driverDetails?->service) && in_array('parcel',$driver?->driverDetails?->service))
                                {{translate("Ride Request")}}, {{translate("Parcel")}}
                            @elseif(in_array('ride_request',$driver?->driverDetails?->service))
                                {{translate("Ride Request")}}
                            @elseif(in_array('parcel',$driver?->driverDetails?->service))
                                {{translate("Parcel")}}
                            @endif
                        @else
                            {{translate("Ride Request")}}, {{translate("Parcel")}}
                        @endif
                </span>
                </li>
                <li>
                    <span class="key">{{translate("Vehicle Category")}}</span>
                    <span>:</span>
                    <span class="value title-color">{{$driver?->vehicle?->category->name}}</span>
                </li>
                <li>
                    <span class="key">{{translate("Vehicle Number")}}</span>
                    <span>:</span>
                    <span class="value title-color">{{$driver?->vehicle?->licence_plate_number}}</span>
                </li>
                <li>
                    <span class="key">{{translate("Vehicle Brand")}}</span>
                    <span>:</span>
                    <span class="value title-color">{{ $driver?->vehicle?->brand?->name }}</span>
                </li>
                <li>
                    <span class="key">{{translate("Vehicle Model")}}</span>
                    <span>:</span>
                    <span class="value title-color">{{$driver?->vehicle?->model?->name}}</span>
                </li>
            </ul>
            @if($trip)
                <div class="bg-F6F6F6 rounded my-2">
                    <div class="customer-details-media-info-card shadow-none bg-input">
                        <div class="customer-details-media-info-card-header">
                            <span>{{translate("Ongoing Trip")}}</span>
                            <span>
                {{translate("ID")}} #{{$trip->ref_id}}
                <a href="{{route('admin.trip.show', ['type' => ALL, 'id' => $trip->id, 'page' => 'summary'])}}"
                   target="_blank">
                    <img
                        src="{{dynamicAsset('public/assets/admin-module/img/maps/up-right-arrow-square.svg')}}"
                        class="svg" alt="">
                </a>
            </span>
                        </div>
                        <div class="customer-details-media-info-card-body">
                            <ul class="customer-details-media-info-card-body-list border-list">
                                <li>
                                    <img src="{{dynamicAsset('public/assets/admin-module/img/maps/gps.svg')}}"
                                         alt="" class="svg">
                                    <span class="value">{{$trip?->coordinate?->pickup_address}}</span>
                                </li>
                                <li>
                                    <img
                                        src="{{dynamicAsset('public/assets/admin-module/img/maps/paper-plane-2.svg')}}"
                                        alt="" class="svg">
                                    <span class="value">{{$trip->coordinate->destination_address}}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                @if($trip?->driverSafetyAlertPending)
                    <div class="bg-danger-light rounded py-2">
                        <div class="border-bottom p-3 border-white"><span class="fw-bold">Safety Alert</span>
                            ({{ $trip?->driverSafetyAlertPending?->number_of_alert }})
                        </div>
                        @if($trip?->driverSafetyAlertPending?->reason || $trip?->driverSafetyAlertPending?->comment)
                            <div class="p-3">
                                <ol class="d-flex flex-column gap-2 mb-0">
                                    @if($trip?->driverSafetyAlertPending?->reason)
                                        @foreach($trip?->driverSafetyAlertPending?->reason as $reason)
                                            <li>{{ $reason }}</li>
                                        @endforeach
                                    @endif
                                    @if($trip?->driverSafetyAlertPending?->comment)
                                        <li>{{ $trip?->driverSafetyAlertPending?->comment }}</li>
                                    @endif
                                </ol>
                            </div>
                        @endif
                    </div>
                    <div class="w-100 bg-white py-2">
                        <button type="button"
                                class="btn btn-primary fw-semibold text-uppercase w-100 d-flex justify-content-center markAsSolvedBtn"
                                data-url="{{ route('admin.safety-alert.ajax-mark-as-solved', $trip?->driverSafetyAlertPending->id) }}">
                            {{ translate('Mark as Solved') }}
                        </button>
                    </div>
                @endif
            @endif
        </div>
    </div>
    @if(count($otherTrips) > 0)
        <div class="customer-details-media mb-3">
            <h5>{{ translate('Unresolved Alerts from Previous') }}</h5>
        </div>
        @foreach($otherTrips as $otherTrip)
            <div class="customer-details-media-info-card-body p-0 my-2">
                <div class="border rounded p-3">
                    <ul class="customer-details-media-info-card-body-list">
                        <li>
                            <span class="key">{{translate("Trip Id")}}</span>
                            <span>:</span>
                            <span>
                                {{translate("ID")}} #{{$otherTrip->ref_id}}
                                <a href="{{route('admin.trip.show', ['type' => ALL, 'id' => $otherTrip->id, 'page' => 'summary'])}}"
                                   target="_blank">
                                <img src="{{dynamicAsset('public/assets/admin-module/img/maps/up-right-arrow-square.svg')}}"
                                     class="svg"
                                     alt="">
                                </a>
                            </span>
                        </li>
                        <li>
                            <span class="key">{{translate("Service")}}</span>
                            <span>:</span>
                            <span class="value title-color">
                     @if($driver?->driverDetails?->service)
                                    @if(in_array('ride_request',$driver?->driverDetails?->service) && in_array('parcel',$driver?->driverDetails?->service))
                                        {{translate("Ride Request")}}, {{translate("Parcel")}}
                                    @elseif(in_array('ride_request',$driver?->driverDetails?->service))
                                        {{translate("Ride Request")}}
                                    @elseif(in_array('parcel',$driver?->driverDetails?->service))
                                        {{translate("Parcel")}}
                                    @endif
                                @else
                                    {{translate("Ride Request")}}, {{translate("Parcel")}}
                                @endif
                </span>
                        </li>
                    </ul>
                    @if($otherTrip?->driverSafetyAlertPending)
                        <div class="bg-danger-light rounded py-2 mt-2">
                            <div class="border-bottom p-3 border-white"><span
                                    class="fw-bold">Safety Alert</span>
                                ({{ $otherTrip?->driverSafetyAlertPending?->number_of_alert }})
                            </div>
                            @if($otherTrip?->driverSafetyAlertPending?->reason || $otherTrip?->driverSafetyAlertPending?->comment)
                                <div class="p-3">
                                    <ol class="d-flex flex-column gap-2 mb-0">
                                        @if($otherTrip?->driverSafetyAlertPending?->reason)
                                            @foreach($otherTrip?->driverSafetyAlertPending?->reason as $reason)
                                                <li>{{ $reason }}</li>
                                            @endforeach
                                        @endif
                                        @if($otherTrip?->driverSafetyAlertPending?->comment)
                                            <li>{{ $otherTrip?->driverSafetyAlertPending?->comment }}</li>
                                        @endif
                                    </ol>
                                </div>
                            @endif
                        </div>
                        <div class="w-100 bg-white py-2">
                            <button type="button"
                                    class="btn btn-primary fw-semibold text-uppercase w-100 d-flex justify-content-center markAsSolvedBtn"
                                    data-url="{{ route('admin.safety-alert.ajax-mark-as-solved', $otherTrip?->driverSafetyAlertPending->id) }}">
                                {{ translate('Mark as Solved') }}
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    @endif
</div>


