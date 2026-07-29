"use strict";

(function ($) {
    $(document).ready(function () {
        let element = document.getElementById("coordinates");
        $(".js-select").select2();
        if (element) {
            auto_grow();
        }
    });
    $(".js-select2").select2({
        dropdownParent: $("#activityLogModal"),
    });

    // character count
    function updateCharacterCount(item) {
        let str = item.val() || "";
        let maxCharacterCount = item.data("max-character");
        let characterCount = str.length;

        if (characterCount > maxCharacterCount) {
            item.val(str.substring(0, maxCharacterCount));
            characterCount = maxCharacterCount;
        }

        item.closest(".character-count")
            .find("span, div")
            .text(characterCount + "/" + maxCharacterCount);
    }

    $(document).on("keyup change", ".character-count-field", function () {
        updateCharacterCount($(this));
    });

    $(".character-count-field").each(function () {
        updateCharacterCount($(this));
    });

    $(document).on("reset", "form", function () {
        let form = this;

        setTimeout(function () {
            $(form).find(".character-count-field").each(function () {
                updateCharacterCount($(this));
            });
        }, 0);
    });


    $(document).on("reset", "form", function () {
        let form = this;

        setTimeout(function () {
            $(form).find(".character-count-field").each(function () {
                updateCharacterCount($(this));
                initialCharacterCountDiv($(this));
            });
        }, 0);
    });

    function auto_grow() {
        if (element) {
            element.style.height = "5px";
            element.style.height = element.scrollHeight + "px";
        }
    }

    function ajax_get(route, id) {
        $.get({
            url: route,
            dataType: "json",
            data: {},
            beforeSend: function () {
            },
            success: function (response) {
                $("#" + id).html(response.template);
            },
            complete: function () {
            },
        });
    }

    document.querySelectorAll('input[data-decimal]').forEach(input => {
        input.addEventListener('input', function() {
            let decimal = parseInt(this.dataset.decimal, 10);

            if (decimal === 0) {
                this.value = this.value.replace(/[^0-9]/g, '');
            } else {
                this.value = this.value.replace(/[^0-9.]/g, '');
                let parts = this.value.split('.');

                if (parts.length > 2) {
                    this.value = parts[0] + '.' + parts[1];
                }

                if (parts[1] && parts[1].length > decimal) {
                    this.value = parts[0] + '.' + parts[1].slice(0, decimal);
                }
            }

            if (this.value !== '' && parseFloat(this.value) < 1) {
                this.value = '1';
            }
        });
    });

    $(document).ready(function (){
        // --- Daterangepicker ---
        $(".js-daterangepicker").daterangepicker({
            timePicker: false,
            autoUpdateInput: false,
            ranges: {
                Today: [moment(), moment()],
                Yesterday: [
                    moment().subtract(1, "days"),
                    moment().subtract(1, "days")
                ],
                "Last 7 Days": [moment().subtract(6, "days"), moment()],
                "Last 30 Days": [moment().subtract(29, "days"), moment()],
                "This Month": [moment().startOf("month"), moment().endOf("month")],
                "Last Month": [
                    moment()
                        .subtract(1, "month")
                        .startOf("month"),
                    moment()
                        .subtract(1, "month")
                        .endOf("month")
                ]
            },
            alwaysShowCalendars: true
        });

        $(".js-daterangepicker").on("apply.daterangepicker", function(
            ev,
            picker
        ) {
            $(this).removeAttr("readonly");
            $(this).removeClass("cursor-pointer");
            $(this).val(
                picker.startDate.format("MM/DD/YYYY") +
                " - " +
                picker.endDate.format("MM/DD/YYYY")
            );
        });

        // --- TagsInput ---
        if ($.fn.tagsinput) {
            $(".bootstrap-tags-input").each(function() {
                let $input = $(this);

                $input.tagsinput({
                    confirmKeys: [13, 44], // Enter & Comma
                    trimValue: true
                });

                $input.on("itemAdded itemRemoved", function() {
                    $(this).val(
                        $(this)
                            .tagsinput("items")
                            .join(",")
                    );
                });
            });
        } else {
            console.error("Bootstrap Tags Input plugin not loaded");
        }
    });
})(jQuery);
