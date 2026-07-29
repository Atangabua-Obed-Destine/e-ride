<form class="d-flex flex-column h-100" action="{{ route('admin.promotion.send-notification.update', $sendNotification->id) }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    <div class="offcanvas-header">
        <h4 class="offcanvas-title flex-grow-1">
            {{translate('Edit Send Notification')}}
        </h4>
        <div class="d-flex gap-3 align-items-center">
            <button type="submit" name="update_and_resend" value="1" class="btn btn-outline-primary px-3 {{ $sendNotification->is_active ? '' : 'disabled' }}"><i class="bi bi-arrow-clockwise"></i> {{translate('Resend')}}</button>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
    </div>
    @php
        $maxSize = readableUploadMaxFileSize('image');
    @endphp
    <div class="offcanvas-body scrollbar-thin">
        <div class="mb-4">
            <div class="bg-light p-20 rounded mb-20">
                <div class="d-flex flex-column justify-content-around gap-3 w-100">
                    <div>
                        <h6 class="mb-1">{{ translate('Image') }}</h6>
                        <p class="fs-12 mb-0">{{ translate('Upload your cover Image') }}</p>
                    </div>

                    <div class="d-flex justify-content-center">
                        <div class="upload-file auto profile-image-upload-file">
                            <input type="file" name="image" class="upload-file__input" accept="{{ IMAGE_ACCEPTED_EXTENSIONS }}"
                                   data-max-upload-size="{{ $maxSize }}">
                            <div
                                class="upload-file__img bg-white border-gray d-flex justify-content-center align-items-center h-100px aspect-ratio-2-1 p-0">
                                <div class="upload-file__textbox text-center" style="display: {{ is_null($sendNotification->image) ? 'block' : 'none' }}">
                                    <div class="d-flex gap-1 align-items-center flex-wrap">
                                        <img width="34" height="34"
                                             src="{{ dynamicAsset('public/assets/admin-module/img/document-upload-2.png') }}" alt=""
                                             class="svg">
                                        <h6 class="fw-semibold fs-10">
                                            <span class="text-info mb-1">{{ translate('Click to upload') }}</span>
                                            <br>
                                            {{ translate('or drag and drop') }}
                                        </h6>
                                    </div>
                                </div>
                                <img class="upload-file__img__img h-100" width="180" height="180" src="{{ onErrorImage(
                                                $sendNotification?->image,
                                                dynamicStorage('storage/app/public/push-notification') . '/' . $sendNotification?->image,
                                                dynamicAsset('public/assets/admin-module/img/media/banner-upload-file.png'),
                                                'push-notification/',
                                            ) }}" loading="lazy" alt="" style="display: {{ is_null($sendNotification->image) ? 'none' : 'block' }}">
                            </div>
                            <a href="javascript:void(0)" class="remove-img-icon {{ is_null($sendNotification->image) ? 'd-none' : '' }}">
                                <i class="tio-clear"></i>
                            </a>
                        </div>
                    </div>
                    <p class="opacity-75 mx-auto fs-10">
                        {{ translate(key: 'File Format - {format}, Image Size - Maximum {imageSize}, Image Ratio - {ratio}', replace: ['format' => IMAGE_ACCEPTED_EXTENSIONS, 'imageSize' => $maxSize, 'ratio' => '2:1']) }}
                    </p>
                </div>
            </div>
            <div class="bg-light p-20 rounded h-100">
                <div class="mb-20 character-count">
                    <label for="business_address" class="mb-2 form-label">
                        {{ translate('Tittle') }}
                        <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip"
                           data-bs-title="{{ translate('Keep it short and catchy. Maximum 100 characters') }}"></i>
                    </label>
                    <textarea name="name" id="" rows="1" class="form-control min-h-45px pt-2 character-count-field"
                              placeholder="{{ translate('Type Title') }}" maxlength="100" data-max-character="100">{{ $sendNotification->name }}</textarea>
                    <span class="d-flex justify-content-end">0/100</span>
                </div>
                <div class="mb-20 character-count">
                    <label for="business_address" class="mb-2 form-label">
                        {{ translate('Description') }}
                        <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip"
                           data-bs-title="{{ translate('Provide more context about the notification. Maximum 200 characters') }}"></i>
                    </label>
                    <textarea name="description" id="" rows="1" class="form-control min-h-45px pt-2 character-count-field"
                              placeholder="{{ translate('Type about the description') }}" maxlength="200" data-max-character="200">{{ $sendNotification->description }}</textarea>
                    <span class="d-flex justify-content-end">0/200</span>
                </div>
                <div class="mb-0 character-count">
                    <label for="business_address" class="mb-2 form-label">
                        {{ translate('Targeted user') }}
                    </label>
                    <select name="targeted_users[]" id="" class="js-select-offcanvas" data-placeholder="{{translate('select_users')}}" multiple="multiple" required>
                        <option value="customers" {{ in_array('customers', $sendNotification->targeted_users) ? 'selected' : '' }}>Customers</option>
                        <option value="drivers" {{ in_array('drivers', $sendNotification->targeted_users) ? 'selected' : '' }}>Drivers</option>
                    </select>
                </div>
            </div>
        </div>
    </div>
    <input type="hidden" id="oldImage" name="old_image" value="{{ $sendNotification->image }}">
    <div
        class="offcanvas-footer d-flex gap-3 bg-white shadow position-sticky bottom-0 p-3 justify-content-center">
        <button type="reset" class="btn btn-light fw-semibold flex-grow-1">
            {{translate('Reset')}}</button>
        <button type="submit" class="btn btn-primary fw-semibold flex-grow-1">
            {{translate('Submit') }}
        </button>
    </div>
</form>
<script>
    (function () {
        $('#edit_notification_offcanvas .character-count-field').each(function () {
            $(this).trigger('change');
        });
    })();
</script>
