"use strict";
let lastOpenedModal = null;

$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
});

$(document).on('click', '.surge-pricing-offcanvas', function () {
    const url = $(this).data('url');
    const $btn = $(this);
    $.ajax({
        url: url,
        type: 'GET',
        success: function (response) {
            const $offcanvas = $('#surge-price-offcanvas');
            $offcanvas.empty().html(response);
            if(!$btn.hasClass('no-form-submit'))
            {
                loadScriptsSequentially();

                let repeatedList = [];
                let formId= null;
                const getCustomDateTimeRangeData = $offcanvas.find('.offcanvas-create-edit-fetched-data').data('get-custom-date-time-range');
                const isCreateBlade = $offcanvas.find('.offcanvas-create-edit-fetched-data').data('is-create-blade');
                const onCustomDateRange = $offcanvas.find('.offcanvas-create-edit-fetched-data').data('on-custom-date-range');
                if (getCustomDateTimeRangeData)
                {
                    getCustomDateTimeRangeData.forEach((item) => {
                        repeatedList.push({id: item.id, date: item.date, time: item.time});
                    });
                }
                window.repeatedList = repeatedList;
                window.activeCurrentDate =  isCreateBlade || !onCustomDateRange;
                window.formId = $offcanvas.find('.data-to-js').data('id');

                $('.character-count-field').on('keyup change', function () {
                    initialCharacterCount($(this));
                });
                $('.character-count-field').each(function () {
                    initialCharacterCount($(this));
                });
                select2AllHandler();
                $offcanvas.find('[data-bs-toggle="tooltip"]').each(function () {
                    new bootstrap.Tooltip(this);
                });
            }
        },
        error: function (xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed to load offcanvas.');
        }
    });
});

$(document).on('submit', '.submit-form', function (e) {
    e.preventDefault();
    const $form = $(this);
    let formData = $form.serialize();
    const method = $form.data('form-method');
    if (method.toUpperCase() === 'PUT') {
        formData += '&_method=' + method.toUpperCase();
    }
    $.ajax({
        url: $form.attr('action'),
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function (response) {
            if (response?.errors) {
                $.each(response.errors, function (key, value) {
                    setTimeout(() => toastr.error(value.message), key * 1000);
                });
            } else {
                toastr.success(response);
                setTimeout(() => window.location.reload(), 1000);
            }
        },
        error: function (xhr) {
            const errors = xhr.responseJSON?.errors;
            if (errors)
            {
                if (errors.hasOwnProperty("surge_pricing_overlap_message")){
                    showSurgePriceOverlapModal(errors.surge_pricing_overlap_message[0]);
                } else {
                    $.each(errors, function (key, value) {
                        toastr.error(value[0]);
                    });
                }
            } else{
                toastr.error('An unexpected error occurred.');
            }
        }
    });
});

function showSurgePriceOverlapModal(error) {
    const $openModals = $('.modal.show').not('#surgePriceOverlapModal');
    if ($openModals.length > 0) {
        lastOpenedModal = $openModals.last().attr('id');
        $openModals.modal('hide');
    } else {
        lastOpenedModal = null;
    }
    $('#surgePriceOverlapModal').find('#subTitle').text(error);
    $('#surgePriceOverlapModal').modal('show');
}

$('#surgePriceOverlapModal').on('hidden.bs.modal', function () {
    if (lastOpenedModal) {
        $('#' + lastOpenedModal).modal('show');
        lastOpenedModal = null;
    }
});

function initialCharacterCount(item) {
    let str = item.val();
    let maxCharacterCount = item.data('max-character');
    let characterCount = str.length;
    if (characterCount > maxCharacterCount) {
        item.val(str.substring(0, maxCharacterCount));
        characterCount = maxCharacterCount;
    }
    item.closest('.character-count').find('span').text(characterCount + '/' + maxCharacterCount);
}

function select2AllHandler(){
    let select = $('.js-select-2').select2({
        placeholder: $(this).data('placeholder')
    });
    select.on('select2:select', function (e) {
        let select = $(this);
        if (e.params.data.id === 'all') {
            select.find('option').prop('selected', false);
            select.val(['all']).trigger('change');
        } else {
            let selectedValues = select.val().filter(item => item !== 'all');
            select.find('option[value="all"]').prop('selected', false);
            select.val(selectedValues).trigger('change');
        }
    });

    select.on('select2:unselect', function (e) {
        let select = $(this);
        select.find('option[value="all"]').prop('selected', false);
    });
}

select2AllHandler();

async function loadScriptsSequentially() {
    try {
        await $.getScript($('.script-url').data('script-select-2'));
        await $.getScript($('.script-url').data('script-date-range-picker'));
        await $.getScript($('.script-url').data('script-surge-price-schedule'));
    } catch (e) {
        console.error("Script load error:", e);
    }
}
