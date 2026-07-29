"use strict";

(function ($) {
    function initTimeRangePicker($inputs) {
        $inputs?.each(function () {
            const $input = $(this);
            if ($input.data('daterangepicker')) return;

            const placeholder = $input.attr('placeholder') || 'Select Time';
            const initialValue = $input.val();
            let lastValue = initialValue;

            $input.daterangepicker({
                timePicker: true,
                timePicker24Hour: false,
                timePickerIncrement: 1,
                locale: {
                    format: 'h:mm A',
                    cancelLabel: 'Clear'
                },
                autoApply: true,
                autoUpdateInput: false,
                drops: 'auto'
            });

            $input.on('show.daterangepicker', function (ev, picker) {
                picker.container.find('.calendar-table').hide();
                picker.container.find('.calendar-time').css('visibility', 'visible');

                const offset = $input.offset();
                const winHeight = $(window).height();

                if (offset.top > winHeight / 2) {
                    picker.drops = 'up';
                } else {
                    picker.drops = 'down';
                }
                picker.move();
            });

            $input.on('apply.daterangepicker', function (ev, picker) {
                let start = picker.startDate.clone();
                let end = picker.endDate.clone();

                if (end.diff(start, 'minutes') < 1) {
                    end = start.clone().add(1, 'minute');
                    picker.setEndDate(end);
                }

                const newValue = `${start.format('h:mm A')} - ${end.format('h:mm A')}`;
                $input.val(newValue);
            });

            $input.on('cancel.daterangepicker', function () {
                if (lastValue?.trim()) {
                    $input.val(lastValue);
                } else {
                    $input.val('').attr('placeholder', placeholder);
                }
            });
        });
    }


    let $activeWeeklyWrapper = null;

    function initWeeklyModalHandler() {
        $(document).on('click', '.saveWeeklyModalData', function () {
            const $modal = $(this).closest('.modal');
            if (!$activeWeeklyWrapper) return;
            const selectedDays = [];
            $modal.find('input[type="checkbox"][name="for_whom[]"]:checked').each(function () {
                const label = $(this).siblings('label').text().trim();
                selectedDays.push(label);
            });

            const $weeklyInput = $activeWeeklyWrapper.find('.weeklyInputVal');
            const $weeklyShowVal = $activeWeeklyWrapper.find('.weeklyShowVal');
            const $startDateEl = $activeWeeklyWrapper.find('.weeklyShowVal_startDate');
            const $endDateEl = $activeWeeklyWrapper.find('.weeklyShowVal_endDate');
            const $assignAllTime = $modal.find('.assignAllTime');
            const $rangeInput = $modal.find('.date-range-picker');
            $weeklyInput.val(selectedDays.join(',')).attr('title', selectedDays.join(','));

            if ($assignAllTime.is(':checked')) {
                const today = moment().format('D MMM, YYYY');
                $startDateEl.text(today);
                $endDateEl.text('until you turn off');
                $activeWeeklyWrapper.find('input[name="date_range_weekly"]').val(today + ' - unlimited');
            } else {
                const rangeVal = $rangeInput.val();
                if (rangeVal.includes(' - ')) {
                    const [start, end] = rangeVal.split(' - ');
                    const formattedStart = moment(start, 'MM/DD/YYYY').format('D MMM, YYYY');
                    const formattedEnd = moment(end, 'MM/DD/YYYY').format('D MMM, YYYY');
                    $startDateEl.text(formattedStart);
                    $endDateEl.text(formattedEnd);
                    $activeWeeklyWrapper.find('input[name="date_range_weekly"]').val(formattedStart + ' - ' + formattedEnd);
                }
            }
            if ($weeklyInput.val())
            {
                $weeklyShowVal.removeClass('d-none');
            }
            $modal.modal('hide');
            $activeWeeklyWrapper = null;
        });

        $(document).on('change', '.assignAllTime', function () {
            const $modal = $(this).closest('.modal');
            const $dateRangeWrapper = $modal.find('.select_date_range_wrapper');
            const $picker = $dateRangeWrapper.find('.date-range-picker');
            $dateRangeWrapper.toggleClass('disabled', $(this).is(':checked'));
            if (!($(this).is(':checked')))
            {
                const drp = $picker.data('daterangepicker');
                if (drp) {
                    const formatted = drp.startDate.format(drp.locale.format) +
                        drp.locale.separator +
                        drp.endDate.format(drp.locale.format);
                    $picker.val(formatted);

                }
            }
        });
    }

    // Calendar Module (per container)
    function CalendarModule($container) {
        let selectedDates = new Set();
        window.duplicateSelectedDates = selectedDates;

        const today = new Date();
        let year = today.getFullYear();
        let month = today.getMonth();

        const $days = $container.find(".days");
        const $display = $container.find(".display");
        const $selectedListInner = $container.find(".selected-list-inner");

        function renderCalendar() {
            $days.empty();
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);
            const firstDayIndex = firstDay.getDay();
            const numberOfDays = lastDay.getDate();

            $display.text(firstDay.toLocaleString("en-US", { month: "long", year: "numeric" }));

            const repeatedDates = !window.activeCurrentDate
                ? new Set(repeatedList.map(item => item.date))
                : null;

            for (let x = 0; x < firstDayIndex; x++) {
                $days.append("<div></div>");
            }

            for (let i = 1; i <= numberOfDays; i++) {
                const currentDate = new Date(year, month, i);
                const dateStr = currentDate.toDateString();
                const isToday = dateStr === new Date().toDateString();

                const isSelected = window.activeCurrentDate
                    ? selectedDates.has(dateStr)
                    : repeatedDates.has(dateStr);


                const $day = $("<div>", {
                    text: i,
                    "data-date": dateStr,
                    class: `${isToday && !repeatedDates ? "current-date active" : "current-date"} ${isSelected ? "active" : ""}`
                });

                $days.append($day);
            }

            bindDateClick();
        }
        function generateUniqueId() {
            let id;
            do {
                id = Math.floor(Math.random() * 1000000);
            } while (repeatedList.some(item => item.id == id));
            return id;
        }
        function bindDateClick() {
            $days.find("div[data-date]").off("click").on("click", function () {
                const uniqueId = generateUniqueId();
                const date = $(this).data("date");
                if (!selectedDates.has(date)) {
                    selectedDates.add(date);
                    $(this).addClass("active");
                    addSelectedDay(date, '', uniqueId);
                }
            });
        }

        function addSelectedDay(dateStr, time = '',  index) {
            const $item = $(`
                <div id="${index ?? ''}" class="selected-list-item d-flex flex-sm-nowrap flex-wrap align-items-center justify-content-between justify-content-md-start gap-3 bg-light rounded py-2 px-2">
                    <span class="fs-14 text-dark text-nowrap after-date">
                        <span class="display-selected">
                            <p class="selected m-0 p-0">${dateStr}</p>
                        </span>
                    </span>
                    <div class="d-flex align-items-center gap-3 flex-grow-1">
                        <div class="position-relative cursor-pointer flex-grow-1">
                            <i class="bi bi-clock icon-absolute-on-right fs-12"></i>
                            <input type="text" value="${time}" class="form-control time-range-picker ltr text-start position-relative fs-14 h-32px bg-white" name="time_range[]" placeholder="Select Time" readonly>
                        </div>
                        <button type="button" class="removeDay text-danger btn p-0 border-0">
                            <i class="tio-clear-circle-outlined fs-20"></i>
                        </button>
                    </div>
                </div>
            `);

            $item.find('.removeDay').on('click', function () {
                $item.remove();
                selectedDates.delete(dateStr);
                $days.find(`div[data-date="${dateStr}"]`).removeClass("active");
            });

            $selectedListInner.append($item);

            // Init daterangepicker for the new input
            initTimeRangePicker($item.find('.time-range-picker'));
        }

        function bindNavigation() {
            $container.find(".left").on("click", () => {
                if (month === 0) {
                    month = 11;
                    year--;
                } else {
                    month--;
                }
                renderCalendar();
            });

            $container.find(".right").on("click", () => {
                if (month === 11) {
                    month = 0;
                    year++;
                } else {
                    month++;
                }
                renderCalendar();
            });
        }

        function selectToday() {
            const todayStr = today.toDateString();
            const uniqueId = generateUniqueId();
            if (window.activeCurrentDate){
                selectedDates.add(todayStr);
                addSelectedDay(todayStr, '',  uniqueId);
            }
        }

        function init() {
            renderCalendar();
            bindNavigation();
            selectToday();
        }

        if (!window.activeCurrentDate){
            repeatedList.forEach((item) => {
                selectedDates.add(item.date);
                addSelectedDay(item.date, item.time, item.id);
            });
        }

        return { init };
    }

    // Main component initializer (per container)
    function initPricingScheduleCalendar($container) {

        // Scoped selectors inside container
        const $rideContent = $container.find('.rideContent');
        const $parcelContent = $container.find('.ParcelContent');
        const $allVehicleRate = $container.find('.all-vehicle-rate');
        const $differentVehicleRate = $container.find('.different-vehicle-rate');
        const $dailyContent = $container.find('.dailyContent');
        const $weeklyContent = $container.find('.weeklyContent');
        const $customContent = $container.find('.customContent');
        const $pricingForInputs = $container.find(`input[name="pricing_for"]`);
        const $increaseRateInputs = $container.find(`input[name="increase_rate"]`);
        const $priceScheduleInputs = $container.find(`input[name="price_schedule"]`);
        const $scheduleRadios = $container.find('.shedule-checkbox-inner .form-check-input');
        const $scheduleItems = $container.find('.change-shedule-wrapper .shedule_item');
        const $timeRangeInputs = $container.find('.time-range-picker');

        function togglePricingContent() {
            const selected = $pricingForInputs.filter(':checked').val();
            $rideContent.toggle(selected === 'ride' || selected === 'both');
            $parcelContent.toggle(selected === 'parcel' || selected === 'both');
        }

        function toggleRateType() {
            const selectedRateType = $increaseRateInputs.filter(':checked').val();
            $allVehicleRate.toggleClass('d-none', selectedRateType !== 'all_vehicle');
            $differentVehicleRate.toggleClass('d-none', selectedRateType === 'all_vehicle');
        }

        function toggleScheduleContent() {
            const selectedSchedule = $priceScheduleInputs.filter(':checked').val();
            $dailyContent.toggle(selectedSchedule === 'daily');
            $weeklyContent.toggle(selectedSchedule === 'weekly');
            $customContent.toggle(selectedSchedule === 'custom');
        }

        function updateScheduleVisibility() {
            $scheduleItems.hide();
            $scheduleRadios.each(function (index) {
                if ($(this).is(':checked')) {
                    $scheduleItems.eq(index).show();
                }
            });
        }

        $pricingForInputs.on('change', togglePricingContent);
        $increaseRateInputs.on('change', toggleRateType);
        $priceScheduleInputs.on('change', toggleScheduleContent);
        $scheduleRadios.on('change', updateScheduleVisibility);

        // Init toggles on load
        togglePricingContent();
        toggleRateType();
        toggleScheduleContent();
        updateScheduleVisibility();

        // Init time pickers inside container
        initTimeRangePicker($timeRangeInputs);

        // Init calendar
        const calendar = CalendarModule($container);
        calendar.init();

        // Open modal on weeklyInputVal click, setting active wrapper for modal save
        $container.find('.weeklyInputVal').on('click', function () {
            $activeWeeklyWrapper = $container;
            const modalId = $(this).data('bs-target');
            const $modal = $(modalId);
            // Reset modal inputs
            $modal.find('input[type="checkbox"][name="for_whom[]"]').prop('checked', false);
            // $modal.find('.assignAllTime').prop('checked', false);
            $modal.find('.date-range-picker').val('');
            // $modal.find('.select_date_range_wrapper').removeClass('disabled');

            // Load selected days from current wrapper
            const val = $(this).val();
            if (val) {
                const selectedDays = val.split(',').map(s => s.trim().toLowerCase());
                $modal.find('input[type="checkbox"][name="for_whom[]"]').each(function () {
                    const checkboxVal = $(this).val().toLowerCase();
                    $(this).prop('checked', selectedDays.includes(checkboxVal));
                });
            }

            // Load start and end date from wrapper, if exists
            const startDateText = $container.find('.weeklyShowVal_startDate').text().trim();
            const endDateText = $container.find('.weeklyShowVal_endDate').text().trim();

            const $weeklyDateRangePicker = $modal.find('.date-range-picker');

            if (endDateText === 'until you turn off') {
                $modal.find('.assignAllTime').prop('checked', true);
                $modal.find('.select_date_range_wrapper').addClass('disabled');
                $weeklyDateRangePicker.val('');
            } else if (startDateText && endDateText) {
                const startMoment = moment(startDateText, 'D MMM, YYYY');
                const endMoment = moment(endDateText, 'D MMM, YYYY');
                if (startMoment.isValid() && endMoment.isValid()) {
                    const drp = $weeklyDateRangePicker.data('daterangepicker');
                    if (drp) {
                        drp.setStartDate(startMoment);
                        drp.setEndDate(endMoment);
                    }
                    $weeklyDateRangePicker.val(
                        `${startMoment.format('MM/DD/YYYY')} - ${endMoment.format('MM/DD/YYYY')}`
                    );
                }
            }

            $modal.modal('show');
        });
    }

    // Initialize all containers on document ready
    $(function () {
        $('.pricing-schedule-calendar-wrapper').each(function () {
            initPricingScheduleCalendar($(this));
        });

        // Global init for any time range pickers outside containers
        initTimeRangePicker($('.time-range-picker').not('.initialized'));
        initWeeklyModalHandler();

        // --- Custom Schedule Repeated List Logic ---
        let repeatedList = window.repeatedList;

        function formatDisplayDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', {
                weekday: 'short',
                month: 'short',
                day: 'numeric',
                year: "numeric"
            });
        }

        function updateCustomInputVal() {
            $('.customInputVal').val(`${repeatedList.length} Days Selected`);
        }

        function renderRepeatedList() {
            const $wrapper = $('.repeated_list_wrapper');
            const $list = $wrapper.find('.repeated_list');
            $list.empty();

            if (repeatedList.length === 0) {
                $wrapper.addClass('d-none');
                return;
            } else {
                $wrapper.removeClass('d-none');
            }
            repeatedList.forEach(item => {
                const $entry = $(`
                    <div class="repeated_list_item d-flex justify-content-between align-items-center gap-2 gap-sm-3 text-dark flex-wrap" data-id="${item.id}">
                        <span>${formatDisplayDate(item.date)}</span>
                        <span class="opacity-75 ltr">${item.time}</span>
                        <span class="d-flex gap-2 align-items-center">
                            <a href="#" class="text-primary edit-item" data-id="${item.id}"><i class="bi bi-pencil"></i></a>
                            <a href="#" class="text-danger delete-item" data-id="${item.id}"><i class="bi bi-trash"></i></a>
                        </span>
                    </div>
                    <input type="hidden" name="date_range_custom[${item.id}]" value="${item.date}">
                    <input type="hidden" name="time_range_custom[${item.id}]" value="${item.time}">
                `);
                $list.append($entry);
            });

            $('.repeated_list_count').text(repeatedList.length);
        }

        // Update button inside custom schedule modal
        $('#custom_shedule_modal .saveCustomModalData').on('click', function () {
            repeatedList = [];

            $('.selected-list-inner .selected-list-item').each(function () {
                const date = $(this).find('.selected').text().trim();
                const time = $(this).find('input.time-range-picker').val();
                const id = $(this).attr('id');
                if (date && time) {
                    repeatedList.push({ id , date, time });
                }
            });

            updateCustomInputVal();
            renderRepeatedList();
            $('#custom_shedule_modal').modal('hide');
        });

        // Delete from repeated list
        $(document).on('click', '.delete-item', function (e) {
            e.preventDefault();
            const id = Number($(this).data('id'));
            const item = repeatedList.find(item => Number(item.id) == id);
            repeatedList = repeatedList.filter(item => Number(item.id) !== id);
            duplicateSelectedDates.delete(item.date);
            $(`div[data-date="${item.date}"]`).removeClass('active');
            $('.selected-list-inner').find('#' + id).remove();
            updateCustomInputVal();
            renderRepeatedList();
            updateCustomSurgeCount();
        });

        // Edit from repeated list
        $(document).on('click', '.edit-item', function (e) {
            e.preventDefault();
            const id = Number($(this).data('id'));
            const item = repeatedList.find(item => Number(item.id) === id);
            if (!item) return;

            $('#custom_shedule_modal').modal('show');

            const $container = $('.pricing-schedule-calendar-wrapper');
            const $calendarDays = $container.find('.days');
            const $selectedList = $container.find('.selected-list-inner');

            $calendarDays.find('div[data-date]').removeClass('active');
            $selectedList.empty();

            repeatedList.forEach((entry) => {
                const $item = $(`
                    <div id="${entry.id}" class="selected-list-item d-flex flex-sm-nowrap flex-wrap align-items-center justify-content-between justify-content-md-start gap-3 bg-light rounded py-2 px-2">
                        <span class="fs-14 text-dark text-nowrap after-date">
                            <span class="display-selected">
                                <p class="selected m-0 p-0">${entry.date}</p>
                            </span>
                        </span>
                        <div class="d-flex align-items-center gap-3 flex-grow-1">
                            <div class="position-relative cursor-pointer flex-grow-1">
                                <i class="bi bi-clock icon-absolute-on-right fs-12"></i>
                                <input type="text" class="form-control time-range-picker ltr text-start position-relative fs-14 h-32px bg-white" value="${entry.time}" name="time_range[]" placeholder="Select Time" readonly>
                            </div>
                            <button type="button" class="removeDay text-danger btn p-0 border-0">
                                <i class="tio-clear-circle-outlined fs-20"></i>
                            </button>
                        </div>
                    </div>
                `);

                $calendarDays.find(`div[data-date="${entry.date}"]`).addClass('active');

                $item.find('.removeDay').on('click', function () {
                    $item.remove();
                    repeatedList = repeatedList.filter(d => d.id !== entry.id);
                    $calendarDays.find(`div[data-date="${entry.date}"]`).removeClass("active");
                    updateCustomInputVal();
                    renderRepeatedList();
                });

                $selectedList.append($item);
                initTimeRangePicker($item.find('.time-range-picker'));

                if (entry.id == id) {
                    setTimeout(() => {
                        $item[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                        $item.find('.time-range-picker').focus();
                    }, 300);
                }
            });
        });

        // --- Surge Time Count Calculation ---
        function calculateDateRangeDays(start, end) {
            return moment(end).diff(moment(start), 'days') + 1;
        }

        // DailyContent
        function updateDailySurgeCount() {
            const $daily = $('.dailyContent');
            const range = $daily.find('.date-range-picker').val();
            if (range.includes(' - ')) {
                const [start, end] = range.split(' - ');
                const days = calculateDateRangeDays(moment(start, 'MM/DD/YYYY'), moment(end, 'MM/DD/YYYY'));
                $daily.find('.surge_time_count').text(`${days} time${days > 1 ? 's' : ''}`);
            }
        }

        // CustomContent
        function updateCustomSurgeCount() {
            const $custom = $('.customContent');
            const count = repeatedList.length;
            $custom.find('.surge_time_count').text(`${count} time${count > 1 ? 's' : ''}`);
        }

        // WeeklyContent
        function updateWeeklySurgeCount() {
            const $modal = $('.modal.show'); // currently open modal
            const $weekly = $('.weeklyContent');
            const $assignAllTime = $modal.find('.assignAllTime');
            const $wrapper = $weekly.find('.surge_time_count_wrapper');

            if ($assignAllTime){
                if ($assignAllTime.is(':checked')) {
                    $wrapper.hide();
                    return;
                } else {
                    $wrapper.show();
                }
            }

            const selectedDays = $modal.find('input[type="checkbox"][name="for_whom[]"]:checked')
                .map(function () { return $(this).val().toLowerCase(); })
                .get();
            const range = $modal.find('.date-range-picker').val();
            if (range){
                if (!range || !range.includes(' - ') || selectedDays.length === 0) {
                    $weekly.find('.surge_time_count').text(`0 time`);
                    return;
                }


                const [start, end] = range.split(' - ');
                let count = 0;
                let current = moment(start, 'MM/DD/YYYY');
                const last = moment(end, 'MM/DD/YYYY');

                while (current.isSameOrBefore(last, 'day')) {
                    const dayName = current.format('dddd').toLowerCase();
                    if (selectedDays.includes(dayName)) count++;
                    current.add(1, 'day');
                }

                $weekly.find('.surge_time_count').text(`${count} time${count > 0 ? 's' : ''}`);
            }

        }


        // Event bindings
        $('.dailyContent .date-range-picker').on('apply.daterangepicker', updateDailySurgeCount);
        $(document).on('click', '#custom_shedule_modal .saveCustomModalData', updateCustomSurgeCount);
        $(document).find('.weekly-days-modal .date-range-picker').on('apply.daterangepicker', function(ev, picker) {
            updateWeeklySurgeCount();
        });

        $(document).on('change', 'input[type="checkbox"][name="for_whom[]"]', function () {
            updateWeeklySurgeCount();
        });

        $(document).on('change', '.assignAllTime', function () {
            updateWeeklySurgeCount();
        });

        $('#surge-price-offcanvas').on('hidden.bs.offcanvas', function () {
            $('.selected-list-inner').empty();
        });

        // Initial calls
        // updateDailySurgeCount();
        // updateCustomSurgeCount();
        // updateWeeklySurgeCount();
        renderRepeatedList();
        if (window.formId)
        {
            const $assignAllTime = $('#selectWeeklyDaysModal-' + window.formId).find('.assignAllTime');
            const $wrapper = $('.weeklyContent').find('.surge_time_count_wrapper');
            if ($assignAllTime){
                if ($assignAllTime.is(':checked')) {

                    $wrapper.hide();
                    return;
                } else {
                    $wrapper.show();
                }
            }
        }
    });

    $('.date_range_calender_icon.pointer-events-auto').on('click', function() {
        var inputField = $(this).closest('.position-relative').find('input');
        inputField.click();
    });

})(jQuery);
