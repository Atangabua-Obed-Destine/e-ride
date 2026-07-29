@extends('adminmodule::layouts.master')

@section('title', translate('Update_Employee'))

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex flex-wrap justify-content-between gap-3 align-items-center mb-4">
                <h2 class="fs-22 text-capitalize">{{ translate('update_employee') }}</h2>
            </div>

            <form action="{{ route('admin.employee.update',  $employee->id) }}" id='myForm' method="post"
                  enctype="multipart/form-data">
                @csrf
                @method('put')
                @php
                    $maxSize = readableUploadMaxFileSize('image');
                @endphp
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="text-uppercase mb-2">{{ translate('employee_information') }}</h5>
                        <div
                            class="fs-12 title-color"> {{ translate('Setup the necessary information and include the documents of the employee') }}</div>
                    </div>
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-lg-4 mb-4 mb-lg-0">
                                <div>
                                    <h5 class="mb-2">{{ translate('Personal_Information') }}</h5>
                                    <div
                                        class="fs-12 title-color"> {{ translate('Here you can set the primary information of the employee to ensure the basic details.') }}</div>
                                </div>
                            </div>
                            <div class="col-lg-8">
                                <div class="row align-items-center bg-fafafa p-4 rounded">
                                    <div class="col-lg-6">
                                        <div class="mb-4">
                                            <label for="f_name"
                                                   class="mb-2 text-capitalize">{{ translate('first_name') }}</label>
                                            <input type="text" value="{{ $employee?->first_name }}" name="first_name"
                                                   id="f_name" class="form-control"
                                                   placeholder="{{ translate('Ex: Maximilian') }}" required tabindex="1">
                                        </div>
                                        <div class="mb-4">
                                            <label for="l_name" class="mb-2">{{ translate('last_name') }}</label>
                                            <input type="text" value="{{ $employee?->last_name }}" name="last_name"
                                                   id="l_name" class="form-control"
                                                   placeholder="{{ translate('Ex: Schwarzmüller') }}" required tabindex="2">
                                        </div>
                                        <div class="mb-4 d-flex flex-column">
                                            <label for="phone_number" class="mb-2">{{ translate('phone') }}</label>
                                            <input type="tel" pattern="[0-9]{1,14}" value="{{ $employee->phone }}"
                                                   id="phone_number" class="form-control w-100 text-dir-start"
                                                   placeholder="{{ translate('Ex: xxxxx xxxxxx') }}" required tabindex="3">
                                            <input type="hidden" id="phone_number-hidden-element" name="phone" tabindex="3">
                                        </div>
                                        <div>
                                            <label for="address" class="mb-2">{{ translate('address') }}</label>
                                            <input type="text" name="address" id="address" class="form-control"
                                                   value="{{ $employeeAddress->address ?? '' }}"
                                                   placeholder="{{ translate('Ex: Dhaka') }}"
                                                   required tabindex="4">
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="d-flex flex-column justify-content-around gap-3 mt-4 mt-lg-0">
                                            <h5 class="text-center text-capitalize">{{ translate('employee_image') }}</h5>

                                            <div class="d-flex justify-content-center">
                                                <div class="upload-file auto cmn_focus rounded-10">
                                                    <input type="file" name="profile_image" class="upload-file__input"
                                                           accept="{{ IMAGE_ACCEPTED_EXTENSIONS }}" tabindex="5" data-max-upload-size="{{ $maxSize }}">
                                                        <span class="edit-btn">
                                                            <i class="bi bi-pencil-square text-primary"></i>
                                                        </span>
                                                    <div
                                                        class="upload-file__img border-gray d-flex justify-content-center align-items-center w-180 h-180 p-0">
                                                        <img class="upload-file__img__img h-100" width="180"
                                                             height="180"
                                                             loading="lazy"
                                                             src="{{ onErrorImage(
                                                        $employee?->profile_image,
                                                        dynamicStorage('storage/app/public/employee/profile') . '/' . $employee?->profile_image,
                                                        dynamicAsset('public/assets/admin-module/img/avatar/avatar.png'),
                                                        'employee/profile/',
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
                        </div>
                    </div>
                            <hr class="my-4">
                            <div class="row align-items-center">
                                <div class="col-lg-4 mb-4 mb-lg-0">
                                    <div>
                                        <h5 class="text-uppercase mb-2">{{ translate('Identity_Information') }}</h5>
                                        <div class="fs-12 title-color"> {{ translate("Include the necessary information & upload documents that confirms the employee's identity") }}</div>
                                    </div>
                                </div>
                                <div class="col-lg-8">
                                    <div class="row bg-fafafa p-4 rounded">
                                        <div class="col-lg-6">
                                            <div class="mb-4">
                                                <label for="identity_type"
                                                       class="mb-2">{{ translate('identity_type') }}</label>
                                                <select name="identification_type" class="js-select cmn_focus text-capitalize"
                                                        id="identity_type" tabindex="6">
                                                    <option value="passport"
                                                        {{ $employee->identification_type == 'passport' ? 'selected' : '' }}>
                                                        {{ translate('passport') }}</option>
                                                    <option value="nid"
                                                        {{ $employee->identification_type == 'nid' ? 'selected' : '' }}>
                                                        {{ translate('NID') }}</option>
                                                    <option value="driving_license"
                                                        {{ $employee->identification_type == 'driving_license' ? 'selected' : '' }}>
                                                        {{ translate('driving_license') }}</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-lg-6">
                                            <div class="mb-4">
                                                <label for="identity_card_num"
                                                       class="mb-2">{{ translate('identity_number') }}</label>
                                                <input type="text" value="{{ $employee->identification_number }}"
                                                       name="identification_number" id="identity_card_num" class="form-control"
                                                       placeholder="{{ translate('Ex: 3032') }}" required tabindex="7">
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
                                                    <div class="upload-file d-flex custom" id="multi_image_picker">
                                                        @foreach ($employee->identification_image as $index => $img)
                                                            <div
                                                                class="upload-file__img cmn_focus upload-file__img_banner spartan_item_wrapper mb-20"
                                                                data-spartanindexrow="{{ $index + 100 }}">
                                                                <div class="file_upload">
                                                                    <!-- Close button -->
                                                                    <a href="javascript:void(0)"
                                                                       data-spartanindexremove="{{ $index+100  }}"
                                                                       class="spartan_remove_row">
                                                                        <i class="tio-clear"></i>
                                                                    </a>
                                                                    <!-- Existing image -->
                                                                    <img alt="{{ $img }}"
                                                                        src="{{ onErrorImage(
                                                                                $img,
                                                                                dynamicStorage('storage/app/public/employee/identity') . '/' . $img,
                                                                                dynamicAsset('public/assets/admin-module/img/media/banner-upload-file.png'),
                                                                                'employee/identity/',
                                                                            )  }}"
                                                                        class="existing_image img_"
                                                                        data-spartanindeximage="{{ $index + 100 }}"
                                                                        style="width: 100%; height: 130px;">
                                                                    <!-- Hidden file input for re-upload -->
                                                                    <input class="form-control spartan_image_input d-none"
                                                                           accept="image/*"
                                                                           data-spartanindexinput="{{ $index + 100 }}"
                                                                           name="identity_images[]" type="file" tabindex="8">
                                                                    <input type="hidden" name="existing_identity_images[]"
                                                                           value="{{ $img }}" tabindex="8">
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>

                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-body">
                        <h5 class="text-uppercase mb-2">
                            {{ translate('setup_role_and_responsibility') }}
                        </h5>
                        <div
                            class="fs-12 title-color mb-4"> {{ translate("Define the employee's role, assign tasks, and set access levels accordingly.") }}</div>

                        <div class="bg-fafafa p-4 rounded">
                            <label for="employee-role" class="mb-2">{{ translate('employee_role') }}</label>
                            <select name="role_id" id="employee-role" class="form-control js-select cmn_focus" required tabindex="9">
                                <option value="" selected disabled>{{ translate('--Select_Employee_Role--') }}
                                </option>
                                @forelse($roles as $employee_role)
                                    <option value="{{ $employee_role->id }}"
                                        {{ $employee->role_id == $employee_role->id ? 'selected' : '' }}>{{ $employee_role->name }}
                                    </option>
                                @empty
                                @endforelse
                            </select>
                        </div>
                        <div id="roles">
                            @include('usermanagement::admin.employee.partials._employee_roles', ['employee' => $employee])
                        </div>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-body">
                        <h5 class="text-uppercase mb-2">{{ translate('account_information') }}</h5>
                        <div
                            class="fs-12 mb-3 title-color"> {{ translate('Here you can setup the details that will be helpful to login to the panel') }}</div>
                        <div class="row align-items-end">
                            <div class="col-sm-4">
                                <div class="mb-4">
                                    <label for="p_email" class="mb-2">{{ translate('email') }}</label>
                                    <input type="email" value="{{ $employee->email }}" name="email" id="p_email"
                                           class="form-control" placeholder="{{ translate('Ex: company@company.com') }}"
                                           required tabindex="10">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-4 input-group_tooltip">
                                    <label for="password" class="mb-2">{{ translate('password') }}</label>
                                    <input type="password" name="password" id="password" class="form-control"
                                           placeholder="{{ translate('Ex: ********') }}" tabindex="11"
                                           autocomplete="new-password">
                                    <i id="password-eye" class="mt-3 bi bi-eye-slash-fill text-primary tooltip-icon"
                                       data-bs-toggle="tooltip" data-bs-title=""></i>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-4 input-group_tooltip">
                                    <label for="confirm_password"
                                           class="mb-2">{{ translate('confirm_password') }}</label>
                                    <input type="password" name="confirm_password" id="confirm_password"
                                           class="form-control" placeholder="{{ translate('Ex: ********') }}" tabindex="12"
                                           autocomplete="new-password">
                                    <i id="conf-password-eye"
                                       class="mt-3 bi bi-eye-slash-fill text-primary tooltip-icon"
                                       data-bs-toggle="tooltip" data-bs-title=""></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-3 mt-3">
                    <button class="btn btn-primary cmn_focus" type="submit" tabindex="13">{{ translate('save') }}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- End Main Content -->
    <input type="hidden" id="existingValue" value="{{count($employee->identification_image)}}">
