"use strict";

const uploadedSummernoteImages = new Set();

$(document).ready(function () {
    const $summernote = $('#blogDescription');
    const initialContent = $summernote.val();

    $('#blogDescription').summernote({
        placeholder: 'Write a short description of the blog',
        tabsize: 2,
        height: 200,
        toolbar: [
            ['style', ['style']],
            ['font', ['bold', 'underline', 'clear']],
            ['color', ['color']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['table', ['table']],
            ['insert', ['link', 'picture']],
            ['view', ['fullscreen', 'codeview', 'help']]
        ],
        callbacks: {
            onImageUpload: function (files) {
                uploadImage(files[0]);
            },
            onMediaDelete: function (target) {
                const image = $(target).attr('src');

                if (uploadedSummernoteImages.has(image)) {
                    deleteSummernoteImage(image);
                    uploadedSummernoteImages.delete(image);
                }
            }
        }
    });

    $('form').on('reset', function() {
        setTimeout(() => {
            $summernote.summernote('code', initialContent)
        }, 0);
    });

    $('#category-store-or-update').on('submit', function (e) {
        e.preventDefault();
        let formData = $(this).serialize();
        $(this).find('button[type="submit"]').prop('disabled', true);

        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: formData,
            success: function (response) {
                if (response?.success) {
                    toastr.success(response.message);
                    $('#category-store-or-update').find('#blog_category_name').val('');
                    $('#category-store-or-update').find('#blog_category_id').val('');

                    $.ajax({
                        url: $('.blog-data-to-js').data('category-index-route'),
                        type: 'GET',
                        success: function (response) {
                            console.log(response)
                            $('#blog-category-list').empty().html(response.view);
                            $('#active-categories').empty().html(response.create_blade_category_view)
                        },
                        error: function () {
                            toastr.error("Something went wrong");
                        }
                    });
                } else {
                    for (let index = 0; index < response?.errors.length; index++) {
                        setTimeout(() => {
                            toastr.error(response.errors[index].message);
                        }, index * 1000);
                    }
                }
            },
            error: function (xhr) {
                toastr.error('Something went wrong!');
            },
            complete: function () {
                $('#category-store-or-update button[type="submit"]').prop('disabled', false);
                $('#category-store-or-update button[type="submit"]').text('Submit');
                $('#category-store-or-update button[type="reset"]').text('Reset');
                $('.offcanvas-form-title').text($('.blog-data-to-js').data('offcanvas-create-form-title'));
            }
        });
    });
    $(document).off('click', '.edit-blog-category').on('click', '.edit-blog-category', function (e) {
        e.preventDefault();
        $('#category-store-or-update').find('#blog_category_name').val($(this).data('name'));
        $('#category-store-or-update').find('#blog_category_id').val($(this).data('id'));
        $('#category-store-or-update button[type="submit"]').text('Update');
        $('#category-store-or-update button[type="reset"]').text('Cancel');
        $('.offcanvas-form-title').text($('.blog-data-to-js').data('offcanvas-update-form-title'));
    });
    $(document).off('click', '#category-store-or-update button[type="reset"]').on('click', '#category-store-or-update button[type="reset"]', function (e) {
        e.preventDefault();
        $('#category-store-or-update').find('#blog_category_name').val('');
        $('#category-store-or-update').find('#blog_category_id').val('');
        $('#category-store-or-update button[type="submit"]').text('Submit');
        $('#category-store-or-update button[type="reset"]').text('Reset');
        $('.offcanvas-form-title').text($('.blog-data-to-js').data('offcanvas-create-form-title'));
    });
    $('.search-form-blog-category').on('submit', function (e) {
        e.preventDefault();
        let search = $(this).find('input[name="search"]').val();
        $.ajax({
            url: $('.blog-data-to-js').data('category-index-route'),
            data: {
                search
            },
            type: 'GET',
            success: function (response) {
                $('#blog-category-list').empty().html(response.view);
            },
            error: function () {
                toastr.error("Something went wrong");
            }
        });
    })

    $(document).on('click', '.blog-image-remove-btn', function (e) {
        e.preventDefault();
        const card = this.closest('.upload-file-new');
        const input = card?.querySelector('.single_file_input');
        const removeInput = card?.querySelector('.blog-image-remove-input');
        const imgElement = card?.querySelector('.upload-file-new-img');
        const textbox = card?.querySelector('.upload-file-new-textbox');
        const overlay = card?.querySelector('.overlay');

        if (input) {
            input.value = '';
        }

        if (removeInput) {
            removeInput.value = '1';
        }

        if (imgElement) {
            imgElement.src = '';
            imgElement.style.display = 'none';
        }

        if (textbox) {
            textbox.style.display = 'block';
        }

        if (overlay) {
            overlay.classList.remove('show');
        }

        card?.classList.remove('input-disabled');
    });

    $(document).on('change', '.single_file_input', function () {
        const removeInput = this.closest('.upload-file-new')?.querySelector('.blog-image-remove-input');

        if (removeInput && this.files.length) {
            removeInput.value = '0';
        }
    });

    $('form').on('reset', function () {
        $(this).find('.blog-image-remove-input').val('0');
    });
});

function uploadImage(file) {
    let data = new FormData();
    data.append('image', file);
    data.append('_token', $('.blog-data-to-js').data('csrf-token'));

    $.ajax({
        url: $('.blog-data-to-js').data('upload-summernote-image-route'),
        method: 'POST',
        data: data,
        contentType: false,
        processData: false,
        success: function (url) {
            uploadedSummernoteImages.add(url);
            $('#blogDescription').summernote('insertImage', url);
        },
        error: function (xhr) {
            if (xhr.status === 413) {
                toastr.error('File is too large. Please upload a smaller file.');
            } else if (xhr?.responseJSON?.errors) {
                xhr?.responseJSON?.errors.forEach((error) => {
                    toastr.error(error.message);
                });
            } else {
                toastr.error('Upload failed.');
            }
        }
    });
}

function deleteSummernoteImage(image) {
    if (!image || !$('.blog-data-to-js').data('delete-summernote-image-route')) {
        return;
    }

    $.ajax({
        url: $('.blog-data-to-js').data('delete-summernote-image-route'),
        method: 'POST',
        data: {
            image,
            _token: $('.blog-data-to-js').data('csrf-token')
        }
    });
}
