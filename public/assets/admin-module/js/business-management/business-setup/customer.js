"use strict";

let lpSwitch = $('#loyalty_point_switch')
let points = $('#equivalent_points')

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

$('form').on('reset', function () {
    setTimeout(() => {
        updatePointsState();
    }, 10);
});


let lvSwitch = $('#loyal_level_switch')
let lv_points = $('#equivalent_lv_points')

if (lvSwitch.prop('checked') === true) {
    lv_points.prop('disabled', false)
    lv_points.attr('required', true)
} else {
    lv_points.prop('disabled', true)
}
lvSwitch.on('change', function () {

    if ($(this).prop('checked') === false) {
        lv_points.prop('disabled', true)
    } else {
        lv_points.prop('disabled', false)
        lv_points.attr('required', true)
    }
})


$('.bidding-btn').on('click', function () {
    $(this).attr('value', 1)
})
