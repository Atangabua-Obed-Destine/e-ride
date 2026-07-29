<button type="button" class="btn customer-back-btn">
    <img src="{{dynamicAsset('public/assets/admin-module/img/maps/left-arrow.svg')}}"
         class="svg" alt=""> {{ translate('Customer List') }}
</button>
<div class="customer-details-media">
    <img src="{{onErrorImage(
                $customer?->profile_image,
                dynamicStorage('storage/app/public/customer/profile') . '/' . $customer?->profile_image,
                dynamicAsset('public/assets/admin-module/img/avatar/avatar.png'),
                'customer/profile/',
            )}}"
         alt="">
    <div class="customer-details-media-content">
        <div class="d-flex gap-2">
            <h6>
                <a href="{{route('admin.customer.show', ['id' => $customer->id])}}">
                    {{$customer?->first_name . ' ' . $customer?->last_name}}
                </a>
            </h6>
        </div>
        <small>{{$customer?->phone ?? "N/A"}}</small>
    </div>
</div>
<hr>
<div class="overflow-y-auto max-h-100vh-360">
    <div class="customer-details-media-info-card-body p-0 ">
        <div class="border rounded">
            <div class="customer-details-media-info-card">
                @if($trip)
                    <div class="customer-details-media-info-card-header">
                        <span>{{ translate('Ongoing Trip') }}</span>
                        <span>
                  ID #{{ $trip->ref_id }}
                  <a href="{{route('admin.trip.show', ['type' => ALL, 'id' => $trip->id, 'page' => 'summary'])}}"
                     target="_blank">
                            <img
                                src="{{dynamicAsset('public/assets/admin-module/img/maps/up-right-arrow-square.svg')}}"
                                class="svg" alt="">
                  </a>
            </span>
                    </div>
                    <div class="customer-details-media-info-card-body">
                        <ul class="customer-details-media-info-card-body-list">
                            <li>
                                <img src="{{dynamicAsset('public/assets/admin-module/img/maps/gps.svg')}}"
                                     alt="" class="svg">
                                <span class="value">{{$trip?->coordinate?->pickup_address}}</span>
                            </li>
                            <li>
                                <img
                                    src="{{dynamicAsset('public/assets/admin-module/img/maps/paper-plane-2.svg')}}"
                                    alt="" class="svg">
                                <span class="value">{{$trip?->coordinate?->destination_address}}</span>
                            </li>
                        </ul>
                    </div>
                @endif
                <div class="customer-details-media-info-card-header">
                    <span>{{ translate('Details') }}</span>
                </div>
                @if($trip)
                    <div class="rounded p-3">
                        <ul class="customer-details-media-info-card-body-list">
                            <li>
                                <span class="key">{{translate("Trip Type")}}</span>
                                <span>:</span>
                                <span
                                    class="value title-color">{{ translate($trip?->type) . ' - '. $trip?->vehicle?->category?->name }}</span>
                            </li>
                            <li>
                                <span class="key">{{translate("Driver")}}</span>
                                <span>:</span>
                                <span
                                    class="value title-color">{{$trip?->driver?->full_name ?? ($trip?->driver?->first_name && $trip?->driver?->last_name ? $trip?->driver?->first_name . $trip?->driver?->last_name : "N/A" )}}</span>
                            </li>
                            <li>
                                <span class="key">{{translate("Driver Phone")}}</span>
                                <span>:</span>
                                <span class="value title-color">{{$trip?->driver?->phone}}</span>
                            </li>
                            <li>
                                <span class="key">{{translate("Vehicle Number")}}</span>
                                <span>:</span>
                                <span
                                    class="value title-color">{{$trip->driver?->vehicle?->licence_plate_number}}</span>
                            </li>
                            <li>
                                <span class="key">{{translate("Vehicle Brand")}}</span>
                                <span>:</span>
                                <span class="value title-color">{{ $trip->driver?->vehicle?->brand?->name }}</span>
                            </li>
                            <li>
                                <span class="key">{{translate("Vehicle Model")}}</span>
                                <span>:</span>
                                <span class="value title-color">{{$trip->driver?->vehicle?->model?->name}}</span>
                            </li>
                        </ul>
                    </div>
                    @if($trip?->customerSafetyAlertPending)
                        <div class="p-3">
                            <div class="bg-danger-light rounded mb-2">
                                <div class="border-bottom p-3 border-white"><span class="fw-bold">Safety Alert</span>
                                    ({{ $trip?->customerSafetyAlertPending->number_of_alert }})
                                </div>
                                @if($trip?->customerSafetyAlertPending?->reason || $trip?->customerSafetyAlertPending?->comment)
                                    <div class="p-3">
                                        <ol class="d-flex flex-column gap-2 mb-0">
                                            @if($trip?->customerSafetyAlertPending?->reason)
                                                @foreach($trip?->customerSafetyAlertPending?->reason as $reason)
                                                    <li>{{ $reason }}</li>
                                                @endforeach
                                            @endif
                                            @if($trip?->customerSafetyAlertPending?->comment)
                                                <li>{{ $trip?->customerSafetyAlertPending?->comment }}</li>
                                            @endif
                                        </ol>
                                    </div>
                                @endif
                            </div>
                            <div class="w-100 bg-white pb-2">
                                <button type="button"
                                        class="btn btn-primary fw-semibold text-uppercase w-100 d-flex justify-content-center markAsSolvedBtn"
                                        data-url="{{ route('admin.safety-alert.ajax-mark-as-solved', $trip?->customerSafetyAlertPending->id) }}">
                                    {{ translate('Mark as Solved') }}
                                </button>
                            </div>
                        </div>
                    @endif
                @else
                    <div class=" rounded p-3">
                        <ul class="customer-details-media-info-card-body-list">
                            <li>
                                <span class="key">{{translate("Joined")}}</span>
                                <span>:</span>
                                <span
                                    class="value title-color">{{ date('d M Y',strtotime($customer?->created_at)) }}</span>
                            </li>
                            <li>
                                <span class="key">{{translate("Total Trip")}}</span>
                                <span>:</span>
                                <span class="value title-color">{{ $customer?->customerTrips?->count() }}</span>
                            </li>
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    </div>
    @if(count($otherTrips) > 0)
        <div class="customer-details-media mb-3">
            <h5>{{ translate('Unresolved Alerts from Previous') }}</h5>
        </div>
        @foreach($otherTrips as $otherTrip)
            <div class="customer-details-media-info-card-body p-0 my-2">
                <div class="border rounded">
                    <div class="customer-details-media-info-card">
                        @if($otherTrip)
                            <div class="customer-details-media-info-card-header">
                                <span class="text-capitalize">{{ $otherTrip?->current_status }} {{ translate( 'Trip') }}</span>
                                <span>
                  ID #{{ $otherTrip->ref_id }}
                  <a href="{{route('admin.trip.show', ['type' => ALL, 'id' => $otherTrip->id, 'page' => 'summary'])}}"
                     target="_blank">
                            <img
                                src="{{dynamicAsset('public/assets/admin-module/img/maps/up-right-arrow-square.svg')}}"
                                class="svg" alt="">
                  </a>
            </span>
                            </div>
                            <div class="customer-details-media-info-card-body">
                                <ul class="customer-details-media-info-card-body-list">
                                    <li>
                                        <img src="{{dynamicAsset('public/assets/admin-module/img/maps/gps.svg')}}"
                                             alt="" class="svg">
                                        <span class="value">{{$otherTrip?->coordinate?->pickup_address}}</span>
                                    </li>
                                    <li>
                                        <img
                                            src="{{dynamicAsset('public/assets/admin-module/img/maps/paper-plane-2.svg')}}"
                                            alt="" class="svg">
                                        <span class="value">{{$otherTrip?->coordinate?->destination_address}}</span>
                                    </li>
                                </ul>
                            </div>
                        @endif
                        <div class="customer-details-media-info-card-header">
                            <span>{{ translate('Details') }}</span>
                        </div>
                        @if($otherTrip)
                            <div class=" rounded p-3">
                                <ul class="customer-details-media-info-card-body-list">
                                    <li>
                                        <span class="key">{{translate("Trip Type")}}</span>
                                        <span>:</span>
                                        <span
                                            class="value title-color">{{ translate($otherTrip?->type) . ' - '. $otherTrip?->vehicle?->category?->name }}</span>
                                    </li>
                                    <li>
                                        <span class="key">{{translate("Driver")}}</span>
                                        <span>:</span>
                                        <span
                                            class="value title-color">{{$otherTrip?->driver?->full_name ?? ($otherTrip?->driver?->first_name && $otherTrip?->driver?->last_name ? $otherTrip?->driver?->first_name . $otherTrip?->driver?->last_name : "N/A" )}}</span>
                                    </li>
                                    <li>
                                        <span class="key">{{translate("Driver Phone")}}</span>
                                        <span>:</span>
                                        <span class="value title-color">{{$otherTrip?->driver?->phone}}</span>
                                    </li>
                                </ul>
                            </div>
                            @if($otherTrip?->customerSafetyAlertPending)
                                <div class="p-3">
                                    <div class="bg-danger-light rounded mb-2">
                                        <div class="border-bottom p-3 border-white"><span
                                                class="fw-bold">Safety Alert</span>
                                            ({{ $otherTrip?->customerSafetyAlertPending->number_of_alert }})
                                        </div>
                                        @if($otherTrip?->customerSafetyAlertPending?->reason || $otherTrip?->customerSafetyAlertPending?->comment)
                                            <div class="p-3">
                                                <ol class="d-flex flex-column gap-2 mb-0">
                                                    @if($otherTrip?->customerSafetyAlertPending?->reason)
                                                        @foreach($otherTrip?->customerSafetyAlertPending?->reason as $reason)
                                                            <li>{{ $reason }}</li>
                                                        @endforeach
                                                    @endif
                                                    @if($otherTrip?->customerSafetyAlertPending?->comment)
                                                        <li>{{ $otherTrip?->customerSafetyAlertPending?->comment }}</li>
                                                    @endif
                                                </ol>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="w-100 bg-white pb-2">
                                        <button type="button"
                                                class="btn btn-primary fw-semibold text-uppercase w-100 d-flex justify-content-center markAsSolvedBtn"
                                                data-url="{{ route('admin.safety-alert.ajax-mark-as-solved', $otherTrip?->customerSafetyAlertPending->id) }}">
                                            {{ translate('Mark as Solved') }}
                                        </button>
                                    </div>
                                </div>
                            @endif
                        @else
                            <div class=" rounded p-3">
                                <ul class="customer-details-media-info-card-body-list">
                                    <li>
                                        <span class="key">{{translate("Joined")}}</span>
                                        <span>:</span>
                                        <span
                                            class="value title-color">{{ date('d M Y',strtotime($customer?->created_at)) }}</span>
                                    </li>
                                    <li>
                                        <span class="key">{{translate("Total Trip")}}</span>
                                        <span>:</span>
                                        <span class="value title-color">{{ $customer?->customerTrips?->count() }}</span>
                                    </li>
                                </ul>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    @endif
</div>



