<form
    action="{{route('admin.business.setup.parcel-refund.reason.update', ['id' => $parcelRefundReason?->id])}}"
    method="post" id="updateForm" class="d-none">
    @csrf
    <div class="modal-header border-0">
        <button type="button" class="btn-close outline-none shadow-none" data-bs-dismiss="modal"
                aria-label="Close">
        </button>
    </div>
    <div class="modal-body py-0 mt-0">
        <h5 class="mb-2">{{ translate('Parcel_Refund_Reasons') }}</h5>
        <div class="fs-14 pb-4">
            {{ translate('Specify the refund reasons that customers can choose when submitting a refund request') }}
        </div>
        <div class="p-4 rounded bg-F6F6F6">
            <label for="" class="form-label">{{ translate('Parcel_Refund_Reasons') }}
            </label>
            <div class="character-count">
                <textarea id="title" name="title"
                          class="form-control character-count-field" cols="30" rows="1"
                          placeholder="{{ translate('Ex: Received product broken') }}"
                          maxlength="200" data-max-character="200" required>{{$parcelRefundReason?->title}}</textarea>
                <span class="d-flex justify-content-end mt-1 text-muted fw-medium">{{translate('0/200')}}</span>
            </div>
        </div>
    </div>
    <div class="modal-footer border-0">
        <button type="button" class="btn btn-secondary btn-lg"
                data-bs-dismiss="modal">{{ translate('cancel') }}</button>
        <button type="submit" class="btn btn-primary btn-lg">{{ translate('update') }}</button>
    </div>
</form>

