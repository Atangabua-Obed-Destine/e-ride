@php
    use Carbon\Carbon;
    use Modules\ParcelManagement\Entities\ParcelCategory;
    use Modules\VehicleManagement\Entities\VehicleCategory;
    $isOnlySchedule = isset($isOnlySchedule) ?? false;
    $isOnlyPriceApplicableFor = isset($isOnlyPriceApplicableFor) ?? false;
    $timeSlot = !$isCreateBlade ? $surgePricing?->surgePricingTimeSlot : null;
    $startDate = $timeSlot?->start_date;
    $endDate = $timeSlot?->end_date;
    $totalDates = 0;
    if (!$isCreateBlade && isset($surgePricing) && $surgePricing->schedule === 'weekly' && $surgePricing?->surgePricingTimeSlot?->end_date !== 'unlimited'){
            $selectedDates = $timeSlot?->selected_days ?? [];
            $period = new DatePeriod(new DateTime($startDate), new DateInterval('P1D'), (new DateTime($endDate))->modify('+1 day'));
            $totalDates = count(array_filter(iterator_to_array($period), fn($date) => in_array($date->format('l'), $selectedDates)));
    } elseif (!$isCreateBlade && isset($surgePricing) && $surgePricing?->schedule === 'daily'){
        $totalDates = Carbon::parse($surgePricing->surgePricingTimeSlot->start_date)->diffInDays(Carbon::parse($surgePricing->surgePricingTimeSlot->end_date)) + 1;
    } elseif(!$isCreateBlade && isset($surgePricing) && $surgePricing?->schedule === 'custom'){
        $totalDates = count($surgePricing->surgePricingTimeSlot->slots);
    }
