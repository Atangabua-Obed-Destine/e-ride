document.querySelectorAll(".upload-file__input").forEach(function (input) {
    input.addEventListener("change", function (event) {
        const card = event.target.closest(".upload-file");
        const textbox = card.querySelector(".upload-file__textbox");
        const imgElement = card.querySelector(".upload-file__img__img");
        const removeIcon = card.querySelector(".remove-img-icon");
        const prevSrc = textbox.querySelector("img").src;

        const newFile = input.files[0];
        if (!newFile) return;

        if (typeof canAddUploadFiles === 'function' && !canAddUploadFiles([newFile], {form: input.form, excludeInput: input})) {
            input.value = "";
            return;
        }

        totalSize = typeof getUploadPayloadSize === 'function' ? getUploadPayloadSize({form: input.form}) : totalSize;

        const reader = new FileReader();
        reader.onload = function (e) {
            imgElement.src = e.target.result;
            imgElement.style.display = "block";
            textbox.style.display = "none";
            removeIcon.classList.remove("d-none");
        };
        reader.readAsDataURL(newFile);

        removeIcon.onclick = function () {
            input.value = "";
            imgElement.src = "";
            imgElement.style.display = "none";
            textbox.style.display = "block";
            textbox.querySelector("img").src = prevSrc;
            removeIcon.classList.add("d-none");
            totalSize = typeof getUploadPayloadSize === 'function' ? getUploadPayloadSize({form: input.form}) : totalSize;
        };
    });
});

document.querySelectorAll("form").forEach(function (form) {
    form.addEventListener("reset", function () {
        setTimeout(function () {
            form.querySelectorAll(".upload-file").forEach(function (card) {
                const input = card.querySelector(".upload-file__input");
                const previewImg = card.querySelector(".upload-file__img__img");
                const textbox = card.querySelector(".upload-file__textbox");
                const removeIcon = card.querySelector(".remove-img-icon");

                input.value = "";

                if (previewImg.dataset.original && previewImg.dataset.original.trim() !== "") {
                    previewImg.src = previewImg.dataset.original;
                    previewImg.style.display = "block";
                    textbox.style.display = "none";
                } else {
                    previewImg.src = "";
                    previewImg.style.display = "none";
                    textbox.style.display = "block";
                }

                removeIcon.classList.add("d-none");
            });
        }, 0);
    });
});

