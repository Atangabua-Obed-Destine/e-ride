@extends('adminmodule::layouts.master')

@section('title', translate('Intro Section'))

@push('css_or_js')
@endpush

@section('content')
    @php($env = env('APP_MODE') == 'live' ? 'live' : 'test')
    @php($link = '<a href="' . route('admin.business.app-version-setup.index')  .'" class="fw-semibold text-info text-decoration-underline" target="_blank">'. translate('App Version Setup') .'</a>')
    @php($customerAppVersionControlForAndroid = businessConfig(key: CUSTOMER_APP_VERSION_CONTROL_FOR_ANDROID, settingsType: APP_VERSION)?->value)
    @php($customerAppVersionControlForIos = businessConfig(key: CUSTOMER_APP_VERSION_CONTROL_FOR_IOS, settingsType: APP_VERSION)?->value)
    @php($driverAppVersionControlForAndroid = businessConfig(key: DRIVER_APP_VERSION_CONTROL_FOR_ANDROID, settingsType: APP_VERSION)?->value)
    @php($driverAppVersionControlForIos = businessConfig(key: DRIVER_APP_VERSION_CONTROL_FOR_IOS, settingsType: APP_VERSION)?->value)
    <!-- Main Content -->
    <div class="main-content">
        <form action="{{route('admin.business.pages-media.landing-page.intro-section.update')}}"
            id="banner_form" enctype="multipart/form-data" method="POST">
            @csrf
            <div class="container-fluid">
                <h2 class="fs-20 fw-bold mb-3 text-capitalize">{{translate('landing_page_setup')}}</h2>
                <div class="mb-3">
                    <div class="">
                        @include('businessmanagement::admin.pages.partials._landing_page_inline_menu')
                    </div>
                </div>

                <input type="hidden" name="key_name" value="{{ INTRO_CONTENTS }}">
                <div class="card card-body mb-3">
                    <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                        <div>
                            <h4 class="mb-1">
                                {{ translate('Intro_Section') }}
                            </h4>
                            <p class="fs-12 mb-0">{{ translate('See how your Intro Section will look to customers.') }}</p>
                        </div>
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <button type="button" class="btn btn-outline-primary text-capitalize cmn_focus px-3" tabindex="1"
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

                <div class="card card-body mb-2">
                    <div class="mb-20">
                        <h4 class="mb-1">
                            {{ translate('Header_Intro_Section') }}
                        </h4>
                        <p class="fs-12 mb-0">{{ translate('Manage intro section content including title, subtitle, and background image.') }}</p>
                    </div>
                    <div class="row g-3">
                        <div class="col-lg-8">
                            <div class="p-lg-4 p-3 rounded bg-F6F6F6 h-100">
                                <div class="mb-4">
                                    <label for="title" class="mb-2">{{ translate('title') }} <span
                                            class="text-danger">*</span></label>
                                    <div class="character-count d-flex flex-column align-items-end">
                                        <input type="text" class="form-control character-count-field" maxlength="100"
                                            data-max-character="100" id="title" name="title"
                                            value="{{$data?->value['title']??''}}" tabindex="2"
                                            placeholder="{{ translate('Ex: Title') }}" required>
                                        <span>{{translate('0/100')}}</span>
                                    </div>

                                </div>
                                <div class="">
                                    <label for="subTitle" class="mb-2">{{ translate('sub_Title') }} <span
                                            class="text-danger">*</span></label>
                                    <div class="character-count d-flex flex-column align-items-end">
                                        <input type="text" class="form-control character-count-field" maxlength="255"
                                            data-max-character="255" id="subTitle" name="sub_title"
                                            value="{{$data?->value['sub_title']??''}}" tabindex="3"
                                            placeholder="{{ translate('Ex: Sub_Title') }}" required>
                                        <span>{{translate('0/255')}}</span>
                                    </div>
                                </div>

                            </div>
                        </div>
                        <div class="col-lg-4">
                            <?php
                            $maxSize = readableUploadMaxFileSize('image');
                            ?>
                            <div
                                class="p-lg-4 p-3 rounded bg-F6F6F6 d-flex flex-column justify-content-around gap-3 h-100">
                                <div class="text-center">
                                    <h5 class="mb-1">
                                        {{ translate('Background Image') }} <span class="text-danger">*</span>
                                        <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                        data-bs-toggle="tooltip"
                                        title="{{ translate('Upload_background_image') }}"></i>
                                    </h5>
                                    <p class="fs-12 mb-0">{{ translate('Upload your Header Section Image') }}</p>
                                </div>

                                <div class="upload-file-new">
                                    <input type="file" class="upload-file-new__input single_file_input" tabindex="4"
                                        name="background_image" accept="{{ IMAGE_ACCEPTED_EXTENSIONS }}"
                                        data-max-upload-size="{{ $maxSize }}"
                                        {{ $data?->value['background_image'] ? '' : 'required' }}>
                                    <label class="upload-file-new__wrapper ratio-3-1">
                                        <div class="upload-file-new-textbox text-center">
                                            <img width="34" height="34" class="svg"
                                                src="{{ dynamicAsset('public/assets/admin-module/img/document-upload.svg') }}"
                                                alt="image upload">
                                            <h6 class="mt-2 fw-medium text-center text-info mb-0">{{ translate('Add') }}</h6>
                                        </div>
                                        <img class="upload-file-new-img" loading="lazy"
                                            src="{{ $data?->value['background_image'] ? dynamicStorage('storage/app/public/business/landing-pages/intro-section/'.$data?->value['background_image']) : '' }}"
                                            data-default-src="{{ $data?->value['background_image'] ? dynamicStorage('storage/app/public/business/landing-pages/intro-section/'.$data?->value['background_image']) : '' }}"
                                            alt="">
                                    </label>
                                    <div class="overlay">
                                        <div class="d-flex gap-10 justify-content-center align-items-center h-100">
                                            <button type="button" class="btn btn-outline-info icon-btn edit_btn">
                                                <i class="bi bi-camera"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <p class="text-dark fs-10 text-center">
                                    <span
                                        class="opacity-75">{{ translate(key: '{format}, Image Size - Max {imageSize}', replace: ['format' => IMAGE_ACCEPTED_EXTENSIONS, 'imageSize' => $maxSize]) }}</span>
                                    <span>(3:1)</span>
                                </p>
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
            <div class="footer--sticky">
                <div class="container-fluid">
                    <div class="btn--container justify-content-end py-4">
                        <button type="reset" class="btn btn-secondary text-capitalize cmn_focus min-w-120"
                                tabindex="5">{{ translate('reset') }}</button>
                        <button type="submit" class="btn btn-primary text-capitalize cmn_focus"
                                tabindex="6">{{ translate('save_information') }}</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <!-- End Main Content -->

    {{-- Section Preview Offcanvas --}}
    <div class="offcanvas offcanvas-end" id="sectionPriview-offcanvas" style="--bs-offcanvas-width: 755px;">
        <form action="javascript:" class="d-flex flex-column h-100">
            <div class="offcanvas-header">
                <h4 class="offcanvas-title flex-grow-1">
                    {{ translate('Intro Section Preview') }}
                </h4>
                <button type="button" class="btn btn-circle btn-secondary text-white" data-bs-dismiss="offcanvas" aria-label="Close" style="--size: 20px;">
                    <i class="bi bi-x-lg d-flex"></i>
                </button>
            </div>
            <div class="offcanvas-body scrollbar-thin">
                <div class="h-100 overflow-y-auto py-5 px-0">
                    <img src="{{ dynamicAsset('public/assets/admin-module/img/preview/intro-section.png') }}" alt=""
                         class="img-fluid w-100 d-block">
                </div>
            </div>
        </form>
    </div>

    @include('businessmanagement::admin.pages.partials._note-modal',['page' => 'intro_section'])
@endsection


@push('script')
    <script src="{{ dynamicAsset('public/assets/admin-module/js/single-image-upload-new.js') }}"></script>
@endpush
