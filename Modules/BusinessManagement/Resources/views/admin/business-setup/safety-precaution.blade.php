@extends('adminmodule::layouts.master')

@section('title', translate('safety_precautions'))

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-22 mb-4 text-capitalize">{{ translate('business_management') }}</h2>

            <div class="mb-3">
                @include('businessmanagement::admin.business-setup.partials._business-setup-inline')
            </div>

            @include('businessmanagement::admin.business-setup.partials.safety-precaution._safety-precaution-setup-inline')

            <div class="tab-content">
                @include('businessmanagement::admin.business-setup.partials.safety-precaution.safety')

                @include('businessmanagement::admin.business-setup.partials.safety-precaution.precaution')
            </div>
        </div>
        <input type="hidden" id="otherNumberCount" value="{{ $emergencyNumbers ? count($emergencyNumbers) : ''  }}">
    </div>
@endsection
@push('script')
    <script>
        "use strict";

        let permission = false;
        @can('business_edit')
            permission = true;
        @endcan

        $(document).ready(function () {

            // Collapse contents with switcher starts
            $('.safety_view-btn').on('click', function (e) {
                e.preventDefault();
                const $viewBtn = $(this);
                const $content = $('.safety_view-card');
                const $arrow = $viewBtn.find('i');

                $content.slideToggle('fast');
                $arrow.toggleClass('tio-arrow-downward tio-arrow-upward');
            });

            function updateContentVisibility() {
                let $safetyViewContent = $($('#safetyFeatureStatus').data('target-content'));
                const $viewBtnArrow = $('.safety_view-btn i');


                if ($('#safetyFeatureStatus').attr('data-confirm-btn') === "Turn Off") {
                    $safetyViewContent.slideDown();
                    $viewBtnArrow.removeClass('tio-arrow-downward');
                    $viewBtnArrow.addClass('tio-arrow-upward');
                } else {
                    $safetyViewContent.slideUp();
                    $viewBtnArrow.removeClass('tio-arrow-upward');
                    $viewBtnArrow.addClass('tio-arrow-downward');
                }
            }

            // Initial update on page load
            updateContentVisibility();

            const $safetyAlertReasonsCard = $('#safetyAlertReasonsStatusCard');
            const confirmBtnState = $('#safetyAlertReasonsStatus').attr('data-confirm-btn') === "Turn Off";
            const $safetyAlertReasonsViewBtnArrow = $safetyAlertReasonsCard.find('.view-btn i');

            if (confirmBtnState) {
                $safetyAlertReasonsViewBtnArrow.removeClass('tio-arrow-downward').addClass('tio-arrow-upward');
            } else {
                $safetyAlertReasonsViewBtnArrow.removeClass('tio-arrow-upward').addClass('tio-arrow-downward');
            }

            const $emergncyNumberCard = $('#emergncyNumberCard');
            const emergncyNumberConfirmBtnState = $('#emergencyNumberCallForStatus').attr('data-confirm-btn') === "Turn Off";
            const $emergncyNumberViewBtnArrow = $emergncyNumberCard.find('.view-btn i');

            if (emergncyNumberConfirmBtnState) {
                $emergncyNumberViewBtnArrow.removeClass('tio-arrow-downward').addClass('tio-arrow-upward');
            } else {
                $emergncyNumberViewBtnArrow.removeClass('tio-arrow-upward').addClass('tio-arrow-downward');
            }

            // collapse card start
            function collapsibleCard(thisInput) {
                let $card = thisInput.closest(".collapsible-card-body");
                let $content = $card.children(".collapsible-card-content");
                let confirmBtn = thisInput.attr("data-confirm-btn") || "";
                if (confirmBtn === "Turn Off") {
                    $content.slideDown();
                } else {
                    $content.slideUp();
                }
            }

            function collapsibleCard3(thisInput) {
                let $card = thisInput.closest(".collapsible-card-body3");
                let $content = $card.children(".collapsible-card-content3");
                if ($(thisInput).is(':checked')) {
                    $content.slideDown();
                } else {
                    $content.slideUp();
                }
            }

            // Handle change event
            $("#safetyAlertReasonsStatusCard .collapsible-card-switcher").on("change", function () {
                collapsibleCard($(this));
            });

            // Initialize collapsible card states
            $("#safetyAlertReasonsStatusCard .collapsible-card-switcher").each(function () {
                collapsibleCard($(this));
            });

            // Handle change event
            $("#emergncyNumberCard .collapsible-card-switcher").on("change", function () {
                collapsibleCard($(this));
            });
            $("#after_trip_complete .collapsible-card-switcher3").on("change", function () {
                collapsibleCard3($(this));
            });
            $("#after_trip_complete .collapsible-card-switcher3").each(function () {
                collapsibleCard3($(this));
            });

            // Initialize collapsible card states
            $("#emergncyNumberCard .collapsible-card-switcher").each(function () {
                collapsibleCard($(this));
            });
            // collapse card start ends


            $('.targetToolTip').each(function () {
                var $this = $(this);
                var reason = $this.data('reason');
                if ($this[0].scrollHeight > $this[0].clientHeight) {
                    $this.attr('data-bs-toggle', 'tooltip');
                    $this.attr('data-bs-title', reason);
                    new bootstrap.Tooltip($this[0]);
                } else {
                    $this.removeAttr('data-bs-toggle');
                    $this.removeAttr('data-bs-title');
                }
            });

        });


        $(document).ready(function () {
            "use strict";
            let numberBox = $('.number-box');
            let label = numberBox.find('label');

            $('input[name="choose_number_type"]').on('change', function () {
                changeNumberBox(this);
                // Re-initialize tooltips after DOM change
                $('[data-bs-toggle="tooltip"]').tooltip();
            });


            function changeNumberBox(thisInput) {
                let govtNumberType = $(thisInput).val();
                if (govtNumberType === 'phone' || govtNumberType === 'telephone') {
                    if (govtNumberType === 'phone') {
                        label.html('{{ translate('Phone Number') }}' + ' <span class="text-danger">*</span> <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip" data-bs-title="{{ translate('Specify the emergency contact number for making a direct call') }}"></i>');
                    } else {
                        label.html('{{ translate('Telephone Number') }}' + ' <span class="text-danger">*</span> <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip" data-bs-title="{{ translate('Specify the emergency contact number for making a direct call') }}"></i>');
                    }
                    // Show the number field and hide hotline field
                    $('.number-field').removeClass('d-none');
                    $('.number-field-hotline').addClass('d-none');
                } else if (govtNumberType === 'hotline') {
                    label.html('{{ translate('Hotline Number') }}' + ' <span class="text-danger">*</span> <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip" data-bs-title="Emergency hotline number"></i>');
                    // Show the hotline field and hide number field
                    $('.number-field').addClass('d-none');
                    $('.number-field-hotline').removeClass('d-none');
                }
                $('[data-bs-toggle="tooltip"]').tooltip();
            }

            // Function to renumber rows dynamically and assign unique IDs
            initializePhoneInput("#phone_number", "#hidden-element");
            initializePhoneInput("#base_phone_number", "#base-hidden-element");


            function renumberRows() {
                $('.order-number-row').each(function (index) {
                    $(this).find('.fs-16').text((index + 1) + '.');
                });
            }


            // Function to update buttons (plus/minus)
            function updateButtons() {
                // Remove d-none from all buttons and reassign classes
                $('.order-number-clone').removeClass('d-none');
                $('.order-number-close').removeClass('d-none');

                // Only the last row should have a plus button, but no close button
                const lastRow = $('.order-number-row').last();
                lastRow.find('.order-number-clone').removeClass('d-none');
                lastRow.find('.order-number-close').addClass('d-none'); // Hide instead of removing

                // All other rows should have a minus button
                $('.order-number-row:not(:last)').find('.order-number-clone').addClass('d-none');
                $('.order-number-row:not(:last)').find('.order-number-close').removeClass('d-none');

                // Ensure at least one row remains (no minus button on single row)
                if ($('.order-number-row').length === 1) {
                    $('.order-number-row').find('.order-number-close').addClass('d-none'); // Hide instead of removing
                }
            }

            $(document).on('click', '.order-number-close', function (e) {
                e.preventDefault();
                const index = $(this).closest('.order-number-row').index();
                console.log('Clicked row index:', index);
                removePhoneInput(`#phone_number${index}`, `#hidden-element${index}`);
                $(this).closest('.order-number-row').remove(); // Remove the row
                renumberRows();
                updateButtons();
            });

            $(document).on('click', '.order-number-clone', function (e) {
                e.preventDefault();

                const originalRow = $(this).closest('.order-number-row');
                const clonedRow = originalRow.clone();

                let newIndex = $('.order-number-row').length; // Get the new index
                let phoneInputId = `phone_number${newIndex}`;
                let hiddenElementId = `hidden-element${newIndex}`;
                while ($(`#${phoneInputId}`).length > 0 || $(`#${hiddenElementId}`).length > 0) {
                    newIndex++;
                    phoneInputId = `phone_number${newIndex}`;
                    hiddenElementId = `hidden-element${newIndex}`;
                }
                clonedRow.find('.iti').children().unwrap();
                clonedRow.find('.iti__flag-container').remove();
                // Update IDs for the cloned row
                clonedRow.find('[id^="phone_number"]').attr('id', phoneInputId);
                clonedRow.find('[id^="hidden-element"]').attr('id', hiddenElementId);
                // Clear input values in the cloned row
                clonedRow.find('input').val('');
                // Append the cloned row
                $(this).closest('.order-number-row-container').append(clonedRow);
                // Renumber rows and update buttons (implement these functions separately)
                renumberRows();
                updateButtons();
                initializePhoneInput(`#${phoneInputId}`, `#${hiddenElementId}`);
            });

            function removePhoneInput(selector, outputSelector) {
                const phoneInput = document.querySelector(selector);
                if (phoneInput && phoneInput.intlTelInputInstance) {
                    // Destroy the intl-tel-input instance
                    phoneInput.intlTelInputInstance.destroy();
                    delete phoneInput.intlTelInputInstance; // Remove reference for cleanup
                }

                // Clear the value of the outputSelector
                const outputField = document.querySelector(outputSelector);
                if (outputField) {
                    outputField.value = ''; // Reset the value of the output field
                }
            }


            // Function to reinitialize phone inputs for all rows
            function reinitializePhoneInputs() {
                $('.order-number-row').each(function (index) {
                    $(this).find('.iti').children().unwrap();
                    $(this).find('.iti__flag-container').remove();
                    const phoneInputId = `#phone_number${index}`;
                    const hiddenElementId = `#hidden-element${index}`;

                    if ($(phoneInputId).length) {
                        initializePhoneInput(phoneInputId, hiddenElementId);
                    }
                });
            }

            // Initialize the first row
            renumberRows();
            updateButtons();

            // Initialize phone input for the first row
            let otherNumberCount = $('#otherNumberCount').val();
            if (otherNumberCount > 0) {
                for (let i = 0; i < otherNumberCount; i++) {
                    const newPhoneInputId = `#phone_number${i}`;
                    const newHiddenElementId = `#hidden-element${i}`;
                    initializePhoneInput(newPhoneInputId, newHiddenElementId);
                }
            }

            $('#emergencyNumberForCallForm').on('submit', function (e) {
                e.preventDefault();
                const formData = $(this).serialize();

                $.ajax({
                    url: '{{ route('admin.business.setup.safety-precaution.emergency-number-for-call.store') }}',
                    type: 'POST',
                    data: formData,
                    success: function (response) {
                        toastr.success(response.message);
                        setTimeout(function () {
                            location.reload();
                        }, 1000);
                    },
                    error: function (xhr) {
                        if (xhr.status === 422) {
                            showValidationErrors(xhr.responseJSON.errors);
                        } else {
                            toastr.error('An unexpected error occurred.');
                        }
                    }
                })
            });

            function showValidationErrors(errors) {
                for (const key in errors) {
                    if (errors.hasOwnProperty(key)) {
                        toastr.error(errors[key][0], 'Validation Error');
                    }
                }
            }
        });


        // view-advance-content
        $('.view-advance-btn').on('click', function (e) {
            e.preventDefault();

            $('.view-advance-content').slideToggle('fast');
            let span = $(this).find('span');
            if (span.text() === "Hide Advance") {
                span.text("See More");
                $(this).find('i').removeClass('bi--lg').addClass('bi-plus-lg');
            } else {
                span.text("Hide Advance");
                $(this).find('i').removeClass('bi-plus-lg').addClass('bi--lg');
            }
        })

        $(document).ready(function () {
            $('.editSafetyAlertReasonData').click(function () {
                let id = $(this).data('id');
                let url = "{{ route('admin.business.setup.safety-precaution.safety-alert-reason.edit', ':id') }}";
                url = url.replace(':id', id);
                $.get({
                    url: url,
                    success: function (data) {
                        $('#editSafetyAlertReasonModal .modal-content').html(data);
                        $('#updateForm').removeClass('d-none');
                        $('#editSafetyAlertReasonModal').modal('show');
                        $.getScript('{{ dynamicAsset('public/assets/admin-module/js/dev.js') }}');
                    },
                    error: function (xhr, status, error) {
                        console.log(error);
                    }
                });
            });

            $('.editSafetyPrecautionData').click(function () {
                let id = $(this).data('id');
                let url = "{{ route('admin.business.setup.safety-precaution.precaution.edit', ':id') }}";
                url = url.replace(':id', id);
                $.get({
                    url: url,
                    success: function (data) {
                        $('#editSafetyPrecautionModal .modal-content').html(data);
                        $('#updateForm').removeClass('d-none');
                        $('#editSafetyPrecautionModal').modal('show');
                        $.getScript('{{ dynamicAsset('public/assets/admin-module/js/dev.js') }}');
                    },
                    error: function (xhr, status, error) {
                        console.log(error);
                    }
                });
            });
        });
    </script>
@endpush
