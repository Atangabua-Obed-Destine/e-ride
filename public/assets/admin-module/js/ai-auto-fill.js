function setAiImageRemoveButtonDisabled(disabled) {
    const $imageRemoveButton = $('#removeImageBtn');

    $imageRemoveButton
        .toggleClass('disabled', disabled)
        .attr('aria-disabled', disabled ? 'true' : 'false')
        .css('pointer-events', disabled ? 'none' : '');

    if (disabled) {
        $imageRemoveButton.attr('tabindex', '-1');
    } else {
        $imageRemoveButton.removeAttr('tabindex');
    }
}

function resetAnalyzeBlogImageButton() {
    const $button = $('#analyzeBlogImageBtn');

    $button.prop('disabled', false);
    $button.find('.btn-text').text('Generate');
    $button.find('.ai-btn-animation').addClass('d-none');
    $button.find('i').removeClass('d-none');
    $('.upload-image-for-generating-content').css('pointer-events', 'auto');
    $('#chooseImageBtnBlog').removeClass('disabled');
    setAiImageRemoveButtonDisabled(false);
}

function setAiGeneratedImageAsThumbnail(file) {
    const thumbnailInput = document.querySelector('input[name="thumbnail"].single_file_input');

    if (!file || !thumbnailInput || typeof DataTransfer === 'undefined') {
        return;
    }

    const dataTransfer = new DataTransfer();
    dataTransfer.items.add(file);
    thumbnailInput.files = dataTransfer.files;
    thumbnailInput.dispatchEvent(new Event('change', {bubbles: true}));
}

$(document).off('click', '.blog_title_auto_fill').on('click', '.blog_title_auto_fill', function () {
    const $button = $(this);
    const $editorContainer = $('#title-container');
    const route = $button.data('route');
    const $nameInput = $('#blogTitle');
    const name = ($nameInput.val() || '').trim();
    if (name.length === 0) {
        toastr.error("Blog Title  is required");
        return;
    }
    let $existingTitle = $button.data('item')?.title ?? "";

    $editorContainer.addClass('outline-animating');
    $button.prop('disabled', true);
    $button.find('.btn-text').text('');
    const $aiText = $button.find('.ai-text-animation');
    $aiText.removeClass('d-none').addClass('ai-text-animation-visible');

    $.ajax({
        url: route,
        type: 'GET',
        dataType: 'json',
        data: {
            name: name,
        },
        success: function (response) {
            $nameInput.val(response.data);
        },
        error: function (xhr, status, error) {
            $editorContainer.removeClass('outline-animating');

            if (xhr.responseJSON && xhr.responseJSON.errors) {
                Object.values(xhr.responseJSON.errors).forEach(fieldErrors => {
                    fieldErrors.forEach(errorMessage => {
                        toastr.error(errorMessage);
                    });
                });
            } else if (xhr.responseJSON && xhr.responseJSON.message) {
                toastr.error(xhr.responseJSON.message);
            } else {
                toastr.error('An unexpected error occurred.');
            }

            $nameInput.val($existingTitle);
            $button.prop('disabled', false);
            $button.find('.btn-text').text('Re-generate');
            $aiText.addClass('d-none').removeClass('ai-text-animation-visible');
        },
        complete: function () {
            setTimeout(function () {
                $editorContainer.removeClass('outline-animating');
            }, 500);

            $button.prop('disabled', false);
            $button.find('.btn-text').text('Re-generate');
            $aiText.addClass('d-none').removeClass('ai-text-animation-visible');
        }
    });
});

