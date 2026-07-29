@extends('adminmodule::layouts.master')

@section('title', translate('Landing_Page'))

@push('css_or_js')
@endpush

@section('content')
    @php($env = env('APP_MODE') == 'live' ? 'live' : 'test')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-22 mb-4 text-capitalize">{{translate('landing_page')}}</h2>
            @include('businessmanagement::admin.pages.partials._landing_page_inline_menu')


            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-3">
                        <h5 class="text-uppercase">{{ translate('Edit Testimonial') }}</h5>
                    </div>

                    <form action="{{ route('admin.business.pages-media.landing-page.testimonial.update') }}"
                          id="banner_form" enctype="multipart/form-data" method="POST">
                        @csrf
                        @php($maxSize = readableUploadMaxFileSize('image'))
                        <input type="hidden" name="id" value="{{ $data->id }}">
                        <div class="row g-3">
                            <div class="col-lg-9">
                                <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <div>
                                                <label for="reviewer_name"
                                                       class="mb-2">{{ translate('Reviewer_Name') }} <span
                                                        class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="reviewer_name"
                                                       name="reviewer_name" placeholder="{{ translate('Ex: Ahmed') }}"
                                                       value="{{ $data?->value['reviewer_name']  ?? "" }}"
                                                       required tabindex="3">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div>
                                                <label for="designation" class="mb-2">{{ translate('Designation') }}
                                                    <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="designation" name="designation"
                                                       placeholder="{{ translate('Ex: Engineer') }}"
                                                       value="{{  $data?->value['designation']  ?? "" }}"
                                                       required tabindex="4">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div>
                                                <label for="rating" class="mb-2">{{ translate('Rating') }} <span
                                                        class="text-danger">*</span></label>
                                                <select name="rating" id="rating" class="form-select bg-white" required
                                                        tabindex="5">
                                                    <option value="1" {{ $data?->value['rating'] == 1 || $data?->value['rating']? 'selected' : ""  }}>1</option>
                                                    <option value="2" {{ $data?->value['rating'] == 2 }}>2</option>
                                                    <option value="3" {{ $data?->value['rating'] == 3 }}>3</option>
                                                    <option value="4" {{ $data?->value['rating'] == 4 }}>4</option>
                                                    <option value="5" {{ $data?->value['rating'] == 5 }}>5</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div>
                                                <label for="review" class="mb-2">{{ translate('Review') }} <span
                                                        class="text-danger">*</span></label>
                                                <div class="character-count d-flex flex-column align-items-end">
                                                <textarea name="review" id="review" rows="3"
                                                          class="form-control character-count-field" maxlength="200"
                                                          data-max-character="200"
                                                          placeholder="{{ translate('Ex: review ...') }}"
                                                          required tabindex="6">{{ $data?->value['review'] ?? "" }}</textarea>
                                                    <span>{{translate('0/200')}}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php
                            $maxSize = readableUploadMaxFileSize('image');
                            ?>
                            <div class="col-lg-3">
                                <div
                                    class="p-lg-4 p-3 rounded bg-F6F6F6 d-flex flex-column justify-content-around gap-3 h-100">
                                    <div class="text-center">
                                        <h5 class="mb-1">
                                            {{ translate('Reviewer Image') }} <span class="text-danger">*</span>
                                        </h5>
                                        <p class="fs-12 mb-0">{{ translate('Upload Reviewer Image') }}</p>
                                    </div>

                                    <div class="upload-file-new">
                                        <input type="file" class="upload-file-new__input single_file_input" tabindex="7"
                                               name="reviewer_image" accept="{{ IMAGE_ACCEPTED_EXTENSIONS }}"
                                               data-max-upload-size="{{ $maxSize }}" {{ $data?->value['reviewer_image'] ? '' : 'required' }}>
                                        <label class="upload-file-new__wrapper ratio-1-1">
                                            <div class="upload-file-new-textbox text-center">
                                                <img width="34" height="34" class="svg"
                                                     src="{{ dynamicAsset('public/assets/admin-module/img/document-upload.svg') }}"
                                                     alt="image upload">
                                                <h6 class="mt-2 fw-medium text-center text-info mb-0">{{ translate('Add') }}</h6>
                                            </div>
                                            <img class="upload-file-new-img" loading="lazy"
                                                 src="{{$data?->value['reviewer_image'] ? dynamicStorage('storage/app/public/business/landing-pages/testimonial/'.$data?->value['reviewer_image']) : ''}}"
                                                 data-default-src="{{$data?->value['reviewer_image'] ? dynamicStorage('storage/app/public/business/landing-pages/testimonial/'.$data?->value['reviewer_image']) : ''}}"
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
                            <button class="btn btn-secondary cmn_focus min-w-120" tabindex="8" type="reset">
                                {{ translate('reset') }}
                            </button>
                            <button class="btn btn-primary cmn_focus min-w-120" tabindex="9" type="submit">
                                {{ translate('update') }}
                            </button>
                        </div>
                    </form>
                </div>
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
