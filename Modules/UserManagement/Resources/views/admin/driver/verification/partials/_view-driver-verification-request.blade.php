<form action="{{ route('admin.driver.verification.mark-as-verified', $unverifiedDriverInfo->id) }}" class="d-flex flex-column h-100" method="POST" enctype="multipart/form-data">
    @csrf
    <?php
    use Illuminate\Support\Str;
    use Carbon\Carbon;
    $attemptDetails = collect($unverifiedDriverInfo->attempt_details ?? []);
    ?>
    <div class="offcanvas-header">
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        <h4 class="offcanvas-title flex-grow-1 text-center">
            {{ translate('Driver Verification Request') }}
        </h4>
    </div>
    <div class="offcanvas-body scrollbar-thin">
        <div class="row g-3">
            <div class="col-12">
                <div class="d-flex justify-content-between gap-3 flex-wrap">
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <img loading="lazy"
                             src="{{ dynamicAsset('public/assets/admin-module/img/avatar/avatar.png') }}"
                             class="rounded aspect-1 w-100px flex-shrink-0" alt="">
                        <div class="">
                            <h6 class="mb-2">{{ ($unverifiedDriverInfo->driver->first_name ?? '') . ' ' . ($unverifiedDriverInfo->driver->last_name ?? '') }}</h6>
                            <p class="fs-12 mb-2">ID #{{ $unverifiedDriverInfo->driver->id }}</p>
                            <p class="fs-12 mb-2">{{ $unverifiedDriverInfo->driver->phone }}</p>
                            <div
                                class="badge text-bg-success bg-opacity-10 text-body fs-12 fw-normal">{{ translate($unverifiedDriverInfo->driver->level->name) }}</div>
                        </div>
                    </div>
                    <div class="flex-shrink-0">
                        <div
                            class="badge {{ $unverifiedDriverInfo->current_status == 'skipped' ? 'text-bg-info text-info' : 'text-bg-danger text-danger' }}  bg-opacity-10">{{ translate('Verification '. $unverifiedDriverInfo->current_status) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                    <h5 class="mb-3">{{ translate('Verification Info') }}</h5>

                    <div class="bg-white rounded p-3 mb-20">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                            <span class="text-dark opacity-75">{{ translate('Total Attempt Made') }}</span>
                            <span><strong>{{ $attemptDetails->count() ?? 0 }} {{ translate(Str::plural('time', $attemptDetails->count())) }}</strong></span>
                        </div>
                    </div>
                    @if($attemptDetails->last()['reason'] ?? null)
                        <div class="bg-white rounded p-3">
                            <div
                                class="text-dark opacity-75 mb-2">{{ translate('Verification Failed Reason') }}</div>
                            <div class="bg-danger bg-opacity-10 text-dark fw-medium rounded p-3">
                                {{ translate($attemptDetails->last()['reason']) }}
                            </div>
                        </div>
                    @endif
                </div>
            </div>
            <div class="col-12">
                <?php
                $maxSize = readableUploadMaxFileSize('image');
                ?>
                <div class="p-lg-4 p-3 rounded bg-F6F6F6 d-flex flex-column justify-content-around gap-3 h-100">
                    <div>
                        <h5 class="mb-2">
                            {{ translate('Upload Driver Image') }}
                        </h5>
                        <p class="mb-0">
                            {{ translate('To mark a driver as verified, you may upload their documents or proceed without uploading and verify them.') }}
                        </p>
                    </div>

                    <div class="upload-file-new">
                        <input type="file" class="upload-file-new__input single_file_input" name="image"
                               accept="{{ IMAGE_ACCEPTED_EXTENSIONS }}" data-max-upload-size="{{ $maxSize }}">
                        <label class="upload-file-new__wrapper ratio-1-1">
                            <div class="upload-file-new-textbox text-center">
                                <img width="34" height="34" class="svg"
                                     src="{{ dynamicAsset('public/assets/admin-module/img/document-upload.svg') }}"
                                     alt="image upload">
                                <h6 class="mt-2 fw-medium text-center text-info mb-0">{{ translate('Add') }}</h6>
                            </div>
                            <img class="upload-file-new-img"
                                 loading="lazy"
                                 src="{{ onErrorImage(
                                                                        $unverifiedDriverInfo->driver->driverDetails?->verified_image,
                                                                        dynamicStorage('storage/app/public/driver/face-verification/verified/') . '/' . $unverifiedDriverInfo->driver->driverDetails?->verified_image,
                                                                        '',
                                                                        'driver/face-verification/verified/',
                                                                    ) }}"
                                 data-default-src="{{ onErrorImage(
                                                                        $unverifiedDriverInfo->driver->driverDetails?->verified_image,
                                                                        dynamicStorage('storage/app/public/driver/face-verification/verified/') . '/' . $unverifiedDriverInfo->driver->driverDetails?->verified_image,
                                                                        '',
                                                                        'driver/face-verification/verified/',
                                                                    ) }}"
                                 alt="verified-image">
                        </label>
                        <div class="overlay">
                            <div class="d-flex gap-10 justify-content-center align-items-center h-100">
                                <button type="button" class="btn btn-outline-info icon-btn edit_btn">
                                    <i class="bi bi-camera"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <p class="text-dark fs-10 text-center">
                                <span
                                    class="opacity-75">{{ translate(key: '{format}, Image Size - Max {imageSize}', replace: ['format' => IMAGE_ACCEPTED_EXTENSIONS, 'imageSize' => $maxSize]) }}</span>
                        <span>(3:1)</span>
                    </p>
                </div>
            </div>
            @if($attemptDetails->count())
                <div class="col-12">
                    <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                        <h5 class="mb-2">{{ translate('Attempt Date & Time') }}</h5>
                        <p class="fs-12 mb-3">{{ translate('Driver All  Attempt Date & Time Will be shown here') }}</p>

                        <div class="table-responsive">
                            <table class="table table-borderless align-middle text-dark text-nowrap mb-0">
                                <thead class="bg-white align-middle text-capitalize fw-normal">
                                <tr>
                                    <th>{{ translate('Attempt Date & Time ') }}</th>
                                    <th class="text-center">{{ translate('Verification Status') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($attemptDetails->sortByDesc('time')->values() as $attemptDetail)
                                    <tr>
                                        <td>{{ Carbon::parse($attemptDetail['time'])->format('Y-m-d h:i A') }}</td>
                                        <td class="text-center">
                                            <div
                                                class="badge text-bg-danger bg-opacity-10 text-danger fw-normal">{{ translate('Failed') }}</div>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
    <div
        class="offcanvas-footer d-flex gap-3 bg-white shadow position-sticky bottom-0 p-3 justify-content-center">
        <button type="button" class="btn btn-secondary fw-semibold mark-as-suspended"
                data-id="{{ $unverifiedDriverInfo->id }}"
                data-bs-toggle="modal" data-bs-target="#suspend-modal">
            {{translate('Marked As Suspended') }}
        </button>
        <button type="submit" class="btn btn-primary fw-semibold"
                data-bs-dismiss="offcanvas">
            {{translate('Marked As Verified') }}
        </button>
    </div>
</form>
