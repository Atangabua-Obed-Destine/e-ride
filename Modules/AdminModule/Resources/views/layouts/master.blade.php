<!DOCTYPE html>
<html lang="{{ session()->has('locale') ? session('locale') : 'en' }}" dir="{{ session()->get('direction') ?? 'ltr' }}">
@php($logo = getSession('header_logo'))
@php($favicon = getSession('favicon'))
@php($preloader = getSession('preloader'))

<head>
    <!-- Page Title -->
    <title>@yield('title')</title>

    <!-- Meta Data -->
    <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
    <meta http-equiv="content-type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <meta name="description" content=""/>
    <meta name="keywords" content=""/>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Favicon -->
    <link rel="shortcut icon"
          href="{{ onErrorImage(
            $favicon,
            dynamicStorage('storage/app/public/business') . '/' . $favicon,
            dynamicAsset('public/assets/admin-module/img/favicon.png'),
            'business/',
        ) }}"/>
    <link rel="shortcut icon"
          href="{{ onErrorImage(
            $favicon,
            dynamicStorage('storage/app/public/business') . '/' . $favicon,
            dynamicAsset('public/assets/admin-module/img/favicon.png'),
            'business/',
        ) }}"/>
    <!-- Web Fonts -->
    <link rel="stylesheet" href="{{ dynamicAsset('public/assets/admin-module/css/fonts/google.css') }}"/>

    <!-- ======= BEGIN GLOBAL MANDATORY STYLES ======= -->
    <link rel="stylesheet" href="{{ dynamicAsset('public/assets/admin-module/css/bootstrap.min.css') }}"/>
    <link rel="stylesheet" href="{{ dynamicAsset('public/assets/admin-module/css/bootstrap-icons.min.css') }}"/>
    <link rel="stylesheet" href="{{ dynamicAsset('public/assets/admin-module/plugins/icon-set/style.css') }}"/>
    <link rel="stylesheet"
          href="{{ dynamicAsset('public/assets/admin-module/plugins/perfect-scrollbar/perfect-scrollbar.min.css') }}"/>
    <link rel="stylesheet" href="{{ dynamicAsset('public/assets/admin-module/css/toastr.css') }}"/>
    <!-- ======= END BEGIN GLOBAL MANDATORY STYLES ======= -->

    <!-- ======= BEGIN PAGE LEVEL PLUGINS STYLES ======= -->
    <link rel="stylesheet" href="{{ dynamicAsset('public/assets/admin-module/plugins/apex/apexcharts.css') }}"/>
    <link rel="stylesheet" href="{{ dynamicAsset('public/assets/admin-module/plugins/select2/select2.min.css') }}"/>
    <link rel="stylesheet" href="{{ dynamicAsset('public/assets/admin-module/plugins/daterangepicker/daterangepicker.css') }}"/>
    <link rel="stylesheet" href="{{ dynamicAsset('public/assets/admin-module/plugins/tags-input/tags-input.min.css') }}"/>
    <!-- ======= END BEGIN PAGE LEVEL PLUGINS STYLES ======= -->

    <link href="{{ dynamicAsset('public/assets/admin-module/css/intlTelInput.min.css') }}" rel="stylesheet"/>

    <!-- ======= MAIN STYLES ======= -->
    <link rel="stylesheet" href="{{ dynamicAsset('public/assets/admin-module/css/style.css') }}"/>
    <link rel="stylesheet" href="{{ dynamicAsset('public/assets/admin-module/css/custom.css') }}"/>
    @include('adminmodule::layouts.css')
    <!-- ======= END MAIN STYLES ======= -->

    <!-- ======= FOR CUSTOM STYLE ======= -->
    @stack('css_or_js')
    @stack('css_or_js2')
</head>

<body>
<script>
    localStorage.theme && document.querySelector('body').setAttribute("theme", localStorage.theme);
    localStorage.dir && document.querySelector('html').setAttribute("dir", localStorage.dir);
</script>

<!-- Offcanval Overlay -->
<div class="offcanvas-overlay"></div>
<!-- Offcanval Overlay -->
<!-- Preloader -->
<div class="preloader" id="preloader">
    @if ($preloader)
        <img class="preloader-img" width="160" loading="eager"
             src="{{ $preloader ? dynamicStorage('storage/app/public/business/' . $preloader) : '' }}" alt="">
    @else
        <div class="spinner-grow" role="status">
            <span class="visually-hidden">{{ translate('Loading...') }}</span>
        </div>
    @endif
</div>
<div class="resource-loader" id="resource-loader" style="display: none;">
    @if ($preloader)
        <img width="160" loading="eager"
             src="{{ dynamicStorage('storage/app/public/business') }}/{{ $preloader ?? null }}" alt="">
    @else
        <div class="spinner-grow" role="status">
            <span class="visually-hidden">{{ translate('Loading...') }}</span>
        </div>
    @endif
</div>
<!-- End Preloader -->

<!-- Header -->
@include('adminmodule::partials._header')
<!-- End Header -->

<!-- Aside -->
@include('adminmodule::partials._sidebar')
<!-- End Aside -->

<!-- Settings Sidebar -->
@include('adminmodule::partials._settings')
<!-- End Settings Sidebar -->


<!-- Wrapper -->
<main class="main-area">
    @include('adminmodule::modal._custom-modal')

    <!-- Main Content -->
    @yield('content')
    <!-- End Main Content -->

    <!-- Footer -->
    @include('adminmodule::partials._footer')
    <!-- End Footer -->

</main>
<!-- End wrapper -->

<span class="system-default-country-code" data-value="{{ getSession('country_code') ?? 'us' }}"></span>
<span class="image-file-size-data-to-js"
      data-max-upload-size-for-image="{{ readableUploadMaxFileSize('image') }}"
      data-max-upload-size-for-file="{{ readableUploadMaxFileSize('file') }}"
      data-post-max-size="{{ convertToReadableSize(convertToBytes(ini_get('post_max_size'))) }}"
      data-allowed-extensions="{{ IMAGE_ACCEPTED_EXTENSIONS }}"
></span>
<span class="global-data"
      data-dashboard-route="{{ route('admin.dashboard') }}"
      data-on-ajax-form-submit-successful="{{ translate('') }}"
></span>

<script src="{{ dynamicAsset('public/assets/admin-module/js/firebase.min.js') }}"></script>
@include('adminmodule::partials._firebase-script')
<!-- ======= BEGIN GLOBAL MANDATORY SCRIPTS ======= -->
<script src="{{ dynamicAsset('public/assets/admin-module/js/jquery-3.6.0.min.js') }}"></script>
<script src="{{ dynamicAsset('public/assets/admin-module/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ dynamicAsset('public/assets/admin-module/plugins/perfect-scrollbar/perfect-scrollbar.min.js') }}"></script>
<script src="{{ dynamicAsset('public/assets/admin-module/plugins/select2/select2.min.js') }}"></script>
<script src="{{dynamicAsset('public/assets/admin-module/plugins/daterangepicker/moment.min.js')}}"></script>
<script src="{{dynamicAsset('public/assets/admin-module/plugins/daterangepicker/daterangepicker.min.js')}}"></script>
<script src="{{dynamicAsset('public/assets/admin-module/plugins/tags-input/tags-input.min.js')}}"></script>
{{-- TOASTR and SWEETALERT --}}
<script src="{{ dynamicAsset('public/assets/admin-module/js/sweet_alert.js') }}"></script>
<script src="{{ dynamicAsset('public/assets/admin-module/js/toastr.js') }}"></script>
<script src="{{ dynamicAsset('public/assets/admin-module/js/dev.js') }}"></script>

