"use strict";

$('.js-select-modal').select2({
    dropdownParent: $('#lang-modal')
});
$('.language-status-change').on('change', function () {
    updateStatus(this);
})

function updateStatus(obj) {
    let url = $(obj).data('url');
    let code = $(obj).data("code");
    $.get({
        url: url,
        data: {code: code},
        beforeSend: function () {
            $('#resource-loader').show()
        },
        success: function (data) {
            $('#resource-loader').hide()
            if (data['status'] === 0) {
                toastr.info(data['message']);
                $('.status_' + code).prop('checked', true)
            }
            toastr.success(data['message']);
        },
        error: function () {
            $('#resource-loader').hide()
        },
    });
}

$('.default-language-change').on('change', function () {
    window.location.href = $(this).data('url')
})
