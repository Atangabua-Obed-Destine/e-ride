@extends('adminmodule::layouts.master')

@section('title', translate('Newsletter'))

@push('css_or_js')
@endpush

@section('content')
    @php($env = env('APP_MODE') == 'demo' ? 'demo' : 'live')
    <!-- Main Content -->
    <form action="{{ route('admin.business.pages-media.landing-page.newsletter.update-section') }}"
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

            <div class="card card-body mb-3">
                <div class="d-flex flex-md-nowrap flex-wrap align-items-center justify-content-between gap-3">
                    <div class="w-0 flex-grow-1">
                        <h4 class="text-capitalize mb-1">{{ translate('Show_Newsletter') }}</h4>
                        <div class="fs-14">
                            {{ translate('Allow this option to display the Newsletter Section on the landing page.') }}
                        </div>
                    </div>
                    <label
                        class="max-w300 w-100 form-control rounded d-flex align-items-center justify-content-between">
                        <label for="showNewsletter"
                               class="fs-14 lh-1 d-block cursor-pointer text-dark">{{ translate('Status') }}</label>
                        <label class="switcher cmn_focus rounded-pill">
                            <input class="switcher_input update-business-setting" id="showNewsletter"
                                   name="is_newsletter_enabled" type="checkbox" data-name="is_newsletter_enabled"
                                   data-type="{{ NEWSLETTER }}" tabindex="4"
                                   data-icon="{{ $isNewsletterEnabled == 0 ? dynamicAsset('public/assets/admin-module/img/svg/info-circle-green.svg') : dynamicAsset('public/assets/admin-module/img/svg/info-circle-red.svg') }}"
                                   data-url="{{ route('admin.business.pages-media.landing-page.update-landing-page-setting') }}"
                                   data-title="{{ $isNewsletterEnabled == 0 ? translate('Want to enable Newsletter') : translate('Want to disable Newsletter') }}?"
                                   data-sub-title="{{ $isNewsletterEnabled == 0 ? translate(' If you turn on the Newsletter, users will see it in the landing page.') : translate('If you turn off the Newsletter, users will no longer see it in the landing page.') }}"
                                {{ $isNewsletterEnabled == 1 ? 'checked' : '' }}
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
                            {{ translate('Newsletter_Section') }}
                        </h4>
                        <p class="fs-12 mb-0">{{ translate('See how your our newsletter section will look to customers.') }}</p>
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


                <input type="hidden" name="key_name" value="{{ INTRO_CONTENTS }}">
                <div class="card card-body">
                    <div class="mb-20">
                        <h5 class="mb-1">
                            {{ translate('Newsletter_Section_Content') }}
                        </h5>
                        <p class="fs-12 mb-0">{{ translate('Configure the section content by setting the title, subtitle, and background image.') }}</p>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <div class="p-lg-4 p-3 rounded bg-F6F6F6 h-100">
                                <div class="mb-4">
                                    <label for="title" class="mb-2">{{ translate('title') }} <span
                                            class="text-danger">*</span></label>
                                    <div class="character-count d-flex flex-column align-items-end">
                                        <input type="text" class="form-control character-count-field" maxlength="100"
                                               data-max-character="100" id="" name="title"
                                               value="{{ $introContents?->value['title'] }}" tabindex="2"
                                               placeholder="{{ translate('Ex: Title') }}" required>
                                        <span>{{translate('0/100')}}</span>
                                    </div>

                                </div>
                                <div class="">
                                    <label for="subTitle" class="mb-2">{{ translate('sub_Title') }} <span
                                            class="text-danger">*</span></label>
                                    <div class="character-count d-flex flex-column align-items-end">
                                        <input type="text" class="form-control character-count-field" maxlength="200"
                                               data-max-character="200" id="" name="subtitle"
                                               value="{{ $introContents?->value['subtitle'] }}" tabindex="3"
                                               placeholder="{{ translate('Ex: Sub_Title') }}" required>
                                        <span>{{translate('0/200')}}</span>
                                    </div>
                                </div>

                            </div>
                        </div>
                        <div class="col-12">
                            <?php
                            $maxSize = readableUploadMaxFileSize('image');
                            ?>
                            <div
                                class="p-lg-4 p-3 rounded bg-F6F6F6 d-flex flex-column justify-content-around gap-3 h-100">
                                <div class="text-center">
                                    <h5 class="mb-1">
                                        {{ translate('Upload Background Image') }} <span class="text-danger">*</span>
                                    </h5>
                                    <p class="fs-12 mb-0">{{ translate('Upload Newsletter Section background Image') }}</p>
                                </div>

                                <div class="upload-file-new">
                                    <input type="file" class="upload-file-new__input single_file_input" tabindex="4"
                                           name="background_image" accept="{{ IMAGE_ACCEPTED_EXTENSIONS }}"
                                           data-max-upload-size="{{ $maxSize }}"
                                        {{ $introContents?->value['background_image'] ? '' : 'required' }}
                                    >
                                    <label class="upload-file-new__wrapper ratio-7-1">
                                        <div class="upload-file-new-textbox text-center">
                                            <img width="34" height="34" class="svg"
                                                 src="{{ dynamicAsset('public/assets/admin-module/img/document-upload.svg') }}"
                                                 alt="image upload">
                                            <h6 class="mt-2 fw-medium text-center text-info mb-0">{{ translate('Add') }}</h6>
                                        </div>
                                        <img class="upload-file-new-img" loading="lazy"
                                             src="{{ $introContents?->value['background_image'] ? dynamicStorage('storage/app/public/business/landing-pages/newsletter/'.$introContents?->value['background_image']) : '' }}"
                                             data-default-src="{{ $introContents?->value['background_image'] ? dynamicStorage('storage/app/public/business/landing-pages/newsletter/'.$introContents?->value['background_image']) : '' }}"
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
                                    <span>(1200x150 px)</span>
                                </p>
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
    <!-- End Main Content -->

    {{-- Section Preview Offcanvas --}}
    <div class="offcanvas offcanvas-end" id="sectionPriview-offcanvas" style="--bs-offcanvas-width: 755px;">
        <form action="javascript:" class="d-flex flex-column h-100">
            <div class="offcanvas-header">
                <h4 class="offcanvas-title flex-grow-1">
                    {{ translate('Newsletter  Section Preview') }}
                </h4>
                <button type="button" class="btn btn-circle btn-secondary text-white" data-bs-dismiss="offcanvas" aria-label="Close" style="--size: 20px;">
                    <i class="bi bi-x-lg d-flex"></i>
                </button>
            </div>
            <div class="offcanvas-body scrollbar-thin">
                <div class="h-100 overflow-y-auto py-5 px-0">
                    <img src="{{ dynamicAsset('public/assets/admin-module/img/preview/newsletter.png') }}" alt=""
                         class="img-fluid w-100 d-block">
                </div>
            </div>
        </form>
    </div>

    @include('businessmanagement::admin.pages.partials._note-modal',['page' => 'newsletter'])
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