<script src="{{ dynamicAsset('public/assets/admin-module/js/intlTelInput.min.js') }}"></script>
<script src="{{ dynamicAsset('public/assets/admin-module/js/country-picker-init.js') }}"></script>
<script src="{{ dynamicAsset('public/assets/admin-module/js/select-2-init.js') }}"></script>
<script src="{{ dynamicAsset('public/assets/admin-module/js/main.js') }}"></script>
	<script>
	    window.onMultipleImageUploadBaseImage = "{{ dynamicAsset('public/assets/admin-module/img/document-upload.png') }}";
	    let onMultipleImageUploadSuccess = "{{ translate('image_added') }}";
	    let onMultipleImageUploadExtensionError = "{{ translate('please_only_input_png_,_jpg_,_jpeg_,_webp_type_file') }}";
	    let onMultipleImageUploadSizeError = "{{ translate('Image size must be less than {maxSize}', replace: ['maxSize' => readableUploadMaxFileSize('image')]) }}";
	    let onMultipleImageUploadText1 = "{{ translate('Click to upload') }}";
	    let onMultipleImageUploadText2 = "{{ translate('or drag and drop') }}";
        const postMaxSize = parseFloat($('.image-file-size-data-to-js').data('post-max-size')) * 1024 * 1024;
        let totalSize = 0;

        function getFilesSize(files) {
            return Array.from(files || []).reduce((sum, file) => sum + file.size, 0);
        }

        function getUploadPayloadSize(options = {}) {
            const form = options.form || document.querySelector('form');
            const excludeInput = options.excludeInput || null;
            if (!form) {
                return 0;
            }

            let total = 0;
            form.querySelectorAll('input[type="file"][name]').forEach(function (input) {
                if (excludeInput && input === excludeInput) {
                    return;
                }
                total += getFilesSize(input.files);
            });

            return total;
        }

        function showUploadPayloadSizeError() {
            toastr.error(
                `Total uploaded file size exceeds ${$('.image-file-size-data-to-js').data('post-max-size')} MB. Please remove some files.`,
                { CloseButton: true, ProgressBar: true }
            );
        }

        function canAddUploadFiles(files, options = {}) {
            const newTotal = getUploadPayloadSize(options) + getFilesSize(files);
            if (newTotal > postMaxSize) {
                showUploadPayloadSizeError();
                return false;
            }

            totalSize = newTotal;
            return true;
        }
	</script>
	<script src="{{ dynamicAsset('public/assets/admin-module/js/single-file-size-type-validation.js') }}"></script>
<!-- ======= BEGIN GLOBAL MANDATORY SCRIPTS ======= -->

{!! Toastr::message() !!}
@if ($errors->any())
    <script>
        "use strict";
        @foreach ($errors->all() as $error)
        toastr.error('{{ $error }}', {
            CloseButton: true,
            ProgressBar: true,
        });
        @endforeach
    </script>
