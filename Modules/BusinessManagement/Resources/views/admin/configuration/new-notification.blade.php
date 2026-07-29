@extends('adminmodule::layouts.master')

@section('title', translate('notification'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ dynamicAsset('public/assets/admin-module/plugins/swiper@11/swiper-bundle.min.css') }}"/>
@endpush

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <form action="{{ route('admin.business.configuration.notification.push-store', $type) }}" method="POST"
              id="notification_setup_form">
            @csrf
            <div class="container-fluid">
                <h2 class="fs-22 mb-4 text-capitalize">{{ translate('notifications') }}</h2>
                <div class="mb-4 overflow-x-auto">
                    <ul class="nav nav--tabs_two">
                        <li class="nav-item">
                            <a href="{{route('admin.business.configuration.notification.index', ['type' => 'schedule-trip'])}}"
                               class="nav-link text-capitalize {{Request::is('admin/business/configuration/notification/*') && !Request::is('admin/business/configuration/notification/firebase-configuration') ? "active":""}}">{{ translate('notification_message') }}</a>
                        </li>
                        <li class="nav-item">
                            <a href="{{route('admin.business.configuration.notification.firebase-configuration')}}"
                               class="nav-link text-capitalize {{Request::is('admin/business/configuration/notification/firebase-configuration') ? "active":""}}">{{ translate('firebase_configuration') }}</a>
                        </li>
                    </ul>
                </div>
                @include('businessmanagement::admin.configuration.partials._notification_inline_menu')
                <div class="firebase-push-notifications">
                    @include('businessmanagement::admin.configuration.partials._firebase-notification-fields')
                </div>
            </div>
            <div class="footer-sticky">
                <div class="container-fluid">
                    <div class="d-flex justify-content-end py-4">
                        <button type="button" class="btn btn-primary text-capitalize submit-notifications" tabindex="2">Submit
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <!-- End Main Content -->

    {{--Read Instruction for push notification Modal with slider Start--}}
    <div class="modal fade" id="ReadInstructionSliderModal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header px-4 border-0">
                    <h6 class="d-flex gap-3 align-items-center flex-grow-1">
                        <i class="bi bi-exclamation-circle-fill text-danger"></i>
                        {{translate("Notification Message ")}}
                    </h6>
                    <button type="button" class="btn-close" data-bs-toggle="modal">
                    </button>
                </div>
                <div class="modal-body px-0 pt-0 pb-4">
                    <div class="swiper instruction-carousel instruction-carousel_new">
                        <div class="swiper-wrapper">
                            <div class="swiper-slide px-4">
                                <div class="bg-white border rounded p-3">
                                    <div class="mb-3">
                                        <h6 class="mb-3">{{translate("Scheduled Trip")}}</h6>
                                        <p class="fs-12">{{translate("Setup notifications for the scheduled trip status updates to customers & drivers")}}. {{translate("Here are some examples of placeholders you can use")}}:</p>
                                    </div>
                                    <div class="bg-F6F6F6 p-3 rounded fs-12 title-color">
                                        <div class="d-flex flex-column gap-3">
                                            <div><b>{tripId} =</b> {{ translate("The unique identifier for the trip") }}.</div>
                                            <div><b>{vehicleCategory} =</b> {{ translate("The type or category of vehicle assigned to the trip") }}.</div>
                                            <div><b>{pickUpLocation} =</b> {{ translate("The pickup location where the trip starts") }}.</div>
                                            <div><b>{dropOffLocation} =</b> {{ translate("The drop-off location where the trip ends") }}.</div>
                                            <div><b>{sentTime} =</b> {{ translate("The timestamp when the notification or action occurred") }}.</div>
                                            <div><b>{paidAmount} =</b> {{ translate("The total amount paid by the customer") }}.</div>
                                            <div><b>{methodName} =</b> {{ translate("The payment method used (e.g., credit card, PayPal)") }}.</div>
                                            <div><b>{approximateAmount} =</b> {{ translate("The estimated fare for the scheduled trip") }}.</div>
                                            <div><b>{tipsAmount} =</b> {{ translate("The total amount of tips paid by the customer") }}.</div>
                                            <div><b>{customerName} =</b> {{ translate("The full name of the customer who booked the trip") }}.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="swiper-slide px-4">
                                <div class="bg-white border rounded p-3">
                                    <div class="mb-3">
                                        <h6 class="mb-3">{{translate("Regular Trips")}}</h6>
                                        <p class="fs-12">{{translate("Setup notifications for the regular trip status updates to customers & drivers")}}. {{translate("Here are some examples of placeholders you can use")}}:</p>
                                    </div>
                                    <div class="bg-F6F6F6 p-3 rounded fs-12 title-color">
                                        <div class="d-flex flex-column gap-3">
                                            <div><b>{tripId} =</b> {{ translate("The unique identifier for the trip") }}.</div>
                                            <div><b>{vehicleCategory} =</b> {{ translate("The type or category of vehicle assigned to the trip") }}.</div>
                                            <div><b>{pickUpLocation} =</b> {{ translate("The pickup location where the trip starts") }}.</div>
                                            <div><b>{dropOffLocation} =</b> {{ translate("The drop-off location where the trip ends") }}.</div>
                                            <div><b>{sentTime} =</b> {{ translate("The timestamp when the notification or action occurred") }}.</div>
                                            <div><b>{paidAmount} =</b> {{ translate("The total amount paid by the customer") }}.</div>
                                            <div><b>{methodName} =</b> {{ translate("The payment method used (e.g., credit card, PayPal)") }}.</div>
                                            <div><b>{approximateAmount} =</b> {{ translate("The estimated fare for the scheduled trip") }}.</div>
                                            <div><b>{tipsAmount} =</b> {{ translate("The total amount of tips paid by the customer") }}.</div>
                                            <div><b>{customerName} =</b> {{ translate("The full name of the customer who booked the trip") }}.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="swiper-slide px-4">
                                <div class="bg-white border rounded p-3">
                                    <div class="mb-3">
                                        <h6 class="mb-3">{{translate("parcel")}}</h6>
                                        <p class="fs-12">{{translate("Setup notifications for the parcel status updates to customers & drivers")}}. {{translate("Here are some examples of placeholders you can use")}}:</p>
                                    </div>
                                    <div class="bg-F6F6F6 p-3 rounded fs-12 title-color">
                                        <div class="d-flex flex-column gap-3">
                                            <div><b>{parcelId} =</b> {{ translate("The unique identifier for the parcel request") }}.</div>
                                            <div><b>{sentTime} =</b> {{ translate("The timestamp when the notification or event occurred") }}.</div>
                                            <div><b>{customerName} =</b> {{ translate("The full name of the customer") }}.</div>
                                            <div><b>{otp} =</b> {{ translate("The One-Time Password used for parcel return verification") }}.</div>
                                            <div><b>{approximateAmount} =</b> {{ translate("The estimated or refund amount related to the parcel") }}.</div>
                                            <div><b>{dropOffLocation} =</b> {{ translate("The location where the parcel will be delivered") }}.</div>
                                            <div><b>{pickUpLocation} =</b> {{ translate("The location where the parcel will be picked up") }}.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="swiper-slide px-4">
                                <div class="bg-white border rounded p-3">
                                    <div class="mb-3">
                                        <h6 class="mb-3">{{translate("Driver Registration")}}</h6>
                                        <p class="fs-12">{{translate("Setup notifications for the driver registration to drivers")}}. {{translate("Here are some examples of placeholders you can use")}}:</p>
                                    </div>
                                    <div class="bg-F6F6F6 p-3 rounded fs-12 title-color">
                                        <div class="d-flex flex-column gap-3">
                                            <div><b>{userName} =</b> {{ translate("The name of the driver or user associated with the notification") }}.</div>
                                            <div><b>{sentTime} =</b> {{ translate("The timestamp when the notification or event occurred") }}.</div>
                                            <div><b>{vehicleCategory} =</b> {{ translate("The category or type of vehicle associated with the user or trip") }}.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="swiper-slide px-4">
                                <div class="bg-white border rounded p-3">
                                    <div class="mb-3">
                                        <h6 class="mb-3">{{translate("others")}}</h6>
                                        <p class="fs-12">{{translate("Set up notifications to keep users informed and engaged across all features")}}. {{translate("Here are some examples of placeholders you can use")}}:</p>
                                    </div>
                                    <div class="bg-F6F6F6 p-3 rounded fs-12 title-color">
                                        <div class="d-flex flex-column gap-3">
                                            <div><b>{userName} =</b> {{ translate("The name of the customer, driver, or admin who triggered the notification") }}</div>
                                            <div><b>{referralRewardAmount} =</b> {{ translate("The reward amount received from a referral") }}</div>
                                            <div><b>{sentTime} =</b> {{ translate("The timestamp when the notification or message was sent") }}</div>
                                            <div><b>{levelName} =</b> {{ translate("The name of the level achieved in the system") }}</div>
                                            <div><b>{walletAmount} =</b> {{ translate("The current wallet balance shown to the user") }}</div>
                                            <div><b>{withdrawNote} =</b> {{ translate("The note explaining the reason for withdrawal request rejection") }}</div>
                                            <div><b>{businessName} =</b> {{ translate("The registered name of the business or application") }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="swiper-button-next hover_show"></div>
                        <div class="swiper-button-prev hover_show"></div>
                    </div>

                    <div class="pt-3">
                        <div class="bottom_arrow">
                            <div class="swiper-button-prev"></div>
                            <div class="instruction-pagination-custom_new"></div>
                            <div class="swiper-button-next"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    {{--Read Instruction Modal with slider End--}}
@endsection

@push('script')
    <script src="{{ dynamicAsset('public/assets/admin-module/plugins/swiper@11/swiper-bundle.min.js') }}"></script>
    <script>
        "use strict";

        let permission = false;
        @can('business_edit')
            permission = true;
        @endcan

        $('#notification_setup_form').on('submit', function (e) {
            if (!permission) {
                toastr.error('{{ translate('you_do_not_have_enough_permission_to_update_this_settings') }}');
                e.preventDefault();
            }
        });

        $('#server_key_form').on('submit', function (e) {
            if (!permission) {
                toastr.error('{{ translate('you_do_not_have_enough_permission_to_update_this_settings') }}');
                e.preventDefault();
            }
        });

        $('.switcher_input').on('click', function () {
            if ($(this).attr('data-type') == 'push') {
                updateSettings(this)
            }
        })

        function updateSettings(obj) {
            $.ajax({
                url: '{{ route('admin.business.configuration.notification.notification-settings') }}',
                _method: 'PUT',
                data: {
                    id: $(obj).data('id'),
                    type: $(obj).data('type'),
                    status: ($(obj).prop("checked")) === true ? 1 : 0
                },
                beforeSend: function () {
                    $('.preloader').removeClass('d-none');
                },
                success: function (d) {
                    $('.preloader').addClass('d-none');
                    toastr.success("{{ translate('status_successfully_changed') }}");
                },
                error: function () {
                    $('.preloader').addClass('d-none');
                    toastr.error("{{ translate('status_change_failed') }}");

                }
            });
        }

        $(document).ready(function () {
            //----- sticky footer
            $(window).on('scroll', function () {
                const $footer = $('.footer-sticky');
                const scrollPosition = $(window).scrollTop() + $(window).height();
                const documentHeight = $(document).height();

                if (scrollPosition >= documentHeight - 5) {
                    $footer.addClass('no-shadow');
                } else {
                    $footer.removeClass('no-shadow');
                }
            });
        });

        $(".read-instruction").on('click', function () {
            const modalElement = document.getElementById('ReadInstructionSliderModal');
            let bootstrapModal = new bootstrap.Modal(modalElement);
            bootstrapModal.show();
        });

        $(".submit-notifications").on('click', function (e) {
            e.preventDefault();
            let form = $('#notification_setup_form');
            let formData = form.serialize();
            $.ajax({
                url: form.attr('action'),
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function (response) {

                    if(response?.errors) {
                        for (let index = 0; index < response.errors.length; index++) {
                            setTimeout(() => {
                                toastr.error(response.errors[index].message);
                            }, index * 1000);
                        }
                        $.ajax({
                            url: '{{ route('admin.business.configuration.notification.firebase-push-notifications-fields', $type) }}',
                            type: 'GET',
                            success: function (response) {
                                $('.firebase-push-notifications').empty().html(response);
                                //initialize tooltip
                                $('[data-bs-toggle="tooltip"]').tooltip();
                            },
                            error: function () {
                                toastr.error('Something went wrong');
                                setTimeout(function () {
                                    location.reload();
                                }, 2000);
                            }
                        })
                    } else {
                        toastr.success(response.success);
                        $('.firebase-push-notifications').empty().html(response.view);
                        $('[data-bs-toggle="tooltip"]').tooltip();
                    }
                },
            })
        });

        $(window).on("load", function () {
            if ($(".instruction-carousel_new").length) {
                let slideCount = $(".instruction-carousel_new .swiper-slide").length;
                let swiperPaginationCustom = $('.instruction-pagination-custom_new');

                swiperPaginationCustom.html(`<span class="active">1</span> / ${slideCount}`);

                const swiper = new Swiper(".instruction-carousel_new", {
                    direction: "horizontal",
                    autoHeight: true,
                    pagination: {
                        el: ".instruction-pagination",
                        clickable: true,
                    },
                    navigation: {
                        nextEl: [
                            document.querySelector(".instruction-carousel_new .swiper-button-next"),
                            document.querySelector(".bottom_arrow .swiper-button-next")
                        ],
                        prevEl: [
                            document.querySelector(".instruction-carousel_new .swiper-button-prev"),
                            document.querySelector(".bottom_arrow .swiper-button-prev")
                        ]
                    },
                    on: {
                        slideChange: () => {
                            swiperPaginationCustom.html(
                                `<span class="active">${swiper.realIndex + 1}</span> / ${swiper.slidesGrid.length}`
                            );
                        },
                    }
                });
            }
        });



    </script>
@endpush
