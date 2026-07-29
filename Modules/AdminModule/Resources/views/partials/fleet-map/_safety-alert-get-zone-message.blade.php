@if($safetyAlertCount)
    <div class="alert alert-danger m-0 zone-message" role="alert">
        <div class="d-flex gap-2 align-items-center">
            <div class="flex-grow-1 gap-4">
                {{ translate('You have') }}
                <span
                    class="fw-semibold">{{ $safetyAlertCount . translate(' Safety Alert') }}</span>
                {{ translate(' arrived for this zone. To see the list click on') }}
                <span class="fw-semibold">{{ translate('Safety Alert') }}</span>
                {{ translate('button. Click') }}
                <span class="fw-semibold">{{ translate('Zone Selection') }}</span>
                {{ translate('button to check other zone safety status.') }}
            </div>
            <div class="flex-shrink-0 zone-message-hide cursor-pointer">
                <i class="bi bi-x-lg"></i>
            </div>
        </div>
    </div>
@endif
