"use strict";

let lpSwitch = $('#loyalty_point_switch');
let points = $('#equivalent_points');

function updatePointsState() {
    if (lpSwitch.prop('checked')) {
        points.prop('disabled', false);
        points.attr('required', true);
    } else {
        points.prop('disabled', true);
        points.removeAttr('required');
    }
}

updatePointsState();
lpSwitch.on('change', updatePointsState);

$('#loyalty_point_form').on('reset', function () {
    setTimeout(() => {
        updatePointsState();
    }, 10);
});

$('.bidding-btn').on('click', function () {
    $(this).attr('value', 1)
})
