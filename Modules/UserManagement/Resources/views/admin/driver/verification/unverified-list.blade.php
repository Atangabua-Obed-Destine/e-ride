@php use Carbon\Carbon; @endphp
@extends('adminmodule::layouts.master')

@section('title', translate('Verification_List'))

@push('css_or_js')
@endpush

@section('content')
    @php($env = env('APP_MODE') == 'live' ? 'live' : 'test')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="fs-20 fw-bold mb-0 text-capitalize">{{translate('Drivers_Verification_Need_List')}}</h2>
                <a href="#howItWork-offcanvas" data-bs-toggle="offcanvas"
                   class="text-primary fw-medium fs-12 d-flex gap-1 align-items-center">{{ translate('How it Works') }}
                    <i class="bi bi-info-circle"></i></a>
            </div>
            <div class="card card-body">
                <div class="table-top d-flex flex-wrap gap-10 justify-content-between mb-3">
                    <form action="javascript:;" class="search-form search-form_style-two"
                          method="GET">
                        <div class="input-group search-form__input_group">
                            <span class="search-form__icon px-2">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="search" class="theme-input-style search-form__input"
                                   value="{{ request()->get('search') }}" name="search" id="search"
                                   placeholder="{{ translate('search by driver name') }}">
                        </div>
                        <button type="submit" class="btn btn-primary search-submit"
                                data-url="{{ url()->full() }}">{{ translate('search') }}</button>
                    </form>

                    <div class="d-flex flex-wrap gap-3">
                        <a href="{{ route('admin.driver.verification.unverified-list') }}"
                           class="btn btn-outline-primary px-3" data-bs-toggle="tooltip"
                           data-bs-title="{{ translate('refresh') }}">
                            <i class="bi bi-arrow-repeat"></i>
                        </a>
                        <div class="dropdown">
                            <button type="button" class="btn btn-outline-primary"
                                    data-bs-toggle="dropdown">
                                <i class="bi bi-download"></i>
                                {{ translate('download') }}
                                <i class="bi bi-caret-down-fill"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                                <li>
                                    <a class="dropdown-item" href="{{route('admin.driver.verification.export-unverified-list',[
                                                'file'=>'excel',
                                                'search' =>request()->get('search'),
                                                'verification_status'=>request()->get('verification_status'),
                                                'filter_date'=>request()->get('filter_date'),
                                                'start_date'=>request()->get('start_date'),
                                                'end_date'=>request()->get('end_date'),
                                                'order_by' => request()->get('order_by'),
                                            ]
                                            )}}">{{ translate('excel') }}</a>
                                </li>
                            </ul>
                        </div>
                        <a href="#" type="button" data-bs-toggle="offcanvas" data-bs-target="#filter-offcanvas"
                           class="btn btn-outline-primary text-capitalize cmn_focus">
                            <i class="bi bi-funnel"></i>
                            {{ translate('Filter') }}
                        </a>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-borderless align-middle table-hover col-mx-w300 text-dark text-nowrap">
                        <thead class="table-light align-middle text-capitalize">
                        <tr>
                            <th>{{ translate('SL') }}</th>
                            <th>{{ translate('Driver_Info') }}</th>
                            <th>{{ translate('Attempts_Made') }}</th>
                            <th>{{ translate('Last_Attempt_Time') }}</th>
                            <th>{{ translate('Verification_Status') }}</th>
                            <th class="text-center">{{ translate('Action') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($unverifiedDrivers as $key => $unverifiedDriver)
                                <?php
                                $attemptDetails = collect($unverifiedDriver->attempt_details);
                                ?>
                            <tr>
                                <td>{{$unverifiedDrivers->firstItem() + $key}}</td>
                                <td>
                                    <a href="#"
                                       class="media d-inline-flex align-items-center gap-10">
                                        <img loading="lazy"
                                             src="{{ onErrorImage(
                                                                        $unverifiedDriver->driver?->profile_image,
                                                                        dynamicStorage('storage/app/public/driver/profile') . '/' . $unverifiedDriver->driver?->profile_image,
                                                                        dynamicAsset('public/assets/admin-module/img/avatar/avatar.png'),
                                                                        'driver/profile/',
                                                                    ) }}"
                                             class="rounded custom-box-size" alt=""
                                             style="--size: 40px">
                                        <div class="flex-shrink-0">
                                            <div class="d-flex align-items-center gap-1 mb-1">
                                                <span>{{ ($unverifiedDriver->driver->first_name ?? '') . ' ' . ($unverifiedDriver->driver->last_name ?? '') }}</span>
                                                @if($unverifiedDriver->driver->driverDetails->is_verified)
                                                    <span class="fs-14 lh-1" data-bs-toggle="tooltip"
                                                          title="{{ translate('verified') }}">
                                                        <i class="bi bi-patch-check-fill text-success"></i>
                                                    </span>
                                                @else
                                                    <span class="fs-14 lh-1" data-bs-toggle="tooltip"
                                                          title="{{ translate('Unverified') }}">
                                                        <i class="bi bi-patch-exclamation-fill text-danger"></i>
                                                    </span>
                                                @endif
                                                @if($unverifiedDriver->driver->driverDetails->is_suspended)
                                                    <img width="14"
                                                         src="{{ dynamicAsset('public/assets/admin-module/img/svg/on-hold.svg') }}"
                                                         alt="" data-bs-toggle="tooltip" data-bs-placement="right"
                                                         data-bs-title="{{ translate('on_hold') }}">
                                                @endif
                                            </div>
                                            <div class="fs-12 opacity-75">{{ $unverifiedDriver->driver->phone }}</div>
                                        </div>
                                    </a>
                                </td>
                                <td>{{ collect($unverifiedDriver->attempt_details)->count() ?? 0 }}</td>
                                <td>
                                    @if($attemptDetails->count())
                                        {{Carbon::parse($attemptDetails->last()['time'])->format('Y-m-d') }}
                                        <br>
                                        {{ Carbon::parse($attemptDetails->last()['time'])->format('h:i A') }}
                                    @else
                                        N/A
                                    @endif
                                </td>
                                <td>
                                    <span
                                        class="badge {{ $unverifiedDriver->current_status == 'skipped' ? 'badge-info' : 'badge-danger' }}">{{ $unverifiedDriver->current_status }}</span>
                                </td>
                                <td>
                                    <div class="d-flex justify-content-center gap-2 align-items-center">
                                        <a href="javascript:"
                                           class="btn btn-outline-info btn-action view-driver-verification-request"
                                           data-id="{{ $unverifiedDriver->id }}"
                                           data-bs-toggle="offcanvas"
                                           data-bs-target="#driverVerification-offcanvas"
                                           title="{{ translate('View Driver Verification Request') }}">
                                            <i class="bi bi-eye-fill"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="14">
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
                <div class="d-flex justify-content-end mt-3">
                    {{ $unverifiedDrivers->links() }}
                </div>
            </div>

        </div>
    </div>
    <!-- End Main Content -->

    {{-- How Verification Work Offcanvas --}}
    <div class="offcanvas offcanvas-end" id="howItWork-offcanvas" style="--bs-offcanvas-width: 490px">
        <form action="javascript:" class="d-flex flex-column h-100">
            <div class="offcanvas-header">
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                <h4 class="offcanvas-title flex-grow-1 text-center">
                    {{ translate('How Verification Work') }}
                </h4>
            </div>
            <div class="offcanvas-body scrollbar-thin">
                <div class="d-flex flex-column gap-20">
                    <div class="bg-fafafa rounded p-sm-4 p-3">
                        <h5 class="fw-semibold mb-3">{{ translate('Driver Face Verification Overview') }}</h5>

                            <div class="bg-white rounded p-lg-4 p-3 d-flex flex-column gap-3 mb-3">
                                <h6 class="fw-semibold fs-14 mb-1">{{ translate('Overview') }}</h6>
                                <p class="fs-14 mb-0 text-dark">
                                    {{ translate('This section shows the list of drivers who need face verification review or action by the admin.') }}
                                </p>
                            </div>

                            <div class="bg-white rounded p-lg-4 p-3 d-flex flex-column gap-3 mb-3">
                                <h6 class="fw-semibold fs-14 mb-1">{{ translate('At Signup') }}</h6>
                                <p class="fs-14 mb-0 text-dark">
                                    {{ translate('Drivers may complete face verification during registration or choose to skip it.') }}
                                </p>
                            </div>

                            <div class="bg-white rounded p-lg-4 p-3 d-flex flex-column gap-3 mb-3">
                                <h6 class="fw-semibold fs-14 mb-1">{{ translate('Skipped Drivers') }}</h6>
                                <p class="fs-14 mb-0 text-dark">
                                    {{ translate('If a driver skips face verification, their status will appear as Skipped.') }}
                                </p>
                            </div>

                            <div class="bg-white rounded p-lg-4 p-3 d-flex flex-column gap-3 mb-3">
                                <h6 class="fw-semibold fs-14 mb-1">{{ translate('Failed Attempts') }}</h6>
                                <p class="fs-14 mb-0 text-dark">
                                    {{ translate('Drivers who attempt verification but fail will be marked as Failed, along with the number of attempts made.') }}
                                </p>
                            </div>

                            <div class="bg-white rounded p-lg-4 p-3 d-flex flex-column gap-3 mb-3">
                                <h6 class="fw-semibold fs-14 mb-1">{{ translate('Attempts Tracking') }}</h6>
                                <p class="fs-14 mb-0 text-dark">
                                    {{ translate('The system records how many verification attempts were made and the last attempt time.') }}
                                </p>
                            </div>

                            <div class="bg-white rounded p-lg-4 p-3 d-flex flex-column gap-3 mb-3">
                                <h6 class="fw-semibold fs-14 mb-1">{{ translate('Admin Review') }}</h6>
                                <p class="fs-14 mb-0 text-dark">
                                    {{ translate('Admins can view driver details and decide when to prompt or require re-verification.') }}
                                </p>
                            </div>

                            <div class="bg-white rounded p-lg-4 p-3 d-flex flex-column gap-3 mb-3">
                                <h6 class="fw-semibold fs-14 mb-1">{{ translate('Purpose') }}</h6>
                                <p class="fs-14 mb-0 text-dark">
                                    {{ translate('This helps ensure driver identity, authenticity, and platform safety before allowing full access or promotions.') }}
                                </p>
                            </div>
                    </div>
                </div>
            </div>
            <div
                class="offcanvas-footer d-flex gap-3 bg-white shadow position-sticky bottom-0 p-3 justify-content-center">
                <button type="button" class="btn btn-primary fw-semibold"
                        data-bs-dismiss="offcanvas">
                    {{translate('Okay, Got It') }}
                </button>
            </div>
        </form>
    </div>

    {{-- Filter Verification Request Offcanvas --}}
    <div class="offcanvas offcanvas-end" id="filter-offcanvas">
        <form class="d-flex flex-column h-100" action="{{url()->full()}}" id="filterForm">
            <div class="offcanvas-header">
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                <h4 class="offcanvas-title flex-grow-1 text-center">
                    {{translate('Filter Verification Request')}}
                </h4>
            </div>
            <input type="hidden" name="search" id="search" value="{{ request()->input('search') }}">
            <div class="offcanvas-body scrollbar-thin">
                <div class="mb-4 custom-input-grp position-relative">
                    <label class="mb-2 position-absolute bg-white left-3 px-1 index-2">
                        {{translate("Verification Status")}}
                    </label>
                    <select class="js-select-offcanvas" name="verification_status">
                        @foreach(['failed', 'skipped'] as $status)
                            <option
                                value="{{$status}}" {{request() && request()->input('verification_status') == $status ? "selected" : ""}}>{{ translate($status) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-4 custom-input-grp position-relative">
                    <label class="mb-2 mb-2 position-absolute bg-white left-3 px-1 index-2">
                        {{ translate('Select Date') }}
                    </label>
                    <select class="js-select-offcanvas" name="filter_date" id="filterDate">
                        <option value="{{ALL_TIME}}" class="text-primary"
                            {{request() && request()->input('filter_date') == ALL_TIME ? "selected" : ""}}>{{translate(ALL_TIME)}}</option>
                        <option
                            value="{{TODAY}}" {{request() && request()->input('filter_date') == TODAY ? "selected" : ""}}>{{translate(TODAY)}}</option>
                        <option
                            value="{{THIS_WEEK}}" {{request() && request()->input('filter_date') == THIS_WEEK ? "selected" : ""}}>{{translate(THIS_WEEK)}}</option>
                        <option
                            value="{{THIS_MONTH}}" {{request() && request()->input('filter_date') == THIS_MONTH ? "selected" : ""}}>{{translate(THIS_MONTH)}}</option>
                        <option
                            value="{{THIS_YEAR}}" {{request() && request()->input('filter_date') == THIS_YEAR ? "selected" : ""}}>{{translate(THIS_YEAR)}}</option>
                        <option
                            value="{{CUSTOM_DATE}}" {{request() && request()->input('filter_date') == CUSTOM_DATE ? "selected" : ""}}>{{translate(CUSTOM_DATE)}}</option>
                    </select>
                </div>
                <div id="filterCustomDate" class="d-none">
                    <div class="row">
                        <div class="col-6">
                            <label class="mb-2">{{translate("Start date")}}</label>
                            <input type="date" value="{{request()->input('start_date')}}" id="start_date"
                                   name="start_date" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="mb-2">{{translate("End date")}}</label>
                            <input type="date" id="end_date" value="{{request()->input('end_date')}}"
                                   name="end_date" class="form-control">
                        </div>
                    </div>
                </div>
                <h5 class="fw-semibold border-bottom pb-3 mb-3">{{ translate('Sort By') }}</h5>
                <div class="d-flex flex-column gap-4">
                    <div class="checked-label-wrapper">
                        <input type="radio" name="order_by" value="desc" id="verification_sort_default"
                               tabindex="1" {{(request() && request()->input('order_by') == 'desc') || !request()->filled('order_by') ? "checked" : ""}}>
                        <label for="verification_sort_default"
                               class="media gap-2 align-items-center checked-label-bold">
                            <span class="media-body">{{ translate('Default') }} ({{ translate('Newest') }})</span>
                        </label>
                    </div>
                    <div class="checked-label-wrapper">
                        <input type="radio" name="order_by" value="asc" id="verification_sort_reverse"
                               tabindex="2" {{request() && request()->input('order_by') == 'asc' ? "checked" : ""}}>
                        <label for="verification_sort_reverse"
                               class="media gap-2 align-items-center checked-label-bold">
                            <span class="media-body">{{ translate('Oldest to Newest') }}</span>
                        </label>
                    </div>
                </div>
            </div>
            <div
                class="offcanvas-footer d-flex gap-3 bg-white position-sticky bottom-0 p-3 pos-sticky-btn-shadow justify-content-center">
                <button type="reset" class="btn btn-secondary cmn_reset" data-bs-dismiss="offcanvas" aria-label="Close">
                    {{translate('Cancel')}}</button>
                <button type="submit" class="btn btn-primary cmn_focus">
                    {{translate('Apply') }}
                </button>
            </div>
        </form>
    </div>

    {{-- Driver Verification Request Offcanvas --}}
    <div class="offcanvas {{ Session::get('direction') === 'rtl' ? 'offcanvas-start' : ' offcanvas-end' }}"
         id="driverVerification-offcanvas" style="--bs-offcanvas-width: 490px">
    </div>

    {{-- Suspend Modal --}}
    <div class="modal fade" id="suspend-modal">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <button type="button" class="btn-close" data-bs-toggle="modal">
                    </button>
                </div>
                <div class="modal-body pb-5 pt-0">
                    <div>
                        <div class="text-center">
                            <img width="80" alt="" class="aspect-1 mb-20"
                                 src="{{dynamicAsset('public/assets/admin-module/img/modal/driving.png')}}">
                        </div>
                        <div class="mb-30">
                            <h5 class="text-center text-capitalize mb-3">{{ translate('Suspend this driver') . '?' }}</h5>
                            <ul class="fs-12 text-dark d-grid gap-2">
                                <li>
                                    {{ translate('Suspending this driver will place the account on Hold and keep the driver Unverified.') }}
                                </li>
                                <li>
                                    {{ translate('While on hold, the driver will not be able to accept or complete any trips.') }}
                                </li>
                                <li>
                                    {{ translate('You can manage or Un-suspend the driver later from the Driver Details section.') }}
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="btn--container justify-content-center">
                        <a href="" class="btn btn-secondary min-w-120 suspend-driver">
                            {{translate('Suspend This Driver')}}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script src="{{ dynamicAsset('public/assets/admin-module/js/single-image-upload-new.js') }}"></script>
    <script>
        $(document).ready(function () {
            $('.js-select-offcanvas').select2({
                dropdownParent: $('#filter-offcanvas')
            });

            $("select").closest("form").on("reset", function (ev) {
                var targetJQForm = $(ev.target);
                setTimeout((function () {
                    this.find("select").trigger("change");
                }).bind(targetJQForm), 0);
            });
            let unverifiedDriverId = localStorage.getItem('unverifiedDriverId');
            @if($errors->any())
            if (unverifiedDriverId) {
                loadDriverVerificationRequestForm(unverifiedDriverId)
            }
            @endif
            localStorage.removeItem('unverifiedDriverId');
            $(document).on('click', '.view-driver-verification-request', function () {
                let id = $(this).data('id');
                localStorage.setItem('unverifiedDriverId', id);
                loadDriverVerificationRequestForm(id)
            })

            function loadDriverVerificationRequestForm(id) {
                let url = "{{ route('admin.driver.verification.view-verification-request', ':id') }}";
                url = url.replace(':id', id);
                $('#driverVerification-offcanvas').empty();
                $('#driverVerification-offcanvas').offcanvas('show');
                $.get(url, function (data) {
                    $('#driverVerification-offcanvas').html(data);
                    if (document.querySelectorAll('.upload-file-new').length) {
                        initFileUpload();
                        checkPreExistingImages();
                    }
                });
            }

            var offcanvasEl = document.getElementById('driverVerification-offcanvas');
            offcanvasEl.addEventListener('hidden.bs.offcanvas', function () {
                localStorage.removeItem('unverifiedDriverId');
            });

            $(document).off('click', '.mark-as-suspended').on('click', '.mark-as-suspended', function () {
                let id = $(this).data('id');
                let url = '{{ route('admin.driver.verification.mark-as-suspended', ':id') }}';
                url = url.replace(':id', id);

                $('.suspend-driver').attr('href', url);

            })
        })
    </script>
    <script>
        function loadPartialView(url, divId, data) {
            $.get({
                url: url,
                dataType: 'json',
                data: {data},
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
                    toastr.error('{{translate('failed_to_load_data')}}')
                },
            });
        }

        let data_range = $('#date-range');
        let data_input = $('#data-input');

        data_range.on('change', function () {
            if (data_range.val() === 'custom_date') {
                data_input.css('display', 'flex')
            } else {
                data_input.css('display', 'none')
                loadPartialView('{{url()->full()}}', '#trip-stats', data_range.val())
            }
        });


        function getDate() {
            let start = $('#start_date').val()
            let end = $('#end_date').val()
            if (!start || !end || start > end) {
                toastr.error('{{translate('please_select_proper_date_range')}}');
                return;
            }
            let data = {start: start, end: end}
            loadPartialView('{{url()->full()}}', '#trip-stats', data)
        }


        //filter


        let filterDate = $('#filterDate');
        let filterCustomDate = $('#filterCustomDate');
        filterDateChange();

        filterDate.on('change', function () {
            filterDateChange();
        });

        function filterDateChange() {
            if (filterDate.val() == 'custom_date') {
                filterCustomDate.removeClass('d-none')
                $("#start_date").attr('required', 'true')
                $("#end_date").attr('required', 'true')
            } else {
                $("#start_date").removeAttr('required')
                $("#end_date").removeAttr('required')
                filterCustomDate.addClass('d-none')
            }
        }

        document.getElementById('start_date').addEventListener('change', function () {
            // Get the selected start date value
            var startDate = this.value;

            // Set the minimum value of the end date to the selected start date
            var endDateInput = document.getElementById('end_date');
            endDateInput.setAttribute('min', startDate);

            // Optional: If the current end date is less than the new start date, update it to match the start date
            if (endDateInput.value && endDateInput.value < startDate) {
                endDateInput.value = startDate;
            }
        });
    </script>
@endpush
