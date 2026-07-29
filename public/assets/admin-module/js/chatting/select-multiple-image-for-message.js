"use strict";
$(document).ready( () => {
    $("#select-image").on("change", function () {
        const maxImageSize = parseFloat($('.image-file-size-data-to-js').data('max-upload-size-for-image')) * 1024 * 1024;

        const images = this.files;
        if (!images || images.length === 0) return;
        let newFilesSize = 0;

        // Check the total size of selected images
        for (let i = 0; i < images.length; i++) {
            const file = images[i];
            newFilesSize += file.size;

            if (file.size > maxImageSize) {
                toastr.error(`File ${images[i].name} is too large. Maximum allowed size is 2MB.`);
                // Clear the input field if file is too large
                this.value = null;
                return; // Exit function if file exceeds size limit
            }
        }

        const newTotalSize = totalSize + newFilesSize;
        if (newTotalSize > postMaxSize) {
            toastr.error(`Total uploaded file size exceeds ${$('.image-file-size-data-to-js').data('post-max-size')} MB. Please remove some files.`);
            this.value = ''; // Reset input
            return;
        }

        totalSize = newTotalSize;
        // If all files are valid, push them to the selectedImages array
        for (let index = 0; index < images.length; ++index) {
            selectedImages.push(images[index]);
        }

        // Call your functions
        displaySelectedImages();
        msgBtn();
    });

    function displaySelectedImages() {
        const containerImage = document.getElementById("selected-image-container");
        containerImage.innerHTML = "";

        selectedImages.forEach((file, index) => {
            const input = document.createElement("input");
            input.type = "file";
            input.name = `image[${index}]`;
            input.classList.add(`image-index${index}`);
            input.hidden = true;
            containerImage.appendChild(input);

            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            input.files = dataTransfer.files;
        });

        let imageArray = $(".image-array");
        imageArray.empty();

        selectedImages.forEach((file, index) => {
            let fileReader = new FileReader();
            let $uploadDiv = jQuery.parseHTML(
                "<div class='upload_img_box'><span class='img-clear'><i class='tio-clear'></i></span><img src='' alt=''></div>"
            );

            fileReader.onload = function () {
                $($uploadDiv).find("img").attr("src", this.result);
            };
            fileReader.readAsDataURL(file);

            imageArray.append($uploadDiv);

            // ✅ Handle removal
            $($uploadDiv).find(".img-clear").on("click", function () {
                $(this).closest(".upload_img_box").remove();

                // Adjust total size when removing
                totalSize -= file.size;
                selectedImages.splice(index, 1);

                // Remove hidden input
                $(".image-index" + index).remove();

                msgBtn();
            });
        });
    }
});
