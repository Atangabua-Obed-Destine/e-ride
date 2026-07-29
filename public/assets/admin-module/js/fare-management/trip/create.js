"use strict";

// $(document).ready(function () {
//     if ($("input[name='category_wise_different_fare']:checked").val() == 0) {
//         $('#different-fare-div').addClass('d-none')
//         $('#different-fare-div input').attr("disabled",true)
//     } else {
//         $('#different-fare-div').removeClass('d-none')
//         $('#different-fare-div input').attr("disabled",false)
//     }

//     $('input[type="checkbox"]').click(function () {
//         var inputValue = $(this).attr("value");
//         if ($(this).is(":checked")) {
//             $("." + inputValue).removeClass('d-none');
//             $("." + inputValue).removeAttr('disabled');
//         } else {
//             $("." + inputValue).addClass('d-none');
//             $("." + inputValue).attr('disabled', 'disabled');
//         }
//     });

// });

// $('.copy-value').on('change',function () {
//     $('.' + $(this).attr('id') + '_default').val($(this).val());
// })
// $('.copy-value').keyup(function () {
//     $('.' + $(this).attr('id') + '_default').val($(this).val());
// })

// $(".use_category_wise").click(function () {
//     if ($(this).val() == 0) {
//         $('#different-fare-div').addClass('d-none')
//         $('#different-fare-div input').attr("disabled",true)

//     } else if ($(this).val() == 1) {
//         $('#different-fare-div').removeClass('d-none')
//         $('#different-fare-div input').attr("disabled",false)
//     }
// });



$(document).ready(function () {

    // Instance 1
    if ($("input[name='category_wise_different_fare']:checked").val() == 0) {
        $('#different-fare-div').addClass('d-none');
        $('#different-fare-div input').attr("disabled", true);
    } else {
        $('#different-fare-div').removeClass('d-none');
        $('#different-fare-div input').attr("disabled", false);
    }

    // Checkbox show/hide logic for both instances
    $('input[type="checkbox"]').click(function () {
        var inputValue = $(this).attr("value");
        if ($(this).is(":checked")) {
            $("." + inputValue).removeClass('d-none').removeAttr('disabled');
        } else {
            $("." + inputValue).addClass('d-none').attr('disabled', 'disabled');
        }
    });

    // Copy value handlers (shared logic)
    $('.copy-value').on('change keyup', function () {
        $('.' + $(this).attr('id') + '_default').val($(this).val());
    });

    // Radio button toggle for Instance 1
    $(".use_category_wise").click(function () {
        if ($(this).val() == 0) {
            $('#different-fare-div').addClass('d-none');
            $('#different-fare-div input').attr("disabled", true);
        } else {
            $('#different-fare-div').removeClass('d-none');
            $('#different-fare-div input').attr("disabled", false);
        }
    });



    // Initial state on page load
    if ($('#use_category_wise2').is(':checked')) {
        $('#different-fare-div2').removeClass('d-none');
        $('#different-fare-div2 input').attr('disabled', false);
    } else {
        $('#different-fare-div2').addClass('d-none');
        $('#different-fare-div2 input').attr('disabled', true);
    }

    // On checkbox toggle
    $('#use_category_wise2').change(function () {
        if ($(this).is(':checked')) {
            $('#different-fare-div2').removeClass('d-none');
            $('#different-fare-div2 input').attr('disabled', false);
        } else {
            $('#different-fare-div2').addClass('d-none');
            $('#different-fare-div2 input').attr('disabled', true);
        }
    });

    function updateSwitcherState() {
        const isChecked = $('#use_category_wise2').is(':checked');
        $('#switcher-text2').attr('data-state', isChecked ? 'on' : 'off');
    }

    // Set initial state on page load
    updateSwitcherState();

    // Update state when checkbox changes
    $('#use_category_wise2').change(function () {
        updateSwitcherState();
    });
});
