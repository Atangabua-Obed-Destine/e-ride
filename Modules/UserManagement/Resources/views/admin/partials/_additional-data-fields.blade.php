@php
    use Modules\BusinessManagement\Lib\AdditionalDataFieldNormalizer;
    $additionalDataFields = $additionalDataFields ?? [];
    $additionalData = $additionalData ?? [];
    $additionalFileBaseUrl = dynamicStorage('storage/app/public/user/additional-data');
    $additionalImageMaxSize = readableUploadMaxFileSize('image');
    $additionalFileMaxSize = readableUploadMaxFileSize('file');
@endphp

@if(!empty($additionalDataFields))
    <div class="card mt-3">
        <div class="card-body">
            <h5 class="text-primary text-uppercase mb-4">{{ translate('Additional Data') }}</h5>
            <div class="row align-items-start">
                @foreach($additionalDataFields as $field)
                    @php
                        $field = AdditionalDataFieldNormalizer::normalizeField((array) $field);
                        $title = $field['title'];
                        $type = $field['type'];
                        $label = AdditionalDataFieldNormalizer::humanizeFieldKey($title);
                        $isRequired = (int) ($field['is_required'] ?? 0) === 1;
                        $value = old("additional_data.$title", $additionalData[$title] ?? null);
                        $placeholder = $field['placeholder'] ?? '';
                        $errorKey = "additional_data.$title";
                    @endphp

                    @if($type === 'file')
                        @php
                            $existingFiles = is_array($additionalData[$title] ?? null) ? $additionalData[$title] : [];
                            $accept = [];
                            if (in_array('image', $field['file_format'] ?? [], true)) {
                                $accept[] = IMAGE_ACCEPTED_EXTENSIONS;
                            }
                            if (in_array('pdf', $field['file_format'] ?? [], true)) {
                                $accept[] = '.pdf';
                            }
                            if (in_array('document', $field['file_format'] ?? [], true)) {
                                $accept[] = '.doc, .docx';
                            }
                            $fileQuantity = max(1, min(5, (int) ($field['quantity'] ?? 1)));
                        @endphp
                        <div class="col-12">
                            <div class="mb-4 additional-data-file-upload"
                                 data-field-title="{{ $title }}"
                                 data-max-quantity="{{ $fileQuantity }}">
                                <div class="pb-3">
                                    <h5 class="">
                                        {{ $label }} @if($isRequired)
                                            <span class="text-danger">*</span>
                                        @endif
                                    </h5>
                                    <p class="opacity-75">
                                        {{ translate(key: 'File Format - {format}, Image Size - Maximum {imageSize}, File Size - Maximum {fileSize}', replace: ['format' => implode(', ', $accept), 'imageSize' => $additionalImageMaxSize, 'fileSize' => $additionalFileMaxSize]) }}
                                    </p>
                                </div>
                                <div class="d-flex flex-wrap gap-3 other-documents-wrapper">
                                    @if(!empty($existingFiles))
                                        @foreach($existingFiles as $file)
                                            <div class="show-image cmn_focus rounded-10">
                                                <div
                                                    class="file__value bg-transparent border border-C5D2D2 remove_outside"
                                                    data-document="{{ $file }}">
                                                    <img class="file__value--icon" src="{{ getExtensionIcon($file) }}"
                                                         alt="">
                                                    <a class="file__value--text"
                                                       href="{{ $additionalFileBaseUrl . '/' . $file }}"
                                                       target="_blank">{{ $file }}</a>
                                                    <div class="file__value--remove fw-bold"
                                                         data-id="{{ $file }}">
                                                        <img
                                                            src="{{ dynamicAsset('public/assets/admin-module/img/icons/close-circle.svg') }}"
                                                            alt="">
                                                    </div>
                                                    <input type="hidden" name="existing_additional_data[{{ $title }}][]"
                                                           value="{{ $file }}">
                                                </div>
                                            </div>
                                        @endforeach
                                    @endif
                                    <div
                                        class="d-flex flex-wrap gap-3 additional-selected-files-container d-none"></div>
                                    <div class="additional-input-data d-none"></div>
                                    <div class="upload-file file__input cmn_focus" id="file__input_{{ $title }}">
                                        <input type="file"
                                               id="additional_data_{{ $title }}"
                                               class="upload-file__input2 additional-upload-file-input"
                                               multiple="multiple"
                                               accept="{{ implode(', ', $accept) }}"
                                               data-max-upload-size="{{ $additionalImageMaxSize }}">
                                        <div class="upload-file__img2">
                                            <div class="upload-box rounded media gap-4 align-items-center p-4 px-lg-5">
                                                <i class="bi bi-cloud-arrow-up-fill fs-20"></i>
                                                <div class="media-body">
                                                    <p class="text-muted mb-2 fs-12">{{ translate('upload') }}</p>
                                                    <h6 class="fs-12 text-capitalize">{{ translate('file_or_image') }}</h6>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @error($errorKey)<span class="text-danger fs-12">{{ $message }}</span>@enderror
                            </div>
                        </div>
                        @continue
                    @endif

                    @if(in_array($type, ['text', 'number', 'date', 'email', 'phone'], true))
                        <div class="col-md-4">
                            <div class="mb-4">
                                <label class="mb-2" for="additional_data_{{ $title }}">
                                    {{ $label }} @if($isRequired)
                                        <span class="text-danger">*</span>
                                    @endif
                                </label>
                                <input type="{{ $type === 'phone' ? 'tel' : $type }}"
                                       id="additional_data_{{ $title }}"
                                       @if($type !== 'phone') name="additional_data[{{ $title }}]" @endif
                                       value="{{ is_scalar($value) ? $value : '' }}"
                                       class="form-control {{ $type === 'phone' ? 'w-100 text-dir-start additional-phone-input' : '' }}"
                                       placeholder="{{ $placeholder }}"
                                       @if($type === 'phone') pattern="[0-9]{1,14}"
                                       data-output-selector="#additional_data_{{ $title }}_hidden" @endif
                                       @if($isRequired) required @endif>
                                @if($type === 'phone')
                                    <input type="hidden"
                                           id="additional_data_{{ $title }}_hidden"
                                           name="additional_data[{{ $title }}]"
                                           value="{{ is_scalar($value) ? $value : '' }}">
                                @endif
                                @error($errorKey)<span class="text-danger fs-12">{{ $message }}</span>@enderror
                            </div>
                        </div>
                    @elseif($type === 'textarea')
                        <div class="col-md-4">
                            <div class="mb-4">
                                <label class="mb-2" for="additional_data_{{ $title }}">
                                    {{ $label }} @if($isRequired)
                                        <span class="text-danger">*</span>
                                    @endif
                                </label>
                                <textarea name="additional_data[{{ $title }}]"
                                          id="additional_data_{{ $title }}"
                                          class="form-control"
                                          placeholder="{{ $placeholder }}"
                                          rows="3"
                                          @if($isRequired) required @endif>{{ is_scalar($value) ? $value : '' }}</textarea>
                                @error($errorKey)<span class="text-danger fs-12">{{ $message }}</span>@enderror
                            </div>
                        </div>
                    @elseif($type === 'select')
                        <div class="col-md-4">
                            <div class="mb-4">
                                <label class="mb-2" for="additional_data_{{ $title }}">
                                    {{ $label }} @if($isRequired)
                                        <span class="text-danger">*</span>
                                    @endif
                                </label>
                                <select name="additional_data[{{ $title }}]"
                                        id="additional_data_{{ $title }}"
                                        class="js-select cmn_focus"
                                        @if($isRequired) required @endif>
                                    <option
                                        value="">{{ translate('select {option}', ['option' => AdditionalDataFieldNormalizer::humanizeFieldKey($title)]) }}</option>
                                    @foreach(($field['options'] ?? []) as $option)
                                        <option value="{{ $option }}" {{ $value == $option ? 'selected' : '' }}>
                                            {{ AdditionalDataFieldNormalizer::humanizeFieldKey($option) }}
                                        </option>
                                    @endforeach
                                </select>
                                @error($errorKey)<span class="text-danger fs-12">{{ $message }}</span>@enderror
                            </div>
                        </div>
                    @elseif($type === 'radio')
                        <div class="col-md-4">
                            <div class="mb-4">
                                <label class="mb-2 d-block">
                                    {{ $label }} @if($isRequired)
                                        <span class="text-danger">*</span>
                                    @endif
                                </label>
                                <div class="d-flex flex-wrap align-items-center gap-3 min-h-45px">
                                    @foreach(($field['options'] ?? []) as $optionIndex => $option)
                                        @php($radioId = 'additional_data_' . $title . '_radio_' . $optionIndex)
                                        <div class="custom-radio cmn_focus rounded-pill pe-1">
                                            <input type="radio"
                                                   id="{{ $radioId }}"
                                                   name="additional_data[{{ $title }}]"
                                                   value="{{ $option }}"
                                                   {{ $value == $option ? 'checked' : '' }}
                                                   @if($isRequired) required @endif>
                                            <label
                                                for="{{ $radioId }}">{{ AdditionalDataFieldNormalizer::humanizeFieldKey($option) }}</label>
                                        </div>
                                    @endforeach
                                </div>
                                @error($errorKey)<span class="text-danger fs-12">{{ $message }}</span>@enderror
                            </div>
                        </div>
                    @elseif($type === 'checkbox')
                        @php($selectedValues = is_array($value) ? $value : [])
                        <div class="col-md-4">
                            <div class="mb-4">
                                <label class="mb-2 d-block">
                                    {{ $label }} @if($isRequired)
                                        <span class="text-danger">*</span>
                                    @endif
                                </label>
                                <div class="d-flex flex-wrap align-items-center gap-3 min-h-45px">
                                    @foreach(($field['options'] ?? []) as $option)
                                        <label class="custom-checkbox d-flex align-items-center gap-2 m-0">
                                            <input type="checkbox"
                                                   name="additional_data[{{ $title }}][]"
                                                   value="{{ $option }}"
                                                {{ in_array($option, $selectedValues, true) ? 'checked' : '' }}>
                                            <span>{{ AdditionalDataFieldNormalizer::humanizeFieldKey($option) }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @error($errorKey)<span class="text-danger fs-12">{{ $message }}</span>@enderror
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
@endif

@push('script')
    <script>
        "use strict";

        document.querySelectorAll('.additional-data-file-upload').forEach(function (wrapper) {
            const input = wrapper.querySelector('.additional-upload-file-input');
            const selectedContainer = wrapper.querySelector('.additional-selected-files-container');
            const inputContainer = wrapper.querySelector('.additional-input-data');
            const uploadBox = wrapper.querySelector('.upload-file.file__input');
            const fieldTitle = wrapper.dataset.fieldTitle;
            const maxQuantity = parseInt(wrapper.dataset.maxQuantity || '1', 10);
            let selectedFiles = [];

            if (!input || !selectedContainer || !inputContainer || !uploadBox || !fieldTitle) {
                return;
            }

            updateUploadBoxVisibility();

            input.addEventListener('change', function (event) {
                const pendingFiles = [];
                Array.from(event.target.files || []).forEach(function (file) {
                    if (totalFileCount() >= maxQuantity) {
                        return;
                    }

                    if (typeof canAddUploadFiles === 'function' && !canAddUploadFiles([...pendingFiles, file], {form: input.form})) {
                        return;
                    }

                    pendingFiles.push(file);
                    selectedFiles.push(file);
                });

                renderAdditionalSelectedFiles();
                if (typeof getUploadPayloadSize === 'function') {
                    totalSize = getUploadPayloadSize({form: input.form});
                }
                input.value = '';
            });

            wrapper.addEventListener('click', function (event) {
                if (!event.target.closest('.file__value--remove')) {
                    return;
                }

                setTimeout(updateUploadBoxVisibility, 0);
            });

            function totalFileCount() {
                return wrapper.querySelectorAll('input[name^="existing_additional_data["]').length + selectedFiles.length;
            }

            function updateUploadBoxVisibility() {
                const isMaximumReached = totalFileCount() >= maxQuantity;
                uploadBox.classList.toggle('d-none', isMaximumReached);
                input.disabled = isMaximumReached;
                selectedContainer.classList.toggle('d-none', selectedFiles.length === 0);
            }

            function renderAdditionalSelectedFiles() {
                inputContainer.innerHTML = '';
                selectedContainer.innerHTML = '';

                selectedFiles.forEach(function (file, index) {
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'file';
                    hiddenInput.name = `additional_data[${fieldTitle}][${index}]`;
                    hiddenInput.hidden = true;

                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(new File([file], file.name, {type: file.type}));
                    hiddenInput.files = dataTransfer.files;
                    inputContainer.appendChild(hiddenInput);

                    const fileDiv = document.createElement('div');
                    fileDiv.classList.add('show-image');

                    const fileValueDiv = document.createElement('div');
                    fileValueDiv.classList.add('file__value', 'bg-transparent', 'border', 'border-C5D2D2', 'remove_outside');
                    fileValueDiv.setAttribute('data-document', file.name);

                    const fileIcon = document.createElement('img');
                    fileIcon.classList.add('file__value--icon');
                    fileIcon.src = getFileIcon(file.name);

                    const fileText = document.createElement('div');
                    fileText.classList.add('file__value--text');
                    fileText.textContent = file.name;

                    const removeButton = document.createElement('div');
                    removeButton.classList.add('file__value--remove', 'fw-bold');
                    removeButton.setAttribute('data-id', file.name);
                    removeButton.innerHTML = `<img src="{{ dynamicAsset('public/assets/admin-module/img/icons/close-circle.svg') }}" alt="">`;

                    fileValueDiv.appendChild(fileIcon);
                    fileValueDiv.appendChild(fileText);
                    fileValueDiv.appendChild(removeButton);
                    fileDiv.appendChild(fileValueDiv);
                    selectedContainer.appendChild(fileDiv);

                    removeButton.addEventListener('click', function () {
                        fileDiv.remove();
                        selectedFiles.splice(selectedFiles.indexOf(file), 1);
                        renderAdditionalSelectedFiles();
                        if (typeof getUploadPayloadSize === 'function') {
                            totalSize = getUploadPayloadSize({form: input.form});
                        }
                    });
                });

                updateUploadBoxVisibility();
            }
        });

        document.querySelectorAll('.additional-phone-input').forEach(function (input) {
            if (typeof initializePhoneInput !== 'function') {
                return;
            }

            initializePhoneInput(`#${input.id}`, input.dataset.outputSelector);
        });
    </script>
@endpush
