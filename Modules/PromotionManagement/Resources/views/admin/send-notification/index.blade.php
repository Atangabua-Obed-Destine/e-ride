@extends('adminmodule::layouts.master')

@section('title', translate('Send Notification'))

@push('css_or_js')
@endpush

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-20 mb-20 text-capitalize">{{ translate('Send_Notification') }}</h2>
            @php
                $firebaseConfig = '<a href="' . route('admin.business.configuration.notification.firebase-configuration') .'" class="fw-semibold text-info" target="_blank">'. translate('Firebase Configuration').'</a>';
                $maxSize = readableUploadMaxFileSize('image');
            @endphp
            <div class="alert alert-warning align-items-center d-flex fs-12 gap-2 mb-3 p-2" role="alert">
                <i class="bi bi-info-circle-fill text-warning"></i>
                {!! translate(key: 'Setup Push Notification Messages for customer.') !!} {!! translate('Must_setup_{firebase}_page_to_work_notifications.', replace: ['firebase' => $firebaseConfig]) !!}
            </div>
            <div class="card card-body mb-3">
                <div class="mb-20">
                    <h4 class="mb-1">{{ translate('Send Notification') }}</h4>
                    <p class="fs-12 mb-0">{{ translate('Manually send a custom push notification to all users in your system.') }}</p>
                </div>
                <form action="{{ route('admin.promotion.send-notification.store') }}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="row g-4">
                        <div class="col-lg-8">
                            <div class="bg-light p-20 rounded h-100">
                                <div class="row gy-3">
                                    <div class="col-lg-6">
                                        <div class="mb-0 character-count">
                                            <label for="business_address" class="mb-2 form-label">
                                                {{ translate('Title') }}
                                                <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip" data-bs-title="{{ translate('Keep it short and catchy. Maximum 100 characters') }}"></i>
                                            </label>
                                            <textarea name="name" id="" rows="1"
                                                class="form-control min-h-45px pt-2 character-count-field" placeholder="{{ translate('Type Title') }}" maxlength="100"
                                                data-max-character="100" required tabindex="1"></textarea>
                                            <span class="d-flex justify-content-end">0/100</span>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="mb-0 character-count">
                                            <label for="business_address" class="mb-2 form-label">
                                                {{ translate('Targeted user') }}
                                            </label>
                                            <select name="targeted_users[]" id=""
                                                    class="js-select cmn_focus"
                                                    multiple="multiple"
                                                    data-placeholder="{{translate('select_users')}}"
                                                    required tabindex="2">
                                                <option value="customers">Customers</option>
                                                <option value="drivers">Drivers</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="mb-0 character-count">
                                            <label for="business_address" class="mb-2 form-label">
                                                {{ translate('Description') }}
                                                <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip" data-bs-title="{{ translate('Provide more context about the notification. Maximum 200 characters') }}"></i>
                                            </label>
                                            <textarea name="description" id="" rows="1"
                                                class="form-control min-h-45px pt-2 character-count-field" placeholder="{{ translate('Type about the description') }}" maxlength="200"
                                                data-max-character="200" tabindex="3"></textarea>
                                            <span class="d-flex justify-content-end">0/200</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="bg-light p-20 rounded d-flex align-items-center h-100">
                                <div class="d-flex flex-column justify-content-around gap-3 w-100">
                                    <div>
                                        <h6 class="mb-1">{{ translate('Image') }}</h6>
                                        <p class="fs-12 mb-0">{{ translate('Upload your cover Image') }}</p>
                                    </div>

                                    <div class="d-flex justify-content-center">
                                        <div class="upload-file border-light-mode auto profile-image-upload-file cmn_focus rounded-10">
                                            <input type="file" name="image" class="upload-file__input" accept="{{ IMAGE_ACCEPTED_EXTENSIONS }}"
                                                 tabindex="4" data-max-upload-size="{{ $maxSize }}">
                                            <div class="upload-file__img bg-white border-gray d-flex justify-content-center align-items-center h-100px aspect-ratio-2-1 p-0">
                                                <div class="upload-file__textbox text-center">
                                                    <div class="d-flex gap-1 align-items-center flex-wrap">
                                                        <img width="34" height="34" src="{{ dynamicAsset('public/assets/admin-module/img/document-upload-2.png') }}"
                                                            alt="" class="svg">
                                                        <h6 class="fw-semibold fs-10">
                                                            <span class="text-info mb-1">{{ translate('Click to upload') }}</span>
                                                            <br>
                                                            {{ translate('or drag and drop') }}
                                                        </h6>
                                                    </div>
                                                </div>
                                                <img class="upload-file__img__img h-100" width="180" height="180" loading="lazy" alt="">
                                            </div>
                                            <a href="javascript:void(0)" class="remove-img-icon d-none">
                                                <i class="tio-clear"></i>
                                            </a>
                                        </div>
                                    </div>

                                    <p class="opacity-75 mx-auto fs-10">
                                        {{ translate(key: 'File Format - {format}, Image Size - Maximum {imageSize}, Image Ratio - {ratio}', replace: ['format' => IMAGE_ACCEPTED_EXTENSIONS, 'imageSize' => $maxSize, 'ratio' => '2:1']) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="d-flex align-items-center flex-wrap justify-content-end gap-3">
                                <button class="btn btn-light fw-semibold min-w-100px cmn_reset" type="reset" tabindex="5">{{ translate('Reset') }}</button>
                                <button class="btn btn-primary cmn_focus fw-semibold min-w-100px" type="submit" tabindex="6">{{ translate('Submit') }}</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <h2 class="fs-20 mb-3 text-capitalize">{{ translate('Notification_History') }}</h2>
            <div class="card">
                <div class="card-body">
                    <div class="table-top d-flex flex-wrap gap-10 justify-content-between">
                        <form action="{{url()->current()}}" class="search-form search-form_style-two">
                            <div class="input-group search-form__input_group min-w-sm-250px">
                                <span class="search-form__icon">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="search" name="search" value="{{request()->search}}"
                                    class="theme-input-style search-form__input"
                                    placeholder="{{translate('Search')}}" tabindex="7">
                            </div>
                            <button type="submit" class="btn btn-primary cmn_focus" tabindex="8">{{translate('search')}}</button>
                        </form>

                        <div class="d-flex flex-wrap gap-3">
                            <div class="dropdown">
                                <button type="button" class="btn btn-outline-primary" data-bs-toggle="dropdown" tabindex="9">
                                    <i class="bi bi-download"></i>
                                    {{translate('download')}}
                                    <i class="bi bi-caret-down-fill"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                                    <li>
                                        <a class="dropdown-item" href="{{route('admin.promotion.send-notification.export',['file'=>'excel','search' =>request()->get('search')])}}">
                                            {{ translate('excel') }}
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive mt-3">
                        <table class="table table-borderless align-middle text-nowrap fs-14">
                            <thead class="table-light align-middle">
                                <tr>
                                    <th>{{ translate('SL') }}</th>
                                    <th class="text-capitalize">{{ translate('Image') }}</th>
                                    <th class="text-capitalize">{{ translate('Title') }}</th>
                                    <th class="text-capitalize">{{ translate('Description') }}</th>
                                    <th class="text-capitalize">{{ translate('Targeted_user') }}</th>
                                    <th class="status">{{ translate('status') }}</th>
                                    <th class="text-center action">{{ translate('action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($sendNotifications as $index => $notification)
                                    @php
                                        $desc = $notification?->description;
                                    @endphp
                                    <tr>
                                        <td> {{ $sendNotifications->firstItem() + $index }} </td>
                                        <td>
                                            <img src="{{ onErrorImage(
                                                $notification?->image,
                                                dynamicStorage('storage/app/public/push-notification') . '/' . $notification?->image,
                                                dynamicAsset('public/assets/admin-module/img/media/banner-upload-file.png'),
                                                'push-notification/',
                                            ) }}" class="custom-box-size-banner object-cover rounded dark-support" alt="">
                                        </td>
                                        <td class="text-truncate max-w-280px">{{ $notification?->name }}</td>
                                        <td>
                                        <span class="line--limit-3 max-w-280px min-w-200 text-wrap"
                                              @if($desc) data-bs-toggle="tooltip" data-bs-title="{{ $desc }}"@endif>
                                            {{ $desc ?? 'No description' }}
                                        </span>
                                        </td>
                                        <td>{{ ucwords(implode(', ', $notification->targeted_users)) }}</td>
                                        <td class="status">
                                            <label class="switcher mx-auto">
                                                <input class="switcher_input status-change"
                                                       data-url={{ route('admin.promotion.send-notification.status') }}
                                                        id="{{ $notification->id }}" type="checkbox"
                                                    {{ (int)$notification->is_active ? 'checked' : '' }}>
                                                <span class="switcher_control"></span>
                                            </label>
                                        </td>
                                        <td class="action">
                                            <div class="d-flex justify-content-center gap-2 align-items-center">
                                                @can('promotion_edit')
                                                    <a href="javascript:"
                                                       class="btn btn-outline-primary btn-action view-send-notification"
                                                       data-id="{{ $notification->id }}"
                                                       data-bs-toggle="modal"
                                                       data-bs-target="#view_notification_modal">
                                                        <i class="bi bi-eye-fill"></i>
                                                    </a>
                                                @endcan

                                                @can('promotion_edit')
                                                    <a href="javascript:"
                                                       class="btn btn-outline-info btn-action edit-send-notification"
                                                       data-id="{{ $notification->id }}"
                                                       data-bs-toggle="offcanvas"
                                                       data-bs-target="#edit_notification_offcanvas">
                                                        <i class="bi bi-pencil-fill"></i>
                                                    </a>
                                                @endcan


                                                @can('promotion_delete')
                                                    <button data-url="{{ route('admin.promotion.send-notification.delete', ['id' => $notification->id]) }}"
                                                            data-icon="{{ dynamicAsset('public/assets/admin-module/img/trash.png') }}"
                                                            data-title="{{ translate('Are you sure to delete this Notification') }}?"
                                                            data-sub-title="{{ translate('Once you delete it') . ', ' . translate('This will be permanently removed from the list.') }}"
                                                            data-confirm-btn="{{ translate('Yes, Delete') }}"
                                                            data-cancel-btn="{{ translate('Not Now') }}"
                                                            type="button"
                                                            class="btn btn-outline-danger btn-action delete-button">
                                                        <i class="bi bi-trash-fill"></i>
                                                    </button>
                                                @endcan

                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-end">
                         {!! $sendNotifications->links() !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- End Main Content -->

    {{-- Edit Offcanvas --}}
    <div class="offcanvas {{ Session::get('direction') === 'rtl' ? 'offcanvas-start' : ' offcanvas-end' }}" id="edit_notification_offcanvas">
    </div>
    <div class="modal fade" id="view_notification_modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
            <div class="modal-content" id="view-notification-data">

            </div>
        </div>
    </div>


@endsection

@push('script')
    <script src="{{ dynamicAsset('public/assets/admin-module/js/single-image-upload.js') }}"></script>
    <script>
        $(document).ready(function () {
            let notificationId = localStorage.getItem('editNotificationId');
            @if($errors->any())
            if (notificationId) {
                loadEditSendNotificationForm(notificationId)
            }
            @endif
            localStorage.removeItem('editNotificationId');

            $('.js-select-offcanvas').select2({
                dropdownParent: $('#edit_notification_offcanvas')
            });
            $("select").closest("form").on("reset", function (ev) {
                var targetJQForm = $(ev.target);
                setTimeout((function () {
                    this.find("select").trigger("change");
                }).bind(targetJQForm), 0);
            });

            $(document).on('click', '.edit-send-notification', function () {
                let notificationId = $(this).data('id');
                localStorage.setItem('editNotificationId', notificationId);
                loadEditSendNotificationForm(notificationId)

            })

            function loadEditSendNotificationForm(id)
            {
                let url = "{{ route('admin.promotion.send-notification.edit', ':id') }}";
                url = url.replace(':id', id);
                $('#edit_notification_offcanvas').empty();
                $('#edit_notification_offcanvas').offcanvas('show');
                $.get(url, function (data) {
                    $('#edit_notification_offcanvas').html(data);
                    $('.js-select-offcanvas').select2({
                        dropdownParent: $('#edit_notification_offcanvas')
                    });
                    $('[data-bs-toggle="tooltip"]').tooltip();
                    $.getScript('{{ dynamicAsset('public/assets/admin-module/js/single-image-upload.js') }}');
                    $(document).off('click', '.remove-img-icon').on('click', '.remove-img-icon', function () {
                        const card = $(this).closest(".upload-file");
                        const textbox = card.find(".upload-file__textbox");
                        const imgElement = card.find(".upload-file__img__img");
                        card.find(".upload-file__input").val("");
                        imgElement.attr("src", "").hide();
                        textbox.show();
                        card.find(".remove-img-icon").addClass("d-none");
                        $('#oldImage').val('');
                    });
                });
            }

            $(document).on('click', '.view-send-notification', function () {
                let url = "{{ route('admin.promotion.send-notification.view', ':id') }}";
                url = url.replace(':id', $(this).data('id'));
                $.get(url, function (data) {
                    $('#view-notification-data').html(data);
                    $('#view_notification_modal').modal('show');
                });
            });

            var offcanvasEl = document.getElementById('edit_notification_offcanvas');
            offcanvasEl.addEventListener('hidden.bs.offcanvas', function () {
                localStorage.removeItem('editNotificationId');
            });
        })
    </script>
@endpush
