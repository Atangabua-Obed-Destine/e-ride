@extends('adminmodule::layouts.master')

@section('title', translate('Business Statistics'))

@push('css_or_js')
@endpush

@section('content')
    @php($env = env('APP_MODE') == 'demo' ? 'demo' : 'live')
    @php($maxSize = readableUploadMaxFileSize('image'))
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
                        <h4 class="text-capitalize mb-1">{{ translate('Business Statistics Solutions') }}</h4>
                        <div class="fs-14">
                            {{ translate('If you turn off the availability status, this section will not show in the website') }}
                        </div>
                    </div>
                    <label
                        class="max-w300 w-100 form-control rounded d-flex align-items-center justify-content-between">
                        <label for="showBusinessStatistics"
                               class="fs-14 lh-1 d-block cursor-pointer text-dark">{{ translate('Status') }}</label>
                        <label class="switcher cmn_focus rounded-pill">
                            <input class="switcher_input update-business-setting" id="showBusinessStatistics"
                                   name="is_business_statistics_enabled" type="checkbox"
                                   data-name="is_business_statistics_enabled"
                                   data-type="{{ BUSINESS_STATISTICS }}" tabindex="4"
                                   data-icon="{{$isBusinessStatisticsEnabled == 0 ? dynamicAsset('public/assets/admin-module/img/svg/info-circle-green.svg') : dynamicAsset('public/assets/admin-module/img/svg/info-circle-red.svg') }}"
                                   data-url="{{ route('admin.business.pages-media.landing-page.update-landing-page-setting') }}"
                                   data-title="{{ $isBusinessStatisticsEnabled == 0  ? translate('Want to enable Business Statistics section') : translate('Want to disable Business Statistics section') }}?"
                                   data-sub-title="{{ $isBusinessStatisticsEnabled == 0 ? translate(' If you turn on the Business Statistics section, users will see it in the landing page.') : translate('If you turn off the Business Statistics section, users will no longer see it in the landing page.') }}"
                                {{ $isBusinessStatisticsEnabled == 1 ? 'checked' : '' }}
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
                            {{ translate('Business Statistics Section') }}
                        </h4>
                        <p class="fs-12 mb-0">{{ translate('See how your business statistics section will look to customers.') }}</p>
                    </div>
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <button type="button" class="btn btn-outline-primary text-capitalize cmn_focus px-3"
                                tabindex="1"
                                data-bs-toggle="modal" data-bs-target="#noteModal">
                            <i class="bi bi-journal-text"></i>
                            {{ translate('Note') }}
                        </button>
                        <button type="button" class="btn btn-outline-primary text-capitalize cmn_focus px-3"
                                tabindex="2"
                                data-bs-toggle="offcanvas" data-bs-target="#sectionPriview-offcanvas">
                            <i class="bi bi-eye-fill"></i>
                            {{ translate('Section_Preview') }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                @foreach($data as $key => $item)
                        <?php
                        $title = match ($item?->key_name) {
                            'total_download' => translate('Total Download Count'),
                            'complete_ride' => translate('Total Complete Ride Count'),
                            'happy_customer' => translate('Total Happy Customer Count'),
                            default => translate('title')
                        };
                        $tab = match ($item?->key_name) {
                            'total_download' => translate('Total Download'),
                            'complete_ride' => translate('Complete Ride'),
                            'happy_customer' => translate('Happy Customer'),
                            default => translate('24/7 Support')
                        };
                        $imageText = match ($item?->key_name) {
                            'total_download' => translate('Upload your Download Image'),
                            'complete_ride' => translate('Upload your Complete Ride Image'),
                            'happy_customer' => translate('Upload your Happy Customer Image'),
                            default => translate('Upload your Support Image')
                        };
                        ?>
                    <div class="col-lg-6">
                        <form action="{{ route('admin.business.pages-media.landing-page.business-statistics.update') }}"
                              id="" enctype="multipart/form-data" method="POST" class="h-100">
                            @csrf
                            <input type="hidden" name="key_name" value="{{ $item?->key_name }}">
                            <div class="card">
                                <div class="card-header d-flex align-items-center justify-content-between gap-3">
                                    <h4 class="text-capitalize mb-0">{{ $tab }}</h4>
                                    <label class="d-flex align-items-center gap-3">
                                        <label for="customerLevel"
                                               class="fs-14 lh-1 d-block cursor-pointer fw-medium">{{ translate('Status') }}</label>
                                        <label class="switcher rounded-pill cmn_focus">
                                            <input class="switcher_input" type="checkbox" name="status"
                                                   id="{{ $key }}" tabindex="3"
                                                {{ ($item?->value['status'] ?? 0) == 1 ? 'checked' : ''}}>
                                            <span class="switcher_control"></span>
                                        </label>
                                    </label>
                                </div>
                                <div class="card-body">
                                    <div class="p-lg-4 p-3 rounded bg-F6F6F6 d-flex flex-column gap-3 h-100 mb-3">
                                        <div class="text-center">
                                            <h5 class="mb-1">
                                                {{ translate('Upload Icon / Image ') }} <span
                                                    class="text-danger">*</span>
                                            </h5>
                                            <p class="fs-12 mb-0">{{ $imageText }}</p>
                                        </div>

                                        <div class="upload-file-new">
                                            <input type="file" class="upload-file-new__input single_file_input"
                                                   accept="{{ IMAGE_ACCEPTED_EXTENSIONS }}" name="image" tabindex="4"
                                                   data-max-upload-size="{{ $maxSize }}" {{ $item?->value['image'] ? '' : 'required' }}>
                                            <label class="upload-file-new__wrapper ratio-1-1">
                                                <div class="upload-file-new-textbox text-center">
                                                    <img width="34" height="34" class="svg"
                                                         src="{{ dynamicAsset('public/assets/admin-module/img/document-upload.svg') }}"
                                                         alt="image upload">
                                                    <h6 class="mt-2 fw-medium text-center text-info mb-0">{{ translate('Add') }}</h6>
                                                </div>
                                                <img class="upload-file-new-img" loading="lazy"
                                                     src="{{ $item?->value['image'] ? dynamicStorage('storage/app/public/business/landing-pages/business-statistics/' . str_replace('_', '-', $item?->key_name) .  '/' .$item?->value['image']) : '' }}"
                                                     data-default-src="{{ $item?->value['image'] ? dynamicStorage('storage/app/public/business/landing-pages/business-statistics/' . str_replace('_', '-', $item?->key_name) .  '/' .$item?->value['image']) : '' }}"
                                                     alt="">
                                            </label>
                                            <div class="overlay">
                                                <div
                                                    class="d-flex gap-10 justify-content-center align-items-center h-100">
                                                    <button type="button"
                                                            class="btn btn-outline-info icon-btn edit_btn">
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
                                    <div class="p-lg-4 p-3 rounded bg-F6F6F6 h-100 mb-3">
                                        <div class="mb-3">
                                            <label for="totalDownloadCount_{{ $key }}" class="mb-2">{{ $title }} <span
                                                    class="text-danger">*</span></label>
                                            <div class="character-count d-flex flex-column align-items-end">
                                                <input type="text" class="form-control  character-count-field"
                                                       id="totalDownloadCount_{{ $key }}"
                                                       name="title" maxlength="20" data-max-character="20"
                                                       value="{{ $item?->value['title'] ?? "" }}"
                                                       placeholder="{{ translate('Ex: 5') }}" required tabindex="5">
                                                <span>{{translate('0/20')}}</span>
                                            </div>
                                        </div>
                                        <div>
                                            <label for="totalDownloadContent_{{ $key }}"
                                                   class="mb-2">{{ translate('Sub Title') }} <span
                                                    class="text-danger">*</span></label>
                                            <div class="character-count d-flex flex-column align-items-end">
                                                <input type="text" class="form-control  character-count-field"
                                                       id="totalDownloadContent_{{ $key }}"
                                                       name="content" maxlength="40" data-max-character="40"
                                                       value="{{ $item?->value['content'] ?? "" }}"
                                                       placeholder="{{ translate('Ex: Download') }}" required
                                                       tabindex="6">
                                                <span>{{translate('0/40')}}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-end gap-3 flex-wrap">
                                        <button class="btn btn-secondary cmn_focus min-w-120"
                                                type="reset" tabindex="7">{{ translate('reset') }}</button>
                                        <button class="btn btn-primary cmn_focus min-w-120"
                                                type="submit" tabindex="8">{{ translate('save') }}</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    <!-- End Main Content -->

    {{-- Section Preview Offcanvas --}}
    <div class="offcanvas offcanvas-end" id="sectionPriview-offcanvas" style="--bs-offcanvas-width: 755px;">
        <form action="javascript:" class="d-flex flex-column h-100">
            <div class="offcanvas-header">
                <h4 class="offcanvas-title flex-grow-1">
                    {{ translate('Business Statistics Section Preview') }}
                </h4>
                <button type="button" class="btn btn-circle btn-secondary text-white" data-bs-dismiss="offcanvas"
                        aria-label="Close" style="--size: 20px;">
                    <i class="bi bi-x-lg d-flex"></i>
                </button>
            </div>
            <div class="offcanvas-body scrollbar-thin">
                <div class="h-100 overflow-y-auto py-5 px-0">
                    <img src="{{ dynamicAsset('public/assets/admin-module/img/preview/business-statistics1.png') }}"
                         alt="" class="img-fluid w-100 d-block mb-3">

                    <img src="{{ dynamicAsset('public/assets/admin-module/img/preview/business-statistics2.png') }}"
                         alt="" class="img-fluid w-100 d-block">
                </div>
                <div class="h-100 overflow-y-auto py-5 px-0">

                </div>
            </div>
        </form>
    </div>

    @include('businessmanagement::admin.pages.partials._note-modal',['page' => 'business_statistics'])
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