@endphp
<form
    action="{{ $isCreateBlade ? route('admin.fare.surge-pricing.store') : route('admin.fare.surge-pricing.update', $surgePricing?->id) }}"
    data-form-method="{{ $isCreateBlade ? 'POST' : 'PUT' }}" class="d-flex flex-column h-100 submit-form">
    <span class="offcanvas-create-edit-fetched-data"
          data-get-custom-date-time-range="{{ !$isCreateBlade ? json_encode($formatDateRangeForCustomSchedule) : ''}}"
          data-is-create-blade="{{ (bool)$isCreateBlade }}"
          @if(!$isCreateBlade)
              data-on-custom-date-range="{{ (optional($surgePricing)->schedule == 'custom') }}"
          @endif
          >
    </span>

    <div class="offcanvas-header">
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        <h4 class="offcanvas-title flex-grow-1 text-center">
            @if($isOnlyPriceApplicableFor)
                {{ translate('Edit Surge Rate Setup ') }}
            @elseif($isOnlySchedule)
                {{ translate('Surge Price - Schedule') }}
            @else
                {{ translate('Surge Price Setup') }}
            @endif
        </h4>
    </div>
    <div class="offcanvas-body scrollbar-thin">
        <div class="mb-30 {{ ($isOnlySchedule || $isOnlyPriceApplicableFor) ? 'd-none' : ''  }}">
            <label class="form-label">
                {{ translate('name') }}
                <span class="text-danger">*</span>
            </label>
            <input type="text" name="name" value="{{  $isCreateBlade ? '' : $surgePricing?->name }}"
                   class="form-control" placeholder="{{ translate('type_Surge_Name') }}" required>
        </div>
        <div class="mb-30 {{ ($isOnlySchedule || $isOnlyPriceApplicableFor) ? 'd-none' : '' }}">
            <label class="form-label">
                {{ translate('zone') }}
                <span class="text-danger">*</span>
                <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip"
                   data-bs-title="{{ translate('Select the zones where this surge price will apply. You can choose one or multiple zones') }}"></i>
            </label>
            <select class="js-select-multiple multiple-select2 js-select-2" name="zones[]" multiple="multiple" data-placeholder="{{ translate('Select_Zone') }}" required>
                <?php
                    $surgePricingZones = $isCreateBlade ? [] : $surgePricing?->surgePricingZones->pluck('id')->toArray();
                ?>
                <option value="{{ALL}}" @selected(($isCreateBlade ? '' : $surgePricing->zone_setup_type) == 'all')>{{translate('All Zones')}}</option>
                @foreach($zones as $id => $zoneName)
                    <option
                        value="{{ $id }}" @selected((in_array($id, $surgePricingZones) && $surgePricing->zone_setup_type == 'custom'))>{{ $zoneName }}</option>
                @endforeach
            </select>
        </div>
        <div class="pricing-schedule-calendar-wrapper">
            <div class="{{ $isOnlySchedule ? 'd-none' : '' }}">
                <div class="mb-30">
                    <label class="form-label">
                        {{ translate('Setup Surge Pricing For') }}
                        <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip"
                           data-bs-title="{{ translate('Choose whether to apply surge pricing to Rides, Parcels, or Both.') }}"></i>
                    </label>
                    <div class="d-flex align-items-center gap-2 flex-wrap h-auto min-h-40px form-control">
                        <div class="flex-grow-1">
                            <input type="radio" name="pricing_for" value="ride"
                                   id="ride" @checked(($isCreateBlade ? 'ride' : $surgePricing?->surge_pricing_for) === 'ride')>
                            <label for="ride">{{ translate('ride') }}</label>
                        </div>

                        <div class="flex-grow-1">
                            <input type="radio" name="pricing_for" value="parcel"
                                   id="parcel" @checked(($isCreateBlade ? 'ride' : $surgePricing?->surge_pricing_for) === 'parcel')>
                            <label for="parcel">{{ translate('parcel') }}</label>
                        </div>

                        <div class="flex-grow-1">
                            <input type="radio" name="pricing_for" value="both"
                                   id="both" @checked(($isCreateBlade ? 'ride' : $surgePricing?->surge_pricing_for ) === 'both')>
                            <label for="both">{{ translate('both') }}</label>
                        </div>
                    </div>
                </div>
                <div class="bg-light rounded p-3 mb-3 rideContent">
                    <div class="mb-3">
                        <label class="form-label fs-16 fw-semibold mb-3">
                            {{ translate('Price Increase Rate For Ride') }}
                            <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip"
                               data-bs-title="{{ translate('Set surge rate (%) to increase ride fare. Different rates can be set per vehicle type.') }}"></i>
                        </label>
                        <div class="d-flex align-items-center gap-2 flex-wrap h-auto min-h-40px form-control">
                            <div class="flex-grow-1">
                                <input type="radio" name="increase_rate" value="all_vehicle"
                                       id="all_vehicle" @checked(($isCreateBlade ? 1 : $surgePricing?->increase_for_all_vehicles) === 1)>
                                <label for="all_vehicle">{{ translate('Same for All Vehicle') }}</label>
                            </div>

                            <div class="flex-grow-1">
                                <input type="radio" name="increase_rate" value="different_rate"
                                       id="different_rate" @checked(($isCreateBlade ? 1 : $surgePricing?->increase_for_all_vehicles) === 0)>
                                <label for="different_rate">{{ translate('Setup Different Rate ') }}</label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-column gap-3">
                        <!-- Same for All Vehicle -->
                        <div class="all-vehicle-rate">
                            <label class="form-label">
                                {{ translate('Rate') }}
                                <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <input type="number"
                                       name="ride_surge_multiplier"
                                       step="0.01"
                                       min="0"
                                       max="100"
                                       onkeydown="return !['e','E','+','-'].includes(event.key)"
                                       oninput="
                                       if (this.value < 0) this.value = 0;
                                       if (this.value > 100) this.value = 100;
                                       if (this.value.includes('.')) {this.value = this.value.split('.').map((part, index) => index === 1 ? part.slice(0, 2) : part).join('.');}
                                       "
                                       value="{{ $isCreateBlade ? '' : ($surgePricing->increase_for_all_vehicles ? $surgePricing?->all_vehicle_surge_percent : '')}}"
                                       class="form-control" placeholder="{{ translate('Ex: 20') }}">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>

                        <!-- Setup Different Rate -->
                        <div class="different-vehicle-rate d-none">
                            <div class="d-flex flex-column gap-3">
                                @foreach($vehicleCategories as $key => $vehicleCategory)
                                    @php
                                        if (!$isCreateBlade){
                                            $multiplier = $vehicleCategory->surgePricings
                                                ->firstWhere('surge_pricing_id', $surgePricing->id)?->surge_multiplier;
                                        }
                                    @endphp
                                    <div>
                                        <label class="form-label">
                                            {{ translate( $vehicleCategory->name . ' Rate') }}
                                            <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <input type="number"
                                                   name="surge_multipliers[{{ $vehicleCategory->id }}]"
                                                   step="0.01"
                                                   min="0"
                                                   max="100"
                                                   onkeydown="return !['e','E','+','-'].includes(event.key)"
                                                   oninput="
                                                   if (this.value < 0) this.value = 0;
                                                   if (this.value > 100) this.value = 100;
                                                   if (this.value.includes('.')) {this.value = this.value.split('.').map((part, index) => index === 1 ? part.slice(0, 2) : part).join('.');}
                                                   "
                                                   value="{{ $isCreateBlade ? '' : $multiplier }}"
                                                   class="form-control" placeholder="{{ translate('Ex: 20') }}">
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-light rounded p-3 mb-30 ParcelContent">
                    <label class="form-label fs-16 fw-semibold mb-3">
                        {{ translate('Price Increase Rate For Parcel') }}
                        <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip"
                           data-bs-title="{{ translate('Set the surge rate  (%) to increase the parcel delivery fee.') }}"></i>
                    </label>
                    <div>
                        <label class="form-label">
                            {{ translate('Rate') }}
                            <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input type="number"
                                   name="parcel_surge_multiplier"
                                   step="0.01"
                                   min="0"
                                   max="100"
                                   onkeydown="return !['e','E','+','-'].includes(event.key)"
                                   oninput="
                                       if (this.value < 0) this.value = 0;
                                       if (this.value > 100) this.value = 100;
                                       if (this.value.includes('.')) {this.value = this.value.split('.').map((part, index) => index === 1 ? part.slice(0, 2) : part).join('.');}
                                       "
                                   value="{{ $isCreateBlade ? '' : $surgePricing?->all_parcel_surge_percent}}"
                                   class="form-control" placeholder="{{ translate('Ex: 20') }}">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="{{ $isOnlyPriceApplicableFor ? 'd-none' : '' }}">
                <div class="mb-30">
                    <label class="form-label">
                        {{ translate('Surge Price Schedule') }}
                        <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip"
                           data-bs-html="true"
                           data-bs-title="<div style='margin:0; padding:0;'>
                            <div>• {{ translate('Daily – Repeats every day during the selected time range') }}</div>
                            <div>• {{ translate('Weekly – Set specific days of the week') }}</div>
                            <div>• {{ translate('Custom – Pick exact dates and times') }}</div>
                          </div>"></i>
                    </label>
                    <div class="d-flex align-items-center gap-2 flex-wrap h-auto min-h-40px form-control">
                        <div class="flex-grow-1">
                            <input type="radio" name="price_schedule" value="daily"
                                   id="daily" @checked(($isCreateBlade ? 'daily' : $surgePricing?->schedule) === 'daily')>
                            <label for="daily">{{ translate('daily') }}</label>
                        </div>

                        <div class="flex-grow-1">
                            <input type="radio" name="price_schedule" value="weekly"
                                   id="weekly" @checked(($isCreateBlade ? 'daily' : $surgePricing?->schedule) === 'weekly')>
                            <label for="weekly">{{ translate('weekly') }}</label>
                        </div>

                        <div class="flex-grow-1">
                            <input type="radio" name="price_schedule" value="custom"
                                   id="custom" @checked(($isCreateBlade ? 'daily' : $surgePricing?->schedule) === 'custom')>
                            <label for="custom">{{ translate('custom') }}</label>
                        </div>
                    </div>
                </div>

                <div class="bg-light mt-3 rounded p-3 mb-30 dailyContent">
                    <h4 class="mb-1">{{ translate('Select time & date') }}</h4>
                    <p class="fs-12">{{ translate('Select your suitable time within a time range you want add surge price') }}
                        .</p>

                    <div class="mb-30">
                        <label class="form-label">
                            {{ translate('Date Range ') }}
                            <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip"
                               data-bs-title="{{ translate('Select the start and end dates for this surge pricing to be active.') }}"></i>
                        </label>
                        <div class="position-relative select_date_range_wrapper">
                            <input type="text" name="date_range_daily"
                                   value="{{ $isCreateBlade ? '' : ($surgePricing?->schedule === 'daily' ? date('m/d/Y', strtotime($surgePricing?->surgePricingTimeSlot?->start_date)) . ' - ' . date('m/d/Y', strtotime($surgePricing?->surgePricingTimeSlot?->end_date)) : '') }}"
                                   class="form-control date-range-picker" placeholder="mm/dd /yy - mm/dd/yy" autocomplete="off">
                            <div
                                class="position-absolute top-0 h-100 p-3 d-flex justify-content-center align-items-center date_range_calender_icon">
                                <i class="bi bi-calendar"></i>
                            </div>
                        </div>
                    </div>
                    <div class="mb-30">
                        <label class="form-label">
                            {{ translate('time Range ') }}
                            <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip"
                               data-bs-title="{{ translate('Select the start and end time for this surge pricing to be active.') }}"></i>
                        </label>
                        <div class="position-relative cursor-pointer flex-grow-1">
                            <i class="bi bi-clock fs-16 icon-absolute-on-right"></i>
                            <input type="text"
                                   class="form-control time-range-picker ltr text-start position-relative h-40px bg-white"
                                   name="time_range_daily"
                                   value="{{ $isCreateBlade ? '' : (($surgePricing?->schedule === 'daily' || $surgePricing?->schedule === 'weekly') ? date('h:i A', strtotime($surgePricing?->surgePricingTimeSlot?->slots[0]['start_time'])) . ' - ' . date('h:i A', strtotime($surgePricing?->surgePricingTimeSlot?->slots[0]['end_time'])) : '') }}"
                                   placeholder="Ex: 9.00 AM to 12.00 PM" readonly>
                        </div>
                    </div>
                    @php
                        $totalDatesDaily = (isset($surgePricing) && $surgePricing->schedule == 'daily') ? $totalDates : 0;
                        $timeWord = $totalDatesDaily == 1 ? 'time' : 'times';
                        if ($totalDatesDaily == 0) {$timeWord = 'time';}
                        $countHtml = '<strong class="surge_time_count">' . $totalDatesDaily . ' ' . $timeWord . '</strong>';
                    @endphp

                    <p class="text-center fs-12 surge_time_count_wrapper">
                        @if ($isCreateBlade)
                            {!! translate('this_surge_price_will_be_applied_for_{count}.', ['count' => $countHtml]) !!}
                        @elseif($totalDatesDaily > 0)
                            {!! translate('this_surge_price_will_be_applied_for_{count}.', ['count' => $countHtml]) !!}
                        @else
                            {!! translate('this_surge_price_will_be_applied_for_{count}.', ['count' => $countHtml]) !!}
                        @endif
                    </p>
                </div>

                <div class="bg-light mt-3 rounded p-3 mb-30 weeklyContent">
                    <h4 class="mb-1">{{ translate('Select time & date') }}</h4>
                    <p class="fs-12">{{ translate('Select your suitable time within a time range you want add surge price') }}
                        .</p>

                    <div class="mb-3">
                        <label class="form-label">
                            {{ translate('Select Days') }}
                            <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip"
                               data-bs-title="{{ translate('Select the days for this surge pricing to be active.') }}"></i>
                        </label>
                        <div class="position-relative form-control d-flex align-items-center justify-content-between gap-2">
                            <input type="text" title="" name="select_days"
                                   class="form-control border-0 shadow-none bg-transparent p-0 line--limit-1 weeklyInputVal"
                                   value="{{ $isCreateBlade ? '' : implode(',', $surgePricing?->surgePricingTimeSlot?->selected_days ?? []) }}"
                                   placeholder="{{ translate('select_Days') }}"
                                   data-bs-toggle="modal"
                                   data-bs-target="{{ ($isCreateBlade || $surgePricing?->schedule != 'weekly') ? '#selectWeeklyDaysModal-create' : '#selectWeeklyDaysModal-' . $surgePricing->id }}"
                                   readonly>
                            <div
                                class="position-absolute top-0 h-100 p-3 d-flex justify-content-center align-items-center pointer-events-auto cursor-pointer date_range_calender_icon">
                                <i class="bi bi-calendar"></i>
                            </div>
                        </div>
                    </div>
                    @php
                        $startDateText = !$isCreateBlade && $surgePricing->schedule == 'weekly'
                            ? date('d M, Y', strtotime($surgePricing?->surgePricingTimeSlot?->start_date))
                            : '';

                        $endDateText = !$isCreateBlade && $surgePricing->schedule == 'weekly'
                            ? ($surgePricing?->surgePricingTimeSlot?->end_date === 'unlimited'
                                ? translate('until_turn_off')
                                : date('d M, Y', strtotime($surgePricing?->surgePricingTimeSlot?->end_date)))
                            : '';
                    @endphp

                    <p class="fs-12 text-center mb-30 weeklyShowVal {{ $isCreateBlade || $surgePricing?->schedule !== 'weekly' ? 'd-none' : '' }}">
                        {!! translate(
                            key: 'every_week_from_:startDate_to_:endDate',
                            replace: [
                                'startDate' => '<span class="text-success weeklyShowVal_startDate">' . $startDateText . '</span>',
                                'endDate'   => '<span class="text-success weeklyShowVal_endDate">' . $endDateText . '</span>',
                            ]
                        ) !!}
                    </p>
                    <div class="mb-30">
                        <label class="form-label">
                            {{ translate('time Range ') }}
                            <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip"
                               data-bs-title="{{ translate('Select the start and end time for this surge pricing to be active.') }}"></i>
                        </label>
                        <div class="position-relative cursor-pointer flex-grow-1">
                            <i class="bi bi-clock fs-16 icon-absolute-on-right"></i>
                            <input type="text"
                                   class="form-control time-range-picker ltr text-start position-relative h-40px bg-white"
                                   value="{{ $isCreateBlade ? '' : (($surgePricing?->schedule === 'daily' || $surgePricing?->schedule === 'weekly') ? date('h:i A', strtotime($surgePricing?->surgePricingTimeSlot?->slots[0]['start_time'])) . ' - ' . date('h:i A', strtotime($surgePricing?->surgePricingTimeSlot?->slots[0]['end_time'])) : '') }}"
                                   name="time_range_weekly"
                                   placeholder="Ex: 9.00 AM to 12.00 PM" readonly>
                        </div>
                    </div>
                    @php
                        $totalDatesWeekly = (isset($surgePricing) && $surgePricing->schedule == 'weekly' && $surgePricing->surgePricingTimeSlot?->end_date !== 'unlimited')
                            ? $totalDates
                            : 0;
                        $timeWord = $totalDatesWeekly == 1 ? 'time' : 'times';
                        if ($totalDatesWeekly == 0) {$timeWord = 'time';}
                        $countHtml = '<strong class="surge_time_count">' . $totalDatesWeekly . ' ' . $timeWord . '</strong>';
                    @endphp

                    <p class="text-center fs-12 surge_time_count_wrapper">
                        @if ($isCreateBlade)
                            {!! translate('this_surge_price_will_be_applied_for_{count}.', ['count' => $countHtml]) !!}
                        @elseif($totalDatesWeekly > 0)
                            {!! translate('this_surge_price_will_be_applied_for_{count}.', ['count' => $countHtml]) !!}
                        @else
                            {!! translate('this_surge_price_will_be_applied_for_{count}.', ['count' => $countHtml]) !!}
                        @endif
                    </p>
                    <input type="hidden" name="date_range_weekly"
                           value="{{ isset($surgePricing) && $surgePricing->schedule == 'weekly' ? date('d M, Y', strtotime($surgePricing?->surgePricingTimeSlot?->start_date)) . ' - ' . ($surgePricing?->surgePricingTimeSlot?->end_date != 'unlimited' ? date('d M, Y', strtotime($surgePricing?->surgePricingTimeSlot?->end_date)) : 'unlimited') : '' }}">
                </div>

                <div class="customContent mb-30">
                    <div class="d-flex flex-column">
                        <div class="bg-light mt-3 rounded p-3 mb-30">
                            <h4 class="mb-1">{{ translate('Select time & date') }}</h4>
                            <p class="fs-12">{{ translate('Select your suitable time within a time range you want add surge price') }}
                                .</p>

                            <div class="mb-3">
                                <label class="form-label">
                                    {{ translate('Date Range') }}
                                    <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip"
                                       data-bs-title="{{ translate('Select the dates for this surge pricing to be active.') }}"></i>
                                </label>
                                <div
                                    class="position-relative form-control d-flex align-items-center justify-content-between gap-2">
                                    <input type="text" title=""
                                           class="form-control border-0 shadow-none bg-transparent p-0 line--limit-1 customInputVal"
                                           name="total_days_custom"
                                           value="{{!$isCreateBlade && $surgePricing->schedule == 'custom' ?  $totalDates : 0 }} {{ translate('Days Selected') }}"
                                           placeholder="{{ translate('select_Days') }}" data-bs-toggle="modal"
                                           data-bs-target="#custom_shedule_modal" readonly>
                                    <div
                                        class="position-absolute top-0 h-100 p-3 d-flex justify-content-center align-items-center pointer-events-auto cursor-pointer date_range_calender_icon">
                                        <i class="bi bi-calendar"></i>
                                    </div>
                                </div>
                            </div>
                            @php
                                $totalDatesCustom = !$isCreateBlade && $surgePricing->schedule == 'custom' ?  $totalDates : 0;
                                $timeWord = $totalDatesCustom == 1 ? 'time' : 'times';
                                if ($totalDatesCustom == 0) {$timeWord = 'time';}
                                $countHtml = '<strong class="surge_time_count">' . $totalDatesCustom . ' ' . $timeWord . '</strong>';
                            @endphp

                            <p class="text-center fs-12 surge_time_count_wrapper">
                                @if ($isCreateBlade)
                                    {!! translate('this_surge_price_will_be_applied_for_{count}.', ['count' => $countHtml]) !!}
                                @elseif($totalDatesCustom > 0)
                                    {!! translate('this_surge_price_will_be_applied_for_{count}.', ['count' => $countHtml]) !!}
                                @else
                                    {!! translate('this_surge_price_will_be_applied_for_{count}.', ['count' => $countHtml]) !!}
                                @endif
                            </p>
                        </div>
                        <div class="bg-light mt-3 rounded p-3 repeated_list_wrapper d-none">
                            <h4 class="mb-1">{{ translate('Repeated List') }} (<span class="repeated_list_count"></span>)
                            </h4>
                            <p class="fs-12 surge_time_count_wraper">
                                <span>{{ translate('You’ll_receive_this_surge_price_for') }} </span>
                                <span class="surge_time_count"></span>
                                <span class="text-lowercase"> {{ translate('slots') }}.</span>
                            </p>

                            <div class="repeated_list bg-white rounded p-3 d-flex flex-column gap-3 fs-14 fs-12-mobile">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-light mt-3 rounded p-3 mb-30 {{ ($isOnlySchedule || $isOnlyPriceApplicableFor) ? 'd-none' : '' }}">
            <div>
                <h5 class="mb-1">{{ translate('Note for Customer') }}</h5>
                <p class="fs-12">{{ translate('Add a note to inform users about temporary price changes.') }}</p>
                <div class="character-count">
                            <textarea name="customer_note" id="note" rows="2"
                                      class="form-control character-count-field"
                                      maxlength="30"
                                      data-max-character="30"
                                      placeholder="{{ translate('Type note for customer') }}">{{ $isCreateBlade ? '' : $surgePricing->customer_note }}</textarea>
                    <span class="d-flex justify-content-end">0/30</span>
                </div>
            </div>
        </div>

        <div class="mb-30 {{ ($isOnlySchedule || $isOnlyPriceApplicableFor) ? 'd-none' : '' }}">
            <label class="form-label">{{ translate('Availability') }}</label>
            <div class="d-flex align-items-center justify-content-between gap-2 form-control">
                <div>{{ translate('Status') }}</div>
                <label class="switcher">
                    <input class="switcher_input" type="checkbox"
                           name="is_active" @checked($isCreateBlade ? 1 : $surgePricing->is_active === 1)>
                    <span class="switcher_control"></span>
                </label>
            </div>
        </div>
    </div>
    <div class="offcanvas-footer d-flex gap-3 bg-white shadow position-sticky bottom-0 p-3 justify-content-center">
        <button type="button" class="btn btn-light fw-semibold flex-grow-1" data-bs-dismiss="offcanvas"
                aria-label="Close">
            {{translate('cancel')}}</button>
        <button type="submit" class="btn btn-primary fw-semibold flex-grow-1">
            {{translate('save') }}
        </button>
    </div>
    <span class="data-to-js"
          data-id="{{ $surgePricing->id ?? null }}"
    ></span>
</form>