$(document).off('click', '.blog_description_auto_fill').on('click', '.blog_description_auto_fill', function () {
    const $button = $(this);
    const $editorContainer = $('#editor-container');
    const route = $button.data('route');
    const $nameInput = $('#blogTitle');
    const name = ($nameInput.val() || '').trim();
    if (name.length === 0) {
        toastr.error("Blog Title  is required");
        return;
    }
    const $textarea = $('#blogDescription');
    let $existingDescription = $textarea.summernote('code') ?? '';

    $editorContainer.addClass('outline-animating');
    $button.prop('disabled', true);
    $button.find('.btn-text').text('');
    const $aiText = $button.find('.ai-text-animation');
    $aiText.removeClass('d-none').addClass('ai-text-animation-visible');

    $.ajax({
        url: route,
        type: 'GET',
        dataType: 'json',
        data: {
            name: name,
        },
        success: function (response) {
            $textarea.summernote('code', response.data);

            if ($button.data('next-action')?.toString() === 'seo_section') {
                const $card = $('.card:has(' + '.blog_seo_section_auto_fill' + ')');
                if ($card.length > 0) {
                    $('html, body').animate({
                        scrollTop: $card.offset().top - 100
                    }, 800);
                }
            }
        },
        error: function (xhr, status, error) {
            $textarea.summernote('code', $existingDescription);

            if (xhr.responseJSON && xhr.responseJSON.message) {
                toastr.error(xhr.responseJSON.message);
            } else {
                toastr.error('An unexpected error occurred.');
            }
        },
        complete: function () {
            if ($button.data('next-action')?.toString() === 'seo_section') {
                setTimeout(function () {
                    const target = document.querySelector('.blog_seo_section_auto_fill');
                    if (target) {
                        target.click();
                    }
                }, 2000);
            }

            $button.removeAttr('data-next-action');
            $button.removeData('next-action');
            if ($button[0] && $button[0].dataset) {
                delete $button[0].dataset.nextAction;
            }

            setTimeout(function () {
                $editorContainer.removeClass('outline-animating');
            }, 500);

            $button.prop('disabled', false);
            $button.find('.btn-text').text('Re-generate');
            $aiText.addClass('d-none').removeClass('ai-text-animation-visible');
        }
    });
});

$(document).on('click', '.blog_seo_section_auto_fill', function () {
    const $button = $(this);
    const $wrapper = $button.closest('.seo_wrapper');
    const route = $button.data('route');
    const $container = $('.seo_wrapper').find('.outline-wrapper');
    const $textarea = $('#blogDescription');
    let description = $textarea ? $textarea.summernote('code') : '';
    const cleanDescription = description
        .replace(/<p><br><\/p>/gi, '')
        .replace(/<[^>]*>/g, '')
        .trim();
    if (!cleanDescription) {
        description = '';
    }
    const $nameInput = $('#blogTitle');
    const name = ($nameInput.val() || '').trim();
    if (!name) {
        toastr.error("Blog Title is required");
        return;
    }
    const existingData = {};
    $wrapper.find('input, select, textarea').each(function () {
        const $field = $(this);
        const fieldName = $field.attr('name');
        if (!fieldName) return;

        existingData[fieldName] = $field.val();

    });
    $button.data('item', existingData);

    $container.addClass('outline-animating');
    $container.find('.bg-animate').addClass('active');
    $button.prop('disabled', true);
    $button.find('.btn-text').text('');
    const $aiText = $button.find('.ai-text-animation');
    $aiText.removeClass('d-none').addClass('ai-text-animation-visible');


    $.ajax({
        url: route,
        type: 'GET',
        dataType: 'json',
        data: {
            name: name,
            description: description,
        },
        success: function (response) {
            const data = response.data || {};
            if (data.meta_title) {
                let metaTitle = data.meta_title;
                $('#meta_title').val(metaTitle);
            }

            if (data.meta_description) {
                let metaTitle = data.meta_description;
                $('#meta_description').val(metaTitle);
            }
        },
        error: function (xhr, status, error) {
            console.error('Error:', error);

            const previousData = $button.data('item');
            Object.keys(previousData).forEach(key => {
                const $field = $wrapper.find(`[name="${key}"]`);
                if ($field.length) {
                    $field.val(previousData[key]);
                }
            });

            if (xhr.responseJSON && xhr.responseJSON.message) {
                toastr.error(xhr.responseJSON.message);
            } else {
                toastr.error('An unexpected error occurred.');
            }
        },
        complete: function () {
            resetAnalyzeBlogImageButton();

            setTimeout(function () {
                $('#aiAssistantModalBlog').modal('hide');
            }, 1000);

            $('#aiAssistantModalBlog').on('hidden.bs.modal', function () {
                const imageUpload = document.getElementById('aiImageUploadBlog');
                const imagePreview = document.getElementById('aiImageUploadOriginalBlog');
                imageUpload.value = '';
                imagePreview.style.display = 'none';
                $('#chooseImageBtn').find('.text-box').removeClass('d-none');

                $('.upload-image-for-generating-content').css('pointer-events', 'auto')
            });

            setTimeout(() => {
                $container.removeClass('outline-animating');
                $container.find('.bg-animate').removeClass('active');
            }, 500);

            $button.prop('disabled', false);
            $button.find('.btn-text').text('Re-generate');
            $aiText.addClass('d-none').removeClass('ai-text-animation-visible');
        }
    });
});


