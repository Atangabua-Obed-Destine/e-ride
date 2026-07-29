@extends('adminmodule::layouts.master')

@section('title', translate('Parcel Refund Settings'))

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-22 mb-4 text-capitalize">{{ translate('business_management') }}</h2>

            <div class="mb-3">
                <div class="">
                    @include('businessmanagement::admin.business-setup.partials._business-setup-inline')
                </div>
            </div>
            <div class="card mb-3">
                <form action="{{route('admin.business.setup.parcel-refund.store')."?type=".PARCEL_SETTINGS}}"
                      id="parcel_form"
                      method="POST">
                    @csrf
                    <div class="collapsible-card-body">
                        <div class="card-header">
                            <div class="flex-sm-nowrap flex-wrap d-flex align-items-center justify-content-between gap-3">
                                <div class="w-0 flex-grow-1">
                                    <h5 class="mb-2 text-capitalize">{{ translate('Parcel_Refund_Request') }}</h5>
                                    <div class="fs-14">
                                        {{ translate('Allow customers to request a refund for parcel deliveries after completed the trip.') }}
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <a href="javascript:"
                                    class="text-info fw-semibold fs-12 text-nowrap d-flex view-btn">
                                        <span class="text-underline">{{ translate('View') }}</span>
                                        <span><i class="tio-arrow-downward"></i> </span>
                                    </a>
                                    <label class="switcher cmn_focus rounded-pill">
                                        <input class="switcher_input collapsible-card-switcher update-business-setting"
                                                id="parcelRefundStatus" tabindex="1"
                                                type="checkbox"
                                                name="parcel_refund_status"
                                                data-name="parcel_refund_status"
                                                data-type="{{PARCEL_SETTINGS}}"
                                                data-url="{{route('admin.business.setup.update-business-setting')}}"
                                                data-icon=" {{dynamicAsset('public/assets/admin-module/img/parcel_tracking.png')}}"
                                                data-title="{{translate('Are you sure')}}?"
                                                data-sub-title="{{($settings->firstWhere('key_name', 'parcel_refund_status')->value?? 0) == 1 ? translate('Do you want to turn OFF Parcel Refund for customer')."? " .translate("When it’s off the customer will not be able to  refund any parcel.") : translate('Do you want to turn ON Parcel Refund for customer')."? ". translate('When it’s off the customer will be able to  refund any parcel.')}}"
                                                data-confirm-btn="{{($settings->firstWhere('key_name', 'parcel_refund_status')->value?? 0) == 1 ? translate('Turn Off') : translate('Turn On')}}"
                                            {{($settings->firstWhere('key_name', 'parcel_refund_status')->value?? 0) == 1? 'checked' : ''}}
                                        >
                                        <span class="switcher_control"></span>
                                    </label>
                                </div>
                            </div>
                            <span class="mt-3 bg-warning rounded bg-warning-10 d-flex align-items-centre gap-2 py-2 px-3">
                                <i class="bi bi-info-circle-fill text-warning mt-1"></i> {{ translate('When this option is enable, Customer can give a refund request if parcel has any issue.') }}
                            </span>
                        </div>
                        <div class="card-body collapsible-card-content">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <h6 class="mb-2 text-capitalize">{{ translate('Validity_Setup') }}</h6>
                                    <div class="fs-14">
                                        {{ translate('Here you can set the time period during which customers can request a refund for their parcel after completing an order') }}
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="p-4 rounded bg-F6F6F6">
                                        <div class="mt-2">
                                            <label for="refundRequestValidity"
                                                   class="form-label">{{ translate('Refund_Request_Validity') }}
                                                <span class="text-danger">*</span>
                                                <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                   data-bs-toggle="tooltip"
                                                   data-bs-title="{{ translate('Set the time limit (in hours or days) within which customers can request a refund for a parcel order.') }}"></i>
                                            </label>
                                            <div class="input-group input--group">
                                                <input type="number" name="parcel_refund_validity" tabindex="2"
                                                       id="parcelRefundValidity" step="1" min="1" max="99999999"
                                                       class="form-control"
                                                       value="{{$settings->firstWhere('key_name', 'parcel_refund_validity')?->value ?? old('parcel_refund_validity')}}"
                                                       placeholder="Ex : 2">
                                                <select class="form-select" name="parcel_refund_validity_type" tabindex="3">
                                                    <option value="day"
                                                        {{ $settings->firstWhere('key_name', 'parcel_refund_validity_type')?->value == 'day' ? 'selected' : '' }}>
                                                        {{ translate('Day') }}</option>
                                                    <option value="hour"
                                                        {{ $settings->firstWhere('key_name', 'parcel_refund_validity_type')?->value == 'hour' ? 'selected' : '' }}>
                                                        {{ translate('Hour') }}</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end mt-4 gap-3">
                                <button
                                        class="btn btn-secondary h-40px min-w-100px justify-content-center"
                                        type="reset" tabindex="6">{{translate('Reset')}}</button>
                                <button type="submit"
                                        class="btn btn-primary text-uppercase btn-lg" tabindex="4">{{ translate('save') }}</button>
                            </div>
                        </div>

                    </div>
                </form>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <h5 class="mb-2">{{ translate('Parcel_Refund_Reasons') }}</h5>
                            <div class="fs-14">
                                {{ translate('Here you can set the reason that can be chosen by the customer when they are submitting a refund request') }}
                            </div>
                        </div>
                        <div class="col-md-12">
                            <form action="{{ route('admin.business.setup.parcel-refund.reason.store') }}"
                                  method="post">
                                @csrf
                                <div class="col-md-12">
                                    <div class="p-4 rounded bg-F6F6F6">
                                        <div class="mt-2">
                                            <label for=""
                                                   class="form-label">{{ translate('Parcel_Refund_Reasons') }}
                                                <span class="text-danger">*</span>
                                                <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                                   data-bs-toggle="tooltip"
                                                   data-bs-title="{{ translate('Define refund reasons that customers can select when submitting refund requests') }}"></i>
                                            </label>
                                            <div class="character-count">
                                                <textarea id="title" name="title" tabindex="5"
                                                          class="form-control character-count-field" cols="30" rows="1"
                                                          placeholder="{{ translate('Type your refund reason') }}"
                                                          maxlength="200" data-max-character="200" required></textarea>
                                                <span class="d-flex justify-content-end fw-medium mt-1 text-muted">{{translate('0/200')}}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="d-flex gap-3 justify-content-end mt-4">
                                        <button
                                            class="btn btn-secondary h-40px min-w-100px justify-content-center"
                                            type="reset" tabindex="6">{{translate('Reset')}}</button>
                                        <button
                                            class="btn btn-primary h-40px min-w-100px justify-content-center" type="submit" tabindex="7">{{translate('Submit')}}</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="border-top pt-4 mt-1 px-lg-4 px-3">
                    <div class="mb-20 border-0 d-flex flex-wrap gap-3 justify-content-between align-items-center">
                        <h5 class="d-flex align-items-center gap-2 m-0">
                            <i class="bi bi-person-fill-gear"></i>
                            {{ translate('Parcel Refund Reason List') }}
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-borderless align-middle">
                                <thead class="table-light align-middle">
                                <tr>
                                    <th class="text-center sl">{{translate('SL')}}</th>
                                    <th class=" text-capitalize">{{translate('Reason')}}</th>
                                    <th class="text-center text-capitalize">{{translate('Status')}}</th>
                                    <th class="text-center action">{{translate('Action')}}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($parcelRefundReasons as $key => $parcelRefundReason)
                                    <tr>
                                        <td class="text-center sl">{{ $key + $parcelRefundReasons->firstItem() }}</td>
                                        <td class="">
                                            <p class="max-w-450 min-w-200 m-1 line--limit-1">{{$parcelRefundReason->title}}</p>
                                        </td>
                                        <td class="text-center">
                                            <label class="switcher mx-auto">
                                                <input class="switcher_input status-change"
                                                       data-url="{{ route('admin.business.setup.parcel-refund.reason.status') }}"
                                                       id="{{ $parcelRefundReason->id }}"
                                                       type="checkbox"
                                                       name="status" {{ $parcelRefundReason->is_active == 1 ? "checked": ""  }} >
                                                <span class="switcher_control"></span>
                                            </label>
                                        </td>
                                        <td>
                                            <div class="d-flex justify-content-center gap-2 align-items-center">
                                                <button class="btn btn-outline-primary btn-action editData"
                                                        data-id="{{$parcelRefundReason->id}}">
                                                    <i class="bi bi-pencil-fill"></i>
                                                </button>
                                                <button data-url="{{ route('admin.business.setup.parcel-refund.reason.delete', ['id' => $parcelRefundReason?->id]) }}"
                                                        data-icon="{{ dynamicAsset('public/assets/admin-module/img/trash.png') }}"
                                                        data-title="{{ translate('Are you sure to delete this Refund Reason') }}?"
                                                        data-sub-title="{{ translate('Once you delete it') . ', ' . translate('This will be permanently removed from the list.') }}"
                                                        data-confirm-btn="{{ translate('Yes, Delete') }}"
                                                        data-cancel-btn="{{ translate('Not Now') }}"
                                                        type="button"
                                                        class="btn btn-outline-danger btn-action delete-button">
                                                    <i class="bi bi-trash-fill"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6">
                                            <div
                                                class="d-flex flex-column justify-content-center align-items-center gap-2 py-3">
                                                <img
                                                    src="{{ dynamicAsset('public/assets/admin-module/img/empty-icons/no-data-found.svg') }}"
                                                    alt="" width="100">
                                                <p class="text-center">{{translate('no_data_available')}}</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
        <!-- End Main Content -->
        <div class="d-flex justify-content-end mt-3">
            {{ $parcelRefundReasons->links() }}
        </div>
        {{-- Edit modal --}}
        <div class="edit-modal modal fade" id="editDataModal">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                </div>
            </div>
        </div>
        @endsection

        @push('script')
            <script>
                "use strict";


                $('.character-count-field').on('keyup change', function () {
                    initialCharacterCount($(this));
                });
                $('.character-count-field').each(function () {
                    initialCharacterCount($(this));
                });

                function collapsibleCard(thisInput) {
                    let $card = thisInput.closest('.collapsible-card-body');
                    let $content = $card.children('.collapsible-card-content');
                    if (thisInput.prop('checked')) {
                        $content.slideDown();
                    } else {
                        $content.slideUp();
                    }
                }

                $('.collapsible-card-switcher').each(function () {
                    collapsibleCard($(this))
                });


                function initialCharacterCount(item) {
                    let str = item.val();
                    let maxCharacterCount = item.data('max-character');
                    let characterCount = str.length;
                    if (characterCount > maxCharacterCount) {
                        item.val(str.substring(0, maxCharacterCount));
                        characterCount = maxCharacterCount;
                    }
                    item.closest('.character-count').find('span').text(characterCount + '/' + maxCharacterCount);
                }

                let permission = false;
                @can('business_edit')
                    permission = true;
                @endcan

                $(document).ready(function () {
                    $('.editData').click(function () {
                        let id = $(this).data('id');
                        let url = "{{ route('admin.business.setup.parcel-refund.reason.edit', ':id') }}";
                        url = url.replace(':id', id);
                        $.get({
                            url: url,
                            success: function (data) {
                                $('#editDataModal .modal-content').html(data);
                                $('#updateForm').removeClass('d-none');
                                $('#editDataModal').modal('show');
                                $('.character-count-field').on('keyup change', function () {
                                    initialCharacterCount($(this));
                                });
                                $('.character-count-field').each(function () {
                                    initialCharacterCount($(this));
                                });
                            },
                            error: function (xhr, status, error) {
                                console.log(error);
                            }
                        });
                    });
                });


            </script>
    @endpush