@endif

	<script>
    "use strict";

    let formSubmitSuccessfulMessage = sessionStorage.getItem('formSubmittedSuccessfully');
    if (formSubmitSuccessfulMessage)
    {
        toastr.success(formSubmitSuccessfulMessage, {
            CloseButton: true,
            ProgressBar: true
        });
        sessionStorage.removeItem('formSubmittedSuccessfully');
    }

    // Toggle card with view button
    function toggleCard($viewBtn) {
        const $cardBody = $viewBtn.closest('.collapsible-card-body');
        const $content = $cardBody.find('.collapsible-card-content');
        const $arrow = $viewBtn.find('i');

        $content.slideToggle(300);
        $arrow.toggleClass('tio-arrow-upward tio-arrow-downward');
    }

    $('.view-btn').on('click', function () {
        toggleCard($(this));
    });

    $(document).on('click', '.call-demo', function () {
        @if(env('APP_MODE') =='demo')
        toastr.info('{{ translate('Update option is disabled for demo!') }}', {
            CloseButton: true,
            ProgressBar: true
        });
        @endif
    });
    $(".status-change-level").on('change', function () {
        statusAlertNew(this);
    });

    function statusAlertNew(obj) {
        let url = $(obj).data('url');
        let iconContent = $(obj).data('icon');
        let titleContent = $(obj).data('title');
        let subTitleContent = $(obj).data('sub-title');
        let checked = $(obj).prop("checked");
        let status = checked === true ? 1 : 0;

        // Show custom modal
        let bootstrapModal = new bootstrap.Modal(document.getElementById("customModal"));
        bootstrapModal.show();

        if (iconContent) {
            $("#icon").attr('src', iconContent);
        }
        if (titleContent) {
            $("#title").html("");
            $("#title").html(titleContent);
        }
        if (subTitleContent) {
            $("#subTitle").html("");
            $("#subTitle").html(subTitleContent);
        }
        let confirmBtn = document.getElementById("modalConfirmBtn");
        let cancelBtn = document.getElementById("modalCancelBtn");


        // // When the user clicks on OK button
        confirmBtn.onclick = function () {
            $.ajax({
                url: url,
                _method: 'PUT',
                data: {
                    status: status,
                    id: obj.id
                },
                success: function () {
                    toastr.success("{{ translate('status_changed_successfully') }}");
                    setTimeout(function () {
                        location.reload();
                    }, 1000);
                },
                error: function () {
                    resetCheckbox();
                    toastr.error("{{ translate('status_change_failed') }}");
                }
            });
            bootstrapModal.hide();
        }

        // When the user clicks on Cancel button
        cancelBtn.onclick = function () {
            bootstrapModal.hide();
            resetCheckbox();
        }

        function resetCheckbox() {
            if (status === 1) {
                $('#' + obj.id).prop('checked', false);
            } else if (status === 0) {
                $('#' + obj.id).prop('checked', true);
            }
        }
    }

    $(document).off('change', '.status-change-blog-category').on('change', '.status-change-blog-category', function () {
        changeBlogCategoryStatus(this);
    });


    function changeBlogCategoryStatus(obj) {
        if (!permission) {
            toastr.error('{{ translate('you_do_not_have_enough_permission_to_update_this_settings') }}');
            return;
        }

        let url = $(obj).data('url');
        let iconContent = $(obj).data('icon');
        let titleContent = $(obj).data('title');
        let subTitleContent = $(obj).data('sub-title');
        let confirmBtnContent = $(obj).data('confirm-btn');
        let cancelBtnContent = $(obj).data('cancel-btn');
        let cancelBtnClass = $(obj).data('cancel-btn-class');

        let isChecked = $(obj).prop("checked");
        let id = obj.id;

        $('#' + id).prop('checked', !isChecked);

        const modalElement = document.getElementById('customModal');
        let bootstrapModal = new bootstrap.Modal(modalElement);
        bootstrapModal.show();

        if (iconContent) $("#icon").attr('src', iconContent);
        if (titleContent) $("#title").html(titleContent);
        if (subTitleContent) $("#subTitle").html(subTitleContent);
        if (confirmBtnContent) $("#modalConfirmBtn").html(confirmBtnContent);
        if (cancelBtnContent) $("#modalCancelBtn").html(cancelBtnContent);
        if(cancelBtnClass) $("#modalCancelBtn").removeClass('btn--cancel').addClass(cancelBtnClass);

        $('#modalConfirmBtn').off('click').on('click', function () {
            $.ajax({
                url: url,
                _method: 'PUT',
                data: {
                    value: isChecked ? 1 : 0,
                    search: $(obj).data('search') ?? null
                },
                success: function (response) {
                    toastr.success("{{ translate('status_changed_successfully') }}");
                    $('#blog-category-list').empty().html(response.view)

                    $.ajax({
                        url: '{{ route('admin.blog.category.index') }}',
                        type: 'GET',
                        success: function (response) {
                            $('#active-categories').empty().html(response.create_blade_category_view)
                        },
                        error: function () {
                            toastr.error("{{ translate('something went wrong') }}");
                        }
                    });
                },
                error: function () {
                    toastr.error("{{ translate('status_change_failed') }}");
                }
            });
            bootstrapModal.hide();
        });

        $('#modalCancelBtn').off('click').on('click', function () {
            bootstrapModal.hide();
        });
    }


    $(".status-change").on('change', function () {
        statusAlert(this);
    })

    function statusAlert(obj) {
        let url = $(obj).data('url');
        let checked = $(obj).prop("checked");
        let status = checked === true ? 1 : 0;
        Swal.fire({
            title: '{{ translate('are_you_sure') }}?',
            text: '{{ translate('want_to_change_status') }}',
            type: 'warning',
            showCancelButton: true,
            confirmButtonColor: 'var(--bs-primary)',
            cancelButtonColor: 'default',
            cancelButtonText: '{{ translate('no') }}',
            confirmButtonText: '{{ translate('yes') }}',
            reverseButtons: true
        }).then((result) => {
            if (result.value) {
                $.ajax({
                    url: url,
                    _method: 'PUT',
                    data: {
                        status: status,
                        id: obj.id
                    },
                    success: function () {
                        toastr.success("{{ translate('status_changed_successfully') }}");
                        setTimeout(function () {
                            location.reload();
                        }, 1000);
                    },
                    error: function () {
                        if (status === 1) {
                            $('#' + obj.id + '.status-change').prop('checked', false)
                        } else if (status === 0) {
                            $('#' + obj.id + '.status-change').prop('checked', true)
                        }
                        toastr.error("{{ translate('status_change_failed') }}");
                    }
                });
            } else {
                if (status === 1) {
                    $('#' + obj.id + '.status-change').prop('checked', false)
                } else if (status === 0) {
                    $('#' + obj.id + '.status-change').prop('checked', true)
                }
            }
        })
    }

    $('.custom_status_change').on('change', function () {
        customStatusChange(this)
    })

    function customStatusChange(obj) {
        let url = $(obj).data('url');
        let iconContent = $(obj).data('icon');
        let titleContent = $(obj).data('title');
        let subTitleContent = $(obj).data('sub-title');
        let confirmBtnContent = $(obj).data('confirm-btn');
        let cancelBtnContent = $(obj).data('cancel-btn');

        let checked = $(obj).prop("checked");
        let status = checked === true ? 1 : 0;

        const modalElement = document.getElementById('customModalForStatus');
        let bootstrapModal = new bootstrap.Modal(modalElement);
        bootstrapModal.show();

        if (iconContent) {
            $("#iconForStatus").attr('src', iconContent);
            $('.swal2-animate-warning-icon').addClass('d-none');
            $('.swal2-animate-warning-icon').removeClass('d-flex');
        }
        if (titleContent) {
            $("#titleForStatus").html("");
            $("#titleForStatus").html(titleContent);
        }
        if (subTitleContent) {
            $("#subTitleForStatus").html("");
            $("#subTitleForStatus").html(subTitleContent);
        }
        if (confirmBtnContent) {
            $("#modalConfirmBtnForStatus").html("");
            $("#modalConfirmBtnForStatus").html(confirmBtnContent);
        }
        if (cancelBtnContent) {
            $("#modalCancelBtnForStatus").html("");
            $("#modalCancelBtnForStatus").html(cancelBtnContent);
        }


        let confirmBtn = document.getElementById("modalConfirmBtnForStatus");
        let cancelBtn = document.getElementById("modalCancelBtnForStatus");

        confirmBtn.onclick = function () {
            $.ajax({
                url: url,
                _method: 'PUT',
                data: {id: obj.id, status: status},
                success: function () {
                    toastr.success("{{ translate('status_changed_successfully') }}");
                    setTimeout(function () {
                        location.reload();
                    }, 1000);
                },
                error: function () {
                    resetCheckbox();
                    toastr.error("{{ translate('status_change_failed') }}");
                }
            });
            bootstrapModal.hide();
        }

        cancelBtn.onclick = function () {
            bootstrapModal.hide();
            resetCheckbox();
        }

        // $('.btn-close').on('click', function () {
        //     bootstrapModal.hide();
        //     resetCheckbox();
        // });

        modalElement.addEventListener('hidden.bs.modal', function () {
            resetCheckbox();
        });

        function resetCheckbox() {
            if (status === 1) {
                $('#' + obj.id).prop('checked', false);
            } else if (status === 0) {
                $('#' + obj.id).prop('checked', true);
            }
        }
    }

    $('.surge_price_status_change').on('change', function () {
        surgePriceStatusChange(this)
    })

    function surgePriceStatusChange(obj) {
        let url = $(obj).data('url');
        let iconContent = $(obj).data('icon');
        let titleContent = $(obj).data('title');
        let subTitleContent = $(obj).data('sub-title');
        let confirmBtnContent = $(obj).data('confirm-btn');
        let cancelBtnContent = $(obj).data('cancel-btn');

        let checked = $(obj).prop("checked");
        let status = checked === true ? 1 : 0;

        const modalElement = document.getElementById('surgePriceModalForStatus');
        let bootstrapModal = new bootstrap.Modal(modalElement);
        bootstrapModal.show();

        if (iconContent) {
            $("#iconForSurgePriceStatus").attr('src', iconContent);
            $('.swal2-animate-warning-icon').addClass('d-none');
            $('.swal2-animate-warning-icon').removeClass('d-flex');
        }
        if (titleContent) {
            $("#titleForSurgePriceStatus").html("");
            $("#titleForSurgePriceStatus").html(titleContent);
        }
        if (subTitleContent) {
            $("#subTitleForSurgePriceStatus").html("");
            $("#subTitleForSurgePriceStatus").html(subTitleContent);
        }
        if (confirmBtnContent) {
            $("#modalConfirmBtnForSurgePriceStatus").html("");
            $("#modalConfirmBtnForSurgePriceStatus").html(confirmBtnContent);
        }
        if (cancelBtnContent) {
            $("#modalCancelBtnForSurgePriceStatus").html("");
            $("#modalCancelBtnForSurgePriceStatus").html(cancelBtnContent);
        }


        let confirmBtn = document.getElementById("modalConfirmBtnForSurgePriceStatus");
        let cancelBtn = document.getElementById("modalCancelBtnForSurgePriceStatus");

        confirmBtn.onclick = function () {
            $.ajax({
                url: url,
                _method: 'PUT',
                data: {id: obj.id, status: status},
                success: function () {
                    toastr.success("{{ translate('status_changed_successfully') }}");
                    setTimeout(function () {
                        location.reload();
                    }, 1000);
                },
                error: function (xhr) {
                    const errors = xhr.responseJSON?.errors;
                    if (errors)
                    {
                        if (errors.hasOwnProperty("surge_pricing_overlap_message")){
                            showSurgePriceOverlapModal(errors.surge_pricing_overlap_message[0]);
                        } else {
                            $.each(errors, function (key, value) {
                                toastr.error(value[0]);
                            });
                        }
                    } else{
                        toastr.error('{{ translate('status_change_failed') }}');
                    }
                }
            });
            bootstrapModal.hide();
        }

        cancelBtn.onclick = function () {
            bootstrapModal.hide();
            resetCheckbox();
        }

        modalElement.addEventListener('hidden.bs.modal', function () {
            resetCheckbox();
        });

        function resetCheckbox() {
            if (status === 1) {
                $('#' + obj.id).prop('checked', false);
            } else if (status === 0) {
                $('#' + obj.id).prop('checked', true);
            }
        }
    }


    $('.wallet_bonus_status_change').on('change', function () {
        walletBonusStatusChange(this);
    });

    function walletBonusStatusChange(obj) {
        let url = $(obj).data('url');
        let iconContent = $(obj).data('icon');
        let titleContent = $(obj).data('title');
        let subTitleContent = $(obj).data('sub-title');
        let confirmBtnContent = $(obj).data('confirm-btn');
        let cancelBtnContent = $(obj).data('cancel-btn');
        let actionBtnClass = $(obj).data('action-button-class');

        let checked = $(obj).prop("checked");
        let status = checked === true ? 1 : 0;

        const modalElement = document.getElementById('walletBonusModalForStatus');
        let bootstrapModal = new bootstrap.Modal(modalElement);
        bootstrapModal.show();

        if (iconContent) {
            $("#iconForWalletBonusStatus").attr('src', iconContent);
            $('.swal2-animate-warning-icon').addClass('d-none');
            $('.swal2-animate-warning-icon').removeClass('d-flex');
        }
        if (titleContent) {
            $("#titleForWalletBonusStatus").html("");
            $("#titleForWalletBonusStatus").html(titleContent);
        }
        if (subTitleContent) {
            $("#subTitleForWalletBonusStatus").html("");
            $("#subTitleForWalletBonusStatus").html(subTitleContent);
        }
        if (confirmBtnContent) {
            $("#modalConfirmBtnForWalletBonusStatus").html("");
            $("#modalConfirmBtnForWalletBonusStatus").html(confirmBtnContent);
        }
        if (cancelBtnContent) {
            $("#modalCancelBtnForWalletBonusStatus").html("");
            $("#modalCancelBtnForWalletBonusStatus").html(cancelBtnContent);
        }
        if (actionBtnClass) {
            $('#modalConfirmBtnForWalletBonusStatus').addClass(actionBtnClass);
        }


        let confirmBtn = document.getElementById("modalConfirmBtnForWalletBonusStatus");
        let cancelBtn = document.getElementById("modalCancelBtnForWalletBonusStatus");

        confirmBtn.onclick = function () {
            $.ajax({
                url: url,
                _method: 'PUT',
                data: {id: obj.id, status: status},
                success: function (data) {
                    if (data?.errors) {
                        toastr.error(data.errors[0].message);
                    } else {
                        toastr.success("{{ translate('status_changed_successfully') }}");
                        setTimeout(function () {
                            location.reload();
                        }, 1000);
                    }
                },
                error: function (xhr) {
                    toastr.error('{{ translate('status_change_failed') }}');
                }
            });
            bootstrapModal.hide();
        }

        cancelBtn.onclick = function () {
            bootstrapModal.hide();
            resetCheckbox();
        }

        modalElement.addEventListener('hidden.bs.modal', function () {
            resetCheckbox();
        });

        function resetCheckbox() {
            if (status === 1) {
                $('#' + obj.id).prop('checked', false);
            } else if (status === 0) {
                $('#' + obj.id).prop('checked', true);
            }
        }
    }

    $(".maintenance-off").on('change', function () {
        maintenanceOff(this);
    })

    function maintenanceOff(obj) {
        let url = $(obj).data('url');
        let checked = $(obj).prop("checked");
        let status = checked === true ? 1 : 0;
        Swal.fire({
            title: '{{ translate('are_you_sure') }}?',
            text: '{{ translate('Do you want to turn off Maintenance mode? Turning it off will activate all systems that were deactivated.') }}',
            imageUrl: "{{ dynamicAsset('public/assets/admin-module/img/maintenance_off.svg') }}",
            imageAlt: "Custom image",
            showCancelButton: true,
            confirmButtonColor: 'var(--bs-primary)',
            cancelButtonColor: 'default',
            cancelButtonText: '{{ translate('discard') }}',
            confirmButtonText: '{{ translate('yes') }}',
            reverseButtons: true
        }).then((result) => {
            if (result.value) {
                $.ajax({
                    url: url,
                    _method: 'PUT',
                    data: {
                        status: status,
                        id: obj.id
                    },
                    success: function () {
                        toastr.success("{{ translate('maintenance_mode_successfully_off') }}");
                        setTimeout(function () {
                            location.reload();
                        }, 1000);
                    },
                    error: function () {
                        if (status === 1) {
                            $('#' + obj.id).prop('checked', false)
                        } else if (status === 0) {
                            $('#' + obj.id).prop('checked', true)
                        }
                        toastr.error("{{ translate('status_change_failed') }}");
                    }
                });
            } else {
                if (status === 1) {
                    $('#' + obj.id).prop('checked', false)
                } else if (status === 0) {
                    $('#' + obj.id).prop('checked', true)
                }
            }
        })
    }


    $('.update-business-setting, .update-referral-setting').on('change', function () {
        updateSettingLevel(this)
    });


    function updateSettingLevel(obj) {
        if (!permission) {
            toastr.error('{{ translate('you_do_not_have_enough_permission_to_update_this_settings') }}');
            return;
        }

        let url = $(obj).data('url');
        let iconContent = $(obj).data('icon');
        let titleContent = $(obj).data('title');
        let subTitleContent = $(obj).data('sub-title');
        let confirmBtnContent = $(obj).data('confirm-btn');
        let cancelBtnContent = $(obj).data('cancel-btn');

        let isChecked = $(obj).prop("checked");
        let name = $(obj).data('name');
        let type = $(obj).data('type');
        let id = obj.id;

        $('#' + id).prop('checked', !isChecked);

        const modalElement = document.getElementById('customModal');
        let bootstrapModal = new bootstrap.Modal(modalElement);
        bootstrapModal.show();

        if (iconContent) $("#icon").attr('src', iconContent);
        if (titleContent) $("#title").html(titleContent);
        if (subTitleContent) $("#subTitle").html(subTitleContent);
        if (confirmBtnContent) $("#modalConfirmBtn").html(confirmBtnContent);
        if (cancelBtnContent) $("#modalCancelBtn").html(cancelBtnContent);

        $('#modalConfirmBtn').off('click').on('click', function () {
            $.ajax({
                url: url,
                _method: 'PUT',
                data: {
                    value: isChecked ? 1 : 0,
                    name: name,
                    type: type ?? ''
                },
                success: function () {
                    toastr.success("{{ translate('status_changed_successfully') }}");
                    $('#' + id).prop('checked', isChecked);
                    setTimeout(function () {
                        location.reload();
                    }, 1000);
                },
                error: function () {
                    toastr.error("{{ translate('status_change_failed') }}");
                }
            });
            bootstrapModal.hide();
        });

        $('#modalCancelBtn').off('click').on('click', function () {
            bootstrapModal.hide();
        });
    }

    $('.update-blog-status').on('change', function () {
        updateBlogStatus(this)
    });

    function updateBlogStatus(obj) {
        if (!permission) {
            toastr.error('{{ translate('you_do_not_have_enough_permission_to_update_this_settings') }}');
            return;
        }

        let url = $(obj).data('url');
        let iconContent = $(obj).data('icon');
        let titleContent = $(obj).data('title');
        let subTitleContent = $(obj).data('sub-title');
        let confirmBtnContent = $(obj).data('confirm-btn');
        let cancelBtnContent = $(obj).data('cancel-btn');
        let cancelBtnClass = $(obj).data('cancel-btn-class');

        let isChecked = $(obj).prop("checked");
        let id = obj.id;

        $('#' + id).prop('checked', !isChecked);

        const modalElement = document.getElementById('customModal');
        let bootstrapModal = new bootstrap.Modal(modalElement);
        bootstrapModal.show();

        if (iconContent) $("#icon").attr('src', iconContent);
        if (titleContent) $("#title").html(titleContent);
        if (subTitleContent) $("#subTitle").html(subTitleContent);
        if (confirmBtnContent) $("#modalConfirmBtn").html(confirmBtnContent);
        if (cancelBtnContent) $("#modalCancelBtn").html(cancelBtnContent);
        if(cancelBtnClass) $("#modalCancelBtn").removeClass('btn--cancel').addClass(cancelBtnClass);

        $('#modalConfirmBtn').off('click').on('click', function () {
            $.ajax({
                url: url,
                _method: 'PUT',
                data: {
                    status: isChecked ? 1 : 0,
                    id: id
                },
                success: function () {
                    toastr.success("{{ translate('status_changed_successfully') }}");
                    $('#' + id).prop('checked', isChecked);
                    setTimeout(function () {
                        location.reload();
                    }, 1000);
                },
                error: function () {
                    toastr.error("{{ translate('status_change_failed') }}");
                }
            });
            bootstrapModal.hide();
        });

        $('#modalCancelBtn').off('click').on('click', function () {
            bootstrapModal.hide();
        });
    }

    $('.update-extra-fare-setting').on('change', function () {
        updateExtraFareSetting(this)
    })

    function updateExtraFareSetting(obj) {
        let url = $(obj).data('url');
        let iconContent = $(obj).data('icon');
        let titleContent = $(obj).data('title');
        let subTitleContent = $(obj).data('sub-title');
        let confirmBtnContent = $(obj).data('confirm-btn');
        let cancelBtnContent = $(obj).data('cancel-btn');

        let checked = $(obj).prop("checked");
        let status = checked === true ? 1 : 0;

        // Show custom modal
        const modalElement = document.getElementById('customModal');
        let bootstrapModal = new bootstrap.Modal(modalElement);
        bootstrapModal.show();

        if (iconContent) {
            $("#icon").attr('src', iconContent);
        }
        if (titleContent) {
            $("#title").html("");
            $("#title").html(titleContent);
        }
        if (subTitleContent) {
            $("#subTitle").html("");
            $("#subTitle").html(subTitleContent);
        }
        if (confirmBtnContent) {
            $("#modalConfirmBtn").html("");
            $("#modalConfirmBtn").html(confirmBtnContent);
        }
        if (cancelBtnContent) {
            $("#modalCancelBtn").html("");
            $("#modalCancelBtn").html(cancelBtnContent);
        }


        let confirmBtn = document.getElementById("modalConfirmBtn");
        let cancelBtn = document.getElementById("modalCancelBtn");


        // // When the user clicks on OK button
        confirmBtn.onclick = function () {
            $.ajax({
                url: url,
                _method: 'PUT',
                data: {
                    status: status,
                    id: obj.id
                },
                success: function () {
                    toastr.success("{{ translate('status_changed_successfully') }}");
                    setTimeout(function () {
                        location.reload();
                    }, 1000);
                },
                error: function () {
                    resetCheckbox();
                    toastr.error("{{ translate('status_change_failed') }}");
                }
            });
            bootstrapModal.hide();
        }

        // When the user clicks on Cancel button
        cancelBtn.onclick = function () {
            bootstrapModal.hide();
            resetCheckbox();
        }
        modalElement.addEventListener('hidden.bs.modal', function () {
            resetCheckbox();
        });

        function resetCheckbox() {
            if (status === 1) {
                $('#' + obj.id).prop('checked', false);
            } else if (status === 0) {
                $('#' + obj.id).prop('checked', true);
            }
        }
    }

    $(".default-status").on('change', function () {
        defaultStatusAlert(this);
    })

    function defaultStatusAlert(obj) {
        let url = $(obj).data('url');
        let checked = $(obj).prop("checked");
        let status = checked === true ? 1 : 0;
        Swal.fire({
            title: '{{ translate('are_you_sure') }}?',
            text: '{{ translate('want_to_change_default_status') }}',
            type: 'warning',
            showCancelButton: true,
            confirmButtonColor: 'var(--bs-primary)',
            cancelButtonColor: 'default',
            cancelButtonText: '{{ translate('no') }}',
            confirmButtonText: '{{ translate('yes') }}',
            reverseButtons: true
        }).then((result) => {
            if (result.value) {
                $.ajax({
                    url: url,
                    _method: 'PUT',
                    data: {
                        status: status,
                        id: obj.id
                    },
                    success: function () {
                        toastr.success("{{ translate('default_status_changed_successfully') }}");
                        setTimeout(function () {
                            location.reload();
                        }, 1000);
                    },
                    error: function () {
                        if (status === 1) {
                            $('#' + obj.id + '.default-status').prop('checked', false)
                        } else if (status === 0) {
                            $('#' + obj.id + '.default-status').prop('checked', true)
                        }
                        toastr.error("{{ translate('default_status_change_failed') }}");
                    }
                });
            } else {
                if (status === 1) {
                    $('#' + obj.id + '.default-status').prop('checked', false)
                } else if (status === 0) {
                    $('#' + obj.id + '.default-status').prop('checked', true)
                }
            }
        })
    }

    $(".form-alert").on('click', function () {
        let id = $(this).data('id');
        let message = $(this).data('message');
        formAlert(id, message)
    })

    function formAlert(id, message) {
        Swal.fire({
            title: '{{ translate('are_you_sure') }}?',
            text: message,
            type: 'warning',
            showCancelButton: true,
            cancelButtonColor: 'default',
            confirmButtonColor: 'var(--bs-danger)',
            cancelButtonText: '{{ translate('no') }}',
            confirmButtonText: '{{ translate('yes') }}',
            reverseButtons: true
        }).then((result) => {
            if (result.value) {
                $('#' + id).submit()
            }
        })
    }

    $(".form-alert-approved-rejected").on('click', function () {
        let id = $(this).data('id');
        let message = $(this).data('message');
        formAlertApprovedRejected(id, message)
    })

    function formAlertApprovedRejected(id, message) {
        Swal.fire({
            title: '{{ translate('are_you_sure') }}?',
            text: message,
            type: 'warning',
            showCancelButton: true,
            cancelButtonColor: 'default',
            confirmButtonColor: 'var(--bs-danger)',
            cancelButtonText: '{{ translate('no') }}',
            confirmButtonText: '{{ translate('yes') }}',
            reverseButtons: true
        }).then((result) => {
            if (result.value) {
                $('#' + id).submit()
            }
        })
    }

    $(".form-alert-warning").on('click', function () {
        let id = $(this).data('id');
        let message = $(this).data('message');
        formAlertWarning(id, message)
    })

    function formAlertWarning(id, message) {
        Swal.fire({
            title: '{{ translate('warning') }}!',
            imageUrl: '{{dynamicAsset('public/assets/admin-module/img/warning.png')}}',
            text: message,
            showCloseButton: true,
            showConfirmButton: false
        })
    }

    $(".restore-data").on('click', function () {
        let route = $(this).data('route');
        let message = $(this).data('message');
        restoreData(route, message)
    })

    function restoreData(route, message) {
        Swal.fire({
            title: '{{ translate('are_you_sure') }}?',
            text: message,
            type: 'warning',
            showCancelButton: true,
            cancelButtonColor: 'default',
            confirmButtonColor: 'var(--bs-primary)',
            cancelButtonText: '{{ translate('no') }}',
            confirmButtonText: '{{ translate('yes') }}',
            reverseButtons: true
        }).then((result) => {
            if (result.value) {
                window.location.href = route;
            }
        })
    }

    function loadPartialView(url, divId, data = null) {
        $.get({
            url: url,
            dataType: 'json',
            data: {
                data
            },
            beforeSend: function () {
                $('#resource-loader').show();
            },
            success: function (response) {
                $(divId).empty().html(response)
            },
            complete: function () {
                $('#resource-loader').hide();
            },
            error: function () {
                $('#resource-loader').hide();
                toastr.error('{{ translate('failed_to_load_data') }}')
            },
        });
    }

    function seenNotification(id) {
        $.get({
            url: '{{ route('admin.seen-notification') }}',
            dataType: 'json',
            data: {
                id: id
            },
            success: function (response) {
                $('#resource-loader').hide();
                if (id == 0) {
                    location.reload()
                }
            }
        });

    }

    function getNotifications() {
        $.get({
            url: '{{ route('admin.get-notifications') }}',
            dataType: 'json',
            success: function (response) {
                $('#notification').empty().html(response)
                commonFunctionRecall();
            },
            error: function (xhr, status, error) {
            },
        });
    }

    function getSafetyAlerts() {
        $.get({
            url: '{{ route('admin.get-safety-alert') }}',
            dataType: 'json',
            success: function (response) {
                $('#safetyAlert').empty().html(response);
                const showSafetyAlertUserDetails = $('.show-safety-alert-user-details');
                let userId = $(showSafetyAlertUserDetails[0]).data('user-id');
                if (userId) {
                    let getSafetyAlertUserId = localStorage.getItem('safetyAlertUserId');
                    if (userId !== getSafetyAlertUserId) {
                        localStorage.setItem('safetyAlertUserId', userId);
                    }
                } else {
                    localStorage.removeItem('safetyAlertUserId');
                }

                showSafetyAlertUserDetails.on('click', function () {
                    localStorage.setItem('safetyAlertUserDetailsStatus', true);
                });
            },
            error: function (xhr, status, error) {
            },
        });
    }

    function commonFunctionRecall() {
        $('.seen-notification').on('click', function () {
            let id = $(this).data('value');
            seenNotification(id)
        })
    }

    getNotifications();
    setInterval(getNotifications, 15000);

    getSafetyAlerts();
    setInterval(getSafetyAlerts, 15000);

    document.addEventListener("DOMContentLoaded", function () {
        let checkboxes = document.querySelectorAll(".dynamic-checkbox-toggle");
        checkboxes.forEach(function (checkbox) {
            checkbox.addEventListener("click", function (event) {
                event.preventDefault();
                const checkboxId = checkbox.getAttribute("data-id");
                const imageOn = checkbox.getAttribute("data-image-on");
                const imageOff = checkbox.getAttribute("data-image-off");
                const titleOn = checkbox.getAttribute("data-title-on");
                const titleOff = checkbox.getAttribute("data-title-off");
                const textOn = checkbox.getAttribute("data-text-on");
                const textOff = checkbox.getAttribute("data-text-off");
                const confirmButtonTextON = checkbox.getAttribute("data-button-on") ?? '{{translate('Ok')}}';
                const confirmButtonTextOFF = checkbox.getAttribute("data-button-off") ?? '{{translate('Ok')}}';

                const isChecked = checkbox.checked;

                if (isChecked) {
                    $("#toggle-title").empty().append(titleOn);
                    $("#toggle-message").empty().append(textOn);
                    $("#toggle-image").attr("src", imageOn);
                    $("#toggle-ok-button").html(confirmButtonTextON);
                    $("#toggle-ok-button").attr("toggle-ok-button", checkboxId);
                } else {
                    $("#toggle-title").empty().append(titleOff);
                    $("#toggle-message").empty().append(textOff);
                    $("#toggle-image").attr("src", imageOff);
                    $("#toggle-ok-button").html(confirmButtonTextOFF);
                    $("#toggle-ok-button").attr("toggle-ok-button", checkboxId);
                }

                $("#toggle-modal").modal("show");
            });
        });
    });
    $(document).on("click", ".confirm-Toggle", function () {
        let toggle_id = $("#toggle-ok-button").attr("toggle-ok-button");
        if ($("#" + toggle_id).is(":checked")) {
            $("#" + toggle_id).prop("checked", false);
        } else {
            $("#" + toggle_id).prop("checked", true);
        }
        $("#toggle-modal").modal("hide");
    });
    $("#approvalButtonParcelRefund, .approval-button-parcel-refund").on('click', function () {
        parcelRefundAction(this)
    })
    $("#deniedButtonParcelRefund, .denied-button-parcel-refund").on('click', function () {
        parcelRefundAction(this)
    })
    $("#parcelRefundButton, .parcel-refund-button").on('click', function () {
        parcelMakeRefundAction(this)
    })

    function parcelRefundAction(obj) {

        let url = $(obj).data('url');
        let iconContent = $(obj).data('icon');
        let titleContent = $(obj).data('title');
        let subTitleContent = $(obj).data('sub-title');
        let inputLabelContent = $(obj).data('input-title');
        let confirmBtnContent = $(obj).data('confirm-btn');
        let cancelBtnContent = $(obj).data('cancel-btn');

        // Show custom modal
        const modalElement = document.getElementById('parcelRefundModal');
        let bootstrapModal = new bootstrap.Modal(modalElement);
        bootstrapModal.show();
        if (url) {
            $('#parcelRefundForm').attr('action', '');
            $('#parcelRefundForm').attr('action', url);
        }
        if (iconContent) {
            $("#parcelRefundIcon").attr('src', iconContent);
        }
        if (titleContent) {
            $("#parcelRefundTitle").html("");
            $("#parcelRefundTitle").html(titleContent);
        }
        if (subTitleContent) {
            $("#parcelRefundSubTitle").html("");
            $("#parcelRefundSubTitle").html(subTitleContent);
        }
        if (inputLabelContent) {
            $("#inputLabelTitle").html("");
            $("#inputLabelTitle").html(inputLabelContent);
        }
        if (confirmBtnContent) {
            $("#parcelRefundModalConfirmBtn").html("");
            $("#parcelRefundModalConfirmBtn").html(confirmBtnContent);
        }
        if (cancelBtnContent) {
            $("#parcelRefundModalCancelBtn").html("");
            $("#parcelRefundModalCancelBtn").html(cancelBtnContent);
        }
    }

    function parcelMakeRefundAction(obj) {
        let url = $(obj).data('url');
        let amount = $(obj).data('amount');
        $("#refundAmount").val(amount);
        // Show custom modal
        const modalElement = document.getElementById('parcelMakeRefundModal');
        let bootstrapModal = new bootstrap.Modal(modalElement);
        bootstrapModal.show();
        if (url) {
            $('#parcelMakeRefundForm').attr('action', '');
            $('#parcelMakeRefundForm').attr('action', url);
        }
    }

    $(".approval-button-vehicle-request, .approval-button-vehicle-update").on('click', function () {
        vehicleRequestApprovalAction(this);
    });

    function vehicleRequestApprovalAction(obj) {
        let url = $(obj).data('url');
        let iconContent = $(obj).data('icon');
        let titleContent = $(obj).data('title');
        let subTitleContent = $(obj).data('sub-title');
        let confirmBtnContent = $(obj).data('confirm-btn');
        let cancelBtnContent = $(obj).data('cancel-btn');

        // Show custom modal
        const modalElement = document.getElementById('vehicleRequestApprovalModal');
        let bootstrapModal = new bootstrap.Modal(modalElement);
        bootstrapModal.show();
        if (url) {
            $('#vehicleRequestApprovalForm').attr('action', '');
            $('#vehicleRequestApprovalForm').attr('action', url);
        }
        if (iconContent) {
            $("#vehicleRequestApprovalIcon").attr('src', iconContent);
        }
        if (titleContent) {
            $("#vehicleRequestApprovalTitle").html("");
            $("#vehicleRequestApprovalTitle").html(titleContent);
        }
        if (subTitleContent) {
            $("#vehicleRequestApprovalSubTitle").html("");
            $("#vehicleRequestApprovalSubTitle").html(subTitleContent);
        }
        if (confirmBtnContent) {
            $("#vehicleRequestApprovalModalConfirmBtn").html("");
            $("#vehicleRequestApprovalModalConfirmBtn").html(confirmBtnContent);
        }
        if (cancelBtnContent) {
            $("#vehicleRequestApprovalModalCancelBtn").html("");
            $("#vehicleRequestApprovalModalCancelBtn").html(cancelBtnContent);
        }
    }


    $(".deny-button-vehicle-request").on('click', function () {
        vehicleRequestDenyAction(this);
    });
    $(".deny-button-vehicle-update").on('click', function () {
        vehicleUpdateDenyAction(this);
    });

    function vehicleUpdateDenyAction(obj) {
        let url = $(obj).data('url');
        let iconContent = $(obj).data('icon');
        let titleContent = $(obj).data('title');
        let subTitleContent = $(obj).data('sub-title');
        let confirmBtnContent = $(obj).data('confirm-btn');
        let cancelBtnContent = $(obj).data('cancel-btn');

        // Show custom modal
        const modalElement = document.getElementById('vehicleUpdateDenyModal');
        let bootstrapModal = new bootstrap.Modal(modalElement);
        bootstrapModal.show();
        if (url) {
            $('#vehicleUpdateDenyForm').attr('action', '');
            $('#vehicleUpdateDenyForm').attr('action', url);
        }
        if (iconContent) {
            $("#vehicleUpdateDenyIcon").attr('src', iconContent);
        }
        if (titleContent) {
            $("#vehicleUpdateDenyTitle").html("");
            $("#vehicleUpdateDenyTitle").html(titleContent);
        }
        if (subTitleContent) {
            $("#vehicleUpdateDenySubTitle").html("");
            $("#vehicleUpdateDenySubTitle").html(subTitleContent);
        }
        if (confirmBtnContent) {
            $("#vehicleUpdateDenyModalConfirmBtn").html("");
            $("#vehicleUpdateDenyModalConfirmBtn").html(confirmBtnContent);
        }
        if (cancelBtnContent) {
            $("#vehicleUpdateDenyModalCancelBtn").html("");
            $("#vehicleUpdateDenyModalCancelBtn").html(cancelBtnContent);
        }
    }

    function vehicleRequestDenyAction(obj) {
        let url = $(obj).data('url');
        let iconContent = $(obj).data('icon');
        let titleContent = $(obj).data('title');
        let subTitleContent = $(obj).data('sub-title');
        let confirmBtnContent = $(obj).data('confirm-btn');
        let cancelBtnContent = $(obj).data('cancel-btn');

        // Show custom modal
        const modalElement = document.getElementById('vehicleRequestDenyModal');
        let bootstrapModal = new bootstrap.Modal(modalElement);
        bootstrapModal.show();
        if (url) {
            $('#vehicleRequestDenyForm').attr('action', '');
            $('#vehicleRequestDenyForm').attr('action', url);
        }
        if (iconContent) {
            $("#vehicleRequestDenyIcon").attr('src', iconContent);
        }
        if (titleContent) {
            $("#vehicleRequestDenyTitle").html("");
            $("#vehicleRequestDenyTitle").html(titleContent);
        }
        if (subTitleContent) {
            $("#vehicleRequestDenySubTitle").html("");
            $("#vehicleRequestDenySubTitle").html(subTitleContent);
        }
        if (confirmBtnContent) {
            $("#vehicleRequestDenyModalConfirmBtn").html("");
            $("#vehicleRequestDenyModalConfirmBtn").html(confirmBtnContent);
        }
        if (cancelBtnContent) {
            $("#vehicleRequestDenyModalCancelBtn").html("");
            $("#vehicleRequestDenyModalCancelBtn").html(cancelBtnContent);
        }
    }

    // delete  modal for all
    $(".delete-button").on('click', function () {
        deleteAction(this);
    });
    //deleteAction
    function deleteAction(obj) {
        let url = $(obj).data('url');
        let iconContent = $(obj).data('icon');
        let titleContent = $(obj).data('title');
        let subTitleContent = $(obj).data('sub-title');
        let confirmBtnContent = $(obj).data('confirm-btn');
        let cancelBtnContent = $(obj).data('cancel-btn');
        let buttonPosition = $(obj).data('button-position');

        // Show custom modal
        const modalElement = document.getElementById('deleteModal');
        let bootstrapModal = new bootstrap.Modal(modalElement);
        bootstrapModal.show();
        if (url) {
            $('#deleteForm').attr('action', '');
            $('#deleteForm').attr('action', url);
        }
        if (iconContent) {
            $("#deleteIcon").attr('src', iconContent);
        }
        if (titleContent) {
            $("#deleteTitle").html("");
            $("#deleteTitle").html(titleContent);
        }
        if (subTitleContent) {
            $("#deleteSubTitle").html("");
            $("#deleteSubTitle").html(subTitleContent);
        }
        if (confirmBtnContent) {
            $("#deleteModalConfirmBtn").html("");
            $("#deleteModalConfirmBtn").html(confirmBtnContent);
        }
        if (cancelBtnContent) {
            $("#deleteModalCancelBtn").html("");
            $("#deleteModalCancelBtn").html(cancelBtnContent);
        }
        if (buttonPosition === 'left') {
            $('#deleteModalBtnContainer').addClass('flex-row-reverse');
        } else {
            $('#deleteModalBtnContainer').removeClass('flex-row-reverse');
        }
    }

    $(document).off('click', '.delete-blog-category-button')
        .on('click', '.delete-blog-category-button', function() {
            deleteBlogCategoryAction(this);
        });


    function deleteBlogCategoryAction(obj) {
        let url = $(obj).data('url');
        let iconContent = $(obj).data('icon');
        let titleContent = $(obj).data('title');
        let subTitleContent = $(obj).data('sub-title');
        let confirmBtnContent = $(obj).data('confirm-btn');
        let cancelBtnContent = $(obj).data('cancel-btn');

        // Show custom modal
        const modalElement = document.getElementById('deleteBlogCategoryModal');

        let bootstrapModal = new bootstrap.Modal(modalElement);
        bootstrapModal.show();

        if (iconContent) {
            $("#deleteBlogCategoryModalIcon").attr('src', iconContent);
        }
        if (titleContent) {
            $("#deleteBlogCategoryTitle").html("");
            $("#deleteBlogCategoryTitle").html(titleContent);
        }
        if (subTitleContent) {
            $("#deleteBlogCategorySubTitle").html("");
            $("#deleteBlogCategorySubTitle").html(subTitleContent);
        }
        if (confirmBtnContent) {
            $("#deleteBlogCategoryModalConfirmBtn").html("");
            $("#deleteBlogCategoryModalConfirmBtn").html(confirmBtnContent);
        }
        if (cancelBtnContent) {
            $("#deleteBlogCategoryModalCancelBtn").html("");
            $("#deleteBlogCategoryModalCancelBtn").html(cancelBtnContent);
        }

        $('#deleteBlogCategoryModalConfirmBtn').off('click').on('click', function () {
            $.ajax({
                url: url,
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function (response) {
                    if (response?.success){
                        toastr.success(response.message);
                        $.ajax({
                            url: '{{ route('admin.blog.category.index') }}',
                            type: 'GET',
                            success: function (response) {
                             $('#blog-category-list').empty().html(response.view);
                            },
                            error: function () {
                                toastr.error("{{ translate('something went wrong') }}");
                            }
                        });
                    }
                },
                error: function () {
                    toastr.error("{{ translate('something went wrong') }}");
                }
            });
            bootstrapModal.hide();
        });

        $('#deleteBlogCategoryModalCancelBtn').off('click').on('click', function () {
            bootstrapModal.hide();
        });
    }

    function safetyAlertNotification(data) {
        let checkLaterButton = $('#checkLater');
        let showSafetyAlertUserDetails = $('.show-safety-alert-user-details');
        let response = `${data.type} {{ translate('sent a new Safety Alert for') }}`;
        response = response.charAt(0).toUpperCase() + response.slice(1).toLowerCase();
        let trip = `<b> {{ translate('Trip') }} #${data.trip_reference_id}</b>`
        let fullContent = `${response} ${trip}`;
        $('#safetyAlertNotificationTitle').text(data.body);
        $('#safetyAlertNotificationSubtitle').empty().html(fullContent);
        showSafetyAlertUserDetails.attr('data-user-id', data.sent_by);
        showSafetyAlertUserDetails.attr('href', data.route);
        const modalElement = document.getElementById('safetyAlertNotificationModal');
        let bootstrapModal = new bootstrap.Modal(modalElement, {
            backdrop: 'static',
            keyboard: false,
        });
        if (modalElement.classList.contains('show')) {
            bootstrapModal.hide();
            modalElement.removeEventListener('hidden.bs.modal', onHidden);
        }
        bootstrapModal.show();
        const onHidden = () => {
            modalElement.removeEventListener('hidden.bs.modal', onHidden);
        };
        modalElement.addEventListener('hidden.bs.modal', onHidden);
        showSafetyAlertUserDetails.on('click', function () {
            let $userId = localStorage.getItem('safetyAlertUserId');
            if ($userId != data.sent_by) {
                localStorage.setItem('safetyAlertUserId', data.sent_by);
            }
            localStorage.setItem('safetyAlertUserDetailsStatus', true);
        });
        checkLaterButton.on('click', function () {
            stopAudio();
            bootstrapModal.hide();
            let safetyAlertMapIcon = document.getElementById('safetyAlertMapIcon');
            let newSafetyAlertMapIcon = document.getElementById('newSafetyAlertMapIcon');
            if (safetyAlertMapIcon) {
                safetyAlertMapIcon.classList.remove('d-none');
            }
            if (newSafetyAlertMapIcon) {
                newSafetyAlertMapIcon.classList.add('d-none');
            }
        });
        $('#btnClose').on('click', function () {
            stopAudio();
            bootstrapModal.hide();
        });
    }


