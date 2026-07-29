@extends('adminmodule::layouts.master')

@section('title', translate('Customer App Download'))

@push('css_or_js')
@endpush

@section('content')
    @php($env = env('APP_MODE') == 'demo' ? 'demo' : 'live')
    @php($link = '<a href="' . route('admin.business.app-version-setup.index')  .'" class="fw-semibold text-info text-decoration-underline" target="_blank">'. translate('App Version Setup') .'</a>')
    @php($appVersionControlForAndroid = businessConfig(key: CUSTOMER_APP_VERSION_CONTROL_FOR_ANDROID, settingsType: APP_VERSION)?->value)
    @php($appVersionControlForIos = businessConfig(key: CUSTOMER_APP_VERSION_CONTROL_FOR_IOS, settingsType: APP_VERSION)?->value)

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-20 fw-bold mb-3 text-capitalize">{{translate('landing_page_setup')}}</h2>
            <div class="mb-3">
                <div class="">
                    @include('businessmanagement::admin.pages.partials._landing_page_inline_menu')
                </div>
            </div>

            <div class="card card-body mb-3">
                <div class="d-flex flex-md-nowrap flex-wrap align-items-center justify-content-between gap-3">
                    <div class="w-0 flex-grow-1">
                        <h4 class="text-capitalize mb-1">{{ translate('Show Customer App Download') }}</h4>
                        <div class="fs-14">
                            {{ translate('Allow this option to display the app download section on the landing page.') }}
                        </div>
                    </div>
                    <label
                        class="max-w300 w-100 form-control rounded d-flex align-items-center justify-content-between">
                        <label for="showCustomerAppDownload"
                               class="fs-14 lh-1 d-block cursor-pointer text-dark">{{ translate('Status') }}</label>
                        <label class="switcher cmn_focus rounded-pill">
                            <input class="switcher_input update-business-setting" id="showCustomerAppDownload"
                                   name="is_customer_app_download_enabled" type="checkbox"
                                   data-name="is_customer_app_download_enabled"
                                   data-type="{{ CUSTOMER_APP_DOWNLOAD }}" tabindex="4"
                                   data-url="{{ route('admin.business.pages-media.landing-page.update-landing-page-setting') }}"
                                   data-icon="{{ $isCustomerAppDownloadEnabled == 0 ? dynamicAsset('public/assets/admin-module/img/svg/info-circle-green.svg') : dynamicAsset('public/assets/admin-module/img/svg/info-circle-red.svg') }}"
                                   data-title="{{ $isCustomerAppDownloadEnabled == 0 ? translate('Want to enable Customer App Download section') : translate('Want to disable Customer App Download section') }}?"
                                   data-sub-title="{{ $isCustomerAppDownloadEnabled == 0 ? translate(' If you turn on the Customer App Download section, users will see it in the landing page.') : translate('If you turn off the Customer App Download section, users will no longer see it in the landing page.') }}"
                                {{ $isCustomerAppDownloadEnabled == 1 ? 'checked' : '' }}
                            >
                            <span class="switcher_control"></span>
                        </label>
                    </label>
                </div>
            </div>

            <div class="card card-body mb-3">
                <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                    <div>
                        <h4 class="mb-1">
                            {{ translate('Customer App Download Section') }}
                        </h4>
                        <p class="fs-12 mb-0">{{ translate('See how your customer app download Section will look to customers.') }}</p>
                    </div>
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <button type="button" class="btn btn-outline-primary text-capitalize cmn_focus px-3"
                                tabindex="1"
                                data-bs-toggle="modal" data-bs-target="#noteModal">
                            <i class="bi bi-journal-text"></i>
                            {{ translate('Note') }}
                        </button>
                        <button type="button" class="btn btn-outline-primary text-capitalize cmn_focus px-3"
                                tabindex="1"
                                data-bs-toggle="offcanvas" data-bs-target="#sectionPriview-offcanvas">
                            <i class="bi bi-eye-fill"></i>
                            {{ translate('Section_Preview') }}
                        </button>
                    </div>
                </div>
            </div>

            <form action="{{ route('admin.business.pages-media.landing-page.customer-app-download.update-section') }}"
                  id="" enctype="multipart/form-data" method="POST">
                @csrf
                <input type="hidden" name="key_name" value="{{ INTRO_CONTENTS }}">
                <div class="card card-body mb-3">
                    <div class="mb-20">
                        <h5 class="mb-1">{{ translate('Customer App Download Section Content') }}</h5>
                        <p class="fs-12 mb-0">{{ translate('Configure the section content by setting the title, subtitle, and image.') }}</p>
                    </div>

                    <div class="row g-3">
                        <div class="col-lg-8">
                            <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                                <div class="mb-3">
                                    <label for="" class="mb-2">
                                        {{ translate('Title') }} <span class="text-danger">*</span>
                                    </label>
                                    <div class="character-count d-flex flex-column align-items-end">
                                        <input type="text" class="form-control character-count-field"
                                               maxlength="100" data-max-character="100" id=""
                                               name="title" tabindex="5"
                                               value="{{ $introContents?->value['title'] }}"
                                               placeholder="{{ translate('ex') }}: {{ translate('Ride_Sharing') }}"
                                               required>
                                        <span>{{translate('0/100')}}</span>
                                    </div>
                                </div>
                                <div>
                                    <label for="" class="mb-2">
                                        {{ translate('Sub Title') }} <span class="text-danger">*</span>
                                    </label>
                                    <div class="character-count d-flex flex-column align-items-end">
                                        <input type="text" class="form-control character-count-field"
                                               maxlength="200" data-max-character="200" id=""
                                               name="subtitle" tabindex="5"
                                               value="{{ $introContents?->value['subtitle'] }}"
                                               placeholder="{{ translate('ex') }}: {{ translate('Section_Description') }}"
                                               required>
                                        <span>{{translate('0/200')}}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php
                        $maxSize = readableUploadMaxFileSize('image');
                        ?>
                        <div class="col-lg-4">
                            <div
                                class="p-lg-4 p-3 rounded bg-F6F6F6 d-flex flex-column justify-content-around gap-3 h-100">
                                <div class="text-center">
                                    <h5 class="mb-1">
                                        {{ translate('Upload Image') }} <span class="text-danger">*</span>
                                    </h5>
                                    <p class="fs-12 mb-0">{{ translate('Upload customer app download Section Image') }}</p>
                                </div>

                                <div class="upload-file-new">
                                    <input type="file" class="upload-file-new__input single_file_input" tabindex="4"
                                           name="image" accept="{{ IMAGE_ACCEPTED_EXTENSIONS }}"
                                           data-max-upload-size="{{ $maxSize }}"
                                        {{ $introContents?->value['image'] ? '' : 'required' }}
                                    >
                                    <label class="upload-file-new__wrapper ratio-1-1">
                                        <div class="upload-file-new-textbox text-center">
                                            <img width="34" height="34" class="svg"
                                                 src="{{ dynamicAsset('public/assets/admin-module/img/document-upload.svg') }}"
                                                 alt="image upload">
                                            <h6 class="mt-2 fw-medium text-center text-info mb-0">{{ translate('Add') }}</h6>
                                        </div>
                                        <img class="upload-file-new-img" loading="lazy"
                                             src="{{ $introContents?->value['image'] ? dynamicStorage('storage/app/public/business/landing-pages/customer-app-download/' . $introContents?->value['image']) : '' }}"
                                             data-default-src="{{ $introContents?->value['image'] ? dynamicStorage('storage/app/public/business/landing-pages/customer-app-download/' . $introContents?->value['image']) : '' }}"
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
                                    <span>(1:1)</span>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-3 mt-4">
                        <button class="btn btn-secondary cmn_focus min-w-120" tabindex="3" type="reset">
                            {{ translate('reset') }}
                        </button>
                        <button class="btn btn-primary cmn_focus min-w-120" tabindex="4" type="submit">
                            {{ translate('save') }}
                        </button>
                    </div>
                </div>
            </form>

            <form action="{{ route('admin.business.pages-media.landing-page.customer-app-download.update-section') }}"
                  id="" enctype="multipart/form-data" method="POST">
                @csrf
                <input type="hidden" name="key_name" value="{{ BUTTON_CONTENTS }}">
                <div class="card card-body mb-3">
                    <div class="mb-20">
                        <h5 class="mb-1">{{ translate('Customer App Download Section Button ') }}</h5>
                        <p class="fs-12 mb-0">{{ translate('Set a title and subtitle that will appear on the landing page to inform about customer app downloads.') }}</p>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                                <div class="row g-3">
                                    <div class="col-lg-6">
                                        <div>
                                            <label for="" class="mb-2">
                                                {{ translate('Title') }} <span class="text-danger">*</span>
                                            </label>
                                            <div class="character-count d-flex flex-column align-items-end">
                                                <input type="text" class="form-control character-count-field"
                                                       maxlength="100" data-max-character="100" id=""
                                                       name="title" tabindex="5"
                                                       value="{{ $buttonContents?->value['title'] }}"
                                                       placeholder="{{ translate('ex') }}: {{ translate('Ride_Sharing') }}"
                                                       required>
                                                <span>{{translate('0/100')}}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div>
                                            <label for="" class="mb-2">
                                                {{ translate('Sub Title') }} <span class="text-danger">*</span>
                                            </label>
                                            <div class="character-count d-flex flex-column align-items-end">
                                                <input type="text" class="form-control character-count-field"
                                                       maxlength="200" data-max-character="200" id=""
                                                       name="subtitle" tabindex="5"
                                                       value="{{ $buttonContents?->value['subtitle'] }}"
                                                       placeholder="{{ translate('ex') }}: {{ translate('Section_Description') }}"
                                                       required>
                                                <span>{{translate('0/200')}}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                                <div class="row g-3">
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
                                                    class="d-flex gap-2 align-items-baseline fs-12 px-12 py-2 rounded text-dark {{ !empty($appVersionControlForAndroid['app_url']) ? 'bg-info' : 'bg-danger' }}  bg-opacity-10">
                                                    @if(!empty($appVersionControlForAndroid['app_url']))
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
                                                    class="d-flex gap-2 align-items-baseline fs-12 px-12 py-2 rounded text-dark {{ !empty($appVersionControlForIos['app_url']) ? 'bg-info' : 'bg-danger' }}  bg-opacity-10">
                                                    @if(!empty($appVersionControlForIos['app_url']))
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
                    <div class="d-flex justify-content-end gap-3 mt-4">
                        <button class="btn btn-secondary cmn_focus min-w-120" tabindex="3" type="reset">
                            {{ translate('reset') }}
                        </button>
                        <button class="btn btn-primary cmn_focus min-w-120" tabindex="4" type="submit">
                            {{ translate('save') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <!-- End Main Content -->

    {{-- Section Preview Offcanvas --}}
    <div class="offcanvas offcanvas-end" id="sectionPriview-offcanvas" style="--bs-offcanvas-width: 755px;">
        <form action="javascript:" class="d-flex flex-column h-100">
            <div class="offcanvas-header">
                <h4 class="offcanvas-title flex-grow-1">
                    {{ translate('Customer App Download Section Preview') }}
                </h4>
                <button type="button" class="btn btn-circle btn-secondary text-white" data-bs-dismiss="offcanvas" aria-label="Close" style="--size: 20px;">
                    <i class="bi bi-x-lg d-flex"></i>
                </button>
            </div>
            <div class="offcanvas-body scrollbar-thin">
                <div class="h-100 overflow-y-auto py-5 px-0">
                    <img src="{{ dynamicAsset('public/assets/admin-module/img/preview/customer-app-download.png') }}"
                         alt="" class="img-fluid w-100 d-block">
                </div>
            </div>
        </form>
    </div>

    @include('businessmanagement::admin.pages.partials._note-modal',['page' => 'customer_app_download'])
@endsection

@push('script')
    <script src="{{ dynamicAsset('public/assets/admin-module/js/single-image-upload-new.js') }}"></script>
    <script>
        "use strict";
        let permission = false;
        @can('business_edit')
            permission = true;
        @endcan
    </script>
@endpush
