@extends('adminmodule::layouts.master')

@section('title', translate('Parcel_Delivery_Fare_Setup'))

@section('content')
    @php
        $unit = businessConfig('parcel_weight_unit', PARCEL_SETTINGS)?->value;
        $symbol = (session()->get('currency_symbol') ?? businessConfig('currency_symbol', 'business_information')?->value) ?? "$";
    @endphp

        <!-- Main Content -->
    <div class="main-content">
        <form action="{{ route('admin.fare.parcel.store') }}" method="post">
            @csrf
            <div class="container-fluid">
                <h2 class="fs-22 mb-4 text-capitalize">{{ translate('parcel_delivery_fare_setup') }} - <span
                        class="text-primary">{{ $zone->name }} {{ translate('zone') }}</span></h2>

                <div class="card mb-3">
                    <div class="card-header">
                        <input type="hidden" name="zone_id" value="{{ $zone->id }}">

                        <h5 class="mb-3 text-capitalize">{{ translate('available_parcel_category_in_this_zone') }}</h5>

                        <div class="d-flex flex-wrap align-items-center gap-4 gap-xl-5">
                            @forelse($parcelCategory as $pc)
                                @if ($pc->is_active)
                                    <label class="custom-checkbox">
                                        <input type="checkbox" name="parcel_category[]" value="{{ $pc->id }}"
                                               @forelse($fares?->fares?? [] as $fare)
                                                   @if ($fare->parcel_category_id == $pc->id)
                                                       checked
                                            @endif
                                        @empty
                                            @endforelse>
                                        {{ $pc->name }}
                                    </label>
                                @endif
                            @empty
                            @endforelse
                        </div>
                    </div>
                    <div class="card-body">
                        <h5 class="mb-3 text-capitalize">{{ translate('default_fare_setup') }}</h5>
                        <div class="row gy-4">
                            <div class="col-sm-6 col-lg-6 category-fare-class">
                                <label for="base_fare" class="form-label">{{ translate('Base_Fare') }}</label>
                                <div class="input-group_tooltip">
                                    <input type="number" class="form-control" name="base_fare" id="base_fare"
                                           value="{{ $fares?->base_fare + 0 }}"
                                           placeholder="{{ translate('Base_Fare') }}"
                                           step=".01" min="0.01" max="99999999" required>
                                    <i class="bi bi-info-circle-fill text-primary tooltip-icon" data-bs-toggle="tooltip"
                                       data-bs-title="{{ translate('set_the_base_fare_for_calling_a_vehicle_for_parcel_delivery') }}"></i>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-6 parcel-fare-setup-class">
                                <label for="return_fee" class="form-label">{{ translate('return_fee') }} (%)</label>
                                <div class="input-group_tooltip">
                                    <input type="number" class="form-control" name="return_fee" id="return_fee"
                                           value="{{ $fares?->return_fee + 0 }}"
                                           placeholder="{{ translate('return_fee') }}"
                                           step=".01" min="0" max="100" required>
                                    <i class="bi bi-info-circle-fill text-primary tooltip-icon" data-bs-toggle="tooltip"
                                       data-bs-title="{{ translate('Set the Return Fee for the customer when customer need to return the parcel. This fee will added in the total trip cost.') }}"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <h5 class="text-capitalize mb-2">

                            {{ translate('category_wise_delivery_fee') }}
                        </h5>
                        <div class="fs-12">
                            {{ translate("Here you can setup individual price for each parcel category") }}
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="table-responsive border border-primary-light rounded">
                            <table class="table align-middle table-borderless table-variation">
                                <thead class="border-bottom border-primary-light">
                                <tr>
                                    <th>{{ translate('fare') }}</th>
                                    <th>
                                        {{ translate('base_fare') }}
                                        <span class="fs-10">/{{ translate($unit) }}</span>
                                        ({{ $symbol }})
                                    </th>
                                    @forelse($parcelWeight as $pw)
                                        @if ($pw['is_active'] == 1)
                                            <th>{{ $pw->min_weight + 0 . '-' . ($pw->max_weight + 0) . ' /' . translate($unit) }}
                                            </th>
                                        @endif
                                    @empty
                                    @endforelse
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($parcelCategory as $pc)
                                    @if ($pc->is_active)
                                        @php($fare = $fares?->fares->where('parcel_category_id', $pc->id)->first())
                                        <tr>
                                            <td
                                                class="{{ $pc->id }} {{ $fare?->parcel_category_id == $pc->id ? '' : 'd-none' }}">
                                                <div
                                                    class="d-flex align-items-center gap-2 text-primary fw-semibold">
                                                    <div>{{ translate($pc->name) }} <span class="fs-10">/
                                                                km</span> ({{ $symbol }})
                                                    </div>
                                                    <i class="bi bi-info-circle-fill fs-14" data-bs-toggle="tooltip"
                                                       data-bs-title="{{ translate('set_the_fare_for_each_kilometer_added_with_the_base_fare') }}"></i>
                                                </div>
                                            </td>
                                            <td
                                                class="category-fare-class {{ $pc->id }} {{ $fare?->parcel_category_id == $pc->id ? '' : 'd-none' }}">
                                                <input type="number" name="base_fare_{{ $pc->id }}"
                                                       value="{{ $fare?->base_fare ?? $fares?->base_fare }}"
                                                       class="form-control base_fare" step=".01" min="0.01"
                                                       max="99999999" required>
                                            </td>
                                            @forelse($parcelWeight as $pw)
                                                @php($weightFare = $fares?->fares->where('parcel_weight_id', $pw->id)->where('parcel_category_id', $pc->id)->first()
)
                                                @if ($pw->is_active == 1)
                                                    <td
                                                        class="category-fare-class {{ $pc->id }} {{ $fare?->parcel_category_id == $pc->id ? '' : 'd-none' }}">
                                                        <input type="number"
                                                               name="weight_{{ $pc->id }}[{{ $pw->id }}]"
                                                               class="form-control weight_fare {{ $pc->id }}"
                                                               value="{{ $weightFare?->fare_per_km + 0 }}"
                                                               step=".01" min="0.01" max="99999999"
                                                               {{ $fare?->parcel_category_id == $pc->id ? '' : 'disabled' }}
                                                               required>
                                                    </td>
                                                @endif
                                            @empty
                                            @endforelse
                                        </tr>
                                    @endif

                                @empty
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="footer-sticky">
                <div class="container-fluid">
                    <div class="d-flex justify-content-end gap-2 py-4">
                        <button type="button" class="btn btn-light btn-lg fw-semibold"
                                data-bs-dismiss="modal">{{ translate('reset') }}</button>
                        <button type="submit"
                                class="btn btn-primary btn-lg fw-semibold">{{ translate('submit') }}</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <!-- End Main Content -->

@endsection

@push('script')
    <script src="{{ dynamicAsset('public/assets/admin-module/js/fare-management/parcel/create.js') }}"></script>
    <script>
        "use strict";
        $("form").submit(function () {
            if ($('input[type="checkbox"]:checked').length <= 0) {
                toastr.error('{{ translate('must_select_at_least_one_parcel_category') }}')
                return false;
            }
        });

        const inputParcelElements = document.querySelectorAll('.parcel-fare-setup-class input[type="number"]');

        inputParcelElements.forEach(input => {
            input.addEventListener('input', function () {
                if (parseFloat(this.value) < 0) {
                    // this.value = 1;
                    toastr.error('{{ translate('the_value_must_greater_than_or_equal_0') }}')
                }
            });
        });

        const inputCategoryElements = document.querySelectorAll('.category-fare-class input[type="number"]');

        inputCategoryElements.forEach(input => {
            input.addEventListener('input', function () {
                if (parseFloat(this.value) <= 0) {
                    // this.value = 1;
                    toastr.error('{{ translate('the_value_must_greater_than_0') }}')
                }
            });
        });
    </script>
    <script>
        "use strict";
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
    </script>

@endpush
