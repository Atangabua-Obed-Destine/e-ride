@extends('adminmodule::layouts.master')

@section('title', translate('Additional Dynamic Data Setup'))

@push('css_or_js')
@endpush

@section('content')
    <div class="main-content ">
        <form action="{{ route('admin.business.pages-media.additional-data-setup.update') }}"
              class="submit-by-ajax additional-data-setup-form" method="post"
              id="additional-data-setup-form">
            @csrf
            <input type="hidden" name="user_type" value="{{ $userType }}">
            <div class="container-fluid">
                <h4 class="mb-4 fs-20 pb-xxl-1">{{ translate('Additional Dynamic Data Setup') }}</h4>
                @include('businessmanagement::admin.pages.additional-data-setup.partials._index-inline-tab')
                <div class="">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="card border-0">
                                <div class="card-body">
                                    <div class="mb-20">
                                        <h4 class="fs-16 mb-2 font-semibold d-block">{{ translate('Default Input Fields') }}</h4>
                                        <p class="fs-14 mb-0">{{ translate('These are the required standard fields that must be collected during registration.') }}</p>
                                    </div>
                                    <div class="p-xxl-20 p-3 bg-F6F6F6 rounded">
                                        <ul class="d-flex flex-wrap gap-3 list-inline mb-0">
                                            @foreach($defaultInputFields as $key => $defaultInputField)
                                                <li class="fs-14 text-dark d-flex align-items-center gap-1 max-w-180px w-100">
                                                    <i class="bi bi-dot fs-4"></i>
                                                    {{ translate($defaultInputField) }}
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card border-0 custom-input_field_wrap">
                                <div class="card-body">
                                    <div class="mb-20 d-flex align-items-center gap-2 justify-content-between flex-wrap">
                                        <h4 class="fs-16 mb-0 font-semibold d-block">{{ translate('Custom Input Fields') }}</h4>
                                        <div class="add-field-top {{ count($userAdditionalRegistrationFormFields) ? '' : 'd-none' }}">
                                            <button type="button"
                                                    class="btn fw-semibold fs-14 btn-primary add-filed-cmnBtn">
                                                <i class="bi bi-plus fs-18"></i>
                                                {{ translate('Add New Field') }}
                                            </button>
                                        </div>
                                    </div>

                                    <div class="py-5 empty-estate-addfield text-center {{ count($userAdditionalRegistrationFormFields) ? 'd-none' : '' }}">
                                        <div class="">
                                            <div class="mb-20">
                                                <i class="bi bi-plus-circle-fill fs-2 text-muted"></i>
                                            </div>
                                            <h4 class="fs-16 mb-2 font-semibold d-block">{{ translate('Add Custom Input Fields') }}</h4>
                                            <p class="fs-12 mb-20">{{ translate('Customize_the_{user}_registration_form_by_adding_fields_based_on_business_requirements', ['user' => lcfirst(translate($userType))]) }}</p>
                                            <div class="d-flex justify-content-center add-filed-cmnBtn-emptystate">
                                                <button type="button"
                                                        class="btn fw-semibold fs-14 btn-primary add-filed-cmnBtn">
                                                    <i class="bi bi-plus fs-18"></i>
                                                    {{ translate('Add New Field') }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex flex-column gap-3 custom-fields-container"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="footer-sticky">
                <div class="container-fluid">
                    <div class="btn--container justify-content-end py-4">
                        <button type="reset"
                                class="btn btn-secondary min-w-120 cmn_focus reset-additional-data-btn">{{ translate('Reset') }}</button>
                        <button type="submit"
                                class="btn btn-primary min-w-120 cmn_focus">{{ translate('Save Information') }}</button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    {{-- Field row template (rendered via JS) --}}
    <template id="custom-field-template">
        <div class="d-flex flex-column gap-2 custom-main-filed-item" data-field-index="__INDEX__">
            <input type="hidden" name="fields[__INDEX__][id]" value="__FIELD_ID__" class="field-id-input">
            <div class="bg-F6F6F6 rounded">
                <div class="border-bottom">
                    <div class="d-flex align-items-center gap-2 justify-content-between p-xxl-20 p-3">
                        <span class="mb-0">
                            <p class="fs-14 m-0 fw-semibold field-label">{{ translate('Field') }} __LABEL__</p>
                        </span>
                        <div class="gap-xxl-20 gap-3 d-flex align-items-center">
                            <label class="custom-checkbox d-flex align-items-center gap-2 m-0">
                                <input type="checkbox" name="fields[__INDEX__][is_required]" value="1" class="is-required-input">
                                <span class="mb-0">
                                    <p class="fs-14 m-0 fw-semibold">{{ translate('Is Required') }}</p>
                                </span>
                            </label>
                            <button type="button"
                                    class="btn btn-danger px-1 w-30px h-30px py-1 cutom_mainItem-RemoveBtn">
                                <i class="bi bi-trash3 fs-14"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="p-xxl-20 p-3">
                    <div class="row g-4">
                        <div class="col-md-6 col-lg-4">
                            <label class="fs-14 text-black mb-10px d-flex align-items-center gap-1">
                                {{ translate('Type') }} <span class="text-danger">*</span>
                            </label>
                            <div class="form-grop">
                                <select name="fields[__INDEX__][type]"
                                        class="bg-white bs-border-cus form-select min-h-45px px-3 rounded type-select">
                                    <option value="text">{{ translate('Text') }}</option>
                                    <option value="number">{{ translate('Number') }}</option>
                                    <option value="date">{{ translate('Date') }}</option>
                                    <option value="email">{{ translate('Email') }}</option>
                                    <option value="phone">{{ translate('Phone') }}</option>
                                    <option value="checkbox">{{ translate('Check Box') }}</option>
                                    <option value="radio">{{ translate('Radio') }}</option>
                                    <option value="select">{{ translate('Select') }}</option>
                                    <option value="textarea">{{ translate('Textarea') }}</option>
                                    <option value="file">{{ translate('File Upload') }}</option>
                                </select>
                                <span class="error-text text-danger fs-12" data-error="fields.__INDEX__.type"></span>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <label class="fs-14 text-black mb-10px d-flex align-items-center gap-1">
                                {{ translate('Input Field Title') }} <span class="text-danger">*</span>
                            </label>
                            <div class="form-grop">
                                <input type="text" class="form-control title-input"
                                       name="fields[__INDEX__][title]"
                                       placeholder="{{ translate('Ex: Age') }}">
                                <span class="error-text text-danger fs-12" data-error="fields.__INDEX__.title"></span>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-4 flexible-placeholder" style="display: none;">
                            <label class="fs-14 text-black mb-10px d-flex align-items-center gap-1">
                                {{ translate('Place holder') }} <span class="text-danger">*</span>
                            </label>
                            <div class="form-grop">
                                <input type="text" class="form-control placeholder-input"
                                       name="fields[__INDEX__][placeholder]"
                                       placeholder="{{ translate('Ex: Enter your age') }}">
                                <span class="error-text text-danger fs-12" data-error="fields.__INDEX__.placeholder"></span>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-8 flexible-file-format" style="display: none;">
                            <label class="fs-14 text-black mb-10px d-flex align-items-center gap-1">
                                {{ translate('File Format') }} <span class="text-danger">*</span>
                            </label>
                            <div class="form-grop bs-border-cus additional-data-file-format-gap bg-white min-h-45px px-3 py-10px rounded file-format-group">
                                <div>
                                    <div class="custom-checkbox d-flex align-items-center gap-2">
                                        <input type="checkbox" class="file-format-input"
                                               name="fields[__INDEX__][file_format][]" value="image">
                                        <label class="mb-0">
                                            <p class="fs-14 m-0 opacity-75">{{ translate(IMAGE_ACCEPTED_EXTENSIONS) }}</p>
                                        </label>
                                    </div>
                                </div>
                                <div>
                                    <div class="custom-checkbox d-flex align-items-center gap-2">
                                        <input type="checkbox" class="file-format-input"
                                               name="fields[__INDEX__][file_format][]" value="pdf">
                                        <label class="mb-0">
                                            <p class="fs-14 m-0 opacity-75">{{ translate('.pdf') }}</p>
                                        </label>
                                    </div>
                                </div>
                                <div>
                                    <div class="custom-checkbox d-flex align-items-center gap-2">
                                        <input type="checkbox" class="file-format-input"
                                               name="fields[__INDEX__][file_format][]" value="document">
                                        <label class="mb-0">
                                            <p class="fs-14 m-0 opacity-75">{{ translate('.doc, .docx') }}</p>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <span class="error-text text-danger fs-12" data-error="fields.__INDEX__.file_format"></span>
                        </div>
                        <div class="col-md-6 col-lg-4 flexible-quantity" style="display: none;">
                            <div class="mb-10px d-flex align-items-center gap-1 flex-wrap">
                                <label class="fs-14 text-black d-flex align-items-center gap-1 mb-0">
                                    {{ translate('Quantity') }}
                                    <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                       data-bs-toggle="tooltip"
                                       title="{{ translate('A maximum of 5 files can be uploaded for this field.') }}"
                                       data-bs-title="{{ translate('A maximum of 5 files can be uploaded for this field.') }}"></i>
                                    <span class="text-danger">*</span>
                                </label>
                            </div>
                            <div class="form-grop">
                                <input type="number" min="1" max="5" class="form-control quantity-input"
                                       name="fields[__INDEX__][quantity]"
                                       placeholder="{{ translate('Ex:1') }}">
                                <span class="error-text text-danger fs-12" data-error="fields.__INDEX__.quantity"></span>
                            </div>
                        </div>
                    </div>

                    {{-- Options block (used by checkbox / radio / select) --}}
                    <div class="flexible-options mt-3" style="display: none;">
                        <div class="d-flex flex-column gap-2">
                            <div class="bg-F6F6F6 rounded p-xxl-20 p-3">
                                <div class="row g-3 align-items-center">
                                    <div class="col-sm-6 col-md-6 col-lg-3">
                                        <h6 class="m-0 fw-medium options-add-label">{{ translate('Add Option') }}</h6>
                                    </div>
                                    <div class="col-sm-6 col-md-6 col-lg-3">
                                        <h6 class="m-0 fw-medium">{{ translate('Option Name') }}</h6>
                                    </div>
                                    <div class="col-sm-6 col-md-6 col-lg-3">
                                        <input type="text" placeholder="{{ translate('Ex: Enter option') }}"
                                               class="form-control new-option-input">
                                        <span class="error-text text-danger fs-12 new-option-error"></span>
                                    </div>
                                    <div class="col-sm-6 col-md-6 col-lg-3 d-flex justify-content-end">
                                        <button type="button"
                                                class="btn fs-14 px-3 btn-outline-primary add-option-btn">
                                            {{ translate('Add') }} <i class="bi fs-16 bi-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="options-list d-flex flex-column gap-2"></div>
                            <span class="error-text text-danger fs-12" data-error="fields.__INDEX__.options"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>

    {{-- Single option row template --}}
    <template id="custom-option-template">
        <div class="bg-F6F6F6 rounded p-xxl-20 p-3 add-option-item">
            <div class="row g-3 align-items-center">
                <div class="col-sm-6 col-md-6 col-lg-3">
                    <h6 class="m-0 fw-medium options-add-label">{{ translate('Add Option') }}</h6>
                </div>
                <div class="col-sm-6 col-md-6 col-lg-3">
                    <h6 class="m-0 fw-medium">{{ translate('Option Name') }}</h6>
                </div>
                <div class="col-sm-6 col-md-6 col-lg-3">
                    <input type="text" class="form-control option-name-input"
                           name="fields[__INDEX__][options][__OPTION_INDEX__]" value="__VALUE__">
                    <span class="error-text text-danger fs-12" data-error="fields.__INDEX__.options.__OPTION_INDEX__"></span>
                </div>
                <div class="col-sm-6 col-md-6 col-lg-3 d-flex justify-content-end">
                    <button type="button"
                            class="btn btn-danger px-1 w-30px h-30px py-1 option-item-removeBtn">
                        <i class="bi bi-trash3 fs-14"></i>
                    </button>
                </div>
            </div>
        </div>
    </template>
@endsection

@push('script')
    <script>
        (function () {
            const initialFields = @json($userAdditionalRegistrationFormFields);
            const PLACEHOLDER_TYPES = ['text', 'number', 'date', 'email', 'phone', 'textarea'];
            const OPTION_TYPES = ['checkbox', 'radio', 'select'];
            const MAX_CUSTOM_FIELDS = 20;
            const MAX_CUSTOM_FIELDS_TOOLTIP = "{{ translate('Maximum 20 fields are allowed.') . ' ' . translate('If you want to add a new field, you need to remove field first.') }}";
            const LEGACY_TYPE_MAP = {
                'check_boxes': 'checkbox',
                'radio_group': 'radio',
                'file_upload': 'file'
            };
            let nextFieldNumber = 1;
            let usedFieldIds = new Set();
            let nextCreatedSequence = 1;
            let fieldKeySeparatorPattern = /[^A-Za-z0-9]+/g;

            try {
                fieldKeySeparatorPattern = new RegExp('[^\\p{L}\\p{M}\\p{N}]+', 'gu');
            } catch (error) {
                fieldKeySeparatorPattern = /[^A-Za-z0-9]+/g;
            }

            function normalizeType(type) {
                return LEGACY_TYPE_MAP[type] || type || 'text';
            }

            function fieldKey(value) {
                return String(value || '')
                    .replace(fieldKeySeparatorPattern, '_')
                    .replace(/^_+|_+$/g, '')
                    .toLocaleLowerCase();
            }

            function humanizeFieldKey(value) {
                const text = String(value || '').trim();
                if (!text) {
                    return '';
                }

                return text
                    .replace(/[_-]+/g, ' ')
                    .replace(/\s+/g, ' ')
                    .trim()
                    .replace(/\b\w/g, function (char) {
                        return char.toUpperCase();
                    });
            }

            function getFieldIdNumber(id) {
                const match = String(id || '').match(/^field_(\d+)$/);
                return match ? parseInt(match[1], 10) : null;
            }

            function compareFieldIds(firstId, secondId) {
                const firstNumber = getFieldIdNumber(firstId);
                const secondNumber = getFieldIdNumber(secondId);

                if (firstNumber !== null && secondNumber !== null) return secondNumber - firstNumber;
                if (firstNumber !== null) return -1;
                if (secondNumber !== null) return 1;

                return String(secondId || '').localeCompare(String(firstId || ''));
            }

            function sortFieldsById(fields) {
                return fields
                    .map(function (field, index) {
                        return { field: field, index: index };
                    })
                    .sort(function (first, second) {
                        const firstId = first.field && first.field.id;
                        const secondId = second.field && second.field.id;

                        if (!firstId && !secondId) return first.index - second.index;
                        if (!firstId || !secondId) return first.index - second.index;

                        const compared = compareFieldIds(firstId, secondId);
                        return compared === 0 ? first.index - second.index : compared;
                    })
                    .map(function (entry) {
                        return entry.field;
                    });
            }

            function assignFieldIdsInCurrentOrder(fields) {
                let localNextFieldNumber = 1;
                const localUsedFieldIds = new Set();

                function reserveLocalFieldId(fieldId) {
                    localUsedFieldIds.add(fieldId);
                    const fieldNumber = getFieldIdNumber(fieldId);
                    if (fieldNumber !== null && fieldNumber >= localNextFieldNumber) {
                        localNextFieldNumber = fieldNumber + 1;
                    }
                }

                function nextLocalFieldId() {
                    let fieldId = 'field_' + localNextFieldNumber;
                    while (localUsedFieldIds.has(fieldId)) {
                        localNextFieldNumber++;
                        fieldId = 'field_' + localNextFieldNumber;
                    }
                    localNextFieldNumber++;
                    reserveLocalFieldId(fieldId);

                    return fieldId;
                }

                return fields.map(function (field) {
                    const normalized = Object.assign({}, field || {});
                    const fieldId = typeof normalized.id === 'string' ? normalized.id.trim() : '';

                    if (getFieldIdNumber(fieldId) !== null && !localUsedFieldIds.has(fieldId)) {
                        normalized.id = fieldId;
                        reserveLocalFieldId(fieldId);
                    } else {
                        normalized.id = nextLocalFieldId();
                    }

                    return normalized;
                });
            }

            function reserveFieldId(fieldId) {
                usedFieldIds.add(fieldId);
                const fieldNumber = getFieldIdNumber(fieldId);
                if (fieldNumber !== null && fieldNumber >= nextFieldNumber) {
                    nextFieldNumber = fieldNumber + 1;
                }
            }

            function nextGeneratedFieldId() {
                let fieldId = 'field_' + nextFieldNumber;
                while (usedFieldIds.has(fieldId)) {
                    nextFieldNumber++;
                    fieldId = 'field_' + nextFieldNumber;
                }
                nextFieldNumber++;
                reserveFieldId(fieldId);

                return fieldId;
            }

            function fieldIdFrom(fieldData) {
                const fieldId = fieldData && typeof fieldData.id === 'string' ? fieldData.id.trim() : '';
                if (getFieldIdNumber(fieldId) !== null && !usedFieldIds.has(fieldId)) {
                    reserveFieldId(fieldId);
                    return fieldId;
                }

                return nextGeneratedFieldId();
            }

            const $form = $('#additional-data-setup-form');
            const $container = $form.find('.custom-fields-container');
            const $emptyState = $form.find('.empty-estate-addfield');
            const $addTopWrap = $form.find('.add-field-top');
            const $addFieldTooltipWrappers = $form.find('.add-field-top, .add-filed-cmnBtn-emptystate');
            const $footer = $form.find('.footer-sticky');

            function refreshTooltip($element, enabled) {
                const element = $element[0];
                if (!element || typeof bootstrap === 'undefined' || !bootstrap.Tooltip) {
                    return;
                }

                const tooltip = bootstrap.Tooltip.getInstance(element);
                if (tooltip) {
                    tooltip.dispose();
                }

                if (enabled) {
                    new bootstrap.Tooltip(element, {
                        title: MAX_CUSTOM_FIELDS_TOOLTIP,
                        placement: 'top'
                    });
                }
            }

            function disposeTooltip($element) {
                const element = $element[0];
                if (element && typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                    const tooltip = bootstrap.Tooltip.getInstance(element);
                    if (tooltip) {
                        tooltip.dispose();
                    }
                }
            }

            function initializeTooltips($scope) {
                if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) {
                    return;
                }

                $scope.find('[data-bs-toggle="tooltip"]').each(function () {
                    const tooltip = bootstrap.Tooltip.getInstance(this);
                    if (tooltip) {
                        tooltip.dispose();
                    }
                    new bootstrap.Tooltip(this);
                });
            }

            function disposeTooltips($scope) {
                if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) {
                    return;
                }

                $scope.find('[data-bs-toggle="tooltip"]').each(function () {
                    const tooltip = bootstrap.Tooltip.getInstance(this);
                    if (tooltip) {
                        tooltip.dispose();
                    }
                });
            }

            function customFieldCount() {
                return $container.children('.custom-main-filed-item').length;
            }

            function refreshAddFieldLimitState() {
                const hasReachedLimit = customFieldCount() >= MAX_CUSTOM_FIELDS;
                const $buttons = $form.find('.add-filed-cmnBtn');

                $buttons.prop('disabled', hasReachedLimit)
                    .toggleClass('disabled', hasReachedLimit)
                    .attr('aria-disabled', hasReachedLimit ? 'true' : 'false');

                $addFieldTooltipWrappers.each(function () {
                    const $wrapper = $(this);
                    if (hasReachedLimit) {
                        $wrapper
                            .attr('data-bs-toggle', 'tooltip')
                            .attr('data-bs-title', MAX_CUSTOM_FIELDS_TOOLTIP)
                            .attr('aria-label', MAX_CUSTOM_FIELDS_TOOLTIP)
                            .removeAttr('title');
                    } else {
                        disposeTooltip($wrapper);
                        $wrapper
                            .removeAttr('data-bs-toggle')
                            .removeAttr('data-bs-title')
                            .removeAttr('title')
                            .removeAttr('data-bs-original-title')
                            .removeAttr('aria-label');
                    }
                    refreshTooltip($wrapper, hasReachedLimit);
                });
            }

            function releaseAddFieldClickState(button) {
                const $button = $(button);
                $button.removeClass('active focus').trigger('blur');

                if (document.activeElement === button) {
                    button.blur();
                }
            }

            function refreshAdditionalDataFooterState() {
                if (!$footer.length) {
                    return;
                }

                const scrollPosition = $(window).scrollTop() + $(window).height();
                const documentHeight = $(document).height();

                $footer.toggleClass('no-shadow', scrollPosition >= documentHeight - 100);
            }

            function scheduleFooterStateRefresh() {
                requestAnimationFrame(refreshAdditionalDataFooterState);
            }

            function refreshFieldLabels() {
                $container.children('.custom-main-filed-item').each(function (idx) {
                    const fieldNumber = getFieldIdNumber($(this).find('.field-id-input').val());
                    $(this).find('.field-label').text("{{ translate('Field') }} " + (fieldNumber || (idx + 1)));
                });
                if ($container.children('.custom-main-filed-item').length > 0) {
                    $emptyState.addClass('d-none');
                    $addTopWrap.removeClass('d-none');
                } else {
                    $emptyState.removeClass('d-none');
                    $addTopWrap.addClass('d-none');
                }
                refreshAddFieldLimitState();
                scheduleFooterStateRefresh();
            }

            function syncFieldIdsForCurrentOrder() {
                const $fields = $container.children('.custom-main-filed-item');
                usedFieldIds = new Set();
                nextFieldNumber = 1;

                $fields.each(function (idx) {
                    const fieldId = 'field_' + ($fields.length - idx);
                    $(this).find('.field-id-input').val(fieldId);
                    reserveFieldId(fieldId);
                });

                refreshFieldLabels();
            }

            function renumberFieldNames($orderedFields) {
                const $fields = $orderedFields && $orderedFields.length
                    ? $orderedFields
                    : $container.children('.custom-main-filed-item');

                $fields.each(function (idx) {
                    const $item = $(this);
                    $item.attr('data-field-index', idx);
                    $item.find('[name^="fields["]').each(function () {
                        const oldName = $(this).attr('name');
                        const newName = oldName.replace(/^fields\[\d+\]/, 'fields[' + idx + ']');
                        $(this).attr('name', newName);
                    });
                    $item.find('[data-error^="fields."]').each(function () {
                        const oldErr = $(this).attr('data-error');
                        const newErr = oldErr.replace(/^fields\.\d+/, 'fields.' + idx);
                        $(this).attr('data-error', newErr);
                    });
                    $item.find('.add-option-item').each(function (optionIdx) {
                        $(this).find('.option-name-input')
                            .attr('name', 'fields[' + idx + '][options][' + optionIdx + ']');
                        $(this).find('.error-text[data-error^="fields."]')
                            .attr('data-error', 'fields.' + idx + '.options.' + optionIdx);
                    });
                });
            }

            function fieldSubmitPriority($item) {
                if ($item.data('isNewField') === 1) {
                    return 2;
                }

                return fieldKey($item.find('.title-input').val()) === ($item.data('originalTitleKey') || '') ? 0 : 1;
            }

            function orderedFieldsForValidation() {
                return $($container.children('.custom-main-filed-item').get().sort(function (first, second) {
                    const $first = $(first);
                    const $second = $(second);
                    const firstPriority = fieldSubmitPriority($first);
                    const secondPriority = fieldSubmitPriority($second);

                    if (firstPriority !== secondPriority) {
                        return firstPriority - secondPriority;
                    }

                    if (firstPriority === 2) {
                        return (Number($first.data('createdSequence')) || 0) - (Number($second.data('createdSequence')) || 0);
                    }

                    return $container.children('.custom-main-filed-item').index(first) -
                        $container.children('.custom-main-filed-item').index(second);
                }));
            }

            function setBlockVisible($el, visible) {
                $el.removeClass('d-none');
                if (visible) {
                    $el.show();
                } else {
                    $el.hide();
                }
            }

            function applyTypeVisibility($item) {
                const type = $item.find('.type-select').val();
                const $placeholder = $item.find('.flexible-placeholder');
                const $options = $item.find('.flexible-options');
                const $fileFormat = $item.find('.flexible-file-format');
                const $quantity = $item.find('.flexible-quantity');

                setBlockVisible($placeholder, PLACEHOLDER_TYPES.includes(type));
                setBlockVisible($options, OPTION_TYPES.includes(type));
                setBlockVisible($fileFormat, type === 'file');
                setBlockVisible($quantity, type === 'file');

                $item.find('.placeholder-input').prop('disabled', !PLACEHOLDER_TYPES.includes(type));
                $item.find('.file-format-input').prop('disabled', type !== 'file');
                $item.find('.quantity-input').prop('disabled', type !== 'file');

                if (OPTION_TYPES.includes(type)) {
                    let label = "{{ translate('Add Option') }}";
                    if (type === 'checkbox') label = "{{ translate('Add Checkmark Option') }}";
                    else if (type === 'radio') label = "{{ translate('Add Radio Option') }}";
                    else if (type === 'select') label = "{{ translate('Add Select Option') }}";
                    $item.find('.options-add-label').text(label);
                }
            }

            function applyQuantityState($item, defaultWhenEmpty) {
                const $qInput = $item.find('.quantity-input');
                const isFileType = $item.find('.type-select').val() === 'file';

                $qInput.prop('disabled', !isFileType);
                if (!isFileType) {
                    return;
                }

                const quantity = parseInt($qInput.val(), 10);
                if ((!quantity || quantity < 1) && defaultWhenEmpty === true) {
                    $qInput.val(1);
                }
            }

            function buildOptionRow(value, idx, optionIdx, humanizeDisplay) {
                const tpl = document.getElementById('custom-option-template').innerHTML;
                const safeIdx = (typeof idx === 'undefined' || idx === null) ? 0 : idx;
                const safeOptionIdx = (typeof optionIdx === 'undefined' || optionIdx === null) ? 0 : optionIdx;
                const displayValue = humanizeDisplay === true ? humanizeFieldKey(value) : (value || '');
                const html = tpl
                    .replace(/__INDEX__/g, safeIdx)
                    .replace(/__OPTION_INDEX__/g, safeOptionIdx)
                    .replace(/__VALUE__/g, $('<div>').text(displayValue).html());
                return $(html);
            }

            function addOption($item, value) {
                const idx = parseInt($item.attr('data-field-index'), 10) || 0;
                const optionIdx = $item.find('.add-option-item').length;
                const $row = buildOptionRow(value || '', idx, optionIdx);
                $item.find('.options-list').append($row);
                renumberFieldNames();
                applyTypeVisibility($item);
            }

            function buildField(fieldData, prepend, syncIds) {
                const tpl = document.getElementById('custom-field-template').innerHTML;
                const idx = $container.children('.custom-main-filed-item').length;
                const fieldId = fieldIdFrom(fieldData);
                const fieldNumber = getFieldIdNumber(fieldId) || (idx + 1);
                const html = tpl
                    .replace(/__INDEX__/g, idx)
                    .replace(/__FIELD_ID__/g, $('<div>').text(fieldId).html())
                    .replace(/__LABEL__/g, fieldNumber);
                const $item = $(html);

                if (fieldData) {
                    const normalizedType = normalizeType(fieldData.type);
                    $item.data('isNewField', 0);
                    $item.data('originalTitleKey', fieldKey(fieldData.title));
                    $item.find('.type-select').val(normalizedType);
                    $item.find('.title-input').val(humanizeFieldKey(fieldData.title));
                    if (fieldData.placeholder) $item.find('.placeholder-input').val(fieldData.placeholder);
                    if (Number(fieldData.is_required) === 1) $item.find('.is-required-input').prop('checked', true);

                    if (OPTION_TYPES.includes(normalizedType) && Array.isArray(fieldData.options)) {
                        const $list = $item.find('.options-list');
                        fieldData.options.forEach(function (opt, optionIdx) {
                            $list.append(buildOptionRow(opt, idx, optionIdx, true));
                        });
                    }

                    if (normalizedType === 'file') {
                        const formats = Array.isArray(fieldData.file_format) ? fieldData.file_format : [];
                        $item.find('.file-format-input').each(function () {
                            $(this).prop('checked', formats.includes($(this).val()));
                        });
                        const quantity = parseInt(fieldData.quantity, 10);
                        $item.find('.quantity-input').val(quantity || 1);
                    }
                } else {
                    $item.data('isNewField', 1);
                    $item.data('originalTitleKey', '');
                    $item.data('createdSequence', nextCreatedSequence++);
                }

                if (prepend) {
                    $container.prepend($item);
                } else {
                    $container.append($item);
                }
                applyTypeVisibility($item);
                applyQuantityState($item, true);
                initializeTooltips($item);
                if (syncIds === false) {
                    refreshFieldLabels();
                } else {
                    syncFieldIdsForCurrentOrder();
                }
                renumberFieldNames();
                return $item;
            }

            function renderInitialFields() {
                disposeTooltips($container);
                $container.empty();
                nextFieldNumber = 1;
                usedFieldIds = new Set();
                if (Array.isArray(initialFields) && initialFields.length > 0) {
                    sortFieldsById(assignFieldIdsInCurrentOrder(initialFields)).forEach(function (field) {
                        buildField(field, false, false);
                    });
                    syncFieldIdsForCurrentOrder();
                    renumberFieldNames();
                }
                refreshFieldLabels();
            }

            // Add new field
            $form.on('click', '.add-filed-cmnBtn', function () {
                if (customFieldCount() >= MAX_CUSTOM_FIELDS) {
                    refreshAddFieldLimitState();
                    return;
                }
                buildField(null, true);
                requestAnimationFrame(() => releaseAddFieldClickState(this));
            });

            // Remove field
            $form.on('click', '.cutom_mainItem-RemoveBtn', function () {
                const $item = $(this).closest('.custom-main-filed-item');
                disposeTooltips($item);
                $item.remove();
                syncFieldIdsForCurrentOrder();
                renumberFieldNames();
            });

            // Type change
            $form.on('change', '.type-select', function () {
                const $item = $(this).closest('.custom-main-filed-item');
                applyTypeVisibility($item);
                applyQuantityState($item, true);
            });

            // Add option
            $form.on('click', '.add-option-btn', function () {
                const $item = $(this).closest('.custom-main-filed-item');
                const $input = $item.find('.new-option-input');
                const value = ($input.val() || '').trim();
                const $error = $item.find('.new-option-error');
                if (!value) {
                    $input.addClass('is-invalid');
                    $error.text("{{ translate('Option name cannot be empty') }}");
                    return;
                }
                addOption($item, value);
                $input.val('');
                $input.removeClass('is-invalid');
                $error.text('');
            });

            $form.on('input', '.new-option-input', function () {
                if (($(this).val() || '').trim()) {
                    $(this).removeClass('is-invalid')
                        .closest('.custom-main-filed-item')
                        .find('.new-option-error')
                        .text('');
                }
            });

            // Add option on Enter
            $form.on('keydown', '.new-option-input', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    $(this).closest('.custom-main-filed-item').find('.add-option-btn').click();
                }
            });

            // Remove option
            $form.on('click', '.option-item-removeBtn', function () {
                $(this).closest('.add-option-item').remove();
                renumberFieldNames();
            });

            $form.on('change blur', '.quantity-input', function () {
                applyQuantityState($(this).closest('.custom-main-filed-item'), false);
            });

            // Reset: revert to server state
            $form.on('click', '.reset-additional-data-btn', function (e) {
                e.preventDefault();
                renderInitialFields();
                scheduleFooterStateRefresh();
            });

            // Promote any pending option-name text into a real option row before submit,
            // so values typed but not yet "Add"-clicked still get serialized.
            $form.on('submit', function () {
                $container.children('.custom-main-filed-item').each(function () {
                    const $item = $(this);
                    const type = $item.find('.type-select').val();
                    if (type === 'file') {
                        applyQuantityState($item, false);
                    }
                    if (!OPTION_TYPES.includes(type)) return;
                    const $input = $item.find('.new-option-input');
                    const value = ($input.val() || '').trim();
                    if (!value) return;
                    addOption($item, value);
                    $input.val('');
                });
                syncFieldIdsForCurrentOrder();
                renumberFieldNames(orderedFieldsForValidation());
            });

            renderInitialFields();
            $(window)
                .off('resize.additionalDataFooter')
                .on('resize.additionalDataFooter', scheduleFooterStateRefresh);
        })();
    </script>
@endpush
