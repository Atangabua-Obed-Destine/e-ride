@section('title', 'Vehicle Category')

@extends('adminmodule::layouts.master')

@push('css_or_js')
    <style>
        .ed-cat-photo { display: flex; flex-direction: column; gap: 6px; width: 150px; cursor: pointer; }
        .ed-cat-photo img { width: 150px; height: 100px; object-fit: cover; border-radius: 6px; border: 1px solid rgba(0,0,0,.1); }
        .ed-cat-photo:has(input:checked) img { opacity: .35; outline: 2px solid var(--bs-danger, #dc3545); }
    </style>
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
                    <form action="{{ route('admin.vehicle.attribute-setup.category.update', ['id' => $category->id]) }}"
                        enctype="multipart/form-data" method="POST">
                        @csrf
                        @method('PUT')
                        @php
                            $maxSize = readableUploadMaxFileSize('image');
                        @endphp
                        <div class="card">
                            <div class="card-body">
                                <h5 class="text-primary text-uppercase mb-4">{{ translate('category_edit') }}</h5>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-4">
                                            <label for="category_name"
                                                class="mb-2">{{ translate('category_name') }}<span class="text-danger">*</span></label>
                                            <input type="text" id="category_name" required name="category_name"
                                                class="form-control" value="{{ $category->name }}"
                                                placeholder="Ex: Category">
                                        </div>
                                        <div class="mb-4">
                                            <label for="category_type"
                                                class="mb-2">{{ translate('category_type') }}<span class="text-danger">*</span></label>
                                            <select id="category_type" name="type" class="form-control js-select cmn_focus"
                                                data-toggle="tooltip" data-placement="top" title="Tooltip on top">
                                                <option value="car" {{ $category['type'] == 'car' ? 'selected' : '' }}>
                                                    {{ translate('car') }}</option>
                                                <option value="motor_bike"
                                                    {{ $category['type'] == 'motor_bike' ? 'selected' : '' }}>
                                                    {{ translate('Motor_Bike') }}</option>
                                                @foreach(MOVE_VEHICLE_TYPES as $moveType)
                                                    <option value="{{ $moveType }}" {{ $category['type'] == $moveType ? 'selected' : '' }}>
                                                        {{ translate('move_type_' . $moveType) }} ({{ translate('E-Ride Move') }})</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="mb-4">
                                            <label for="short_desc"
                                                class="mb-2">{{ translate('short_description') }}<span class="text-danger">*</span></label>
                                            <div class="character-count">
                                                    <textarea id="short_desc" rows="5" name="short_desc"
                                                              class="form-control character-count-field" maxlength="800"
                                                              data-max-character="800" placeholder="Ex: Description"
                                                              required>{{ $category->description }}</textarea>
                                                <span>{{translate('0/800')}}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card-body d-flex flex-column gap-3">
                                            <h5 class="text-center text-capitalize">{{ translate('category_image') }}<span class="text-danger">*</span>
                                            </h5>

                                            <div class="d-flex justify-content-center">
                                                <div class="upload-file auto profile-image-upload-file">
                                                    <input type="file" class="upload-file__input" accept=".png" name="category_image" data-max-upload-size="{{ $maxSize }}">
                                                    <span class="edit-btn">
                                                        <i class="bi bi-pencil-square text-primary"></i>
                                                    </span>
                                                    <div
                                                        class="upload-file__img border-gray d-flex justify-content-center align-items-center w-150 h-150 aspect-1 p-0">
                                                        <img class="upload-file__img__img h-100 d-block"
                                                            id="image_id"
                                                            src="{{ onErrorImage(
                                                                $category?->image,
                                                                dynamicStorage('storage/app/public/vehicle/category') . '/' . $category?->image,
                                                                dynamicAsset('public/assets/admin-module/img/media/upload-file.png'),
                                                                'vehicle/category/',
                                                            ) }}"
                                                             loading="lazy" alt="">
                                                    </div>
                                                </div>
                                            </div>

                                            <p class="opacity-75 mx-auto text-center max-w220">
                                                {{ translate(key: 'File Format - {format}, Image Size - Maximum {imageSize}', replace: ['format' => '.png', 'imageSize' => $maxSize]) }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                @php
                                    $galleryBase = dynamicStorage('storage/app/public/vehicle/category/gallery');
                                    $gallery = array_values(array_filter((array)($category->gallery ?? [])));
                                    $maxPhotos = \Modules\VehicleManagement\Lib\CategoryDetails::MAX_PHOTOS;
                                @endphp
                                <div class="border-top pt-4 mt-2 mb-4" id="customer-details">
                                    <h5 class="text-primary mb-1">{{ translate('What customers see before booking') }}</h5>
                                    <p class="opacity-75 mb-4">{{ translate('Customers open these details from Trip Setup, next to the fare for their trip. They also see the vehicle models your drivers have in this category.') }}</p>
                                    <div class="row g-4">
                                        <div class="col-sm-6 col-lg-3">
                                            <label for="seats" class="mb-2">{{ translate('Passenger seats') }}</label>
                                            <input type="number" id="seats" name="seats" min="1" max="60" class="form-control"
                                                   value="{{ old('seats', $category->seats) }}" placeholder="{{ translate('Ex : 4') }}">
                                        </div>
                                        <div class="col-sm-6 col-lg-3">
                                            <label for="bags" class="mb-2">{{ translate('Bags that fit') }}</label>
                                            <input type="number" id="bags" name="bags" min="0" max="60" class="form-control"
                                                   value="{{ old('bags', $category->bags) }}" placeholder="{{ translate('Ex : 2') }}">
                                        </div>
                                        <div class="col-lg-6">
                                            <label for="features" class="mb-2">{{ translate('Features') }}</label>
                                            <textarea id="features" name="features" rows="4" maxlength="700" class="form-control"
                                                      placeholder="{{ translate('One per line, up to 8. Ex: Air conditioning') }}">{{ old('features', implode("\n", (array)($category->features ?? []))) }}</textarea>
                                            <small class="opacity-75">{{ translate('One per line, up to 8. Ex: Air conditioning') }}</small>
                                        </div>
                                        <div class="col-12">
                                            <label class="mb-2 d-block">{{ translate('Example photos') }}</label>
                                            <p class="opacity-75 mb-3">{{ translate(key: 'Up to {count} photos of the kind of vehicle (jpg, png or webp). Customers swipe through them.', replace: ['count' => $maxPhotos]) }}</p>
                                            @if(count($gallery))
                                                <div class="d-flex flex-wrap gap-3 mb-3">
                                                    @foreach($gallery as $photo)
                                                        <label class="ed-cat-photo">
                                                            <img src="{{ $galleryBase . '/' . $photo }}" alt="" loading="lazy">
                                                            <span class="d-flex align-items-center gap-2">
                                                                <input type="checkbox" name="gallery_remove[]" value="{{ $photo }}">
                                                                {{ translate('Remove') }}
                                                            </span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            @endif
                                            <input type="file" name="gallery[]" multiple accept=".jpg,.jpeg,.png,.webp" class="form-control" style="max-width: 420px"
                                                   id="gallery_input" data-room="{{ max(0, $maxPhotos - count($gallery)) }}">
                                            <small class="opacity-75 d-block mt-1" id="gallery_room">{{ translate(key: 'You can add {count} more.', replace: ['count' => max(0, $maxPhotos - count($gallery))]) }}</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end gap-3">
                                    <button type="reset" id="reset_btn"
                                        class="btn btn-secondary cmn_reset">{{ translate('reset') }}</button>
                                    <button type="submit" class="btn btn-primary cmn_focus">{{ translate('update') }}</button>
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

        // Removing photos makes room for new ones; more than fits is refused before sending.
        $('#gallery_input').closest('form').on('submit', function (e) {
            let input = document.getElementById('gallery_input');
            let room = parseInt(input.dataset.room || '0', 10) + $('input[name="gallery_remove[]"]:checked').length;
            if (input.files.length > room) {
                e.preventDefault();
                toastr.error('{{ translate('Too many photos') }}: ' + input.files.length + ' / ' + room);
            }
        });

        resetButton.on('click', function() {
            imageElement.attr('src', defaultImageSrc);
            fileInput.val('');
        });
    </script>
@endpush
