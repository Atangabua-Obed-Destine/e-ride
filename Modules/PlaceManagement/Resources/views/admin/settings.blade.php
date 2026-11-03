@extends('adminmodule::layouts.master')

@section('title', translate('Explore_Bamenda_Settings'))

@section('content')
    <div class="main-content">
        <div class="container-fluid" style="max-width: 860px">
            <h2 class="fs-22 mb-3">{{ translate('Explore_Bamenda') }}</h2>
            @include('placemanagement::admin.places._nav', ['active' => 'settings'])
            @if ($errors->any())
                <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <form action="{{ route('admin.place-setting.update') }}" method="POST">
                @csrf @method('PUT')
                <div class="card mb-3">
                    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div class="flex-grow-1" style="min-width: 240px">
                            <h5 class="mb-1">{{ translate('Explore_Bamenda_in_the_customer_app') }}</h5>
                            <p class="fs-12 text-muted mb-0">{{ translate('When on, customers can explore pharmacies, hospitals, banks, bus agencies, hotels and any category you add: your verified listings first, then top rated and nearby places from Google, each with Call, WhatsApp and Ride there.') }}</p>
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
                                <input type="text" name="explore_label" class="form-control" required maxlength="40" value="{{ $settings['explore_label'] }}">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">{{ translate('Name_of_the_hotels_category_in_older_apps') }}</label>
                                <input type="text" name="label" class="form-control" required maxlength="40" value="{{ $settings['label'] }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label">{{ translate('Notice_on_Hospitals_&_clinics') }}</label>
                                <input type="text" name="emergency_note" class="form-control" maxlength="200" value="{{ $settings['emergency_note'] }}" placeholder="{{ translate('Ex: In an emergency, call the emergency services first.') }}">
                                <div class="fs-12 text-muted mt-1">{{ translate('Shown at the top of Hospitals & clinics. Put the official emergency numbers here. Leave empty to hide it.') }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                            <div class="flex-grow-1" style="min-width: 240px">
                                <h5 class="mb-1">{{ translate('Nearby_results_from_Google') }}</h5>
                                <p class="fs-12 text-muted mb-0">{{ translate('Live hotels, guest houses and lodges from Google Maps around the customer. Uses the server map key. Google bills each search and photo above its free monthly allowance, so the limits below stop the calls once they are reached. The app then shows verified listings only.') }}</p>
                            </div>
                            <label class="switcher">
                                <input type="hidden" name="google_status" value="0">
                                <input class="switcher_input" type="checkbox" name="google_status" value="1" {{ $settings['google_status'] ? 'checked' : '' }}>
                                <span class="switcher_control"></span>
                            </label>
                        </div>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label">{{ translate('Search_radius_(km)') }}</label>
                                <input type="number" name="radius_km" class="form-control" required min="1" max="50" step="0.5" value="{{ $settings['radius_km'] }}">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">{{ translate('Reviews_needed_for_Top_rated') }}</label>
                                <input type="number" name="min_reviews" class="form-control" required min="0" max="500" value="{{ $settings['min_reviews'] }}">
                                <div class="fs-12 text-muted mt-1">{{ translate('Stops a 5-star place with 2 reviews from topping the list.') }}</div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">{{ translate('Monthly_search_limit') }}</label>
                                <input type="number" name="search_cap" class="form-control" required min="0" value="{{ $settings['search_cap'] }}">
                                <div class="fs-12 text-muted mt-1">{{ translate('One search each time a customer opens a category.') }} {{ translate('This month') }}: <strong>{{ number_format($usage['search']) }}</strong></div>
                                @if($usage['search'] >= $settings['search_cap'])<div class="fs-12 text-danger">{{ translate('Limit reached: Google results are off until next month or a higher limit.') }}</div>@endif
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">{{ translate('Monthly_photo_limit') }}</label>
                                <input type="number" name="photo_cap" class="form-control" required min="0" value="{{ $settings['photo_cap'] }}">
                                <div class="fs-12 text-muted mt-1">{{ translate('One per Google photo shown. Phones keep photos for the day.') }} {{ translate('This month') }}: <strong>{{ number_format($usage['photo']) }}</strong></div>
                                @if($usage['photo'] >= $settings['photo_cap'])<div class="fs-12 text-danger">{{ translate('Limit reached: Google places show without photos.') }}</div>@endif
                            </div>
                        </div>
                        <p class="fs-12 text-muted mt-3 mb-0">{{ translate('Check current prices and set a budget alert in your Google Cloud console under Billing.') }}</p>
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">{{ translate('Save') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
