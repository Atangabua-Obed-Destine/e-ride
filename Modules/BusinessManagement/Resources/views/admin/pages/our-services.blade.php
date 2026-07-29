@extends('adminmodule::layouts.master')

@section('title', translate('Our Services'))

@push('css_or_js')
    <link rel="stylesheet"
          href="{{ dynamicAsset('public/assets/admin-module/plugins/summernote/summernote-lite.min.css') }}"/>
@endpush

@section('content')
    @php($env = env('APP_MODE') == 'demo' ? 'demo' : 'live')
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
                        <h4 class="text-capitalize mb-1">{{ translate('Show Our Services') }}</h4>
                        <div class="fs-14">
                            {{ translate('If you turn off the availability status, this section will not show in the website') }}
                        </div>
                    </div>
                    <label
                        class="max-w300 w-100 form-control rounded d-flex align-items-center justify-content-between">
                        <label for="showOurServices"
                               class="fs-14 lh-1 d-block cursor-pointer text-dark">{{ translate('Status') }}</label>
                        <label class="switcher cmn_focus rounded-pill">
                            <input class="switcher_input update-business-setting" id="showOurServices"
                                   name="is_our_services_enabled" type="checkbox" data-name="is_our_services_enabled"
                                   data-type="{{ OUR_SERVICES }}" tabindex="4"
                                   data-url="{{ route('admin.business.pages-media.landing-page.update-landing-page-setting') }}"
                                   data-icon="{{$isOurServicesEnabled == 0 ? dynamicAsset('public/assets/admin-module/img/svg/info-circle-green.svg') : dynamicAsset('public/assets/admin-module/img/svg/info-circle-red.svg') }}"
                                   data-title="{{ $isOurServicesEnabled == 0 ? translate('Want to enable Our Services section') : translate('Want to disable Our Services section') }}?"
                                   data-sub-title="{{ $isOurServicesEnabled == 0 ? translate(' If you turn on the Our Services section, users will see it in the landing page.') : translate('If you turn off the Our Services section, users will no longer see it in the landing page.') }}"
                                {{ $isOurServicesEnabled == 1 ? 'checked' : '' }}
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
                            {{ translate('Our Services Section') }}
                        </h4>
                        <p class="fs-12 mb-0">{{ translate('See how your our services Section will look to customers.') }}</p>
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

            <div class="card card-body mb-3">
                <h5 class="mb-20">{{ translate('section_Content') }}</h5>
                <form action="{{ route('admin.business.pages-media.landing-page.our-services.update-section') }}" id=""
                      enctype="multipart/form-data" method="POST">
                    @csrf
                    <input type="hidden" name="key_name" value="{{ INTRO_CONTENTS }}">
                    <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div>
                                    <label for="title" class="mb-2">
                                        {{ translate('title') }}
                                        <span class="text-danger">*</span>
                                    </label>
                                    <div class="character-count d-flex flex-column align-items-end">
                                        <input type="text" class="form-control character-count-field" maxlength="100"
                                               data-max-character="100" id="" name="title"
                                               value="{{ $introContents?->value['title'] }}"
                                               placeholder="{{ translate('Ex: Title') }}" required tabindex="2">
                                        <span>{{translate('0/100')}}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div>
                                    <label for="subTitle" class="mb-2">
                                        {{ translate('sub_Title') }}
                                        <span class="text-danger">*</span>
                                    </label>
                                    <div class="character-count d-flex flex-column align-items-end">
                                        <input type="text" class="form-control character-count-field" maxlength="255"
                                               data-max-character="255" id="" name="subtitle"
                                               value="{{ $introContents?->value['subtitle'] }}"
                                               placeholder="{{ translate('Ex: Subtitle') }}" required tabindex="3">
                                        <span>{{translate('0/255')}}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-3 mt-4">
                        <button class="btn btn-secondary cmn_focus min-w-120" tabindex="4" type="reset">
                            {{ translate('reset') }}
                        </button>
                        <button class="btn btn-primary cmn_focus min-w-120" tabindex="5" type="submit">
                            {{ translate('save') }}
                        </button>
                    </div>
                </form>
            </div>

            @foreach($services as $key => $service)
                <div class="card card-body mb-3">
                    <form action="{{ route('admin.business.pages-media.landing-page.our-services.update-section') }}"
                          id="" enctype="multipart/form-data" method="POST" class="js-form">
                        <div class="d-flex align-items-center justify-content-between gap-3 mb-20">
                            <div class="w-0 flex-grow-1">
                                <h4 class="text-capitalize mb-1">{{ translate('Tab ' . $key + 1) }} </h4>
                                <div class="fs-14">
                                    {{ translate('Configure the tab content by setting the tab name, title, subtitle, and background image.') }}
                                </div>
                            </div>
                            <label class="d-flex align-items-center gap-3">
                                <label class="switcher rounded-pill cmn_focus">
                                    <input class="switcher_input" type="checkbox" name="status"
                                           id="{{ $key }}" tabindex="12"
                                        {{ ($service?->value['status'] ?? 0) == 1 ? 'checked' : ''}}>
                                    <span class="switcher_control"></span>
                                </label>
                            </label>
                        </div>
                        <input type="hidden" name="key_name" value="{{ $service->key_name }}">
                        @csrf
                        <div class="row g-3">
                            <div class="col-lg-8">
                                <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                                    <div class="mb-3">
                                        <label for="" class="mb-2">
                                            {{ translate('tab_Name') }} <span class="text-danger">*</span>
                                        </label>
                                        <div class="character-count d-flex flex-column align-items-end">
                                            <input type="text" class="form-control character-count-field"
                                                   maxlength="20" data-max-character="20" id=""
                                                   name="tab_name" tabindex="6"
                                                   value="{{ $service->value['tab_name'] ?? old('tab_name') }}"
                                                   placeholder="{{ translate('ex') }}: {{ translate('Regular_Trip') }}"
                                                   required>
                                            <span>{{translate('0/20')}}</span>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="" class="mb-2">
                                            {{ translate('Title') }} <span class="text-danger">*</span>
                                        </label>
                                        <div class="character-count d-flex flex-column align-items-end">
                                            <input type="text" class="form-control character-count-field"
                                                   maxlength="200" data-max-character="200" id=""
                                                   name="title" tabindex="7"
                                                   value="{{ $service->value['title'] ?? old('title') }}"
                                                   placeholder="{{ translate('ex') }}: {{ translate('Ride_Sharing') }}"
                                                   required>
                                            <span>{{translate('0/200')}}</span>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="" class="mb-2">
                                            {{ translate('Description') }}
                                        </label>
                                        <div class="bg-white">
                                            <textarea class="summernote" name="description">
                                                {{ $service->value['description'] ?? old('description') }}
                                            </textarea>
                                        </div>
                                    </div>

                                </div>
                            </div>

                                <?php
                                $maxSize = readableUploadMaxFileSize('image');
                                ?>
                            <div class="col-lg-4">
                                <div
                                    class="p-lg-4 p-3 rounded bg-F6F6F6 d-flex flex-column justify-content-center gap-3 h-100">
                                    <div class="text-center">
                                        <h5 class="mb-1">
                                            {{ translate('Upload_Image') }}<span class="text-danger">*</span>
                                        </h5>
                                        <p class="fs-12 mb-0">{{ translate('Upload our services Section Image') }}</p>
                                    </div>

                                    <div class="upload-file-new my-0">
                                        <input type="file" class="upload-file-new__input single_file_input" tabindex="8"
                                               name="image" accept="{{ IMAGE_ACCEPTED_EXTENSIONS }}"
                                               data-max-upload-size="{{ $maxSize }}"
                                            {{ $service?->value['image'] ? '' : 'required' }}
                                        >
                                        <label class="upload-file-new__wrapper ratio-1-1">
                                            <div class="upload-file-new-textbox text-center">
                                                <img width="34" height="34" class="svg"
                                                     src="{{ dynamicAsset('public/assets/admin-module/img/document-upload.svg') }}"
                                                     alt="image upload">
                                                <h6 class="mt-2 fw-medium text-center text-info mb-0">{{ translate('Add') }}</h6>
                                            </div>
                                            <img class="upload-file-new-img" loading="lazy"
                                                 src="{{ $service?->value['image'] ? dynamicStorage('storage/app/public/business/landing-pages/our-services/' . $service?->value['image']) : '' }}"
                                                 data-default-src="{{ $service?->value['image'] ? dynamicStorage('storage/app/public/business/landing-pages/our-services/' . $service?->value['image']) : '' }}"
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
                            <button class="btn btn-secondary cmn_focus min-w-120" tabindex="9" type="reset">
                                {{ translate('reset') }}
                            </button>
                            <button class="btn btn-primary cmn_focus min-w-120" tabindex="10" type="submit">
                                {{ translate('save') }}
                            </button>
                        </div>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
    <!-- End Main Content -->

    {{-- Section Preview Offcanvas --}}
    <div class="offcanvas offcanvas-end" id="sectionPriview-offcanvas" style="--bs-offcanvas-width: 755px;">
        <form action="javascript:" class="d-flex flex-column h-100">
            <div class="offcanvas-header">
                <h4 class="offcanvas-title flex-grow-1">
                    {{ translate('Our Services Section Preview') }}
                </h4>
                <button type="button" class="btn btn-circle btn-secondary text-white" data-bs-dismiss="offcanvas" aria-label="Close" style="--size: 20px;">
                    <i class="bi bi-x-lg d-flex"></i>
                </button>
            </div>
            <div class="offcanvas-body scrollbar-thin">
                <div class="h-100 overflow-y-auto py-5 px-0">
                    <img src="{{ dynamicAsset('public/assets/admin-module/img/preview/our-services.png') }}" alt=""
                         class="img-fluid w-100 d-block">
                </div>
            </div>
        </form>
    </div>

    @include('businessmanagement::admin.pages.partials._note-modal',['page' => 'our_services'])
@endsection

@push('script')
    <script src="{{ dynamicAsset('public/assets/admin-module/js/single-image-upload-new.js') }}"></script>

    <script src="{{ dynamicAsset('public/assets/admin-module/plugins/summernote/summernote-lite.min.js') }}"></script>

    <script>
        "use strict";
        let permission = false;
        @can('business_edit')
            permission = true;
        @endcan

        $(document).ready(function () {
            $('.summernote').each(function () {
                $(this).summernote({
                    placeholder: '{{ translate('enter_description') }}',
                    tabsize: 2,
                    height: 200,
                    toolbar: [
                        ['style', ['style']],
                        ['font', ['bold', 'underline', 'clear']],
                        ['color', ['color']],
                        ['para', ['ul', 'ol', 'paragraph']],
                        ['table', ['table']],
                        ['insert', ['link']],
                        ['view', ['fullscreen', 'codeview', 'help']]
                    ]
                });
            });
        });

        $('.js-form').on('reset', function () {
            const $form = $(this);

            setTimeout(function () {
                $form.find('.summernote').each(function () {
                    $(this).summernote('code', this.defaultValue);
                });
            }, 0);
        });

    </script>
@endpush
