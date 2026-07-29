<div class="modal-header border-0 gap-2">
    <h4 class="offcanvas-title flex-grow-1">
        {{translate('Send Notification Short view')}}
    </h4>
    <button type="button" class="btn-close" data-bs-dismiss="modal">
    </button>
</div>
<div class="modal-body pt-0 pb-2">
    <div class="mx-auto">
        <div class="text-center">
            <img src=" {{ onErrorImage(
                                                $sendNotification?->image,
                                                dynamicStorage('storage/app/public/push-notification') . '/' . $sendNotification?->image,
                                                dynamicAsset('public/assets/admin-module/img/media/banner-upload-file.png'),
                                                'push-notification/',
                                            ) }}" class="aspect-ratio-2-1 max-w300 object-cover rounded dark-support" alt="">
        </div>
        <div class="card border-0 mt-3">
            <div class="card-header">
                <div>
                    <h6 class="fs-14 mb-1">{{ translate('Targeted User') }}</h6>
                    <p class="fs-12 mb-0">{{ implode(', ', $sendNotification->targeted_users) }}</p>
                </div>
            </div>
            <div class="card-body">
                <div class="mb-20">
                    <h6 class="fs-14 mb-1">{{ translate('title') }}</h6>
                    <p class="fs-12 mb-0">{{ $sendNotification->name }}</p>
                </div>
                @if(!is_null($sendNotification->description))
                    <div>
                        <h6 class="fs-14 mb-1">{{ translate('description') }}</h6>
                        <p class="fs-12 mb-0">{{ $sendNotification->description }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
<div class="modal-footer border-0">
    <div class="btn--container justify-content-end">
        <button type="button" class="btn btn-light fw-semibold" id="modalCancelBtn" data-bs-dismiss="modal">
            {{translate('Cancel')}}
        </button>
        <a href="{{ route('admin.promotion.send-notification.resend', $sendNotification->id) }}" class="btn btn-primary fw-semibold {{ $sendNotification->is_active ? '' : 'disabled' }}" id="">{{translate('Resend')}}</a>
    </div>
</div>
