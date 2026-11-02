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
                            <p class="fs-12 text-muted mb-0">{{ translate('When on, customers see the service on the home screen and can order from active, open stores. Orders are prepaid by mobile money or E-Ride wallet. E-Ride keeps the delivery fee; partners receive the full food price.') }}</p>
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
                                <div class="fs-12 text-muted mt-1">{{ translate('After this, the order is cancelled and the customer is refunded to their wallet.') }}</div>
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
