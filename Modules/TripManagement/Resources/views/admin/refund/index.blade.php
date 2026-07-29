@extends('adminmodule::layouts.master')

@section('title', translate('Trips'))

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <h4 class="text-capitalize mb-4 ">{{ translate('refund_request')}}</h4>

            <div class="row mb-4">
                @include('tripmanagement::admin.refund.partials._refund-inline-menu')
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="table-top d-flex flex-wrap gap-10 justify-content-between">
                        <form action="{{url()->current()}}" class="search-form search-form_style-two">
                            <div class="input-group search-form__input_group">
                                    <span class="search-form__icon">
                                        <i class="bi bi-search"></i>
                                    </span>
                                <input type="search" name="search" value="{{request()->search}}"
                                       class="theme-input-style search-form__input"
                                       placeholder="{{translate('Search_here_by_Trip_ID')}}">
                            </div>
                            <button type="submit" class="btn btn-primary cmn_focus">{{translate('search')}}</button>
                        </form>

                        <div class="d-flex flex-wrap gap-3">
                            <a href="" class="btn btn-outline-primary px-3" data-bs-toggle="tooltip"
                               data-bs-title="{{ translate('view_Log') }}">
                                <i class="bi bi-clock-fill"></i>
                            </a>
                            <div class="dropdown">
                                <button type="button" class="btn btn-outline-primary" data-bs-toggle="dropdown">
                                    <i class="bi bi-download"></i>
                                    {{translate('download')}}
                                    <i class="bi bi-caret-down-fill"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                                    <li>
                                        <a class="dropdown-item"
                                           href="{{route('admin.trip.refund.export', ['file'=>'excel','search' =>request()->get('search'),'type'=>$type])}}">{{ translate('excel') }}</a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="table-responsive mt-3">
                            <table class="table table-borderless align-middle table-hover">
                                <thead class="table-light align-middle text-capitalize text-nowrap">
                                <tr>
                                    <th class="text-center sl">{{translate('SL')}}</th>
                                    <th>{{translate('Refund ID')}}</th>
                                    <th>{{translate('Trip ID')}}</th>
                                    <th>{{translate('Parcel Info')}}</th>
                                    <th>{{translate('Customer Info')}}</th>
                                    <th>{{translate('Refund Reason')}}</th>
                                    <th class="text-center action text-center">{{translate('action')}}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($parcelRefunds as $key => $parcelRefund)
                                    <tr>
                                        <td class="text-center sl">{{$parcelRefunds->firstItem() + $key}}</td>
                                        <td>
                                            <a
                                                href="{{route('admin.trip.refund.show', [ $parcelRefund->id])}}">{{$parcelRefund->readable_id}}</a>

                                        </td>
                                        <td>
                                            {{$parcelRefund?->tripRequest->ref_id}}
                                        </td>
                                        <td>
                                            <div>
                                                <div class="flex parcel-info-text">
                                                    <span class="left">{{translate("Category")}}</span> <span>:</span>
                                                    <span
                                                        class="right">{{$parcelRefund?->tripRequest?->parcel?->parcelCategory?->name ?? "N/A"}}</span>
                                                </div>
                                                <div class="flex parcel-info-text">
                                                    <span class="left">{{translate("approximate_price")}}</span>
                                                    <span>:</span> <span
                                                        class="right">{{set_currency_symbol($parcelRefund->parcel_approximate_price)}}</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-capitalize">
                                                <a target="_blank"
                                                   @if($parcelRefund?->tripRequest?->customer)
                                                       href="{{route('admin.customer.show', [$parcelRefund?->tripRequest?->customer?->id])}}"
                                                   @endif
                                                   class="fw-medium">{{$parcelRefund?->tripRequest?->customer?->full_name ?? $parcelRefund?->tripRequest?->customer?->first_name}}</a>
                                                <br>
                                                {{$parcelRefund?->tripRequest?->customer?->phone}}
                                            </div>
                                        </td>
                                        <td class="refund-reason" data-bs-custom-class="refund-reason-tooltip"
                                            data-bs-placement="bottom" data-bs-toggle="tooltip"
                                            title="{{$parcelRefund?->reason}}">
                                            <div class="refund-reason-text">{{$parcelRefund?->reason}}</div>
                                        </td>
                                        <td class="text-center action">
                                            <div class="d-flex justify-content-center gap-2 align-items-center">
                                                <a href="{{route('admin.trip.refund.show', ['type' => $type, 'id' => $parcelRefund->id, 'page' => 'summary'])}}"
                                                   class="btn btn-outline-info btn-action" data-bs-toggle="tooltip"
                                                   title="View Details">
                                                    <i class="bi bi-eye-fill"></i>
                                                </a>
                                                @if($parcelRefund->status == PENDING || $parcelRefund->status == DENIED )
                                                    <a id="approvalButtonParcelRefund{{$key}}"
                                                       data-url="{{route('admin.trip.refund.approved', [$parcelRefund->id])}}"
                                                       data-icon="{{ dynamicAsset('public/assets/admin-module/img/approval-icon.png') }}"
                                                       data-title="{{ translate('Are you sure to Approve the Refund Request')."?" }}"
                                                       data-sub-title="{{translate("The customer has requested a refund of")}}  <strong>{{set_currency_symbol($parcelRefund->parcel_approximate_price)}}</strong> {{translate("for this parcel.")}}"
                                                       data-confirm-btn="{{translate("Approve")}}"
                                                       data-input-title="{{translate("Approval Note")}}"
                                                       class="btn btn-outline-success btn-action d-flex justify-content-center align-items-center approval-button-parcel-refund"
                                                       data-bs-toggle="tooltip" title="{{translate("Approve")}}">
                                                        <i class="bi bi-check fs-16"></i>
                                                    </a>
                                                @endif
                                                @if($parcelRefund->status == PENDING || $parcelRefund->status == APPROVED )
                                                    <a id="deniedButtonParcelRefund{{$key}}"
                                                       data-url="{{route('admin.trip.refund.denied', [$parcelRefund->id])}}"
                                                       data-icon="{{ dynamicAsset('public/assets/admin-module/img/denied-icon.png') }}"
                                                       data-title="{{ translate('Are you sure to Deny the Refund Request')."?" }}"
                                                       data-sub-title="{{translate("Once you deny the request, the customer will not be refunded the amount he asked for.")}}"
                                                       data-confirm-btn="{{translate("Deny")}}"
                                                       data-input-title="{{translate("Deny Note")}}"
                                                       class="btn btn-outline-danger btn-action d-flex justify-content-center align-items-center denied-button-parcel-refund"
                                                       data-bs-toggle="tooltip" title="{{translate("Deny")}}">
                                                        <i class="bi bi-x fs-16"></i>
                                                    </a>
                                                @endif
                                                @if($parcelRefund->status ==  APPROVED )
                                                    <a id="parcelRefundButton{{$key}}"
                                                       data-amount="{{$parcelRefund->parcel_approximate_price}}"
                                                       data-url="{{route('admin.trip.refund.store', [$parcelRefund->id])}}"
                                                       class="btn btn-outline-primary btn-action d-flex justify-content-center align-items-center parcel-refund-button"
                                                       data-bs-toggle="tooltip" title="{{translate("make_refund")}}">
                                                        <img
                                                            src="{{dynamicAsset('public/assets/admin-module/img/refund-icon.png')}}"
                                                            alt="">
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7">
                                            <div
                                                class="d-flex flex-column justify-content-center align-items-center gap-2 py-3">
                                                <img
                                                    src="{{ dynamicAsset('public/assets/admin-module/img/empty-icons/no-data-found.svg') }}"
                                                    alt=""
                                                    width="100">
                                                <p class="text-center">{{translate('no_data_available')}}</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div
                            class="table-bottom d-flex flex-column flex-sm-row justify-content-sm-between align-items-center gap-2">
                            <p class="mb-0"></p>
                            {{$parcelRefunds->links()}}
                        </div>

                    </div>
                </div>
            </div>
        </div>

    </div>
    <!-- End Main Content -->
@endsection

@push('script')
    <script>
        $(document).ready(function () {


        })
    </script>

@endpush
