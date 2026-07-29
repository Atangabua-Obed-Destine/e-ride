<form
    action="{{ route('admin.business.setup.safety-precaution.safety-alert-reason.update',  $safetyAlertReason?->id) }}"
    method="post" id="updateForm" class="">
    @csrf
    <div class="modal-header border-0">
        <button type="button" class="btn-close outline-none shadow-none" data-bs-dismiss="modal"
                aria-label="Close">
        </button>
    </div>
    <div class="modal-body pt-0">
        <div class="mb-20">
            <h5 class="mb-2">{{ translate('Edit Safety Alert Reason') }}</h5>
            <div class="fs-12">
                {{ translate('Here you can set Safety Alert Reason') }}
            </div>
        </div>
        <div class="rounded bg-F6F6F6">
            <div class="row g-3">
                <div class="col-md-12">
                    <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                        <div class="row gy-3 align-items-start">
                            <div class="col-sm-6 ">
                                <label for="updateTitle" class="mb-3 d-flex align-items-center fw-medium gap-2">
                                    {{ translate('Safety Alert Reasons') }}
                                    <span class="text-danger">*</span>
                                </label>
                                <div class="character-count">
                                    <input id="updateTitle" name="reason" type="text" value="{{ $safetyAlertReason->reason }}" placeholder="{{ translate('Ex : Type your safety alert reason') }}" class="form-control character-count-field" maxlength="150" data-max-character="150" required="" tabindex="20">
                                    <span class="mt-1 d-block text-end text-muted">{{ translate('0/150') }}</span>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <label for="updateReasonForWhom" class="mb-3 d-flex align-items-center fw-medium gap-2">
                                    {{ translate('User Type') }}<span class="text-danger">*</span>
                                </label>
                                <select class="js-select cmn_focus" id="updateReasonForWhom"  name="reason_for_whom" required="" tabindex="21">
                                    <option value="" disabled="" >{{ translate('Select user type') }}</option>
                                    <option value="{{ DRIVER }}" {{ $safetyAlertReason->reason_for_whom == DRIVER ? 'selected' : '' }}>{{ translate(DRIVER) }}</option>
                                    <option value="{{ CUSTOMER }}" {{ $safetyAlertReason->reason_for_whom == CUSTOMER ? 'selected' : '' }}>{{ translate(CUSTOMER) }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <div class="d-flex gap-3 justify-content-end">
            <button class="btn btn-light h-40px min-w-100px justify-content-center fw-semibold cmn_focus"
                    data-bs-dismiss="modal" type="button">{{ translate('Cancel') }}</button>
            <button type="submit"
                    class="btn btn-primary h-40px min-w-100px justify-content-center fw-semibold cmn_focus">{{ translate('Update') }}</button>
        </div>
    </div>
</form>