document.addEventListener('DOMContentLoaded', function () {
    const modal = $('#aiAssistantModalBlog');
    const modalTitle = document.getElementById('modalTitleBlog');
    const mainContent = document.getElementById('mainAiContentBlog');
    const uploadContent = document.getElementById('uploadImageContentBlog');
    const titleContent = document.getElementById('giveTitleContentBlog');
    const imageUpload = document.getElementById('aiImageUploadBlog');
    const imagePreview = document.getElementById('imagePreviewBlog');
    const previewImg = document.getElementById('previewImgBlog');

    function showMainContent() {
        document.querySelectorAll('.ai-modal-content-blog').forEach(content => {
            content.style.display = 'none';
        });
        document.querySelector('.ai_backBtn').style.display = "none";
        mainContent.style.display = 'block';
        modalTitle.textContent = 'AI Assistant';
    }

    modal.on('show.bs.modal', function () {
        showMainContent();
    });

    document.querySelectorAll('.ai-action-btn-blog').forEach(button => {
        button.addEventListener('click', function () {
            const action = this.getAttribute('data-action');

            document.querySelectorAll('.ai-modal-content-blog').forEach(content => {
                content.style.display = 'none';
            });

            if (action === 'upload') {
                document.querySelector('.ai_backBtn').style.display = "block";
                modalTitle.textContent = 'Upload & Analyze Image';
                uploadContent.style.display = 'block';
            } else if (action === 'title') {
                modalTitle.textContent = 'Generate Blog Title';
                titleContent.style.display = 'block';
                document.querySelector('.ai_backBtn').style.display = "block";
            }
        });
    });
    if (imageUpload) {
        imageUpload.addEventListener('change', function (e) {
            $('#chooseImageBtnBlog').find('.text-box').addClass('d-none');
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    previewImg.src = e.target.result;
                    imagePreview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });
    }

    if (document.getElementById('removeImageBtn')) {
        document.getElementById('removeImageBtn').addEventListener('click', function () {
            imageUpload.value = '';
            imagePreview.style.display = 'none';
            $('#chooseImageBtnBlog').find('.text-box').removeClass('d-none');
        });
    }
    const backBtn = document.querySelector('.ai_backBtn');
    if (backBtn) {
        backBtn.addEventListener('click', function () {
            showMainContent();
        });
    }
});


