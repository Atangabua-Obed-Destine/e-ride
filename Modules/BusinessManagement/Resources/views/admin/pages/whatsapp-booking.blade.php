@extends('adminmodule::layouts.master')

@section('title', translate('WhatsApp_Booking'))

@section('content')
    <form action="{{ route('admin.business.pages-media.landing-page.whatsapp-booking.update') }}" method="POST">
        @csrf
        <div class="main-content">
            <div class="container-fluid">
                <h2 class="fs-20 fw-bold mb-3 text-capitalize">{{ translate('landing_page_setup') }}</h2>
                <div class="mb-3">
                    @include('businessmanagement::admin.pages.partials._landing_page_inline_menu')
                </div>

                <div class="card card-body mb-3">
                    <div class="d-flex flex-md-nowrap flex-wrap align-items-center justify-content-between gap-3">
                        <div class="w-0 flex-grow-1">
                            <h4 class="text-capitalize mb-1">{{ translate('Automated_WhatsApp_Booking') }}</h4>
                            <div class="fs-14">
                                {{ translate('When on, the Book on WhatsApp buttons on the website open a short booking form: who is riding (the visitor or someone else), pickup (their current location or a search), destination, time and ride type with an approximate fare. The finished booking is then sent to your WhatsApp number as one ready-to-read message with map links. When off, the buttons simply open a WhatsApp chat.') }}
                            </div>
                        </div>
                        <label class="max-w300 w-100 form-control rounded d-flex align-items-center justify-content-between">
                            <label for="whatsappBookingStatus" class="fs-14 lh-1 d-block cursor-pointer text-dark">{{ translate('Status') }}</label>
                            <label class="switcher cmn_focus rounded-pill">
                                <input class="switcher_input" id="whatsappBookingStatus" name="status" type="checkbox" value="1"
                                    {{ $settings['status'] ? 'checked' : '' }}>
                                <span class="switcher_control"></span>
                            </label>
                        </label>
                    </div>
                </div>

                <div class="card card-body">
                    <div class="mb-20">
                        <h5 class="mb-1">{{ translate('Booking_Details') }}</h5>
                        <p class="fs-12 mb-0">{{ translate('Where bookings are sent, and an optional first line for every booking message.') }}</p>
                    </div>
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <div class="p-lg-4 p-3 rounded bg-F6F6F6 h-100">
                                <label for="whatsappNumber" class="mb-2">{{ translate('WhatsApp_number_for_bookings') }}</label>
                                <input type="text" class="form-control" id="whatsappNumber" name="number" maxlength="20"
                                       value="{{ $stored['number'] ?? '' }}"
                                       placeholder="{{ translate('Ex: +237 6XX XXX XXX') }}">
                                <p class="fs-12 mt-2 mb-0 text-muted">
                                    {{ translate('Include the country code. Leave empty to use the business phone number') }}
                                    @if($businessPhone) ({{ $businessPhone }}) @endif.
                                </p>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="p-lg-4 p-3 rounded bg-F6F6F6 h-100">
                                <label for="whatsappGreeting" class="mb-2">{{ translate('Opening_line') }} <span class="text-muted">({{ translate('optional') }})</span></label>
                                <div class="character-count d-flex flex-column align-items-end">
                                    <input type="text" class="form-control character-count-field" id="whatsappGreeting" name="greeting"
                                           maxlength="120" data-max-character="120"
                                           value="{{ $stored['greeting'] ?? '' }}"
                                           placeholder="{{ translate('Ex: Hello E-Ride, I would like to book a ride') }}">
                                    <span>{{ translate('0/120') }}</span>
                                </div>
                                <p class="fs-12 mt-2 mb-0 text-muted">{{ translate('Shown at the top of every booking message.') }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-3 mt-3">
                        <button type="submit" class="btn btn-primary text-uppercase">{{ translate('save') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
