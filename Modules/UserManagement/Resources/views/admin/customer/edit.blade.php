@extends('adminmodule::layouts.master')

@section('title', translate('Update_Customer'))

@section('content')

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex flex-wrap justify-content-between gap-3 align-items-center mb-4">
                <h2 class="fs-22 text-capitalize">{{ translate('update_customer') }}</h2>
            </div>

            <form action="{{ route('admin.customer.update', ['id' => $customer->id]) }}" method="post"
                  enctype="multipart/form-data" class="submit-by-ajax">
                @csrf
                @method('PUT')
                @php
                    $maxSize = readableUploadMaxFileSize('image');
                    $fileMaxSize = readableUploadMaxFileSize('file');
                @endphp
                <div class="card">
                    <div class="card-body">
                        <div class="row gy-4">
                            <div class="col-lg-8">
                                <h5 class="text-primary text-uppercase mb-4">{{ translate('general_info') }}</h5>

                                <div class="row align-items-start">
                                    <div class="col-sm-6">
                                        <div class="mb-4">
                                            <label for="f_name"
                                                   class="mb-2 text-capitalize">{{ translate('first_name') }}  <span class="text-danger">*</span></label>
                                            <input type="text" value="{{ $customer?->first_name }}" name="first_name"
                                                   id="f_name" class="form-control"
                                                   placeholder="{{ translate('Ex: Maximilian') }}" required tabindex="1">
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="mb-4">
                                            <label for="l_name" class="mb-2">{{ translate('last_name') }} </label>
                                            <input type="text" value="{{ $customer?->last_name }}" name="last_name"
                                                   id="l_name" class="form-control"
                                                   placeholder="{{ translate('Ex: Schwarzmüller') }}" tabindex="2">
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="mb-4">
                                            <label for="p_email" class="mb-2">{{ translate('email') }}</label>
                                            <input type="email" value="{{ $customer->email }}" name="email"
                                                   id="p_email" class="form-control"
                                                   placeholder="{{ translate('Ex: company@company.com') }}"  tabindex="3">
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="mb-4">
                                            <label for="identity_type"
                                                   class="mb-2">{{ translate('identity_type') }}</label>
                                            <select name="identification_type" class="js-select cmn_focus text-capitalize"
                                                    id="identity_type" tabindex="4">
                                                <option value="passport"
                                                    {{ $customer->identification_type == 'passport' ? 'selected' : '' }}>
                                                    {{ translate('passport') }}</option>
                                                <option value="nid"
                                                    {{ $customer->identification_type == 'nid' ? 'selected' : '' }}>
                                                    {{ translate('NID') }}</option>
                                                <option value="driving_license"
                                                    {{ $customer->identification_type == 'driving_license' ? 'selected' : '' }}>
                                                    {{ translate('driving_license') }}</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="mb-4">
                                            <label for="identity_card_num"
                                                   class="mb-2">{{ translate('identity_number') }}</label>
                                            <input type="text" value="{{ $customer->identification_number }}"
                                                   name="identification_number" id="identity_card_num"
                                                   class="form-control"
                                                   placeholder="{{ translate('Ex: 3032') }}" tabindex="5">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="d-flex flex-column justify-content-around gap-3">
                                    <h5 class="text-center text-capitalize">{{ translate('customer_image') }}</h5>

                                    <div class="d-flex justify-content-center">
                                        <div class="upload-file auto cmn_focus rounded-10">
                                            <input type="file" name="profile_image" class="upload-file__input" tabindex="6"
                                                   accept="{{ IMAGE_ACCEPTED_EXTENSIONS }}" data-max-upload-size="{{ $maxSize }}"
                                            >
                                            <span class="edit-btn show">
                                                <img
                                                    src="{{ dynamicAsset('public/assets/admin-module/img/svg/edit-circle.svg') }}"
                                                    alt="" class="svg">
                                            </span>
                                            <div
                                                class="upload-file__img border-gray d-flex justify-content-center align-items-center w-180 h-180 p-0">
                                                <img class="upload-file__img__img h-100" width="180" height="180"
                                                     loading="lazy"
                                                     src="{{ onErrorImage(
                                                        $customer?->profile_image,
                                                        dynamicStorage('storage/app/public/customer/profile') . '/' . $customer?->profile_image,
                                                        dynamicAsset('public/assets/admin-module/img/avatar/avatar.png'),
                                                        'customer/profile/',
                                                    ) }}"
                                                     alt="">
                                            </div>
                                        </div>
                                    </div>
                                    <p class="opacity-75 mx-auto max-w220">
                                        {{ translate(key: 'File Format - {format}, Image Size - Maximum {imageSize}', replace: ['format' => IMAGE_ACCEPTED_EXTENSIONS, 'imageSize' => $maxSize]) }}
                                    </p>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="d-flex flex-column justify-content-around gap-3">
                                    <div>
                                        <h5 class="">{{ translate('identity_card_image') }}</h5>
                                        <p class="opacity-75 mb-0">
                                            {{ translate(key: 'File Format - {format}, Image Size - Maximum {imageSize}', replace: ['format' => IMAGE_ACCEPTED_EXTENSIONS, 'imageSize' => $maxSize]) }}
                                        </p>
                                    </div>
                                    <div class="d-flex gap-3 flex-wrap">
                                        @foreach ($customer->identification_image as $index => $img)
                                            <div
                                                class="remove-upload-file cmn_focus upload-file__img_banner mb-20 position-relative">
                                                <!-- Close button -->
                                                <button class="spartan_remove_row border-0 remove-old-image"
                                                        type="button" tabindex="7">
                                                    <i class="tio-clear"></i>
                                                </button>
                                                <!-- Existing image -->
                                                <img
                                                    src="{{ onErrorImage(
                                                                                $img,
                                                                                dynamicStorage('storage/app/public/customer/identity') . '/' . $img,
                                                                                dynamicAsset('public/assets/admin-module/img/media/banner-upload-file.png'),
                                                                                'customer/identity/',
                                                                            )  }}"
                                                    class="existing_image"
                                                    style="width: 100%; height: 130px;">
                                                <input type="hidden" name="existing_identity_images[]"
                                                       value="{{ $img }}">
                                            </div>
                                        @endforeach
                                        {{--                                        @if(count($employee->identification_image)<3)--}}
                                        <div class="upload-file d-flex custom" id="multi_image_picker">

                                        </div>
                                        {{--                                        @endif--}}
                                    </div>

                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="card overflow-visible mt-3">
                    <div class="card-body">
                        <h5 class="text-primary text-uppercase mb-4">{{ translate('account_information') }}</h5>

                        <div class="row align-items-start">
                            <div class="col-md-4">
                                <div class="mb-4">
                                    <label for="phone_number" class="mb-2">{{ translate('phone') }} <span class="text-danger">*</span></label>
                                    <input type="tel" pattern="[0-9]{1,14}" value="{{ $customer->phone }}"
                                           name="phone" id="phone_number" class="form-control w-100 text-dir-start"
                                           placeholder="{{ translate('Ex: xxxxx xxxxxx') }}" required>
                                    <input type="hidden" id="phone_number-hidden-element" name="phone" tabindex="8">
                                </div>
                            </div>
                            <div class="col-md-4">
	                                <div class="mb-4 input-group_tooltip">
	                                    <label for="password" class="mb-2">{{ translate('password') }}</label>
                                        <div class="password-input-wrapper">
                                            <input type="password" name="password" id="password" class="form-control"
                                                   placeholder="{{ translate('Ex: ********') }}" tabindex="9"
                                                   autocomplete="new-password">
                                            <i id="password-eye" class="mt-3 bi bi-eye-slash-fill text-primary tooltip-icon"
                                               data-bs-toggle="tooltip" data-bs-title=""></i>
                                        </div>
	                                </div>
	                            </div>
	                            <div class="col-md-4">
	                                <div class="mb-4 input-group_tooltip">
	                                    <label for="confirm_password"
	                                           class="mb-2">{{ translate('confirm_password') }}</label>
                                        <div class="password-input-wrapper">
                                            <input type="password" name="confirm_password" id="confirm_password"
                                                   class="form-control" placeholder="{{ translate('Ex: ********') }}" tabindex="10"
                                                   autocomplete="new-password">
                                            <i id="conf-password-eye"
                                               class="mt-3 bi bi-eye-slash-fill text-primary tooltip-icon"
                                               data-bs-toggle="tooltip" data-bs-title=""></i>
                                        </div>
	                                </div>
	                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-body">
                        <h5 class="text-primary text-uppercase mb-4">{{ translate('upload_other_documents') }}</h5>
                        <p class="opacity-75">
                            {{ translate(key: 'File Format - {format}, Image Size - Maximum {imageSize}, File Size - Maximum {fileSize}', replace: ['format' => IMAGE_ACCEPTED_EXTENSIONS . ', ' . FILE_ACCEPTED_EXTENSIONS, 'imageSize' => $maxSize, 'fileSize' => $fileMaxSize]) }}
                        </p>
                        <div class="d-flex flex-wrap gap-3  other-documents-wrapper">
                            @if ($customer->other_documents != null)
                                @foreach ($customer->other_documents as $document)
                                    <div class="show-image cmn_focus rounded-10">
                                        <div class="file__value bg-transparent border border-C5D2D2 remove_outside"
                                             data-document="{{ $document }}">
                                            <img class="file__value--icon"
                                                 src="{{ getExtensionIcon($document) }}"
                                                 alt="">
                                            <div class="file__value--text">{{ $document }}</div>
                                            <div class="file__value--remove fw-bold"
                                                 data-id="{{$document}}">
                                                <img
                                                    src="{{ dynamicAsset('public/assets/admin-module/img/icons/close-circle.svg') }}"
                                                    alt="">
                                            </div>
                                            <input type="hidden" name="existing_documents[]"
                                                   value="{{ $document }}" tabindex="11">
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                            <div class="d-flex flex-wrap gap-3 d-none" id="selected-files-container1"></div>
                            <div class="d-none" id="input-data"></div>
                            <!-- Upload New Documents -->
                            <div class="upload-file cmn_focus rounded-10 file__input" id="file__input">
                                <input type="file" class="upload-file__input2" multiple="multiple"
                                       accept="{{ IMAGE_ACCEPTED_EXTENSIONS }}, {{ FILE_ACCEPTED_EXTENSIONS }}"
                                 tabindex="12">
                                <div class="upload-file__img2">
                                    <div class="upload-box rounded media gap-4 align-items-center p-4 px-lg-5">
                                        <i class="bi bi-cloud-arrow-up-fill fs-20"></i>
                                        <div class="media-body">
                                            <p class="text-muted mb-2 fs-12">{{ translate('upload') }}</p>
                                            <h6 class="fs-12 text-capitalize">{{ translate('file_or_image') }}</h6>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @include('usermanagement::admin.partials._additional-data-fields')

                <div class="d-flex justify-content-end gap-3 mt-3">
                    <button class="btn btn-primary cmn_focus" type="submit" tabindex="13">{{ translate('save') }}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- End Main Content -->

@endsection

@push('script')
    <link href="{{ dynamicAsset('public/assets/admin-module/css/intlTelInput.min.css') }}" rel="stylesheet"/>
    <script src="{{ dynamicAsset('public/assets/admin-module/js/intlTelInput.min.js') }}"></script>
    <script src="{{ dynamicAsset('public/assets/admin-module/js/spartan-multi-image-picker.js') }}"></script>
    <script src="{{ dynamicAsset('public/assets/admin-module/js/password.js') }}"></script>
    <script src="{{ dynamicAsset('public/assets/admin-module/js/upload-files-edit.js') }}"></script>

    <script>
        "use strict";
        initializePhoneInput("#phone_number", "#phone_number-hidden-element");
        getCount();
        $(".remove-old-image").on('click', function () {
            $(this).closest('.remove-upload-file').remove();
            getCount();
        })
    </script>
@endpush
