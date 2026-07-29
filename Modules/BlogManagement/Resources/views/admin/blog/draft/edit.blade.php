@extends('adminmodule::layouts.master')

@section('title', translate('Edit_Drafted_Blog'))

@push('css_or_js')
    <link rel="stylesheet"
          href="{{ dynamicAsset('public/assets/admin-module/plugins/summernote/summernote-lite.min.css') }}"/>
    <link rel="stylesheet" href="{{ dynamicAsset('public/assets/admin-module/css/ai-animation.css') }}"/>
@endpush

@section('content')
    @php($env = env('APP_MODE') == 'live' ? 'live' : 'test')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
                <h2 class="fs-20 fw-bold mb-0 text-capitalize ">{{translate('Edit_Drafted_Blog')}}</h2>
                <a href="{{ route('blog.details', ['blog_slug' => $data->blog->slug,'preview' => 'drafted']) }}" target="_blank" class="btn btn-outline-primary text-capitalize cmn_focus px-3">
                    <i class="bi bi-eye-fill"></i>
                    {{ translate('Section_Preview') }}
                </a>
            </div>
            <form action="{{ route('admin.blog.draft.update', $data->id) }}" id="" enctype="multipart/form-data" class="submit-by-ajax"
                  method="POST">
                @csrf
                @method('put')
                <div class="card card-body mb-3">
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <div class="p-lg-4 p-3 rounded bg-F6F6F6 h-100">
                                <div class="mb-20">
                                    <div class="d-flex justify-content-between align-items-center gap-3 mb-2">
                                        <label for="active-categories" class="mb-0">
                                            {{ translate('Category') }}
                                            <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                               data-bs-toggle="tooltip" title="{{ translate('Select a category from the dropdown menu to assign this blog.') }} {{ translate('If no categories are available or want to add new category please add it from the manage category section') }}"></i>
                                        </label>
                                        <button type="button"
                                                class="btn bg-info bg-opacity-10 text-info px-2 py-1 text-capitalize fw-semibold cmn_focus"
                                                tabindex="1" data-bs-toggle="offcanvas"
                                                data-bs-target="#category-offcanvas">
                                            {{ translate('Manage_category') }}
                                        </button>
                                    </div>
                                    <select name="blog_category_id" id="active-categories"
                                            class="form-select js-select">
                                        @include('blogmanagement::admin.blog.partials._active-categories')
                                    </select>
                                </div>
                                <div class="mb-20">
                                    <label for="writer" class="mb-2">{{ translate('Writer') }}</label>
                                    <input type="text" class="form-control" id="writer" name="writer"
                                           value="{{ old('writer', $data?->author?->name ?? '') }}" tabindex="3"
                                           list="blog-author-options"
                                           placeholder="{{ translate('Ex: Jhon Doe') }}">
                                    <datalist id="blog-author-options">
                                        @include('blogmanagement::admin.blog.partials._author-options')
                                    </datalist>
                                </div>
                                <div class="mb-20">
                                    <label for="" class="mb-2">
                                        {{ translate('Publish Date') }}
                                        <span class="text-danger">*</span>
                                        <i class="bi bi-info-circle-fill text-primary cursor-pointer"
                                           data-bs-toggle="tooltip" title="{{ translate('Pick the date that you want to show for the customer as the blog publishing date.') }}"></i>
                                    </label>
                                    <input type="date" class="form-control" id="" name="published_at"
                                           value="{{date('Y-m-d',strtotime($data->published_at))}}" tabindex="3">
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <?php
                            $maxSize = readableUploadMaxFileSize('image');
                            ?>
                            <div
                                class="p-lg-4 p-3 rounded bg-F6F6F6 d-flex flex-column justify-content-around gap-3 h-100">
                                <div class="text-center">
                                    <h5 class="mb-1">
                                        {{ translate('Thumbnail') }} <span class="text-danger">*</span>
                                    </h5>
                                    <p class="fs-12 mb-0">{{ translate('Upload thumbnail image') }}</p>
                                </div>

                                <div class="upload-file-new">
                                    <input type="file" class="upload-file-new__input single_file_input" tabindex="4"
                                           name="thumbnail" accept="{{ IMAGE_ACCEPTED_EXTENSIONS }}"
                                           data-max-upload-size="{{ $maxSize }}">
                                    <label class="upload-file-new__wrapper ratio-3-1">
                                        <div class="upload-file-new-textbox text-center">
                                            <img width="34" height="34" class="svg"
                                                 src="{{ dynamicAsset('public/assets/admin-module/img/document-upload.svg') }}"
                                                 alt="image upload">
                                            <h6 class="mt-2 fw-medium text-center text-info mb-0">{{ translate('Add') }}</h6>
                                        </div>
                                        <img class="upload-file-new-img" loading="lazy"
                                             src="{{ $data->thumbnail ? dynamicStorage('storage/app/public/blog/' . $data->thumbnail) : '' }}"
                                             data-default-src="{{ $data->thumbnail ? dynamicStorage('storage/app/public/blog/' . $data->thumbnail) : '' }}"
                                             alt="">
                                    </label>
                                    <div class="overlay">
                                        <div class="d-flex gap-10 justify-content-center align-items-center h-100">
                                            <button type="button" class="btn btn-outline-info icon-btn edit_btn">
                                                <i class="bi bi-camera"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <p class="text-dark fs-10 text-center">
                                    <span
                                        class="opacity-75">{{ translate(key: '{format}, Image Size - Max {imageSize}', replace: ['format' => IMAGE_ACCEPTED_EXTENSIONS, 'imageSize' => $maxSize]) }}</span>
                                    <span>({{ translate('325 x 100px') }})</span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card card-body mb-3">
                    <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                <label for="" class="mb-0">
                                    {{ translate('Title') }}
                                    <span class="text-danger">*</span>
                                </label>
                                @if($isAiSetupEnabled)
                                    <a href="javascript:"
                                       data-route="{{ route('admin.ai.blog.generate-title') }}"
                                       data-item='@json(["title" => $data->title ?? ''])'
                                       type="button"
                                       class="btn bg-white text-info bg-transparent shadow-none border-0 opacity-1 generate_btn_wrapper p-0 blog_title_auto_fill"
                                       id="title-action-btn"
                                    >
                                        <div class="btn-svg-wrapper">
                                            <img width="18" height="18" class=""
                                                 src="{{ dynamicAsset('public/assets/admin-module/img/ai/blink-right-small.svg') }}"
                                                 alt="">
                                        </div>
                                        <span class="ai-text-animation d-none" role="status">
                                        {{ translate('Just_a_second') }}
                                    </span>
                                        <span class="btn-text">{{ translate('Generate') }}</span>
                                    </a>
                                @endif
                            </div>
                            <div class="outline-wrapper bg-transparent" id="title-container">
                                <input type="text" class="form-control" id="blogTitle"
                                       name="title" tabindex="7" value="{{ old('title', $data->title) }}"
                                       placeholder="{{ translate('ex') }}: {{ translate('Ride_Sharing') }}">
                            </div>
                        </div>
                        <div>
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                <label for="" class="mb-0">
                                    {{ translate('Description') }}
                                    <span class="text-danger">*</span>
                                </label>
                                @if($isAiSetupEnabled)
                                    <a href="javascript:"
                                       data-route="{{ route('admin.ai.blog.generate-description') }}"
                                       data-item='@json(["description" =>  $data->description ?? ''])'
                                       type="button"
                                       class="btn bg-white text-info bg-transparent shadow-none border-0 opacity-1 generate_btn_wrapper p-0 blog_description_auto_fill"
                                       id="description-action-btn"
                                    >
                                        <div class="btn-svg-wrapper">
                                            <img width="18" height="18" class=""
                                                 src="{{ dynamicAsset('public/assets/admin-module/img/ai/blink-right-small.svg') }}"
                                                 alt="">
                                        </div>
                                        <span class="ai-text-animation d-none" role="status">
                                        {{ translate('Just_a_second') }}
                                    </span>
                                        <span class="btn-text">{{ translate('Generate') }}</span>
                                    </a>
                                @endif
                            </div>
                            <div class="outline-wrapper bg-transparent rounded" id="editor-container">
                                <textarea class="summernote" id="blogDescription" name="description">
                                    {{ old('description', $data->description) }}
                                </textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="footer-sticky">
                    <div class="container-fluid">
                        <div class="btn--container justify-content-end py-4">
                            <button type="reset"
                                    class="btn btn-secondary text-capitalize fw-semibold cmn_focus min-w-120"
                                    tabindex="5">{{ translate('reset') }}</button>
                            <button type="submit"
                                    class="btn text-dark border-primary text-capitalize fw-semibold cmn_focus"
                                    tabindex="6" name="draft" value="1">{{ translate('Save_to_Draft') }}</button>
                            <button type="submit" class="btn btn-primary text-capitalize fw-semibold cmn_focus"
                                    tabindex="6">{{ translate('Publish') }}</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <span class="blog-data-to-js"
          data-upload-summernote-image-route="{{ route('admin.blog.upload-summernote-image') }}"
          data-delete-summernote-image-route="{{ route('admin.blog.delete-summernote-image') }}"
          data-csrf-token="{{ csrf_token() }}"
          data-category-index-route="{{ route('admin.blog.category.index') }}"
          data-offcanvas-create-form-title="{{ translate('Add New Category') }}"
          data-offcanvas-update-form-title="{{ translate('Edit Category') }}"
    ></span>
    <!-- End Main Content -->
    @if($isAiSetupEnabled)
        @include('blogmanagement::admin.blog.partials._ai-sidebar')
    @endif
    @include('blogmanagement::admin.blog.category._offcanvas')
@endsection

@push('script')
    <script src="{{ dynamicAsset('public/assets/admin-module/plugins/summernote/summernote-lite.min.js') }}"></script>
    <script src="{{ dynamicAsset('public/assets/admin-module/js/single-image-upload-new.js') }}"></script>
    <script src="{{ dynamicAsset('public/assets/admin-module/js/ai-auto-fill.js') }}"></script>
    <script src="{{ dynamicAsset('public/assets/admin-module/js/blog-management/blog.js') }}"></script>
    <script>
        "use strict";
        let permission = false;
        @can('business_edit')
            permission = true;
        @endcan

        $(document).on('click', '.generate_btn_wrapper', function () {
            let $scope = $(this).closest('.d-flex').parent();
            $scope.find('.error-text.ajax-generated-error').remove();
            $scope.find('input, textarea, select, .note-editor, .select2-selection').removeClass('is-invalid');
        });
    </script>
@endpush
