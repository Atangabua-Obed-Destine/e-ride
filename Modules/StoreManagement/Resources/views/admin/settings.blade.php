@extends('adminmodule::layouts.master')

@section('title', translate('Food_&_Shops_Settings'))

@section('content')
    <div class="main-content">
        <div class="container-fluid" style="max-width: 860px">
            <h2 class="fs-22 mb-3">{{ translate('Food_&_Shops_Settings') }}</h2>
            @if ($errors->any())
                <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <form action="{{ route('admin.store-setting.update') }}" method="POST">
                @csrf @method('PUT')
                <div class="card mb-3">
                    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div class="flex-grow-1" style="min-width: 240px">
                            <h5 class="mb-1">{{ translate('Food_&_Shops_in_the_customer_app') }}</h5>
                            <p class="fs-12 text-muted mb-0">{{ translate('When on, customers see the service on the home screen and can order from active, open stores. The store confirms each order, then the customer pays by Mobile Money. E-Ride keeps the delivery fee; partners receive the full food price.') }}</p>
                        </div>
                        <label class="switcher">
                            <input type="hidden" name="status" value="0">
                            <input class="switcher_input" type="checkbox" name="status" value="1" {{ $settings['status'] ? 'checked' : '' }}>
                            <span class="switcher_control"></span>
                        </label>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label">{{ translate('Name_shown_in_the_app') }}</label>
                                <input type="text" name="service_label" class="form-control" required maxlength="40" value="{{ $settings['service_label'] }}">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">{{ translate('Partner_must_accept_within_(minutes)') }}</label>
                                <input type="number" name="partner_response_minutes" class="form-control" required min="2" max="60" value="{{ $settings['partner_response_minutes'] }}">
                                <div class="fs-12 text-muted mt-1">{{ translate('After this, the order is cancelled. The customer has not paid yet.') }}</div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">{{ translate('Call_a_rider_before_food_is_ready_(minutes)') }}</label>
                                <input type="number" name="rider_lead_minutes" class="form-control" required min="0" max="60" value="{{ $settings['rider_lead_minutes'] }}">
                                <div class="fs-12 text-muted mt-1">{{ translate('A rider is requested automatically this long before the promised ready time, or as soon as the partner taps Ready.') }}</div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">{{ translate('Weight_used_for_the_delivery_fare_(kg)') }}</label>
                                <input type="number" name="parcel_weight" class="form-control" required min="0.1" max="100" step="0.1" value="{{ $settings['parcel_weight'] }}">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div class="flex-grow-1" style="min-width: 240px">
                            <h5 class="mb-1">{{ translate('Refunds') }}</h5>
                            <p class="fs-12 text-muted mb-0">{{ translate('When on, a paid order that is cancelled (by you, or by the store when no rider comes) is refunded to the customer E-Ride wallet, and the customer is told so. Needs the wallet switched on in Ways to pay. When off, nothing is refunded or promised: you are alerted and your team settles with the customer.') }}</p>
                            @if($settings['refund_status'] && !\Modules\TripManagement\Lib\PaymentWays::wallet())
                                <p class="fs-12 text-danger mb-0 mt-1">{{ translate('The wallet is switched off in Ways to pay, so refunds are off too.') }}</p>
                            @endif
                        </div>
                        <label class="switcher">
                            <input type="hidden" name="refund_status" value="0">
                            <input class="switcher_input" type="checkbox" name="refund_status" value="1" {{ $settings['refund_status'] ? 'checked' : '' }}>
                            <span class="switcher_control"></span>
                        </label>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                            <div class="flex-grow-1" style="min-width: 240px">
                                <h5 class="mb-1">{{ translate('The_shop_brings_it') }}</h5>
                                <p class="fs-12 text-muted mb-0">{{ translate('When on, a store can choose to send its own staff to a customer close by instead of an E-Ride rider. The store sets its own fee (never more than a rider would cost), the customer pays it with the order, and gives a code at the door.') }}</p>
                            </div>
                            <label class="switcher">
                                <input type="hidden" name="own_delivery_status" value="0">
                                <input class="switcher_input" type="checkbox" name="own_delivery_status" value="1" {{ $settings['own_delivery_status'] ? 'checked' : '' }}>
                                <span class="switcher_control"></span>
                            </label>
                        </div>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label">{{ translate('Farthest_a_store_may_go_(km)') }}</label>
                                <input type="number" name="own_delivery_max_km" class="form-control" required min="0.1" max="20" step="0.1" value="{{ $settings['own_delivery_max_km'] }}">
                                <div class="fs-12 text-muted mt-1">{{ translate('By road, from the store to the customer. Beyond this, only riders deliver.') }}</div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">{{ translate('E-Ride_share_of_the_store_fee_(%)') }}</label>
                                <input type="number" name="own_delivery_share_percent" class="form-control" required min="0" max="100" step="0.5" value="{{ $settings['own_delivery_share_percent'] }}">
                                <div class="fs-12 text-muted mt-1">{{ translate('0 = the store keeps its whole delivery fee.') }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                            <div class="flex-grow-1" style="min-width: 240px">
                                <h5 class="mb-1">{{ translate('My_riders') }}</h5>
                                <p class="fs-12 text-muted mb-0">{{ translate('When on, a store can keep a list of the E-Ride riders it trusts. Its deliveries call them first, or only them if the store chooses so. A store adds riders who already delivered for it, or by phone number; the rider is told and can leave.') }}</p>
                            </div>
                            <label class="switcher">
                                <input type="hidden" name="my_riders_status" value="0">
                                <input class="switcher_input" type="checkbox" name="my_riders_status" value="1" {{ $settings['my_riders_status'] ? 'checked' : '' }}>
                                <span class="switcher_control"></span>
                            </label>
                        </div>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label">{{ translate('Call_the_store_riders_alone_for_(seconds)') }}</label>
                                <input type="number" name="my_riders_first_seconds" class="form-control" required min="20" max="600" value="{{ $settings['my_riders_first_seconds'] }}">
                                <div class="fs-12 text-muted mt-1">{{ translate('For "my riders first": after this, every rider nearby is called. It can take up to a minute more.') }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                            <div class="flex-grow-1" style="min-width: 240px">
                                <h5 class="mb-1">{{ translate('Resellers') }}</h5>
                                <p class="fs-12 text-muted mb-0">{{ translate('People who do not own a shop share a store\'s link with their code. When a customer who tapped it orders from that store, the reseller earns the store\'s rate on the items (never the delivery) once the order is delivered. It comes out of the store\'s earning, and E-Ride pays resellers by Mobile Money.') }}</p>
                            </div>
                            <label class="switcher">
                                <input type="hidden" name="reseller_status" value="0">
                                <input class="switcher_input" type="checkbox" name="reseller_status" value="1" {{ $settings['reseller_status'] ? 'checked' : '' }}>
                                <span class="switcher_control"></span>
                            </label>
                        </div>
                        <div class="row g-3">
                            <div class="col-sm-3">
                                <label class="form-label">{{ translate('Lowest_rate_(%)') }}</label>
                                <input type="number" name="reseller_min_percent" class="form-control" required min="0" max="100" step="0.5" value="{{ $settings['reseller_min_percent'] }}">
                            </div>
                            <div class="col-sm-3">
                                <label class="form-label">{{ translate('Highest_rate_(%)') }}</label>
                                <input type="number" name="reseller_max_percent" class="form-control" required min="1" max="100" step="0.5" value="{{ $settings['reseller_max_percent'] }}">
                            </div>
                            <div class="col-sm-3">
                                <label class="form-label">{{ translate('A_link_counts_for_(days)') }}</label>
                                <input type="number" name="reseller_window_days" class="form-control" required min="1" max="60" value="{{ $settings['reseller_window_days'] }}">
                            </div>
                            <div class="col-sm-3">
                                <label class="form-label">{{ translate('Pay_from_(FCFA)') }}</label>
                                <input type="number" name="reseller_min_payout" class="form-control" required min="0" step="100" value="{{ (int)$settings['reseller_min_payout'] }}">
                            </div>
                        </div>
                        <div class="fs-12 text-muted mt-2">{{ translate('The last link a customer tapped wins. No commission on a reseller\'s own orders. Stores choose their rate between the lowest and highest.') }}
                            <a href="{{ route('admin.store-reseller.index') }}">{{ translate('Resellers_and_payouts') }}</a></div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="mb-1">{{ translate('Delivery_fee') }}</h5>
                        <p class="fs-12 text-muted mb-2">
                            {{ translate('The delivery fee uses the parcel fare of the category below, for the weight above, in the store zone. Set it in Parcel Delivery Fare Setup.') }}
                        </p>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="badge bg-light text-dark fs-12">{{ $category?->name ?? translate('Category_missing') }}</span>
                            @can('fare_view')
                                <a href="{{ route('admin.fare.parcel.index') }}" class="btn btn-sm btn-outline-primary">{{ translate('Open_parcel_fare_setup') }}</a>
                            @endcan
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">{{ translate('Save') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
