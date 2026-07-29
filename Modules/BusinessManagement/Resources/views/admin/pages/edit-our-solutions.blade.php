@extends('adminmodule::layouts.master')

@section('title', translate('Landing_Page'))

@push('css_or_js')
@endpush

@section('content')
    @php($env = env('APP_MODE') == 'live' ? 'live' : 'test')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-22 mb-4 text-capitalize">{{translate('landing_page_setup')}}</h2>
            @include('businessmanagement::admin.pages.partials._landing_page_inline_menu')


            <div class="card card-body">
                <h5 class="mb-20">{{ translate('edit_Solution') }}</h5>
                <form action="{{ route('admin.business.pages-media.landing-page.our-solutions.update') }}"
                      id="banner_form" enctype="multipart/form-data" method="POST">
                    @csrf
                    <input type="hidden" name="key_name" value="{{ SOLUTIONS }}">
                    <input type="hidden" name="id" value="{{ $data->id }}">
                    <div class="row g-3">
                        <div class="col-lg-8">
                            <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                                <div class="mb-3">
                                    <label for="solution_title" class="mb-2">
                                        {{ translate('Title') }}
                                        <span class="text-danger">*</span>
                                    </label>
                                    <div class="character-count d-flex flex-column align-items-end">
                                        <input type="text" class="form-control character-count-field"
                                               maxlength="50" data-max-character="50" id="solution_title"
                                               value="{{ $data?->value['title']  ?? "" }}"
                                               name="title" tabindex="5"
                                               placeholder="{{ translate('ex') }}: {{ translate('Ride_Sharing') }}"
                                               required>
                                        <span>{{translate('0/50')}}</span>
                                    </div>
                                </div>
                                <div>
                                    <label for="solution_description" class="mb-2">
                                        {{ translate('Description') }}
                                        <span class="text-danger">*</span>
                                    </label>
                                    <div class="character-count d-flex flex-column align-items-end">
                                        <textarea name="description" id="solution_description" rows="2" tabindex="6"
                                                  class="form-control character-count-field" maxlength="100"
                                                  data-max-character="100"
                                                  placeholder="{{ translate('ex') }}: {{ translate('Section_Description') }}"
                                                  required>{{ $data?->value['description']  ?? "" }}</textarea>
                                        <span>{{translate('0/100')}}</span>
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
                                    <h5 class="mb-1">
                                        {{ translate('Icon / Image') }} <span class="text-danger">*</span>
                                    </h5>
                                    <p class="fs-12 mb-0">{{ translate('Upload your Solutions Section Icon/Image') }}</p>
                                </div>

                                <div class="upload-file-new">
                                    <input type="file" class="upload-file-new__input single_file_input" tabindex="4" name="image" accept="{{ IMAGE_ACCEPTED_EXTENSIONS }}" data-max-upload-size="{{ $maxSize }}"
                                        {{ $data?->value['image'] ? '' : 'required' }}>
                                    <label class="upload-file-new__wrapper ratio-1-1">
                                        <div class="upload-file-new-textbox text-center">
                                            <img width="34" height="34" class="svg" src="{{ dynamicAsset('public/assets/admin-module/img/document-upload.svg') }}" alt="image upload">
                                            <h6 class="mt-2 fw-medium text-center text-info mb-0">{{ translate('Add') }}</h6>
                                        </div>
                                        <img class="upload-file-new-img" loading="lazy"
                                             src="{{ $data?->value['image'] ? dynamicStorage('storage/app/public/business/landing-pages/our-solutions/'.$data?->value['image'])  :  '' }}"
                                             data-default-src="{{ $data?->value['image'] ? dynamicStorage('storage/app/public/business/landing-pages/our-solutions/'.$data?->value['image'])  :  '' }}"
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
                    </div>
                    <div class="d-flex justify-content-end gap-3 mt-4">
                        <button class="btn btn-secondary cmn_focus min-w-120" tabindex="3" type="reset">
                            {{ translate('reset') }}
                        </button>
                        <button class="btn btn-primary cmn_focus min-w-120" tabindex="4" type="submit">
                            {{ translate('save') }}
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>
    <!-- End Main Content -->
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
