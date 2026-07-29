"use strict";

let selectedImages = [];
let selectedFiles = [];

function msgBtn() {
    if (selectedFiles.length > 0 || selectedImages.length > 0 || $("#msgInputValue").val().trim() !== "") {
        $("#msgSendBtn").prop("disabled", false);

    } else {
        $("#msgSendBtn").prop("disabled", true);
    }
}

// Copy Button Logic
$(document).on('click', '.copy-btn', function () {
    let answerText = $(this)
        .closest('.q-answer')
        .find('.answer-text')
        .html()
        .replace(/\s+/g, ' ')
        .replace(/<\/?[^>]+(>|$)/g, "")
        .trim();

    navigator.clipboard.writeText(answerText)
        .then(() => {
            $(this).text('Copied!');
            $("#msgInputValue").val(answerText);
            msgBtn();
        })
        .catch((error) => {
            console.error('Copy failed', error);
            $(this).text('Failed to copy');
        });

    // reset button text after a delay
    setTimeout(() => {
        $(this).text('Copy');
    }, 2000);
});

function imageSlider() {
    $(document).ready(function () {
        // Initialize Owl Carousel
        var imgView = $(".imgView-slider").owlCarousel({
            items: 1,
            loop: true,
            margin: 0,
            nav: false,
            mouseDrag: true,
            touchDrag: true,
            autoplay: false,
            smartSpeed: 1000,
        });

        // Set specific slide when modal is shown
        $('[data-bs-target^="#imgViewModal"]').on('click', function () {
            var index = $(this).data('index');
            imgView.trigger('to.owl.carousel', [index,
                300
            ]);
        });

        // Update slide count after initializing
        var slideCount = $(".imgView-slider .owl-item").not(".cloned").length;

        // Enable or disable loop
        if (slideCount <= 1) {
            imgView.trigger('destroy.owl.carousel');
            imgView.owlCarousel({
                items: 1,
                loop: false,
                margin: 0,
                nav: false,
                mouseDrag: false,
                touchDrag: false,
            });
            $(".imgView-owl-prev, .imgView-owl-next").attr("disabled", true);
        } else {
            $(".imgView-owl-prev, .imgView-owl-next").removeAttr("disabled");
        }

        $(".imgView-owl-prev").on("click", function () {
            imgView.trigger("prev.owl.carousel");
        });

        $(".imgView-owl-next").on("click", function () {
            imgView.trigger("next.owl.carousel");
        });

        // --- Get image title from image source
        $(".imgView-item").each(function () {
            var imgSrc = $(this).find("img").attr("src");
            var imgTitle = imgSrc.split('/').pop();
            $(this).find(".img-title").text(imgTitle);
        });

        // --- chat imgView slider ends
    });
}

function fileUpload() {
    $("#msgInputValue").on('input',function () {
        msgBtn()
    })
}

// Zip Download Logic with Event Delegation
$(document).on('click', '.zip-download', function (event) {
    event.preventDefault();

    const zipWrapper = $(this).closest('.zip-wrapper').find('.zip-images');
    if (!zipWrapper.length) {
        console.error('No .zip-images container found.');
        return;
    }

    const zip = new JSZip();
    const zipFolder = zip.folder("images");
    const images = zipWrapper.find('img');

    if (images.length === 0) {
        console.error('No images found to zip.');
        return;
    }

    // Fetch all images and zip them
    const imagePromises = images.map((index, img) => {
        const imgUrl = $(img).attr('src');
        const filename = `image_${index + 1}.png`;

        return fetch(imgUrl)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Failed to fetch image: ${imgUrl}`);
                }
                return response.blob();
            })
            .then(blob => zipFolder.file(filename, blob))
            .catch(error => console.error(`Error fetching image (${filename}):`,
                error));
    }).get();

    Promise.all(imagePromises)
        .then(() => zip.generateAsync({
            type: "blob"
        }))
        .then(content => saveAs(content, "images.zip"))
        .catch(error => console.error('Error generating ZIP:', error));
});


