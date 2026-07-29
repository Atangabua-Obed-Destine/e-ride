document.addEventListener("DOMContentLoaded", function () {
    if (document.querySelectorAll(".upload-file-new").length) {
        initFileUpload();
        checkPreExistingImages();
    }
});

function initFileUpload() {
    document.addEventListener("change", function (e) {
        if (e.target.classList.contains("single_file_input")) {
            handleFileChange(e.target, e.target.files[0]);
        }
    });

    document.addEventListener("click", function (e) {
        const removeBtn = e.target.closest(".remove_btn");
        const editBtn = e.target.closest(".edit_btn");
        const resetBtn = e.target.closest("button[type=reset]");
        const viewBtn = e.target.closest(".view_btn");
        if (removeBtn) {
            const card = removeBtn.closest(".upload-file-new");
            resetFileUpload(card);

        }
        if (viewBtn) {
            e.preventDefault();
            e.stopImmediatePropagation();
            return;
        }

        if (editBtn) {
            e.stopImmediatePropagation();
            const card = editBtn.closest(".upload-file-new");
            if (card) {
                card.classList.remove("input-disabled");
                const input = card.querySelector(".single_file_input");
                if (input) {
                    input.click();
                }
            }
        }

        if (resetBtn) {
            const form = resetBtn.closest("form");
            if (form) {
                form.querySelectorAll(".upload-file-new").forEach(card => {
                    resetFileUpload(card);
                });
            }
        }
    });
}

function checkPreExistingImages() {
    document.querySelectorAll(".upload-file-new").forEach(function (card) {
        const textbox = card.querySelector(".upload-file-new-textbox");
        const imgElement = card.querySelector(".upload-file-new-img");
        const removeBtn = card.querySelector(".remove_btn");
        const overlay = card.querySelector(".overlay");

        const src = imgElement?.getAttribute("src");

        if (src && src !== window.location.href && src !== "") {
            imgElement.setAttribute("data-src", src);
            if (textbox) textbox.style.display = "none";
            if (imgElement) imgElement.style.display = "block";
            if (overlay) overlay.classList.add("show");
            if (removeBtn) removeBtn.style.opacity = 1;
            card.classList.add("input-disabled");
        }
    });
}

function handleFileChange(input, file) {
    const card = input.closest(".upload-file-new");

    if (!file) {
        resetFileUpload(card);
        return;
    }

    if (typeof canAddUploadFiles === 'function' && !canAddUploadFiles([file], {form: input.form, excludeInput: input})) {
        input.value = "";
        return;
    }

    if (typeof getUploadPayloadSize === 'function') {
        totalSize = getUploadPayloadSize({form: input.form});
    }

    const textbox = card.querySelector(".upload-file-new-textbox");
    const imgElement = card.querySelector(".upload-file-new-img");
    const removeBtn = card.querySelector(".remove_btn");
    const overlay = card.querySelector(".overlay");

    const currentSrc = imgElement?.src;
    if (currentSrc && currentSrc !== '' && currentSrc !== window.location.href) {
        input.dataset.previousFileData = currentSrc;
        input.dataset.previousFileName = 'previous-file';
    }

    card.classList.add("input-disabled");

    const reader = new FileReader();
    reader.onload = function (e) {
        if (textbox) textbox.style.display = "none";
        if (imgElement) {
            imgElement.src = e.target.result;
            imgElement.style.display = "block";
        }
        if (removeBtn) removeBtn.style.opacity = 1;
        if (overlay) overlay.classList.add("show");
    };
    reader.readAsDataURL(file);
}

function resetFileUpload(card) {
    const input = card.querySelector(".single_file_input");
    const imgElement = card.querySelector(".upload-file-new-img");
    const textbox = card.querySelector(".upload-file-new-textbox");
    const removeBtn = card.querySelector(".remove_btn");
    const overlay = card.querySelector(".overlay");
    const defaultSrc = imgElement?.dataset.defaultSrc || "";

    if (input) input.value = "";

    if (input) {
        delete input.dataset.previousFileData;
        delete input.dataset.previousFileName;
        if (typeof getUploadPayloadSize === 'function') {
            totalSize = getUploadPayloadSize({form: input.form});
        }
    }

    if (defaultSrc) {
        if (imgElement) {
            imgElement.src = defaultSrc;
            imgElement.style.display = "block";
        }
        if (textbox) textbox.style.display = "none";
        if (overlay) overlay.classList.add("show");
        if (removeBtn) removeBtn.style.opacity = 1;
        card.classList.add("input-disabled");
    } else {
        if (imgElement) {
            imgElement.style.display = "none";
            imgElement.src = "";
        }
        if (textbox) textbox.style.display = "block";
        if (overlay) overlay.classList.remove("show");
        if (removeBtn) removeBtn.style.opacity = 0;
        card.classList.remove("input-disabled");
    }
}

function resetFileUploadAllImage(card) {
    const inputs = card.querySelectorAll(".single_file_input");
    const imgElements = card.querySelectorAll(".upload-file-new-img");
    const textboxes = card.querySelectorAll(".upload-file-new-textbox");
    const removeBtns = card.querySelectorAll(".remove_btn");
    const overlays = card.querySelectorAll(".overlay");

    // Reset all file inputs
    inputs.forEach(input => {
        input.value = "";
        delete input.dataset.previousFileData;
        delete input.dataset.previousFileName;
    });

    const defaultSrc = imgElements[0]?.dataset.defaultSrc || "";

    if (defaultSrc) {
        imgElements.forEach(img => {
            img.src = defaultSrc;
            img.classList.remove("d-none");
        });

        textboxes.forEach(textbox => {
            textbox.style.removeProperty('display');
            textbox.classList.add("d-none");
        });

        overlays.forEach(overlay => overlay.classList.add("show"));
        removeBtns.forEach(btn => btn.style.opacity = "1");

        card.classList.add("input-disabled");
    } else {
        imgElements.forEach(img => {
            img.src = "";
            img.classList.add("d-none");
        });

        textboxes.forEach(textbox => {
            textbox.style.removeProperty('display');
            textbox.classList.remove("d-none");
        });

        overlays.forEach(overlay => overlay.classList.remove("show"));
        removeBtns.forEach(btn => btn.style.opacity = "0");

        card.classList.remove("input-disabled");
    }
}

