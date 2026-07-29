@extends('adminmodule::layouts.master')

@section('title', translate('Gallery'))

@push('css_or_js')
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
                        <h4 class="text-capitalize mb-1">{{ translate('Show Gallery') }}</h4>
                        <div class="fs-14">
                            {{ translate('Allow the option to display the Gallery on the landing page.') }}
                        </div>
                    </div>
                    <label
                        class="max-w300 w-100 form-control rounded d-flex align-items-center justify-content-between">
                        <label for="showGallery"
                               class="fs-14 lh-1 d-block cursor-pointer text-dark">{{ translate('Status') }}</label>
                        <label class="switcher cmn_focus rounded-pill">
                            <input class="switcher_input update-business-setting" id="showGallery"
                                   name="is_gallery_enabled" type="checkbox" data-name="is_gallery_enabled"
                                   data-type="{{ GALLERY }}" tabindex="4"
                                   data-url="{{ route('admin.business.pages-media.landing-page.update-landing-page-setting') }}"
                                   data-icon="{{ $isGalleryEnabled == 0 ? dynamicAsset('public/assets/admin-module/img/svg/info-circle-green.svg') : dynamicAsset('public/assets/admin-module/img/svg/info-circle-red.svg') }}"
                                   data-title="{{ ($isGalleryEnabled == 0 ?? 1) ? translate('Want to enable Gallery section') : translate('Want to disable Gallery section') }}?"
                                   data-sub-title="{{ ($isGalleryEnabled == 0 ?? 1) ? translate(' If you turn on the Gallery section, users will see it in the landing page.') : translate('If you turn off the Gallery section, users will no longer see it in the landing page.') }}"
                                {{ $isGalleryEnabled == 1 ? 'checked' : '' }}
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
                            {{ translate('Gallery Section') }}
                        </h4>
                        <p class="fs-12 mb-0">{{ translate('See how your gallery section will look to customers.') }}</p>
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

            @foreach($cards as $key => $card)
                <form action="{{ route('admin.business.pages-media.landing-page.gallery.update-section') }}"
                      id="banner_form" enctype="multipart/form-data" method="POST">
                    <input type="hidden" name="key_name" value="{{ $card?->key_name }}">
                    @csrf
                    <div class="card card-body mb-3">
                        <h5 class="mb-20">{{ $key == 0 ? translate('1st Card') : translate('2nd Card') }}</h5>

                        <div class="row g-3">
                            <div class="col-lg-8">
                                <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                                    <div class="mb-3">
                                        <label for="" class="mb-2">
                                            {{ translate('Title') }} <span class="text-danger">*</span>
                                        </label>
                                        <div class="character-count d-flex flex-column align-items-end">
                                            <input type="text" class="form-control character-count-field"
                                                   maxlength="50" data-max-character="50" id=""
                                                   name="title" tabindex="5"
                                                   value="{{ $card?->value['title'] }}"
                                                   placeholder="{{ translate('ex') }}: {{ translate('Ride_Sharing') }}"
                                                   required>
                                            <span>{{translate('0/50')}}</span>
                                        </div>
                                    </div>
                                    <div>
                                        <label for="" class="mb-2">
                                            {{ translate('Sub Title') }} <span class="text-danger">*</span>
                                        </label>
                                        <div class="character-count d-flex flex-column align-items-end">
                                        <textarea name="subtitle" id="" rows="2" tabindex="6"
                                                  class="form-control character-count-field" maxlength="150"
                                                  data-max-character="150"
                                                  placeholder="{{ translate('ex') }}: {{ translate('Section_Description') }}"
                                                  required>{{ $card?->value['subtitle'] }}</textarea>
                                            <span>{{translate('0/150')}}</span>
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
                                            {{ translate('Upload Image ') }} <span class="text-danger">*</span>
                                        </h5>
                                        <p class="fs-12 mb-0">{{ translate('Upload your Card Image') }}</p>
                                    </div>

                                    <div class="upload-file-new">
                                        <input type="file" class="upload-file-new__input single_file_input" tabindex="4"
                                               name="image" accept="{{ IMAGE_ACCEPTED_EXTENSIONS }}"
                                               data-max-upload-size="{{ $maxSize }}"
                                            {{ $card?->value['image'] ? '' : 'required' }}
                                        >
                                        <label class="upload-file-new__wrapper ratio-1-1">
                                            <div class="upload-file-new-textbox text-center">
                                                <img width="34" height="34" class="svg"
                                                     src="{{ dynamicAsset('public/assets/admin-module/img/document-upload.svg') }}"
                                                     alt="image upload">
                                                <h6 class="mt-2 fw-medium text-center text-info mb-0">{{ translate('Add') }}</h6>
                                            </div>
                                            <img class="upload-file-new-img" loading="lazy"
                                                 src="{{ $card?->value['image'] ? dynamicStorage('storage/app/public/business/landing-pages/gallery/' . $card?->value['image']) : '' }}"
                                                 data-default-src="{{ $card?->value['image'] ? dynamicStorage('storage/app/public/business/landing-pages/gallery/' . $card?->value['image']) : '' }}"
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
                    </div>
                </form>
            @endforeach
        </div>
    </div>
    <!-- End Main Content -->

    {{-- Section Preview Offcanvas --}}
    <div class="offcanvas offcanvas-end" id="sectionPriview-offcanvas" style="--bs-offcanvas-width: 755px;">
        <form action="javascript:" class="d-flex flex-column h-100">
            <div class="offcanvas-header">
                <h4 class="offcanvas-title flex-grow-1">
                    {{ translate('Our Gallery Section Preview') }}
                </h4>
                <button type="button" class="btn btn-circle btn-secondary text-white" data-bs-dismiss="offcanvas" aria-label="Close" style="--size: 20px;">
                    <i class="bi bi-x-lg d-flex"></i>
                </button>
            </div>
            <div class="offcanvas-body scrollbar-thin">
                <div class="h-100 overflow-y-auto py-5 px-0">
                    <img src="{{ dynamicAsset('public/assets/admin-module/img/preview/gallery.png') }}" alt=""
                         class="img-fluid w-100 d-block">
                </div>
            </div>
        </form>
    </div>

    @include('businessmanagement::admin.pages.partials._note-modal',['page' => 'gallery'])
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
