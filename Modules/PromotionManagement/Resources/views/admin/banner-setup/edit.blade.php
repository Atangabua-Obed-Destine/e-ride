div@section('title', 'Banner Setup')

@extends('adminmodule::layouts.master')

@push('css_or_js')
@endpush

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">

            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-3">
                        <h5 class="text-primary text-uppercase">{{ translate('edit_banner') }}</h5>
                    </div>

                    <form action="{{ route('admin.promotion.banner-setup.update', ['id' => $banner->id]) }}"
                          id="banner_form"
                          enctype="multipart/form-data" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="row g-4 align-items-end">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label for="banner_title" class="mb-2">{{ translate('banner_title') }} <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="banner_title" name="banner_title"
                                           value="{{ $banner->name }}" placeholder="Ex: 50% Off" required tabindex="1">
                                </div>
                                <div class="">
                                    <label for="sort_description"
                                           class="mb-2">{{ translate('short_description') }} <span class="text-danger">*</span></label>

                                    <div class="character-count">
                                        <textarea name="short_desc" id="sort_description" placeholder="Type Here..."
                                                  class="form-control character-count-field" cols="30"
                                                  rows="6" maxlength="800" data-max-character="800"
                                                  required tabindex="2">{{ $banner->description }}</textarea>
                                        <span>{{translate('0/800')}}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex flex-column justify-content-around align-items-center gap-3 mb-4">
                                    <div class="d-flex align-items-center gap-2">
                                        <h5 class="text-capitalize">{{ translate('banner_image') }} <span class="text-danger">*</span>
                                        </h5>
                                    </div>
                                    @php
                                        $maxSize = readableUploadMaxFileSize('image');
                                    @endphp
                                    <div class="d-flex justify-content-center">
                                        <div class="upload-file cmn_focus rounded-10 auto profile-image-upload-file">
                                            <input type="file" name="banner_image" class="upload-file__input"
                                                   accept="{{ IMAGE_ACCEPTED_EXTENSIONS }}" tabindex="4" data-max-upload-size="{{ $maxSize }}">
                                                <span class="edit-btn">
                                                    <i class="bi bi-pencil-square text-primary"></i>
                                                </span>
                                            <div
                                                class="upload-file__img border-gray d-flex justify-content-center align-items-center h-100px w-250px aspect-ratio-3-1 p-0 bg-white">
                                                <img class="upload-file__img__img h-100 aspect-ratio-inherit d-block"
                                                    loading="lazy" alt=""
                                                    src="{{ onErrorImage(
                                                    $banner?->image,
                                                    dynamicStorage('storage/app/public/promotion/banner') . '/' . $banner?->image,
                                                    dynamicAsset('public/assets/admin-module/img/media/banner-upload-file.png'),
                                                    'promotion/banner/',
                                                ) }}"
                                                >
                                            </div>
                                        </div>
                                    </div>

                                    <p class="opacity-75 mx-auto max-w220">
                                        {{ translate(key: 'File Format - {format}, Image Size - Maximum {imageSize}, Image Ratio - {ratio}', replace: ['format' => IMAGE_ACCEPTED_EXTENSIONS, 'imageSize' => $maxSize, 'ratio' => '3:1']) }}
                                    </p>
                                </div>
                            </div>
                            <div class="col-lg-6 order-1 order-lg-2">
                                <div class="">
                                    <label for="redirect_link"
                                    class="mb-2">{{ translate('redirect_link') }} <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="redirect_link" name="redirect_link"
                                    value="{{ $banner->redirect_link }}" placeholder="Ex: www.google.com"
                                    required tabindex="3">
                                </div>
                            </div>
                            <div class="col lg-6 order-2 order-lg-1">
                                <div class="text-capitalize">
                                    <label for="time_period" class="mb-2">{{ translate('time_period') }} <span class="text-danger">*</span></label>
                                    <select name="time_period" class="js-select cmn_focus" id="time_period"
                                            aria-label="{{ translate('time_period') }}" tabindex="5">
                                        <option disabled selected>{{ translate('select_time_period') }}</option>
                                        <option
                                            value="{{ALL_TIME}}" {{ $banner->time_period == ALL_TIME ? 'selected' : '' }}>
                                            {{ translate(ALL_TIME) }}</option>
                                        <option value="period" {{ $banner->time_period == 'period' ? 'selected' : '' }}>
                                            {{ translate('period') }}</option>
                                    </select>
                                </div>

                            </div>
                            <div class="col-lg-6 order-3 date-pick {{ $banner->start_date && $banner->end_date != null ? 'd-block' : 'd-none' }}">
                                <div class="d-flex gap-4 flex-column flex-sm-row w-100">
                                    <div class="flex-grow-1">
                                        <div class="">
                                            <label for="start_date"
                                                   class="mb-2">{{ translate('start_date') }}</label>
                                            <input type="date" name="start_date" id="start_date"
                                                   min="{{date('Y-m-d',strtotime(now()))}}"
                                                   value="{{ $banner->start_date }}" class="form-control" tabindex="6">
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="">
                                            <label for="end_date"
                                                   class="mb-2">{{ translate('end_date') }}</label>
                                            <input type="date" name="end_date" id="end_date"
                                                   min="{{date('Y-m-d',strtotime(now()))}}"
                                                   value="{{ $banner->end_date }}" class="form-control" tabindex="7">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6 order-4 ms-lg-auto">
                                <div class="d-flex justify-content-end gap-3">
                                    <button class="btn btn-primary cmn_focus text-uppercase"
                                            type="submit" tabindex="8">{{ translate('submit') }}</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- End Main Content -->
@endsection

@push('script')
    <script src="{{ dynamicAsset('public/assets/admin-module/js/promotion-management/banner-setup/edit.js') }}"></script>
    <script>
        "use strict";
        $('#banner_form').submit(function (e) {
            let timePeriod = $('#time_period').val();

            if (timePeriod === 'period' && $('#start_date').val() === '') {
                toastr.error('{{ translate('please_select_start_date') }}');
                e.preventDefault();
            }

            if (timePeriod === 'period' && $('#end_date').val() === '') {
                toastr.error('{{ translate('please_select_end_date') }}');
                e.preventDefault();
            }

            if (!timePeriod) {
                toastr.error('{{ translate('please_select_time_period') }}');
                e.preventDefault();
            }

        });
    </script>
@endpush