@endsection

@push('script')
    <link href="{{ dynamicAsset('public/assets/admin-module/css/intlTelInput.min.css') }}" rel="stylesheet"/>
    <script src="{{ dynamicAsset('public/assets/admin-module/js/intlTelInput.min.js') }}"></script>
    <script src="{{ dynamicAsset('public/assets/admin-module/js/spartan-multi-image-picker.js') }}"></script>
    <script src="{{ dynamicAsset('public/assets/admin-module/js/password.js') }}"></script>
    <script src="{{ dynamicAsset('public/assets/admin-module/js/upload-files-edit.js') }}"></script>

    <script>
        "use strict";
        $(document).ready(function () {
            // Function to toggle all checkboxes within a module
            $(document).on('change', '.select-all-module', function () {
                let moduleKey = $(this).data('module');
                let isChecked = $(this).is(':checked');

                $('.module-checkbox[data-module="' + moduleKey + '"]').prop('checked', isChecked);
            });

            // Function to update the Select All checkbox based on individual checkboxes
            $(document).on('change', '.module-checkbox', function () {
                let moduleKey = $(this).data('module');
                let allCheckboxes = $('.module-checkbox[data-module="' + moduleKey + '"]');
                let allChecked = allCheckboxes.length === allCheckboxes.filter(':checked').length;
                $('#select-all-' + moduleKey).prop('checked', allChecked);
            });
            // Initialize "Select All" checkbox state on page load
            $('.select-all-module').each(function () {
                let moduleKey = $(this).data('module');
                let allCheckboxes = $('.module-checkbox[data-module="' + moduleKey + '"]');
                let allChecked = allCheckboxes.length === allCheckboxes.filter(':checked').length;
                $(this).prop('checked', allChecked);
            });
        });
    </script>

    <script>
        "use strict";
        initializePhoneInput("#phone_number", "#phone_number-hidden-element");
        $("#employee-role").on('change', function () {
            let value = $(this).val()
            loadRoles(value)
        })

        function loadRoles(obj) {

            $.ajax({
                url: '{{ route('admin.employee.role.get-roles') }}',
                _method: 'PUT',
                data: {
                    id: obj,
                    employee_id: '{{ $employee->id }}',
                },
                success: function (data) {

                    $('#roles').empty().html(data.view)
                },
            });
        }
    </script>
@endpush
