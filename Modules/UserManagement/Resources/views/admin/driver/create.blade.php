@extends('adminmodule::layouts.master')

@section('title', translate('Add_New_Driver'))

@section('content')

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex flex-wrap justify-content-between gap-3 align-items-center mb-4">
                <h2 class="fs-22">{{ translate('add_Driver') }}</h2>
            </div>

            <form action="{{ route('admin.driver.store') }}" method="post" enctype="multipart/form-data" class="submit-by-ajax">
                @csrf
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
                                                   class="mb-2 text-capitalize">{{ translate('first_name') }}
                                                <span class="text-danger">*</span></label>
                                            <input type="text" value="{{ old('first_name') }}" name="first_name"
                                                   id="f_name" class="form-control cmn_focus"
                                                   placeholder="{{ translate('Ex: Maximilian') }}" required tabindex="1">
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="mb-4">
                                            <label for="l_name" class="mb-2">{{ translate('last_name') }}</label>
                                            <input type="text" value="{{ old('last_name') }}" name="last_name"
                                                   id="l_name" class="form-control"
                                                   placeholder="{{ translate('Ex: Schwarzmüller') }}"  tabindex="2">
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="mb-4">
                                            <label for="gender" class="mb-2">{{ translate('gender') }}<span class="text-danger">*</span></label>
                                            <select name="gender" class="js-select cmn_focus text-capitalize" id="gender">
                                                <option value="" {{ old('gender') ? '' : 'selected' }}>-- {{ translate('select_gender') }} --</option>
                                                @foreach (GENDERS as $genderOption)
                                                    <option value="{{ $genderOption }}" {{ old('gender') == $genderOption ? 'selected' : '' }}>{{ translate($genderOption) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="mb-4">
                                            <label for="p_email" class="mb-2">{{ translate('email') }} <span
                                                    class="text-danger">*</span></label>
                                            <input type="email" value="{{ old('email') }}" name="email" id="p_email"
                                                   class="form-control"
                                                   placeholder="{{ translate('Ex: company@company.com') }}" required tabindex="3">
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="mb-4">
                                            <label for="identity_type" class="mb-2">{{ translate('identity_type') }}
                                                <span class="text-danger">*</span></label>
                                            <select name="identification_type" class="js-select cmn_focus text-capitalize"
                                                    id="identity_type" required tabindex="4">
                                                <option disabled selected>-- {{ translate('select_identity_type') }}
                                                    --
                                                </option>
                                                <option value="passport">{{ translate('passport') }}</option>
                                                <option value="nid">{{ translate('NID') }}</option>
                                                <option
                                                    value="driving_license">{{ translate('driving_license') }}</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="mb-4">
                                            <label for="identity_card_num"
                                                   class="mb-2">{{ translate('identity_number') }} <span
                                                    class="text-danger">*</span></label>
                                            <input type="text" value="{{ old('identification_number') }}"
                                                   name="identification_number" id="identity_card_num"
                                                   class="form-control"
                                                   placeholder="{{ translate('Ex: 3032') }}" required tabindex="5">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="d-flex flex-column justify-content-around gap-3">
                                    <h5 class="text-center">{{ translate('driver_image') }}</h5>

                                    <div class="d-flex justify-content-center">
                                        <div class="upload-file border-light-mode auto profile-image-upload-file cmn_focus rounded-10">
                                            <input type="file" name="profile_image" class="upload-file__input" tabindex="6"
                                                   accept="{{ IMAGE_ACCEPTED_EXTENSIONS }}" required data-max-upload-size="{{ $maxSize }}">
                                            <div
                                                class="upload-file__img border-gray d-flex justify-content-center align-items-center w-180 h-180 p-0">
                                                <div class="upload-file__textbox text-center">
                                                    <img width="34" height="34"
                                                         src="{{ dynamicAsset('public/assets/admin-module/img/document-upload.png') }}"
                                                         alt="" class="svg">
                                                    <h6 class="mt-2 fw-semibold">
                                                        <span class="text-info">{{ translate('Click to upload') }}</span>
                                                        <br>
                                                        {{ translate('or drag and drop') }}
                                                    </h6>
                                                </div>
                                                <img class="upload-file__img__img h-100" width="180" height="180"
                                                     loading="lazy" alt="">
                                            </div>
                                            <a href="javascript:void(0)" class="remove-img-icon d-none">
                                                <i class="tio-clear"></i>
                                            </a>
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

                                    <div class="upload-file d-flex custom cmn_focus" tabindex="7" id="multi_image_picker">
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
                                <div class="d-flex flex-column">
                                    <label for="phone_number" class="mb-2">{{ translate('phone') }} <span
                                            class="text-danger">*</span></label>
                                    <div class="mb-4">
                                        <input type="tel" pattern="[0-9]{1,14}" value="{{ old('phone') }}" required
                                               id="phone_number" class="form-control w-100 text-dir-start"
                                               placeholder="{{ translate('Ex: xxxxx xxxxxx') }}" tabindex="8">
                                        <input type="hidden" id="phone_number-hidden-element" name="phone" tabindex="9">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
	                                <div class="mb-4 input-group_tooltip">
	                                    <label for="password" class="mb-2">{{ translate('password') }}</label>
                                        <div class="password-input-wrapper">
                                            <input type="password" name="password" id="password" required class="form-control"
                                                   placeholder="{{ translate('ex') }}: ********" tabindex="10">
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
                                            <input type="password" name="confirm_password" required id="confirm_password"
                                                   class="form-control" placeholder="{{ translate('ex') }}: ********" tabindex="11">
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
                        <div class="pb-3">
                            <h5 class="">{{ translate('upload_Other_Documents') }}</h5>
                            <p class="opacity-75">
                                {{ translate(key: 'File Format - {format}, Image Size - Maximum {imageSize}, File Size - Maximum {fileSize}', replace: ['format' => IMAGE_ACCEPTED_EXTENSIONS . ', ' . FILE_ACCEPTED_EXTENSIONS, 'imageSize' => $maxSize, 'fileSize' => $fileMaxSize]) }}
                            </p>
                        </div>
                        <div class="d-flex flex-wrap gap-3  other-documents-wrapper">
                            <div class="d-flex flex-wrap gap-3 d-none" id="selected-files-container1"></div>
                            <div class="d-none" id="input-data"></div>
                            <!-- Upload New Documents -->
                            <div class="upload-file file__input cmn_focus" id="file__input">
                                <input type="file" class="upload-file__input2" multiple="multiple" tabindex="12" accept="{{ IMAGE_ACCEPTED_EXTENSIONS }}, {{ FILE_ACCEPTED_EXTENSIONS }}">
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
    <script src="{{ dynamicAsset('public/assets/admin-module/js/upload-files-create.js') }}"></script>
    <script src="{{ dynamicAsset('public/assets/admin-module/js/single-image-upload.js') }}"></script>
    <script>
        "use strict";
        initializePhoneInput("#phone_number", "#phone_number-hidden-element");
    </script>
@endpush
