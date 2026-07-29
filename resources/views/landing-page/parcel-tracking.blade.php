@extends('landing-page.layouts.master')
@section('title', 'Parcel Tracking')

@section('content')
    <section class="py-5">
        <div class="container">
            <h4 class="text-center mb-4">{{translate("Parcel Tracking")}}</h4>
            <div class="parcel-tracking-wrapper">
                <div class="parcel-tracking-left">
                    <div class="product-media mb-20">
                        <div class="img">
                            <img src="{{ dynamicAsset(path: 'public/assets/admin-module/img/parcel-box.png') }}" alt="">
                            <div class="fs-14">{{translate("Parcel")}}</div>
                        </div>
                        <div class="w-0 flex-grow-1">
                            <div class="d-flex flex-column align-items-start gap-1 fs-14">
                                <div>{{$trip?->parcel?->parcelCategory?->name ?? "N/A"}}</div>
                                <div class="text-dark leading-19px fw-medium">
                                    {{translate("Tracking ID")}} #{{$trip->ref_id}}
                                </div>
                                <div class="fs-12">{{ date('d F Y, h:i a', strtotime($trip->created_at)) }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="fs-14 text--base fw-semibold mb-2">{{translate("Trip Details")}}</div>
                    <ul class="trip-details-address mb-20">
                        <li>
                            <img width="18" src="{{dynamicAsset(path: 'public/assets/admin-module/img/svg/gps.svg')}}" class="svg"
                                 alt="">
                            <span class="w-0 flex-grow-1">
                                {{$trip->coordinate->pickup_address}}
                            </span>
                        </li>
                        <li>
                            <img width="18" src="{{dynamicAsset(path: 'public/assets/admin-module/img/svg/map-nav.svg')}}"
                                 class="svg" alt="">
                            <span class="w-0 flex-grow-1">
                                {{$trip->coordinate->destination_address}}
                            </span>
                        </li>
                    </ul>
                    <div class="fs-14 text--base fw-semibold mb-2">{{translate("Time Line")}}</div>
                    <div class="timeline">
                        @if($trip?->tripStatus?->accepted)
                            <div
                                class="item {{$trip->current_status == ACCEPTED || $trip->current_status == ONGOING || $trip->current_status == COMPLETED || $trip->current_status == CANCELLED || $trip->current_status == RETURNING || $trip->current_status == RETURNED ? "active" : "" }}">
                                <h6 class="img">
                                    <img class="svg"
                                         src="{{ dynamicAsset('public/landing-page/assets/img/parcel-tracking/confirmed.svg') }}"
                                         alt="">
                                </h6>
                                <div class="w-0 flex-grow-1">
                                    <h6 class="fw-semibold">{{translate("Confirmed")}}</h6>
                                    @if($trip?->tripStatus?->accepted)
                                        <div>
                                            <img class="svg"
                                                 src="{{ dynamicAsset('public/landing-page/assets/img/parcel-tracking/fi-rr-watch.svg') }}"
                                                 alt="">
                                            {{ date('h:i a, d F Y', strtotime($trip?->tripStatus?->accepted))}}
                                        </div>
                                    @endif
                                </div>
                                <i class="bi bi-check-circle-fill"></i>
                            </div>
                        @endif
                        @if($trip?->tripStatus?->ongoing)
                            <div
                                class="item {{$trip->current_status == ONGOING || $trip->current_status == COMPLETED || $trip->current_status == CANCELLED || $trip->current_status == RETURNING || $trip->current_status == RETURNED ? "active" : "" }}">
                                <h6 class="img">
                                    <img class="svg"
                                         src="{{ dynamicAsset('public/landing-page/assets/img/parcel-tracking/on-the-way.svg') }}"
                                         alt="">
                                </h6>
                                <div class="w-0 flex-grow-1">
                                    <h6 class="fw-semibold">{{translate("On The Way")}}</h6>
                                    @if($trip?->tripStatus?->ongoing)
                                        <div>
                                            <img class="svg"
                                                 src="{{ dynamicAsset('public/landing-page/assets/img/parcel-tracking/fi-rr-watch.svg') }}"
                                                 alt="">
                                            {{ date('h:i a, d F Y', strtotime($trip?->tripStatus?->ongoing))}}
                                        </div>
                                    @endif
                                </div>
                                <i class="bi bi-check-circle-fill"></i>
                            </div>
                        @endif
                        @if($trip->current_status == ACCEPTED || $trip->current_status == ONGOING || $trip->current_status == COMPLETED)
                            <div class="item {{$trip->current_status == COMPLETED ? "active" : "" }}">
                                <h6 class="img">
                                    <img class="svg"
                                         src="{{ dynamicAsset('public/landing-page/assets/img/parcel-tracking/delivery.svg') }}"
                                         alt="">
                                </h6>
                                <div class="w-0 flex-grow-1">
                                    <h6 class="fw-semibold">{{translate("Parcel Delivered")}}</h6>
                                    @if($trip?->tripStatus?->completed)
                                        <div>
                                            <img class="svg"
                                                 src="{{ dynamicAsset('public/landing-page/assets/img/parcel-tracking/fi-rr-watch.svg') }}"
                                                 alt="">
                                            {{ date('h:i a, d F Y', strtotime($trip?->tripStatus?->completed))}}
                                        </div>
                                    @endif
                                </div>
                                <i class="bi bi-check-circle-fill"></i>
                            </div>
                        @endif
                        @if($trip->current_status == CANCELLED || $trip->current_status == RETURNING || $trip->current_status == RETURNED)
                            <div
                                class="item {{$trip->current_status == CANCELLED || $trip->current_status == RETURNING || $trip->current_status == RETURNED ? "active" : "" }}">
                                <h6 class="img">
                                    <img class="svg"
                                         src="{{ dynamicAsset('public/landing-page/assets/img/parcel-tracking/cancelled.svg') }}"
                                         alt="">
                                </h6>
                                <div class="w-0 flex-grow-1">
                                    <h6 class="fw-semibold">{{translate("Parcel Cancelled")}}</h6>
                                    @if($trip?->tripStatus?->cancelled)
                                        <div>
                                            <img class="svg"
                                                 src="{{ dynamicAsset('public/landing-page/assets/img/parcel-tracking/fi-rr-watch.svg') }}"
                                                 alt="">
                                            {{ date('h:i a, d F Y', strtotime($trip?->tripStatus?->cancelled))}}
                                        </div>
                                    @endif
                                </div>
                                <i class="bi bi-check-circle-fill"></i>
                            </div>
                            @if($trip?->tripStatus?->returning)
                                <div
                                    class="item {{$trip->current_status == RETURNING || $trip->current_status == RETURNED ? "active" : "" }}">
                                    <h6 class="img">
                                        <img class="svg"
                                             src="{{ dynamicAsset('public/landing-page/assets/img/parcel-tracking/returning.svg') }}"
                                             alt="">
                                    </h6>
                                    <div class="w-0 flex-grow-1">
                                        <h6 class="fw-semibold">{{translate("Parcel Returning")}}</h6>
                                        @if($trip?->tripStatus?->returning)
                                            <div>
                                                <img class="svg"
                                                     src="{{ dynamicAsset('public/landing-page/assets/img/parcel-tracking/fi-rr-watch.svg') }}"
                                                     alt="">
                                                {{ date('h:i a, d F Y', strtotime($trip?->tripStatus?->returning))}}
                                            </div>
                                        @endif
                                    </div>
                                    <i class="bi bi-check-circle-fill"></i>
                                </div>
                            @endif
                            @if($trip?->tripStatus?->returned)
                                <div class="item {{$trip->current_status == RETURNED ? "active" : "" }}">
                                    <h6 class="img">
                                        <img class="svg"
                                             src="{{ dynamicAsset('public/landing-page/assets/img/parcel-tracking/returned.svg') }}"
                                             alt="">
                                    </h6>
                                    <div class="w-0 flex-grow-1">
                                        <h6 class="fw-semibold">{{translate("Parcel Returned")}}</h6>
                                        @if($trip?->tripStatus?->returned)
                                            <div>
                                                <img class="svg"
                                                     src="{{ dynamicAsset('public/landing-page/assets/img/parcel-tracking/fi-rr-watch.svg') }}"
                                                     alt="">
                                                {{ date('h:i a, d F Y', strtotime($trip?->tripStatus?->returned))}}
                                            </div>
                                        @endif
                                    </div>
                                    <i class="bi bi-check-circle-fill"></i>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
                <div class="parcel-tracking-right">
                    <div class="parcel-tracking-driver-info mb-20">
                        <div class="fs-14 text--base fw-medium mb-0">{{translate("Driver Details")}}</div>
                        @if($trip?->driver)
                            <div class="row">
                                <div class="col-6">
                                    <div class="text-dark">{{$trip?->driver?->full_name ?? "N/A"}}</div>
                                    <small class="d-flex align-items-center gap-1"><i
                                            class="bi text--warning bi-star-fill"></i>{{round($trip?->driver?->received_reviews_avg_rating, 1)}}
                                    </small>
                                </div>
                                <div class="col-6">
                                    <div
                                        class="text-dark">{{$trip?->vehicleCategory?->type? ucwords($trip?->vehicleCategory?->type): "N/A"}}
                                        : {{$trip?->vehicle?->licence_plate_number?? "N/A"}}</div>
                                    <small>{{$trip?->vehicle?->model?->name?? "N/A"}}</small>
                                </div>
                            </div>
                        @else
                            <div class="row">
                                <div class="text-dark">{{ translate('Driver not available') }}</div>
                            </div>
                        @endif

                    </div>
                    <div class="parcel-fare-infos text-dark mb-20">
                        <ul>
                            <li>
                                <span class="text--base d-flex gap-2 align-items-center">
                                    <img class="text--base-50 svg"
                                         src="{{ dynamicAsset('public/landing-page/assets/img/parcel-tracking/receipt-minus.svg') }}"
                                         alt="">
                                    <span class="text-base-dark fs-16 fw-semibold">{{translate("Total")}}</span>
                                </span>
                                <span class="fs-16 fw-semibold total">{{set_currency_symbol($trip->paid_fare)}}</span>
                            </li>
                            <li class="payment-sender-text">
                                <span class="text--base d-flex gap-2 align-items-center">
                                    <img class="text--base-50 svg"
                                         src="{{ dynamicAsset('public/landing-page/assets/img/parcel-tracking/cards.svg') }}"
                                         alt="">
                                    {{translate("Payment By")}} {{ucwords($trip?->parcel?->payer)}}
                                </span>
                                <span class="text-base-dark">{{$trip->payment_method}}</span>
                            </li>
                        </ul>
                    </div>
                    <div class="parcel-tracking-driver-info">
                        <div class="row">
                            <div class="col-6">
                                <div class="fs-14 text--base fw-medium mb-0">{{translate("Sender Details")}}</div>
                                <div
                                    class="text-dark">{{$trip?->parcelUserInfo?->firstWhere('user_type',SENDER)?->name ?? "N/A"}}</div>
                                <small class="d-flex align-items-center gap-1"
                                       dir="ltr">{{$trip?->parcelUserInfo?->firstWhere('user_type',SENDER)?->contact_number ?? "N/A"}}</small>
                            </div>
                            <div class="col-6">
                                <div class="fs-14 text--base fw-medium mb-0">{{translate("Receiver Details")}}</div>
                                <div
                                    class="text-dark">{{$trip?->parcelUserInfo?->firstWhere('user_type',RECEIVER)?->name ?? "N/A"}}</div>
                                <small class="d-flex align-items-center gap-1"
                                       dir="ltr">{{$trip?->parcelUserInfo?->firstWhere('user_type',RECEIVER)?->contact_number ?? "N/A"}}</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

@endsection
