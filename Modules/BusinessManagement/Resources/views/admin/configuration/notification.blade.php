@extends('adminmodule::layouts.master')

@section('title', translate('notification'))

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-22 mb-4 text-capitalize">{{ translate('notifications') }}</h2>

            <div class="mb-4">
                <ul class="nav d-inline-flex nav--tabs p-1 rounded bg-white">
                    <li class="nav-item">
                        <a href="{{route('admin.business.configuration.notification.index')}}"
                           class="nav-link text-capitalize {{Request::is('admin/business/configuration/notification')? "active":""}}">{{ translate('notification_message') }}</a>
                    </li>
                    <li class="nav-item">
                        <a href="{{route('admin.business.configuration.notification.firebase-configuration')}}"
                           class="nav-link text-capitalize {{Request::is('admin/business/configuration/notification/firebase-configuration')? "active":""}}">{{ translate('firebase_configuration') }}</a>
                    </li>
                </ul>
            </div>

            @include('businessmanagement::admin.configuration.partials._notification_inline_menu')

            <div class="card border-0 mb-3">
                <div class="card-header">
                    <h5 class="text-capitalize mb-2">{{ translate('Business Pages Update Notification Setup') }}</h5>
                    <div
                        class="fs-12">{{ translate('Here you can set sending notification On/Off based on the case.') }}</div>
                </div>

                <div class="card-body p-30">
                    <div class="row gap-3 gap-md-0">
                        @foreach ($notificationSettings as $notification)
                            <div class="col-md-4">
                                <div class="form-control gap-2 align-items-center d-flex justify-content-between">
                                    <div class="d-flex align-items-center fw-medium gap-2">
                                        {{ translate($notification->name) }}
                                        @if($notification->name == 'privacy_policy')
                                            <i class="bi bi-info-circle-fill text-primary tooltip-icon"
                                               data-bs-toggle="tooltip"
                                               data-bs-title="{{translate("Allow notifications to be sent to users when the privacy policy is updated")}}"></i>
                                        @elseif($notification->name == 'terms_and_conditions')
                                            <i class="bi bi-info-circle-fill text-primary tooltip-icon"
                                               data-bs-toggle="tooltip"
                                               data-bs-title="{{translate("Allow notifications to be sent to users when the Terms & Conditions is updated")}}"></i>
                                        @elseif($notification->name == 'legal')
                                            <i class="bi bi-info-circle-fill text-primary tooltip-icon"
                                               data-bs-toggle="tooltip"
                                               data-bs-title="{{translate("Allow notifications to be sent to users when the Legals is updated")}}"></i>
                                        @endif

                                    </div>
                                    <div class="position-relative">
                                        <label class="switcher">
                                            <input class="switcher_input " data-type="push"
                                                   data-id="{{ $notification->id }}"
                                                   type="checkbox" {{ $notification->push == 1 ? 'checked' : '' }}>
                                            <span class="switcher_control"></span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>


            <div class="card border-0">
                <div class="card-header">
                    <h5 class="text-capitalize mb-2">{{ translate('push_notification_setup') }}</h5>
                    <div
                        class="fs-12">{{ translate('Here you can set sending notification On/Off based on the case.') }}</div>
                </div>

                <div class="card-body p-30">
                    <form action="{{ route('admin.business.configuration.notification.push-store') }}" method="post"
                          id="notification_setup_form">
                        @csrf
                        <div class="row">
                            @forelse($notifications as $notification)
                                <div class="col-lg-6">
                                    <div class="mb-30">
                                        <div class="d-flex justify-content-between gap-3 align-items-center mb-3">
                                            <label for="trip_req_message"
                                                   class="light-text">{{ translate($notification['name']) }}
                                                @if(in_array($notification['name'] , ['privacy_policy_updated','terms_and_conditions_updated', 'legal_updated']))
                                                    <i class="bi bi-info-circle-fill text-primary tooltip-icon"
                                                       data-bs-toggle="tooltip"
                                                       data-bs-title="{{translate("If the above notification sending option is enabled, these messages will be sent to the customer & driver")}}"></i>
                                                @endif
                                            </label>

                                            @if(!in_array($notification['name'],['privacy_policy_updated','terms_and_conditions_updated','legal_updated']))
                                                <label class="switcher">
                                                    <input class="switcher_input" type="checkbox"
                                                           name="notification[{{ $notification->name }}][status]" {{$notification?->status ==1 ? "checked" : ""}}>
                                                    <span class="switcher_control"></span>
                                                </label>
                                            @endif
                                        </div>
                                        <textarea name="notification[{{ $notification->name }}][value]"
                                                  id="trip_req_message" rows="4"
                                                  class="form-control color-border-focus {{ $notification?->value ? 'color-border' : '' }} fw-medium"
                                                  placeholder="Type Here ...">{{ $notification?->value }}</textarea>
                                    </div>
                                </div>
                            @empty
                            @endforelse
                        </div>
                        <div class="d-flex justify-content-end">
                            <button class="btn btn-primary cmn_focus" type="submit">{{ translate('submit') }}</button>
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
    </script>
@endpush
