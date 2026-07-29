"use strict";

$('#base_fare').on('input change', function () {
    const value = $(this).val();
    $('.base_fare').val(value);
    $('.weight_fare').val(value);
});

$(document).ready(function () {
    $('input[type="checkbox"]').click(function () {
        var inputValue = $(this).attr("value");
        if ($(this).is(":checked")) {
            $("." + inputValue).removeClass('d-none');
            $("." + inputValue).removeAttr('disabled');
        } else {
            $("." + inputValue).addClass('d-none');
            $("." + inputValue).attr('disabled', 'disabled');
        }
    });
});
