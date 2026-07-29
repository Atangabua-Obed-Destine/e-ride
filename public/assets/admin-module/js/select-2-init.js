$(document).ready(function () {
    $(".js-select-multiple, .js-select").each(function () {
        let $select = $(this);
        let isInsideOffcanvas = $select.closest(".offcanvas").length > 0;
        let isInsideModal = $select.closest(".modal").length > 0;
        let enableTags = $select.hasClass("tags");
        let isColorSelect = $select.hasClass("color-var-select");
        let isImageSelect = $select.hasClass("image-var-select");

        $select.select2({
            placeholder: $select.data("placeholder"),
            width: "100%",
            allowClear: true,
            minimumResultsForSearch: $select.data("without-search") || 0,
            tags: enableTags,
            maximumSelectionLength:
                $select.data("max-length") !== undefined
                    ? $select.data("max-length")
                    : 0,
            dropdownParent: isInsideOffcanvas
                ? $select.closest(".offcanvas")
                : isInsideModal
                ? $select.closest(".modal")
                : null,
            templateResult: isColorSelect
                ? formatColor
                : isImageSelect
                ? formatImage
                : undefined,
            templateSelection: isColorSelect
                ? formatColor
                : isImageSelect
                ? formatImage
                : undefined
        });

        // Save initial value 
        let val = $select.val();
        if (Array.isArray(val) && val.length === 0) val = null;
        else if (val === "") val = null;
        $select.data("initial-value", val);

        function formatColor(option) {
            if (!option.id) return option.text;
            let colorCode = $(option.element).data("color");
            if (!colorCode) return option.text;
            return $(
                `<div style="display:flex;align-items:center;gap:5px;">
                    <span style="width:12px;height:12px;background:${colorCode};display:inline-block;border-radius:3px;margin-right:8px;"></span>
                    ${option.text}
                </div>`
            );
        }

        function formatImage(option) {
            if (!option.id) return option.text;
            let imageUrl = $(option.element).data("image-url");
            if (!imageUrl) return option.text;
            return $(
                `<div style="display:flex;align-items:center;gap:5px;">
                    <img src="${imageUrl}" alt="${option.text}" style="width:14px;height:14px;object-fit:contain;">
                    ${option.text}
                </div>`
            );
        }

        if ($select.prop("multiple")) {
            let $selection = $select
                .next(".select2-container")
                .find(".select2-selection");

            if ($selection.find(".select2-selection__arrow").length === 0) {
                $selection.append(
                    '<span class="select2-selection__arrow"><b role="presentation"></b></span>'
                );
            }

            let updateMoreTag = () => {
                $selection.find(".more").remove();
                let $rendered = $selection.find(".select2-selection__rendered");
                let $choices = $rendered.find(".select2-selection__choice");
                let totalChoices = $choices.length;

                if (totalChoices === 0) return;

                let totalWidth = Math.max($selection.outerWidth() - 100, 0);
                let currentWidth = 0;
                let hiddenCount = 0;
                let hiddenNames = [];

                $choices.each(function () {
                    currentWidth += $(this).outerWidth(true);

                    if (currentWidth >= totalWidth) {
                        hiddenCount++;
                        let optionText = $(this)
                            .clone()
                            .children(".select2-selection__choice__remove")
                            .remove()
                            .end()
                            .text()
                            .trim();
                        hiddenNames.push(optionText);
                        $(this).hide();
                    } else {
                        $(this).show();
                    }
                });

                if (hiddenCount > 0) {
                    let tooltipText = hiddenNames
                        .join(", ")
                        .replace(/"/g, "&quot;");

                    let $more = $(
                        `<li class="more" data-bs-toggle="tooltip" title="${tooltipText}">+${hiddenCount}</li>`
                    );

                    $rendered.append($more);

                    if (window.bootstrap) {
                        let tooltip = bootstrap.Tooltip.getInstance($more[0]);
                        if (tooltip) tooltip.dispose();
                        new bootstrap.Tooltip($more[0]);
                    } else {
                        $more.tooltip();
                    }
                }
            };

            $select.data("updateMoreTag", updateMoreTag);

            updateMoreTag();

            $select.on("change select2:select select2:unselect", function () {
                setTimeout(updateMoreTag, 0);
            });
            $(window).on("resize", function () {
                setTimeout(updateMoreTag, 0);
            });
            $select.on("select2:open", updateMoreTag);
        }
    });

    $("form").on("reset", function () {
        let form = this;
        setTimeout(() => {
            $(form)
                .find(".js-select-multiple, .js-select")
                .each(function () {
                    let $select = $(this);
                    let initialValue = $select.data("initial-value");

                    if ($select.prop("multiple")) {
                        if (initialValue === null || initialValue === undefined) {
                            $select.val([]).trigger("change.select2");
                        } else {
                            let valToSet = Array.isArray(initialValue)
                                ? initialValue
                                : [initialValue];
                            $select.val(valToSet).trigger("change.select2");
                        }
                    } else {
                        if (initialValue !== undefined && initialValue !== null) {
                            $select.val(initialValue).trigger("change.select2");
                        } else {
                            $select.val(null).trigger("change.select2");
                        }
                    }
                });
        }, 10);
    });

    $(".modal").on("shown.bs.modal", function () {
        $(this).find(".js-select-multiple").each(function () {
            let updateFn = $(this).data("updateMoreTag");
            if (typeof updateFn === "function") {
                setTimeout(updateFn, 50);
            }
        });
    });
});

