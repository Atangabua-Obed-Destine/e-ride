@extends('adminmodule::layouts.master')

@section('title', translate('App_Download_Setup'))

@push('css_or_js')
@endpush

@section('content')
    @php($env = env('APP_MODE') == 'live' ? 'live' : 'test')
    @php($link = '<a href="' . route('admin.business.app-version-setup.index')  .'" class="fw-semibold text-info text-decoration-underline" target="_blank">'. translate('App Version Setup') .'</a>')
    @php($customerVersionControlForAndroid = businessConfig(key: CUSTOMER_APP_VERSION_CONTROL_FOR_ANDROID, settingsType: APP_VERSION)?->value)
    @php($customerVersionControlForIos = businessConfig(key: CUSTOMER_APP_VERSION_CONTROL_FOR_IOS, settingsType: APP_VERSION)?->value)
    @php($driverVersionControlForAndroid = businessConfig(key: DRIVER_APP_VERSION_CONTROL_FOR_ANDROID, settingsType: APP_VERSION)?->value)
    @php($driverVersionControlForIos = businessConfig(key: DRIVER_APP_VERSION_CONTROL_FOR_IOS, settingsType: APP_VERSION)?->value)
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-20 fw-bold mb-3 text-capitalize">{{translate('Blog')}}</h2>
            <div class="mb-3">
                <div class="">
                    @include('blogmanagement::admin.blog.setting.partials._blog_inline_menu')
                </div>
            </div>

            <div class="card card-body mb-3">
                <div class="d-flex flex-md-nowrap flex-wrap align-items-center justify-content-between gap-3">
                    <div class="w-0 flex-grow-1">
                        <h4 class="text-capitalize mb-1">{{ translate('App Download Setup') }}</h4>
                        <div class="fs-14">
                            {{ translate('If you turn of the availability status, this section will not show in the website') }}
                        </div>
                    </div>
                    <label
                        class="max-w300 w-100 form-control rounded d-flex align-items-center justify-content-between">
                        <label for="pageVisibilityStatus"
                               class="fs-14 lh-1 d-block cursor-pointer text-dark">{{ $name ?? translate('Status') }}</label>
                        <label class="switcher cmn_focus rounded-pill">
                            <input class="switcher_input update-business-setting" id="pageVisibilityStatus"
                                   name="is_enabled" type="checkbox" data-name="is_enabled"
                                   data-type="{{ APP_DOWNLOAD_SETUP }}" tabindex="4"
                                   data-url="{{ route('admin.blog.update-settings') }}"
                                   data-icon="{{ $isEnabled == 0 ? dynamicAsset('public/assets/admin-module/img/svg/info-circle-green.svg') : dynamicAsset('public/assets/admin-module/img/svg/info-circle-red.svg') }}"
                                   data-title="{{ $isEnabled == 0 ? translate('Want to enable App Download Setup section') : translate('Want to disable App Download Setup section') }}?"
                                   data-sub-title="{{ $isEnabled == 0 ? translate(' If you turn on the App Download Setup section, users will see it in the website.') : translate('If you turn off the App Download Setup section, users will no longer see it in the website.') }}"
                                   data-confirm-btn="{{ $isEnabled == 0 ? translate('Yes, On') : translate('Yes, Off') }}"
                                   data-cancel-btn="{{ translate('Not Now') }}"
                                {{ $isEnabled == 1 ? 'checked' : '' }}
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
                            {{ translate('App Download Setup Section') }}
                        </h4>
                        <p class="fs-12 mb-0">{{ translate('See how your app download setup section will look to customers.') }}</p>
                    </div>
                    <button type="button" class="btn btn-outline-primary text-capitalize cmn_focus px-3" tabindex="1" data-bs-toggle="offcanvas" data-bs-target="#sectionPriview-offcanvas">
                        <i class="bi bi-eye-fill"></i>
                        {{ translate('Section_Preview') }}
                    </button>
                </div>
            </div>

            <form action="{{ route('admin.blog.app-download-setup.update-app-contents') }}" id="" enctype="multipart/form-data" method="POST">
                @csrf
                <input type="hidden" name="key_name" value="{{ DRIVER_APP_CONTENTS }}">
                <div class="card card-body mb-3">
                    <div class="mb-20">
                        <h5 class="mb-1">{{ translate('Download The Driver App Button') }}</h5>
                        <p class="fs-12 mb-0">{{ translate('Configure the section content by setting the title and subtitle') }}</p>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                                <div class="mb-3">
                                    <label for="" class="mb-2">
                                        {{ translate('Title') }} <span class="text-danger">*</span>
                                    </label>
                                    <div class="character-count d-flex flex-column align-items-end">
                                        <input type="text" class="form-control character-count-field"
                                            maxlength="100" data-max-character="100" id=""
                                            name="title" tabindex="2"
                                               value="{{ $driverAppContents?->value['title'] }}"
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
                                            name="subtitle" tabindex="3"
                                               value="{{ $driverAppContents?->value['subtitle'] }}"
                                            placeholder="{{ translate('ex') }}: {{ translate('Section_Description') }}"
                                            required>
                                        <span>{{translate('0/200')}}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                                <div class="mb-20">
                                    <h5 class="mb-1">{{ translate('Download Buttons') }}</h5>
                                    <p class="fs-12 mb-0">{{ translate('Complete the setup of the app store download buttons you want to display to users.') }}</p>
                                </div>
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
                                                    class="d-flex gap-2 align-items-baseline fs-12 px-12 py-2 rounded text-dark {{ !empty($driverVersionControlForAndroid['app_url']) ? 'bg-info' : 'bg-danger' }}  bg-opacity-10">
                                                    @if(!empty($driverVersionControlForAndroid['app_url']))
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
                                                    class="d-flex gap-2 align-items-baseline fs-12 px-12 py-2 rounded text-dark {{ !empty($driverVersionControlForIos['app_url']) ? 'bg-info' : 'bg-danger' }}  bg-opacity-10">
                                                    @if(!empty($driverVersionControlForIos['app_url']))
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
                            <div class="d-flex justify-content-end gap-3">
                                <button class="btn btn-secondary cmn_focus min-w-120" tabindex="4" type="reset">
                                    {{ translate('reset') }}
                                </button>
                                <button class="btn btn-primary cmn_focus min-w-120" tabindex="5" type="submit">
                                    {{ translate('save') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <form action="{{ route('admin.blog.app-download-setup.update-app-contents') }}" id="" enctype="multipart/form-data" method="POST">
                @csrf
                <input type="hidden" name="key_name" value="{{ CUSTOMER_APP_CONTENTS }}">
                <div class="card card-body mb-3">
                    <div class="mb-20">
                        <h5 class="mb-1">{{ translate('Download The User App Button') }}</h5>
                        <p class="fs-12 mb-0">{{ translate('Here you can setup the necessary information related to the app download option') }}</p>
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
                                            name="title" tabindex="6"
                                            value="{{ $customerAppContents?->value['title'] }}"
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
                                            name="subtitle" tabindex="7"
                                            value="{{ $customerAppContents?->value['subtitle'] }}"
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
                            <div class="p-lg-4 p-3 rounded bg-F6F6F6 d-flex flex-column justify-content-center gap-3 h-100">
                                <div class="text-center">
                                    <h6 class="fw-medium mb-0">
                                        {{ translate('Background Image') }}
                                    </h6>
                                </div>

                                <div class="upload-file-new my-0">
                                    <input type="file" class="upload-file-new__input single_file_input" tabindex="8" name="image" accept="{{ IMAGE_ACCEPTED_EXTENSIONS }}" data-max-upload-size="{{ $maxSize }}"
                                    {{ $customerAppContents?->value['image'] ? '' : 'required' }}
                                    >
                                    <label class="upload-file-new__wrapper ratio-1-1">
                                        <div class="upload-file-new-textbox text-center">
                                            <img width="34" height="34" class="svg" src="{{ dynamicAsset('public/assets/admin-module/img/document-upload.svg') }}" alt="image upload">
                                            <h6 class="mt-2 fw-medium text-center text-info mb-0">{{ translate('Add') }}</h6>
                                        </div>
                                        <img class="upload-file-new-img" loading="lazy"
                                             src="{{ $customerAppContents?->value['image'] ? dynamicStorage('storage/app/public/blog/setting/app/' . $customerAppContents?->value['image']) : '' }}"
                                             data-default-src="{{ $customerAppContents?->value['image'] ? dynamicStorage('storage/app/public/blog/setting/app/' . $customerAppContents?->value['image']) : '' }}"
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
                                    <span class="opacity-75">{{ translate(key: '{format}, Image Size - Max {imageSize}', replace: ['format' => IMAGE_ACCEPTED_EXTENSIONS, 'imageSize' => $maxSize]) }}</span> <span>(1:1)</span>
                                </p>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                                <div class="mb-20">
                                    <h5 class="mb-1">{{ translate('Download Buttons') }}</h5>
                                    <p class="fs-12 mb-0">{{ translate('Please select the options that you would like to make visible to your users') }}</p>
                                </div>
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
                                                    class="d-flex gap-2 align-items-baseline fs-12 px-12 py-2 rounded text-dark {{ !empty($customerVersionControlForAndroid['app_url']) ? 'bg-info' : 'bg-danger' }}  bg-opacity-10">
                                                    @if(!empty($customerVersionControlForAndroid['app_url']))
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
                                                    class="d-flex gap-2 align-items-baseline fs-12 px-12 py-2 rounded text-dark {{ !empty($customerVersionControlForIos['app_url']) ? 'bg-info' : 'bg-danger' }}  bg-opacity-10">
                                                    @if(!empty($customerVersionControlForIos['app_url']))
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
                            <div class="d-flex justify-content-end gap-3">
                                <button class="btn btn-secondary cmn_focus min-w-120" tabindex="9" type="reset">
                                    {{ translate('reset') }}
                                </button>
                                <button class="btn btn-primary cmn_focus min-w-120" tabindex="10" type="submit">
                                    {{ translate('save') }}
                                </button>
                            </div>
                        </div>
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
                    {{ translate('App Download Setup Preview') }}
                </h4>
                <button type="button" class="btn btn-circle btn-secondary text-white" data-bs-dismiss="offcanvas" aria-label="Close" style="--size: 20px;">
                    <i class="bi bi-x-lg d-flex"></i>
                </button>
            </div>
            <div class="offcanvas-body scrollbar-thin">
                <div class="h-100 p-3 p-sm-5">
                   <div class="border rounded-10 d-flex justify-content-center align-items-center p-3 p-sm-5">
                    <div>
                        <div class="card card-body border-0 mb-3">
                            <div class="d-flex flex-column gap-4 align-items-center justify-content-center text-center">
                                <div>
                                    <h4 class="fw-demibold mb-1">{{ translate('Download_the_Driver_App') }}</h4>
                                    <p class="fs-12 mb-0">{{ translate('Take control of your rides anywhere.') }}</p>
                                </div>
                                <div class="bg-light rounded p-3 d-flex flex-column gap-2 align-items-center">
                                    <div class="border border-D0DBE966 rounded p-2">
                                        <img width="64" height="64" src="{{ dynamicAsset('public/assets/admin-module/img/scan.png') }}" alt="">
                                    </div>
                                    <div class="fs-12">{{ translate('Scan to Download') }}</div>
                                </div>
                                <div class="d-flex justify-content-center align-items-center gap-3 flex-wrap">
                                    <span class="bg-black text-absolute-white px-3 py-2 rounded text-white fw-semibold d-flex gap-2 align-items-center justify-content-center flex-grow-1">
                                        <img width="16" src="{{ dynamicAsset('public/assets/admin-module/img/media/play-store.png') }}" class="aspect-1"  alt="">
                                        <span>{{ translate('Google_Play') }}</span>
                                    </span>
                                    <span class="bg-black text-absolute-white px-3 py-2 rounded text-white fw-semibold d-flex gap-2 align-items-center justify-content-center flex-grow-1 child-svg-w-16">
                                        <img width="16" src="{{ dynamicAsset('public/assets/admin-module/img/svg/apple-new.svg') }}" class="svg"  alt="">
                                        <span>{{ translate('App_Store') }}</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="card card-body bg-center bg-img" style="background: url('{{dynamicAsset('public/assets/admin-module/img/preview/app-download-bg.png')}}')">
                            <div class="d-flex flex-column gap-4 align-items-center justify-content-center text-center">
                                <div>
                                    <h4 class="text-absolute-white fw-demibold mb-1">{{ translate('Download_the_User_App') }}</h4>
                                    <p class="text-absolute-white fs-12 mb-0">{{ translate('Book and manage your rides anytime, anywhere.') }}</p>
                                </div>
                                <div class="bg-light rounded p-3 d-flex flex-column gap-2 align-items-center">
                                    <div class="border border-D0DBE966 rounded p-2">
                                        <img width="64" height="64" src="{{ dynamicAsset('public/assets/admin-module/img/scan.png') }}" alt="">
                                    </div>
                                    <div class="fs-12">{{ translate('Scan to Download') }}</div>
                                </div>
                                <div class="d-flex justify-content-center align-items-center gap-3 flex-wrap">
                                    <span class="bg-white text-dark px-3 py-2 rounded fw-semibold d-flex gap-2 align-items-center justify-content-center flex-grow-1">
                                        <img width="16" src="{{ dynamicAsset('public/assets/admin-module/img/media/play-store.png') }}" class="aspect-1"  alt="">
                                        <span>{{ translate('Google_Play') }}</span>
                                    </span>
                                    <span class="bg-white text-dark px-3 py-2 rounded fw-semibold d-flex gap-2 align-items-center justify-content-center flex-grow-1 child-svg-w-16">
                                        <img width="16" src="{{ dynamicAsset('public/assets/admin-module/img/svg/apple-new.svg') }}" class="svg"  alt="">
                                        <span>{{ translate('App_Store') }}</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                   </div>
                </div>
            </div>
        </form>
    </div>
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
