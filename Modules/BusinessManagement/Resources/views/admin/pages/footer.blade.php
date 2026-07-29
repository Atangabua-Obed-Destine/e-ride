@extends('adminmodule::layouts.master')

@section('title', translate('Footer'))

@push('css_or_js')
@endpush

@section('content')
    @php($env = env('APP_MODE') == 'demo' ? 'demo' : 'live')
    @php($link = '<a href="' . route('admin.business.app-version-setup.index')  .'" class="fw-semibold text-info text-decoration-underline" target="_blank">'. translate('App Version Setup') .'</a>')
    @php($businessLink = '<a href="' . route('admin.business.setup.info.index')  .'" class="fw-semibold text-info text-decoration-underline" target="_blank">'. translate('Business Info Tab') .'</a>')
    @php($customerAppVersionControlForAndroid = businessConfig(key: CUSTOMER_APP_VERSION_CONTROL_FOR_ANDROID, settingsType: APP_VERSION)?->value)
    @php($customerAppVersionControlForIos = businessConfig(key: CUSTOMER_APP_VERSION_CONTROL_FOR_IOS, settingsType: APP_VERSION)?->value)
    @php($driverAppVersionControlForAndroid = businessConfig(key: DRIVER_APP_VERSION_CONTROL_FOR_ANDROID, settingsType: APP_VERSION)?->value)
    @php($driverAppVersionControlForIos = businessConfig(key: DRIVER_APP_VERSION_CONTROL_FOR_IOS, settingsType: APP_VERSION)?->value)
    @php($footerLogo = businessConfig(key: 'footer_logo', settingsType: BUSINESS_INFORMATION)?->value)
    <!-- Main Content -->
    <form action="{{ route('admin.business.pages-media.landing-page.footer.update-section') }}" id=""
          enctype="multipart/form-data" method="POST">
        @csrf
        <div class="main-content">
            <div class="container-fluid">
                <h2 class="fs-20 fw-bold mb-3 text-capitalize">{{translate('landing_page_setup')}}</h2>
                <div class="mb-3">
                    <div class="">
                        @include('businessmanagement::admin.pages.partials._landing_page_inline_menu')
                    </div>
                </div>


                <input type="hidden" name="key_name" value="{{ FOOTER_CONTENTS }}">
                <div class="card card-body mb-3">
                    <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                        <div>
                            <h4 class="mb-1">
                                {{ translate('Footer_Section') }}
                            </h4>
                            <p class="fs-12 mb-0">{{ translate('Configure the setup, view the section preview, and check the notes for setup guidance.') }}</p>
                        </div>
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <button type="button" class="btn btn-outline-primary text-capitalize cmn_focus px-3"
                                    tabindex="1"
                                    data-bs-toggle="modal" data-bs-target="#noteModal">
                                <i class="bi bi-journal-text"></i>
                                {{ translate('Note') }}
                            </button>
                            <button type="button" class="btn btn-outline-primary text-capitalize cmn_focus px-3"
                                    tabindex="1" data-bs-toggle="offcanvas" data-bs-target="#sectionPriview-offcanvas">
                                <i class="bi bi-eye-fill"></i>
                                {{ translate('Section_Preview') }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card card-body">
                    <div class="mb-20">
                        <h4 class="mb-1">
                            {{ translate('Footer_Section_Content') }}
                        </h4>
                        <p class="fs-12 mb-0">{{ translate('Set up the section by adding a title and configuring the customer app and driver app from the required sections.') }}</p>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <div class="p-lg-4 p-3 rounded bg-F6F6F6 h-100">
                                <div>
                                    <label for="title" class="mb-2">{{ translate('title') }} <span
                                            class="text-danger">*</span></label>
                                    <div class="character-count d-flex flex-column align-items-end">
                                        <input type="text" class="form-control character-count-field" maxlength="100"
                                               data-max-character="100" id="" name="title"
                                               value="{{ $footerContents?->value['title'] }}" tabindex="2"
                                               placeholder="{{ translate('Ex: Title') }}" required>
                                        <span>{{translate('0/100')}}</span>
                                    </div>
                                </div>
                                <div
                                    class="d-flex gap-2 align-items-baseline fs-12 px-12 py-2 rounded text-dark {{ $footerLogo ? 'bg-info' : 'bg-danger' }}  bg-opacity-10">
                                    @if($footerLogo)
                                        <i class="bi bi-lightbulb-fill text-info"></i>
                                        <span>
                                                            {!! translate('Footer logo setup is available in Business Management'). ' <span class="bi bi-arrow-right"> </span> '. translate('{link}.', ['link' => $businessLink]) !!}
                                                        </span>
                                    @else
                                        <i class="bi bi-info-circle-fill text-danger"></i>
                                        <span>
                                                                {!! translate('Footer logo is not set up yet.') !!}
                                            {!! translate('Please complete the setup from {link} to enable this button', ['link' => $businessLink]) !!}
                                                            </span>
                                    @endif
                                </div>
                            </div>

                        </div>
                        <div class="col-12">
                            <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <h4 class="mb-1">
                                            {{ translate('Customer_App') }}
                                        </h4>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="card card-body h-100">
                                            <div class="d-flex align-items-center justify-content-between gap-3 mb-20">
                                                <h5 class="mb-0 d-flex align-items-center gap-2">
                                                    <img width="24"
                                                         src="{{ dynamicAsset('public/assets/admin-module/img/media/play-store.png') }}"
                                                         class="aspect-1" alt="">
                                                    <span>{{ translate('Play_Store_Button') }}</span>
                                                </h5>
                                            </div>

                                            <div class="p-lg-4 p-3 rounded bg-F6F6F6 h-100">
                                                <div
                                                    class="d-flex gap-2 align-items-baseline fs-12 px-12 py-2 rounded text-dark {{ !empty($customerAppVersionControlForAndroid['app_url']) ? 'bg-info' : 'bg-danger' }}  bg-opacity-10">
                                                    @if(!empty($customerAppVersionControlForAndroid['app_url']))
                                                        <i class="bi bi-lightbulb-fill text-info"></i>
                                                        <span>
                                                            {!! translate('App download button is connected successfully.') !!}
                                                            {!! translate('Data is synced from {link}', ['link' => $link]) !!}
                                                        </span>
                                                    @else
                                                        <i class="bi bi-info-circle-fill text-danger"></i>
                                                        <span>
                                                                {!! translate('The app download button link is not set up yet.') !!}
                                                            {!! translate('Please complete the setup from {link} to enable this button', ['link' => $link]) !!}
                                                            </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="card card-body h-100">
                                            <div class="d-flex align-items-center justify-content-between gap-3 mb-20">
                                                <h5 class="mb-0 d-flex align-items-center gap-2">
                                                    <img width="24"
                                                         src="{{ dynamicAsset('public/assets/admin-module/img/media/app-store.svg') }}"
                                                         class="aspect-1 svg" alt="">
                                                    <span>{{ translate('Apple_Store_Button') }}</span>
                                                </h5>
                                            </div>
                                            <div class="p-lg-4 p-3 rounded bg-F6F6F6 h-100">
                                                <div
                                                    class="d-flex gap-2 align-items-baseline fs-12 px-12 py-2 rounded text-dark {{ !empty($customerAppVersionControlForIos['app_url']) ? 'bg-info' : 'bg-danger' }}  bg-opacity-10">
                                                    @if(!empty($customerAppVersionControlForIos['app_url']))
                                                        <i class="bi bi-lightbulb-fill text-info"></i>
                                                        <span>
                                                            {!! translate('App download button is connected successfully.') !!}
                                                            {!! translate('Data is synced from {link}', ['link' => $link]) !!}
                                                        </span>
                                                    @else
                                                        <i class="bi bi-info-circle-fill text-danger"></i>
                                                        <span>
                                                                {!! translate('The app download button link is not set up yet.') !!}
                                                            {!! translate('Please complete the setup from {link} to enable this button', ['link' => $link]) !!}
                                                            </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <h4 class="mb-1">
                                            {{ translate('Driver_App') }}
                                        </h4>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="card card-body h-100">
                                            <div class="d-flex align-items-center justify-content-between gap-3 mb-20">
                                                <h5 class="mb-0 d-flex align-items-center gap-2">
                                                    <img width="24"
                                                         src="{{ dynamicAsset('public/assets/admin-module/img/media/play-store.png') }}"
                                                         class="aspect-1" alt="">
                                                    <span>{{ translate('Play_Store_Button') }}</span>
                                                </h5>
                                            </div>
                                            <div class="p-lg-4 p-3 rounded bg-F6F6F6 h-100">
                                                <div
                                                    class="d-flex gap-2 align-items-baseline fs-12 px-12 py-2 rounded text-dark {{ !empty($driverAppVersionControlForAndroid['app_url']) ? 'bg-info' : 'bg-danger' }}  bg-opacity-10">
                                                    @if(!empty($driverAppVersionControlForAndroid['app_url']))
                                                        <i class="bi bi-lightbulb-fill text-info"></i>
                                                        <span>
                                                            {!! translate('App download button is connected successfully.') !!}
                                                            {!! translate('Data is synced from {link}', ['link' => $link]) !!}
                                                        </span>
                                                    @else
                                                        <i class="bi bi-info-circle-fill text-danger"></i>
                                                        <span>
                                                                {!! translate('The app download button link is not set up yet.') !!}
                                                            {!! translate('Please complete the setup from {link} to enable this button', ['link' => $link]) !!}
                                                            </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="card card-body h-100">
                                            <div class="d-flex align-items-center justify-content-between gap-3 mb-20">
                                                <h5 class="mb-0 d-flex align-items-center gap-2">
                                                    <img width="24"
                                                         src="{{ dynamicAsset('public/assets/admin-module/img/media/app-store.svg') }}"
                                                         class="aspect-1 svg" alt="">
                                                    <span>{{ translate('Apple_Store_Button') }}</span>
                                                </h5>
                                            </div>
                                            <div class="p-lg-4 p-3 rounded bg-F6F6F6 h-100">
                                                <div
                                                    class="d-flex gap-2 align-items-baseline fs-12 px-12 py-2 rounded text-dark {{ !empty($driverAppVersionControlForIos['app_url']) ? 'bg-info' : 'bg-danger' }}  bg-opacity-10">
                                                    @if(!empty($driverAppVersionControlForIos['app_url']))
                                                        <i class="bi bi-lightbulb-fill text-info"></i>
                                                        <span>
                                                            {!! translate('App download button is connected successfully.') !!}
                                                            {!! translate('Data is synced from {link}', ['link' => $link]) !!}
                                                        </span>
                                                    @else
                                                        <i class="bi bi-info-circle-fill text-danger"></i>
                                                        <span>
                                                                {!! translate('The app download button link is not set up yet.') !!}
                                                            {!! translate('Please complete the setup from {link} to enable this button', ['link' => $link]) !!}
                                                            </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <div class="footer--sticky">
            <div class="container-fluid">
                <div class="btn--container justify-content-end py-4">
                    <button type="reset" class="btn btn-secondary text-capitalize cmn_focus min-w-120"
                            tabindex="4">{{ translate('reset') }}</button>
                    <button type="submit" class="btn btn-primary text-capitalize cmn_focus"
                            tabindex="5">{{ translate('save_information') }}</button>
                </div>
            </div>
        </div>
    </form>

    <!-- End Main Content -->

    {{-- Section Preview Offcanvas --}}
    <div class="offcanvas offcanvas-end" id="sectionPriview-offcanvas" style="--bs-offcanvas-width: 755px;">
        <form action="javascript:" class="d-flex flex-column h-100">
            <div class="offcanvas-header">
                <h4 class="offcanvas-title flex-grow-1">
                    {{ translate('Footer Section Preview') }}
                </h4>
                <button type="button" class="btn btn-circle btn-secondary text-white" data-bs-dismiss="offcanvas" aria-label="Close" style="--size: 20px;">
                    <i class="bi bi-x-lg d-flex"></i>
                </button>
            </div>
            <div class="offcanvas-body scrollbar-thin">
                <div class="h-100 overflow-y-auto py-5 px-0">
                    <img src="{{ dynamicAsset('public/assets/admin-module/img/preview/footer-section.png') }}" alt=""
                         class="img-fluid w-100 d-block">
                </div>
            </div>
        </form>
    </div>

    @include('businessmanagement::admin.pages.partials._note-modal',['page' => 'footer'])
@endsection


@push('script')
    <script src="{{ dynamicAsset('public/assets/admin-module/js/single-image-upload-new.js') }}"></script>
@endpush
