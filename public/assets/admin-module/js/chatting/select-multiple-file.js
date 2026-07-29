"use strict";

$(document).ready(() => {
    const maxFileSize = 5 * 1024 * 1024;
    $("#select-file").on("change", function () {
        const maxFileSize = parseFloat($('.image-file-size-data-to-js').data('max-upload-size-for-file')) * 1024 * 1024;
        const files = this.files;
        if (!files || files.length === 0) return;
        let newFilesSize = 0;

        // Check the total size of selected images
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            newFilesSize += file.size;

            if (files[i].size > maxFileSize) {
                toastr.error(
                    `File ${files[i].name} is too large. Maximum allowed size is 5MB.`
                );
                // Clear the input field if file is too large
                this.value = null;
                return; // Exit function if file exceeds size limit
            }
        }
        const newTotal = totalSize + newFilesSize;
        if (newTotal > postMaxSize) {
            toastr.error(
                `Total uploaded file size exceeds ${$('.image-file-size-data-to-js').data('post-max-size')} MB. Please remove some files.`
            );
            this.value = ''; // reset input
            return;
        }
        totalSize = newTotal;
        // If all files are valid, push them to the selectedImages array
        for (let index = 0; index < files.length; ++index) {
            selectedFiles.push(files[index]);
        }

        // Call your functions
        displaySelectedFiles();
        msgBtn();
    });

    function displaySelectedFiles() {
        const container = document.getElementById("selected-files-container");
        container.innerHTML = "";

        selectedFiles.forEach((file, index) => {
            const input = document.createElement("input");
            input.type = "file";
            input.name = `file[${index}]`;
            input.classList.add(`file-index${index}`);
            input.hidden = true;
            container.appendChild(input);

            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            input.files = dataTransfer.files;
        });

        let fileArray = $(".file-array");
        fileArray.empty();

        selectedFiles.forEach((file, index) => {
            const fileName = file.name;
            const fileSize = formatBytes(file.size);
            const fileIcon = getFileIcon(fileName);

            const fileDesign = `
                <div class="uploaded-file-item">
                    <img src="${fileIcon}" class="file-icon" alt="">
                    <div class="upload-file-item-content">
                        <div>${fileName}</div>
                        <small>${fileSize}</small>
                    </div>
                    <button type="button" class="remove-file px-0"><i class="tio-clear"></i></button>
                </div>
            `;

            const $uploadDiv = $(fileDesign);
            fileArray.append($uploadDiv);

            $uploadDiv.find(".remove-file").on("click", function () {
                $(this).closest(".uploaded-file-item").remove();
                $(".file-index" + index).remove();

                totalSize -= file.size;
                selectedFiles.splice(index, 1);

                console.log(
                    "File removed, totalSize:",
                    (totalSize / (1024 * 1024)).toFixed(2),
                    "MB"
                );
                msgBtn();
            });
        });
    }

    function formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return "0 Bytes";
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ["Bytes", "KB", "MB", "GB", "TB", "PB", "EB", "ZB", "YB"];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return (
            parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + " " + sizes[i]
        );
    }

    function getFileIcon(fileName) {
        let extension = fileName.split(".").pop().toLowerCase();
        let iconPath = $("#get-file-icon");
        switch (extension) {
            case "doc":
            case "docx":
                return iconPath.data("word-icon");
            case "pdf":
                return iconPath.data("word-icon");
            case "zip":
                return iconPath.data("default-icon");
            default:
                return iconPath.data("default-icon");
        }
    }
});
