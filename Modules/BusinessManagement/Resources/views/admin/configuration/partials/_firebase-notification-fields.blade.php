@foreach($notifications as $group => $groupNotifications)
    <div class="card border-0 mb-3">
        <div class="card-header">
            <h5 class="text-capitalize mb-2">{{ translate($group) }}</h5>
            <div class="fs-12">
               {{translate(NOTIFICATION_SUBTITLE[$group. '_' . convertToSnakeCaseIfNeeded($type)])}}
            </div>
        </div>

        <div class="card-body p-30">
            <div class="row gap-3 gap-md-0">
                @foreach($groupNotifications as $notification)
                    <div class="col-lg-6">
                        <div class="mb-30">
                            <div
                                class="d-flex justify-content-between gap-3 align-items-center mb-3">
                                <label for="{{ $group . '_' . $notification->name }}"
                                       class="light-text">{{ translate($notification->name) }}
                                    @if(!is_null($notification->dynamic_values))
                                        <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip" data-bs-title="{{ translate('Use the following keywords to insert the dynamic values in this field: ') . implode(', ', $notification->dynamic_values)}}"></i>
                                    @endif
                                     </label>

                                <label class="switcher">
                                    <input class="switcher_input" type="checkbox"
                                           name="notification[{{ $group . '_' . $notification->name }}][status]"
                                        {{ $notification?->status == 1 ? "checked" : "" }}>
                                    <span class="switcher_control"></span>
                                </label>
                            </div>
                            <textarea
                                name="notification[{{ $group . '_' . $notification->name }}][value]" tabindex="1"
                                id="{{ $group . '_' . $notification->name }}" rows="4"
                                class="form-control color-border-focus {{ $notification?->value ? 'color-border' : '' }} fw-medium"
                                placeholder="Type Here ...">{{ $notification?->value }}</textarea>
                        </div>
                    </div>

                    <input type="hidden"
                           name="notification[{{ $group . '_' . $notification->name }}][group]"
                           value="{{ $group }}">
                    <input type="hidden"
                           name="notification[{{ $group . '_' . $notification->name }}][name]"
                           value="{{ $notification->name }}">
                @endforeach
            </div>
        </div>
    </div>
@endforeach
