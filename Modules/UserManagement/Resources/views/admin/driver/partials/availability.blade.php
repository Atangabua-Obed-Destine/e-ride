<div class="tab-pane fade active show" role="tabpanel">
    @php
        $dayKeys = [0 => 'sunday', 1 => 'monday', 2 => 'tuesday', 3 => 'wednesday', 4 => 'thursday', 5 => 'friday', 6 => 'saturday'];
        $schedules = $otherData['availabilitySchedules'] ?? collect();
        $sameTime = $otherData['sameTimeForEveryDay'] ?? false;
    @endphp
    <div class="card mt-20">
        <div class="card-body">
            <div class="row justify-content-between align-items-center g-3 mb-20">
                <div class="col-lg-8 col-md-7">
                    <h4 class="mb-1 fs-16">{{ translate('Set specific time for Driver Availability') }}</h4>
                    <p class="fs-14 mb-0 text-muted">{{ translate('Here you setup driver individual active time or same time for every day.') }}</p>
                </div>
                <div class="col-lg-4 col-md-5">
                    <label class="border text-dark bg-white rounded d-flex justify-content-between align-items-center gap-2 py-2 px-3 min-h-40px fs-14 mb-0"
                           data-bs-toggle="modal" data-bs-target="#sameTimeEveryDayModal">
                        {{ translate('Same Time for Every Day') }}
                        <label class="switcher mb-0">
                            <input class="switcher_input" type="checkbox" id="same_time_every_day" {{ $sameTime ? 'checked' : '' }}>
                            <span class="switcher_control"></span>
                        </label>
                    </label>
                </div>
            </div>

            <div class="bg-light rounded-10 p-2 p-sm-3 schedule_section" id="scheduleSection">
                @foreach($dayKeys as $dayIndex => $dayName)
                    @php
                        $slots = $sameTime ? ($schedules->get(0) ?? collect()) : ($schedules->get($dayIndex) ?? collect());
                        $isLocked = $sameTime && $dayIndex != 0;
                    @endphp
                    <div class="schedule-item flex-wrap gap-3 d-flex align-items-center p-xxl-4 p-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                        <span class="w-120px text-dark fw-medium text-capitalize">{{ translate($dayName) }}</span>
                        <div class="schedult-date-content d-flex flex-wrap align-items-center gap-20">
                            @forelse($slots as $slot)
                                @php
                                    $start12 = \Carbon\Carbon::parse($slot->start_time)->format('h:i A');
                                    $end12 = \Carbon\Carbon::parse($slot->end_time)->format('h:i A');
                                @endphp
                                <span class="time-slot-pill d-inline-flex align-items-center border bg-white py-2 px-3 rounded position-relative {{ $isLocked ? 'disabled-shedule' : '' }}">
                                    <span class="start--time">
                                        <span class="info text-nowrap text-dark">{{ $start12 }} - {{ $end12 }}</span>
                                    </span>
                                    @unless($isLocked)
                                        <span class="dismiss--date dismiss--date-absolute delete-schedule" role="button"
                                              data-id="{{ $slot->id }}"
                                              data-bs-toggle="modal" data-bs-target="#deleteScheduleModal">
                                            <i class="tio-clear-circle-outlined text-danger fs-18"></i>
                                        </span>
                                    @endunless
                                </span>
                            @empty
                                <span class="btn btn-sm fw-semibold text-danger bg-danger bg-opacity-10">
                                    {{ translate('Off Day') }}
                                </span>
                            @endforelse
                            @unless($isLocked)
                                <div class="add-button">
                                    <span class="btn btn-primary d-center w-24px h-24px min-h-24px px-1 py-1 mt-0 add-slot-btn"
                                          data-day="{{ $dayIndex }}" data-bs-toggle="offcanvas" data-bs-target="#addshedule_offcanvas" role="button">
                                        <i class="tio-add"></i>
                                    </span>
                                </div>
                            @endunless
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

@push('script')
    <script>
        (function () {
            const STORE_URL = "{{ route('admin.driver.availability-schedule.store', $commonData['driver']->id) }}";
            const SAME_TIME_URL = "{{ route('admin.driver.same-time-for-every-day.update', $commonData['driver']->id) }}";
            const DESTROY_URL = "{{ route('admin.driver.availability-schedule.destroy', ['id' => $commonData['driver']->id, 'scheduleId' => '__SLOT__']) }}";
            const CSRF = "{{ csrf_token() }}";
            const MSG = {
                bothTimes: @json(translate('Please provide both start and end time')),
                order: @json(translate('Start time must be before end time')),
                generic: @json(translate('Something went wrong. Please try again')),
            };

            const checkbox = document.getElementById('same_time_every_day');
            let targetId = null;
            let currentDay = null;
            let sameTimeConfirmed = false;

            function request(url, method, data, onError) {
                $.ajax({
                    url: url,
                    method: method,
                    headers: { 'X-CSRF-TOKEN': CSRF },
                    data: data,
                    success: function (res) {
                        toastr.success(res.message);
                        setTimeout(function () { location.reload(); }, 1000);
                    },
                    error: function (xhr) {
                        let msg = MSG.generic;
                        if (xhr.responseJSON) {
                            if (xhr.responseJSON.message) {
                                msg = xhr.responseJSON.message;
                            } else if (xhr.responseJSON.errors) {
                                const first = Object.values(xhr.responseJSON.errors)[0];
                                msg = Array.isArray(first) ? first[0] : first;
                            }
                        }
                        toastr.error(msg);
                        if (typeof onError === 'function') onError();
                    },
                });
            }

            document.querySelectorAll('.add-slot-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    currentDay = parseInt(this.dataset.day, 10);
                });
            });
            const addBtn = document.getElementById('addSlotConfirmBtn');
            if (addBtn) {
                addBtn.addEventListener('click', function () {
                    const start = document.getElementById('addSlotStart').value;
                    const end = document.getElementById('addSlotEnd').value;
                    if (!start || !end) { toastr.error(MSG.bothTimes); return; }
                    if (start >= end) { toastr.error(MSG.order); return; }
                    request(STORE_URL, 'POST', { day: currentDay, start_time: start, end_time: end });
                });
            }

            document.querySelectorAll('#scheduleSection .delete-schedule').forEach(function (x) {
                x.addEventListener('click', function () {
                    targetId = this.dataset.id;
                });
            });
            const deleteBtn = document.getElementById('deleteScheduleConfirmBtn');
            if (deleteBtn) {
                deleteBtn.addEventListener('click', function () {
                    if (!targetId) return;
                    request(DESTROY_URL.replace('__SLOT__', targetId), 'DELETE', {});
                });
            }

            const sameTimeBtn = document.getElementById('sameTimeConfirmBtn');
            if (sameTimeBtn) {
                sameTimeBtn.addEventListener('click', function () {
                    sameTimeConfirmed = true;
                    request(SAME_TIME_URL, 'POST', { same_time_for_every_day: checkbox.checked ? 1 : 0 }, function () {
                        checkbox.checked = !checkbox.checked;
                    });
                });
            }
            const sameTimeModal = document.getElementById('sameTimeEveryDayModal');
            if (sameTimeModal) {
                sameTimeModal.addEventListener('hidden.bs.modal', function () {
                    if (!sameTimeConfirmed) { checkbox.checked = !checkbox.checked; }
                    sameTimeConfirmed = false;
                });
            }
        })();
    </script>
@endpush