</script>
{{--Remove non-numeric characters from the input value  with type="tel" --}}
<script>
    $(".upload-file .edit-btn").on("click", function () {
        $(this).siblings("input[type=file]").click();
    });

    $(".upload-file__input").on("change", function (event) {
        const input = this;
        const file = input.files[0];
        const img = $(input).siblings(".upload-file__img").find("img");

        if (!file) return;

        if (!canAddUploadFiles([file], {form: input.form, excludeInput: input})) {
            input.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = function (e) {
            img.attr("src", e.target.result);
        };
        reader.readAsDataURL(file);
    });

    document.addEventListener('input', function (event) {

        if (event.target.tagName === 'INPUT' && event.target.type === 'tel') {
            validateNumbers(event.target);
        }
    });

    function validateNumbers(input) {
        input.value = input.value.replace(/\D/g, '');
    }

    // Function to get the file icon
    function getFileIcon(fileName) {
        const asset = "{{ dynamicAsset('public/assets/admin-module/img/file-format/svg') }}";
        const extension = fileName.split('.').pop().toLowerCase();
        switch (extension) {
            case 'pdf':
                return `${asset}/pdf.svg`;
            case 'cvc':
                return `${asset}/cvc.svg`;
            case 'csv':
                return `${asset}/csv.svg`;
            case 'doc':
            case 'docx':
                return `${asset}/doc.svg`;
            case 'jpg':
                return `${asset}/jpg.svg`;
            case 'jpeg':
                return `${asset}/jpeg.svg`;
            case 'webp':
                return `${asset}/webp.svg`;
            case 'png':
                return `${asset}/png.svg`;
            case 'xls':
                return `${asset}/xls.svg`;
            case 'xlsx':
                return `${asset}/xlsx.svg`;
            default:
                return "{{ dynamicAsset('public/assets/admin-module/img/document-upload.png') }}";
        }
    }

    $("body").on("click", ".file__value", function () {
        $(this).remove();
    });

    /*============================================
12: Multiple file upload
==============================================*/

    let newSelectedFiles = [];
    const uploadInput = document.querySelector(".upload-file__input2");
    if (uploadInput) {
        // Event listener to handle file selection
        document.querySelector(".upload-file__input2").addEventListener("change", function (event) {
            const files = Array.from(event.target.files);
            const acceptedFiles = [];

            files.forEach(file => {
                if (!canAddUploadFiles([...acceptedFiles, file], {form: event.target.form})) {
                    return;
                }

                acceptedFiles.push(file);
                newSelectedFiles.push(file);
            });

            newDisplaySelectedFiles();
            totalSize = getUploadPayloadSize({form: event.target.form});
        });
    }

    // Function to display selected files
    function newDisplaySelectedFiles() {
        const container1 = document.getElementById("input-data");
        container1.innerHTML = "";
        newSelectedFiles.forEach((file, index) => {
            const input = document.createElement("input");
            input.type = "file";
            input.name = `other_documents[${index}]`;
            input.classList.add(`file-index${index}`);
            input.hidden = true;
            container1.appendChild(input);
            const blob = new Blob([file], {type: file.type});
            const file_obj = new File([file], file.name);
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file_obj);
            input.files = dataTransfer.files;
        });
        const container = document.getElementById("selected-files-container1");
        container.innerHTML = ""; // Clear existing content
        container.classList.toggle("d-none", newSelectedFiles.length === 0);

        newSelectedFiles.forEach((file, index) => {
            // Create a new div for the file display
            const fileDiv = document.createElement("div");
            fileDiv.classList.add("show-image");

            // Create the file value container
            const fileValueDiv = document.createElement("div");
            fileValueDiv.classList.add("file__value", "bg-transparent", "border", "border-C5D2D2", "remove_outside");
            fileValueDiv.setAttribute("data-document", file.name);

            // Create the icon for the file (this assumes PDF files for now)
            const fileIcon = document.createElement("img");
            fileIcon.classList.add("file__value--icon");
            fileIcon.src = getFileIcon(file.name); // Set the icon based on file type

            // Create the text displaying the file name
            const fileText = document.createElement("div");
            fileText.classList.add("file__value--text");
            fileText.textContent = file.name;

            // Create the remove button
            const removeButton = document.createElement("div");
            removeButton.classList.add("file__value--remove", "fw-bold");
            removeButton.setAttribute("data-id", file.name);
            removeButton.innerHTML = `<img src="{{ dynamicAsset('public/assets/admin-module/img/icons/close-circle.svg') }}" alt="">`;

            // Append everything to the file value div
            fileValueDiv.appendChild(fileIcon);
            fileValueDiv.appendChild(fileText);
            fileValueDiv.appendChild(removeButton);

            // Add the file value div to the container
            fileDiv.appendChild(fileValueDiv);
            container.appendChild(fileDiv);

            removeButton.addEventListener("click", function () {
                fileDiv.remove();
                newSelectedFiles.splice(newSelectedFiles.indexOf(file), 1);
                newDisplaySelectedFiles();
                totalSize = getUploadPayloadSize({form: container1.closest('form')});
            });
        });
    }

    submitByAjax('.submit-by-ajax', {
        hasEditors: false,
        redirectUrl: $('.global-data').data('dashboard-route')
    });

    function submitByAjax(formSelector, options = {}) {
        $(document).on('submit', formSelector, function (e) {
            e.preventDefault();
            let $form = $(this);
            let $btn = $(e.originalEvent.submitter);
            $btn.addClass('disabled').prop('disabled', true);

            $form.find('.ajax-generated-error').remove();
            $form.find('.error-text').text('');
            $form.find('input, textarea, select, .select2-selection').removeClass('is-invalid');
            $form.find('.note-editor').removeClass('is-invalid');
            $form.find('input[type="date"]').css('background-image', '');

            if (options.hasEditors) {
                let editor = document.querySelector('#editor');
                $("#hiddenArea").val(editor.children[0].innerHTML);
            }

            $form.find('.summernote').each(function () {
                if (typeof $(this).summernote === 'function') {
                    $(this).val($(this).summernote('code'));
                }
            });

            let formData = new FormData(this);
            if ($btn.attr('name')) {
                formData.append($btn.attr('name'), $btn.val() || '');
            }
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            $.post({
                url: $form.attr('action'),
                data: formData,
                cache: false,
                contentType: false,
                processData: false,
                success: function (data) {
                    if (data?.errors) {
                        handleAjaxFormErrors($form, data.errors);
                    } else {
                        sessionStorage.setItem('formSubmittedSuccessfully', data.successMessage ?? $('.global-data').data('data-on-ajax-form-submit-successful'));
                        location.href = data.redirectUrl ?? options.redirectUrl;
                    }
                },
                error: function (xhr) {
                    if (xhr.responseJSON?.errors) {
                        handleAjaxFormErrors($form, xhr.responseJSON.errors);
                    } else {
                        toastr.error(xhr.responseJSON?.message || 'Something went wrong! Please try again after some times.', {
                            CloseButton: true,
                            ProgressBar: true,
                        });
                    }
                    $btn.removeClass('disabled').prop('disabled', false);
                },
                complete: function () {
                    $btn.removeClass('disabled').prop('disabled', false);
                }
            });
        });

        function handleAjaxFormErrors($form, errors) {
            let firstErrorElement = null;
            $.each(normalizeAjaxFormErrors(errors), function (field, error) {
                let $input = findAjaxFormInput($form, error.error_code);
                let $errorText = findAjaxFormErrorText($form, error.error_code, $input);

                if ($errorText.length) {
                    $errorText.text(error.message);
                }

                if ($input.length && $input.hasClass('summernote')) {
                    $input.next('.note-editor').addClass('is-invalid');
                } else if ($input.length) {
                    $input.addClass('is-invalid');
                    if ($input.attr('type') === 'date') {
                        $input.css('background-image', 'none');
                    }
                    if ($input.hasClass('js-select2-custom') || $input.hasClass('js-select') || $input.hasClass('js-select-multiple')) {
                        $input.next('.select2-container').find('.select2-selection').addClass('is-invalid');
                    }
                }

                if (!$errorText.length && !$input.length) {
                    toastr.error(error.message);
                }

                if (!firstErrorElement) {
                    firstErrorElement = $input.length ? $input : $errorText;
                }
            });

            if (firstErrorElement && firstErrorElement.length) {
                firstErrorElement[0].scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }
        }

        function normalizeAjaxFormErrors(errors) {
            if (Array.isArray(errors)) {
                return errors.map(function (error) {
                    return {
                        error_code: error.error_code,
                        message: Array.isArray(error.message) ? error.message[0] : error.message
                    };
                });
            }

            return Object.keys(errors || {}).map(function (field) {
                return {
                    error_code: field,
                    message: Array.isArray(errors[field]) ? errors[field][0] : errors[field]
                };
            });
        }

        function findAjaxFormErrorText($form, errorCode, $input) {
            let $errorText = $form.find('.error-text').filter(function () {
                return $(this).data('error') === errorCode || $(this).attr('data-error') === errorCode;
            }).first();

            if ($errorText.length || !$input.length) {
                return $errorText;
            }

            $errorText = $('<span class="error-text text-danger fs-12 d-block mt-1 ajax-generated-error"></span>').attr('data-error', errorCode);
            if ($input.hasClass('summernote')) {
                let $editor = $input.next('.note-editor');
                if ($editor.length) {
                    $editor.after($errorText);
                    return $errorText;
                }
            }

            let $target = getAjaxFormErrorTarget($input);

            if ($target.length) {
                $target.append($errorText);
                return $errorText;
            }

            $target = $input;
            if ($input.hasClass('js-select2-custom') || $input.hasClass('js-select') || $input.hasClass('js-select-multiple')) {
                $target = $input.next('.select2-container');
            } else if ($input.attr('type') === 'file') {
                $target = $input.closest('.upload-file');
            }

            $target.after($errorText);
            return $errorText;
        }

        function getAjaxFormErrorTarget($input) {
            if ($input.attr('type') === 'file') {
                let $fileWrapper = $input.closest('.additional-data-file-upload');
                if ($fileWrapper.length) {
                    return $fileWrapper;
                }
            }

            let $fieldWrapper = $input.closest('.mb-4');
            if ($fieldWrapper.length) {
                return $fieldWrapper;
            }

            return $();
        }

        function findAjaxFormInput($form, errorCode) {
            let fieldNames = ajaxFormFieldNameCandidates(errorCode);
            let $input = $form.find('input, textarea, select').filter(function () {
                return fieldNames.includes($(this).attr('name'));
            }).first();

            if ($input.length && $input.attr('type') === 'hidden') {
                let $visibleInput = $input.closest('.mb-4, .form-group, .d-flex, .col-md-4, .col-sm-6, .col-12')
                    .find('input:not([type="hidden"]), textarea, select')
                    .first();
                if ($visibleInput.length) {
                    return $visibleInput;
                }
            }

            return $input;
        }

        function ajaxFormFieldNameCandidates(errorCode) {
            let fieldNames = [errorCode];
            let parts = String(errorCode).split('.');
            if (parts.length > 1) {
                let bracketName = parts[0] + parts.slice(1).map(function (part) {
                    return '[' + part + ']';
                }).join('');
                fieldNames.push(bracketName);
                fieldNames.push(bracketName + '[]');

                if (/^\d+$/.test(parts[parts.length - 1])) {
                    let arrayParts = parts.slice(0, -1);
                    fieldNames.push(arrayParts[0] + arrayParts.slice(1).map(function (part) {
                        return '[' + part + ']';
                    }).join('') + '[]');
                }
            }

            return [...new Set(fieldNames)];
        }
    }
</script>


@stack('script')
@stack('script2')

</body>

</html>
