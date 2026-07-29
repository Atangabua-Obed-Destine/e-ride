@section('title', 'Vehicle Attribute')

@extends('adminmodule::layouts.master')

@push('css_or_js')
@endpush

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex align-items-center gap-3 justify-content-between mb-4">
                <h2 class="fs-22 text-capitalize">{{ translate('vehicle_attribute') }}</h2>
            </div>

            <div class="row g-4">
                <div class="col-12">
                    <form action="{{ route('admin.vehicle.attribute-setup.brand.update', ['id' => $brand->id]) }}"
                        enctype="multipart/form-data" method="POST">
                        @csrf
                        @method('PUT')
                        @php
                            $maxSize = readableUploadMaxFileSize('image');
                        @endphp
                        <div class="card">
                            <div class="card-body">
                                <h5 class="text-primary text-uppercase mb-4">{{ translate('brand_edit') }}</h5>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-4">
                                            <label for="brand_name"
                                                class="mb-2">{{ translate('brand_name') }}<span class="text-danger">*</span></label>
                                            <input type="text" id="brand_name" name="brand_name" class="form-control"
                                                value="{{ $brand->name }}" placeholder="Ex: Brand" tabindex="1">
                                        </div>
                                        <div class="mb-4">
                                            <label for="short_desc"
                                                class="mb-2">{{ translate('short_description') }}<span class="text-danger">*</span></label>
                                            <div class="character-count">
                                                    <textarea id="short_desc" rows="5" name="short_desc"
                                                              class="form-control character-count-field" maxlength="800" tabindex="2"
                                                              data-max-character="800" placeholder="Ex: Description"
                                                              required>{{ $brand->description }}</textarea>
                                                <span>{{translate('0/800')}}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card-body d-flex flex-column gap-3">
                                            <h5 class="text-center text-capitalize">{{ translate('brand_logo') }}<span class="text-danger">*</span></h5>
                                            <div class="d-flex justify-content-center">
                                                <div class="upload-file border-light-mode auto profile-image-upload-file cmn_focus rounded-10">
                                                    <input type="file" class="upload-file__input" accept=".png" name="brand_logo" tabindex="3" data-max-upload-size="{{ $maxSize }}">
                                                    <span class="edit-btn">
                                                        <i class="bi bi-pencil-square text-primary"></i>
                                                    </span>
                                                    <div
                                                        class="upload-file__img border-gray d-flex justify-content-center align-items-center w-150 h-150 aspect-1 p-0">
                                                        <img class="upload-file__img__img h-100 d-block"
                                                            src="{{ onErrorImage(
                                                                $brand?->image,
                                                                dynamicStorage('storage/app/public/vehicle/brand') . '/' . $brand?->image,
                                                                dynamicAsset('public/assets/admin-module/img/media/upload-file.png'),
                                                                'vehicle/brand/',
                                                            ) }}"
                                                             loading="lazy" alt="" id="image_id">
                                                    </div>
                                                </div>
                                            </div>

                                            <p class="opacity-75 mx-auto text-center max-w220">
                                                {{ translate(key: 'File Format - {format}, Image Size - Maximum {imageSize}', replace: ['format' => '.png', 'imageSize' => $maxSize]) }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end gap-3">
                                    <button type="reset" id="reset_btn"
                                        class="btn btn-secondary cmn_reset" tabindex="4">{{ translate('reset') }}</button>
                                    <button type="submit" class="btn btn-primary cmn_focus" tabindex="5">{{ translate('update') }}</button>
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
    <script>
        "use strict";
        // Assuming you have a reset button with ID 'reset-button'
        let resetButton = $('#reset_btn');
        let defaultImageSrc = '{{ dynamicAsset('public/assets/admin-module/img/media/upload-file.png') }}';
        let imageElement = $('#image_id');
        let fileInput = $('.upload-file__input');

        resetButton.on('click', function() {
            imageElement.attr('src', defaultImageSrc);
            fileInput.val('');
        });
    </script>
@endpush