$('#generateBlogTitleBtn').on('click', function () {
    const $button = $(this);
    const keywords = $('#blogKeywords').val();
    const route = $button.data('route');

    if (!keywords) {
        toastr.error('Please enter some keywords.');
        return;
    }

    $button.prop('disabled', true);
    $button.find('.btn-text').text('Generating');
    $button.find('.ai-btn-animation').removeClass('d-none');
    $button.find('i').addClass('d-none');
    const $titlesList = $('#titlesList');
    $button.prop('disabled', true);
    $('.giveTitleContent_text').addClass('d-none');
    $('#generatedTitles').show();
    $('.show_generating_text').removeClass('d-none');
    $('.text-generate-icon').addClass('d-none');

    $.ajax({
        url: route,
        method: 'GET',
        data: {
            keywords: keywords,
            _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function (response) {
            $titlesList.empty();

            if (!response.data.titles || response.data.titles.length === 0) {
                $titlesList.html('<div class="text-center py-3">No titles generated.</div>');
                return;
            }

            response.data.titles.forEach(function (title) {
                const $item = $(`
                    <div class="list-group-item list-group-item-action title-option p-0">
                        <div class="d-flex justify-content-between align-items-center gap-2">
                            <span class="overflow-wrap-anywhere text-dark">${title}</span>
                            <button class="btn btn-sm btn-outline-primary px-4 use-title-btn min-w-100px" data-title="${title}">Use</button>
                        </div>
                    </div>
                `);
                $titlesList.append($item);
            });

            $titlesList.before($('.titlesList_title').removeClass('d-none'));
            $('#generatedTitles').show();

            let $titleActionButton = $('#title-action-btn');
            $('.use-title-btn').off('click').on('click', function (e) {
                e.preventDefault();
                $('.use-title-btn')
                    .removeClass('btn-primary')
                    .addClass('btn-outline-primary')
                    .text('Use ');

                $(this)
                    .removeClass('btn-outline-primary')
                    .addClass('btn-primary')
                    .text('Used');

                const title = $(this).data('title');
                $('input[name^="title').each(function () {
                    $(this).val(title);
                });
                $('input[name^="title').first()[0].scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
                $titleActionButton.find('.btn-text').text('Re-generate');
            });

        },
        error: function (xhr, status, error) {
            toastr.error('Failed to generate titles. Please try again.');
            $titlesList.empty();
        },
        complete: function () {
            $button.prop('disabled', false);
            $button.find('.btn-text').text('Generate Title');
            $button.find('.ai-btn-animation').addClass('d-none');
            $button.find('i').removeClass('d-none');

            $('.show_generating_text').addClass('d-none');
            $('.text-generate-icon').removeClass('d-none');

        }
    });
});

$(document).on('click', '#analyzeBlogImageBtn', function () {
    const $button = $(this);
    const $titleBtn = $('.blog_title_auto_fill');
    const $chooseImageBtn = $("#chooseImageBtnBlog")
    const route = $button.data('url') || $button.data('route');
    const imageInput = document.getElementById('aiImageUploadBlog');
    const $container = $('#title-container');


    if (!imageInput || !imageInput.files[0]) {
        toastr.error('Please select an image first');
        return;
    }
    const $blogTitleInput = $('input[name="title"]');
    if ($blogTitleInput) {
        $blogTitleInput.trigger("focus");
        $blogTitleInput[0].scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });
    }
    $container.addClass('outline-animating');
    $container.find('.bg-animate').addClass('active');

    $button.prop('disabled', true);
    $button.find('.btn-text').text('Generating');
    $button.find('.ai-btn-animation').removeClass('d-none');
    $button.find('i').addClass('d-none');
    setAiImageRemoveButtonDisabled(true);

    const formData = new FormData();
    formData.append('image', imageInput.files[0]);
    formData.append('description', $('#blog_description').val());
    $('.upload-image-for-generating-content').css('pointer-events', 'none');

    $.ajax({
        url: route,
        type: 'POST',
        dataType: 'json',
        processData: false,
        contentType: false,
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        },
        data: formData,
        success: function (response) {
            $('#blogTitle').val(response.data);

            setAiGeneratedImageAsThumbnail(imageInput.files[0]);

            const $nameField = $('#blogTitle');
            if ($nameField.length > 0) {
                const $card = $('.card:has('+ '.blog_description_auto_fill'+ ')');
                if ($card.length > 0) {
                    $('html, body').animate({
                        scrollTop: $card.offset().top - 100
                    }, 800);
                }
            }

            const target = document.querySelector('.blog_description_auto_fill');
            if (target) {
                target.setAttribute('data-next-action', 'seo_section');
                target.click();
            }

            $titleBtn.find('.btn-text').text('Re-generate');
        },
        error: function (xhr, status, error) {
            $container.removeClass('outline-animating');
            $container.find('.bg-animate').removeClass('active');
            if (xhr.responseJSON && xhr.responseJSON.errors) {
                const errors = xhr.responseJSON.errors;
                Object.keys(errors).forEach(key => {
                    errors[key].forEach(message => {
                        toastr.error(message);
                    });
                });
            } else if (xhr.responseJSON && xhr.responseJSON.message) {
                toastr.error(xhr.responseJSON.message);
            } else {
                toastr.error('An unexpected error occurred during image analysis.');
            }

            resetAnalyzeBlogImageButton();
        },
        complete: function (xhr, textStatus) {
            setTimeout(function () {
                $container.removeClass('outline-animating');
                $chooseImageBtn.removeClass('disabled');
            }, 500);

            if (textStatus === 'success') {
                $button.prop('disabled', true);
                $button.find('.btn-text').text('Generating');
            } else {
                resetAnalyzeBlogImageButton();
            }
        }
    });
});
