@extends('adminmodule::layouts.master')

@section('title', translate('Blog_Page'))

@push('css_or_js')
@endpush

@section('content')
    @php($env = env('APP_MODE') == 'live' ? 'live' : 'test')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-20 fw-bold mb-3 text-capitalize">{{translate('Blog')}}</h2>
            <div class="mb-3">
                <div class="">
                    @include('blogmanagement::admin.blog.setting.partials._blog_inline_menu')
                </div>
            </div>

            <div class="card card-body mb-3">
                <div class="d-flex flex-md-nowrap flex-wrap align-items-center justify-content-between gap-3">
                    <div class="w-0 flex-grow-1">
                        <h4 class="text-capitalize mb-1">{{ translate('Blog Section') }}</h4>
                        <div class="fs-14">
                            {{ translate('Enabling this option will make the blog section visible on the website for viewers') }}
                        </div>
                    </div>
                    <label
                        class="max-w300 w-100 form-control rounded d-flex align-items-center justify-content-between">
                        <label for="pageVisibilityStatus"
                               class="fs-14 lh-1 d-block cursor-pointer text-dark">{{ $name ?? translate('Activate Blog') }}</label>
                        <label class="switcher cmn_focus rounded-pill">
                            <input class="switcher_input update-business-setting" id="pageVisibilityStatus"
                                   name="is_enabled" type="checkbox" data-name="is_enabled"
                                   data-type="{{ BLOG_PAGE }}" tabindex="4"
                                   data-url="{{ route('admin.blog.update-settings') }}"
                                   data-icon="{{ $isEnabled == 0 ? dynamicAsset('public/assets/admin-module/img/svg/info-circle-green.svg') : dynamicAsset('public/assets/admin-module/img/svg/info-circle-red.svg') }}"
                                   data-title="{{ $isEnabled == 0 ? translate('Want to enable Blog section') : translate('Want to disable Blog section') }}?"
                                   data-sub-title="{{ $isEnabled == 0 ? translate(' If you turn on the Blog section, users will see it in the website.') : translate('If you turn off the Blog section, users will no longer see it in the website.') }}"
                                   data-confirm-btn="{{ $isEnabled == 0 ? translate('Yes, On') : translate('Yes, Off') }}"
                                   data-cancel-btn="{{ translate('Not Now') }}"
                                {{ $isEnabled == 1 ? 'checked' : '' }}
                            >
                            <span class="switcher_control"></span>
                        </label>
                    </label>
                </div>
            </div>

            <div class="card card-body mb-3">
                <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                    <div>
                        <h4 class="mb-1">
                            {{ translate('Blog Intro Section Preview') }}
                        </h4>
                        <p class="fs-12 mb-0">{{ translate('See how your blog intro section setup section will look to customers.') }}</p>
                    </div>
                    <button type="button" class="btn btn-outline-primary text-capitalize cmn_focus px-3" tabindex="1"
                            data-bs-toggle="offcanvas" data-bs-target="#sectionPriview-offcanvas">
                        <i class="bi bi-eye-fill"></i>
                        {{ translate('Section_Preview') }}
                    </button>
                </div>
            </div>

            <div class="card card-body mb-3 ">
                <div class="mb-20">
                    <h5 class="mb-1">{{ translate('Intro Section') }}</h5>
                    <p class="fs-12 mb-0">{{ translate('Configure the section content by setting the title and subtitle.') }}</p>
                </div>

                <form action="{{ route('admin.blog.update-intro') }}" id="" enctype="multipart/form-data" method="POST">
                    @csrf
                    <div class="p-lg-4 p-3 rounded bg-F6F6F6 mb-20">
                        <div class="mb-3">
                            <label for="" class="mb-2">
                                {{ translate('Title') }}
                                <span class="text-danger">*</span>
                            </label>
                            <div class="character-count d-flex flex-column align-items-end">
                                <input type="text" class="form-control character-count-field"
                                       maxlength="100" data-max-character="100" id="solution_title"
                                       name="title" tabindex="5"
                                       value="{{ $blogPageTitle }}"
                                       placeholder="{{ translate('Enter_Blog_Title') }}"
                                       required>
                                <span>{{translate('0/100')}}</span>
                            </div>
                        </div>
                        <div>
                            <label for="" class="mb-2">
                                {{ translate('Sub_Title') }}
                                <span class="text-danger">*</span>
                            </label>
                            <div class="character-count d-flex flex-column align-items-end">
                                <textarea name="subtitle" id="" rows="2" tabindex="6"
                                          class="form-control character-count-field" maxlength="255"
                                          data-max-character="255"
                                          placeholder="{{ translate('Enter_Blog_Sub_Title') }}"
                                          required>{{ $blogPageSubtitle }}</textarea>
                                <span>{{translate('0/255')}}</span>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-3">
                        <button class="btn btn-secondary cmn_focus min-w-120 min-w-max-content-mobile" tabindex="3"
                                type="reset">
                            {{ translate('reset') }}
                        </button>
                        <button class="btn btn-primary cmn_focus min-w-120 min-w-max-content-mobile" tabindex="4"
                                type="submit">
                            {{ translate('save') }}
                        </button>
                    </div>
                </form>
            </div>
            <div class="card card-body">
                <h5 class="mb-20">{{ translate('Filter Blog') }}</h5>

                <form action="{{ url()->full() }}" method="GET">
                    <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                        <div class="row g-3 align-items-end">
                            <div class="col-lg-4 col-sm-6">
                                <div>
                                    <label for="" class="mb-2">{{ translate('Category') }}</label>
                                    <select class="form-select js-select cmn_focus" id="" name="blog_category_id">
                                        <option value="">{{ translate('Select_Category')}}</option>
                                        @foreach($blogCategories as $blogCategory)
                                            <option
                                                value="{{ $blogCategory->id }}" {{ request()->get('blog_category_id') == $blogCategory->id ? 'selected' : '' }}>{{ $blogCategory->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-4 col-sm-6">
                                <div>
                                    <label for="" class="mb-2">{{ translate('Publish_Date') }}</label>
                                    <div class="position-relative">
                                        <span class="bi bi-calendar icon-absolute-on-right"></span>
                                        <input type="text" name="publish_date"
                                               class="js-daterangepicker form-control cursor-pointer"
                                               value="{{request('publish_date')}}"
                                               placeholder="{{ translate('Select_Date') }}" autocomplete="off"
                                               readonly>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="search" value="{{ request()->get('search') }}">
                            <div class="col-lg-4">
                                <div class="d-flex justify-content-end gap-3">
                                    <a href="{{ route('admin.blog.index') }}"
                                       class="btn btn-secondary cmn_focus min-w-120 min-w-max-content-mobile"
                                       tabindex="3">
                                        {{ translate('reset') }}
                                    </a>
                                    <button class="btn btn-primary cmn_focus min-w-120 min-w-max-content-mobile"
                                            tabindex="4" type="submit">
                                        {{ translate('save') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>

                <hr class="my-4"/>

                <div>
                    <div class="table-top d-flex flex-wrap gap-10 justify-content-between mb-3">
                        <form action="javascript:;" class="search-form search-form_style-two"
                              method="GET">
                            <div class="input-group search-form__input_group">
                                <span class="search-form__icon px-2">
                                    <i class="bi bi-search"></i>
                                </span>
                                <input type="search" class="theme-input-style search-form__input"
                                       value="{{ request()->get('search') }}" name="search" id="search"
                                       placeholder="{{ translate('search by Title') }}">
                            </div>
                            <button type="submit" class="btn btn-primary search-submit"
                                    data-url="{{ url()->full() }}">{{ translate('search') }}</button>
                        </form>

                        <div class="d-flex flex-wrap gap-3">
                            <a href="{{ route('admin.blog.index') }}"
                               class="btn btn-outline-primary px-3" data-bs-toggle="tooltip"
                               data-bs-title="{{ translate('refresh') }}">
                                <i class="bi bi-arrow-repeat"></i>
                            </a>
                            <div class="dropdown">
                                <button type="button" class="btn btn-outline-primary"
                                        data-bs-toggle="dropdown">
                                    <i class="bi bi-download"></i>
                                    {{ translate('download') }}
                                    <i class="bi bi-caret-down-fill"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                                    <li>
                                        <a class="dropdown-item"
                                           href="{{ route('admin.blog.export', ['blog_category_id' => request()->get('blog_category_id'), 'publish_date' => request()->get('publish_date'), 'search' => request()->get('search'), 'file' => 'excel']) }}">
                                            {{ translate('excel') }}
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            <a href="{{ route('admin.blog.create') }}" type="button"
                               class="btn btn-primary text-capitalize cmn_focus">
                                {{ translate('Create_Blog') }}
                            </a>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-borderless align-middle table-hover col-mx-w300 text-dark">
                            <thead class="table-light align-middle text-capitalize">
                            <tr>
                                <th>{{ translate('SL') }}</th>
                                <th>{{ translate('ID') }}</th>
                                <th>{{ translate('Category') }}</th>
                                <th>{{ translate('Title') }}</th>
                                <th>{{ translate('Writer') }}</th>
                                <th>{{ translate('Publish_Date') }}</th>
                                <th class="text-center">{{ translate('Status') }}</th>
                                <th class="text-center">{{ translate('Action') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($blogList as $key => $blog)
                                <?php
                                    $source = $blog->is_published ? $blog : $blog->draft;
                                ?>
                                <tr>
                                    <td>{{ ++$key }}</td>
                                    <td>
                                        <div class="fw-semibold">#{{ $blog->readable_id }}</div>
                                    </td>
                                    <td>{{ $source?->category?->name ?? 'N/A' }}</td>
                                    <td>
                                        <div class="min-w-150">
                                            {{ $source?->title ?? 'N/A' }}
                                        </div>
                                    </td>
                                    <td>{{ $source?->author?->name ?? 'N/A' }}</td>
                                    <td>
                                        {{ $source?->published_at?->format('d M, Y') ?? 'N/A' }}
                                    </td>
                                    <td class="text-center">
                                        <label class="switcher mx-auto"
                                               @if(!$blog->is_published)
                                                   data-bs-toggle="tooltip"
                                               data-bs-title="{{ translate('Status can not be changed as it is not published yet') }}"
                                            @endif
                                        >
                                            <input class="switcher_input update-blog-status"
                                                   @if($blog->is_published)
                                                       name="status"
                                                   type="checkbox"
                                                   {{ $blog->status == 1 ? 'checked' : '' }}
                                                   data-url="{{ route('admin.blog.status', $blog ->id) }}"
                                                   data-icon="{{ dynamicAsset('public/assets/admin-module/img/svg/info-circle-red.svg') }}"
                                                   data-title="{{$blog->status == 0 ? translate('Are you sure to turn on the Blog Status') : translate('Are you sure to turn off the Blog Status') }}?"
                                                   data-sub-title="{{$blog->status == 0 ? translate('When you turn on this Blog, it will be visible to the Blog list for users') : translate('When you turn on this Blog, it will not be visible to the Blog list for users')}}"
                                                   id="{{ $blog->id }}"
                                                   data-name="status"
                                                   data-confirm-btn="{{ $blog->status == 0 ? translate('Yes, On') : translate('Yes, Off') }}"
                                                   data-cancel-btn="{{ translate('Not Now') }}"
                                                   data-cancel-btn-class="btn-secondary"
                                                   @else
                                                       disabled
                                                @endif
                                            >
                                            <span class="switcher_control"></span>
                                        </label>
                                    </td>
                                    <td>
                                        <div class="d-flex justify-content-center gap-2 align-items-center">
                                            @if($blog->draft)
                                                <a href="{{ route('admin.blog.draft.edit', $blog->draft->id) }}"
                                                   class="btn btn-outline-success btn-action"
                                                   title="{{ translate('Edit Draft') }}">
                                                    <i class="bi bi-card-text"></i>
                                                </a>
                                            @endif
                                            @if($blog->is_published)
                                                <a href="{{ route('blog.details', ['blog_slug' => $blog->slug,'preview' => 'published']) }}"
                                                   target="_blank"
                                                   class="btn btn-outline-success btn-action"
                                                   title="{{ translate('View Blog') }}">
                                                    <i class="bi bi-eye-fill"></i>
                                                </a>
                                                <a href="{{ route('admin.blog.edit', $blog->id) }}"
                                                   class="btn btn-outline-info btn-action"
                                                   title="{{ translate('Edit Blog') }}">
                                                    <i class="bi bi-pencil-fill"></i>
                                                </a>
                                            @endif
                                            <a
                                                data-url="{{ route('admin.blog.destroy', ['id' => $blog?->id]) }}"
                                                data-icon="{{ dynamicAsset('public/assets/admin-module/img/trash.png') }}"
                                                data-title="{{ translate('Are you sure to delete this Blog')."?" }}"
                                                data-sub-title="{{ translate('Once you delete it') . ', ' . translate('This will be permanently removed from the list.') }}"
                                                data-confirm-btn="{{translate("Yes, Delete")}}"
                                                data-cancel-btn="{{translate("Not Now")}}"
                                                class="btn btn-outline-danger btn-action d-flex justify-content-center align-items-center delete-button"
                                                data-bs-toggle="tooltip" title="{{translate("Delete")}}">
                                                <i class="bi bi-trash-fill"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="14">
                                        <div
                                            class="d-flex flex-column justify-content-center align-items-center gap-2 py-3">
                                            <img
                                                src="{{ dynamicAsset('public/assets/admin-module/img/empty-icons/no-data-found.svg') }}"
                                                alt=""
                                                width="100">
                                            <p class="text-center">{{translate('no_data_available')}}</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>

                    </div>
                </div>
                <div class="d-flex justify-content-end mt-3">
                    {{ $blogList->links() }}
                </div>
            </div>


        </div>
    </div>
    <!-- End Main Content -->

    {{-- Section Preview Offcanvas --}}
    <div class="offcanvas offcanvas-end" id="sectionPriview-offcanvas" style="--bs-offcanvas-width: 755px;">
        <form action="javascript:" class="d-flex flex-column h-100">
            <div class="offcanvas-header">
                <h4 class="offcanvas-title flex-grow-1">
                    {{ translate('Blog Section Preview') }}
                </h4>
                <button type="button" class="btn btn-circle btn-secondary text-white" data-bs-dismiss="offcanvas" aria-label="Close" style="--size: 20px;">
                    <i class="bi bi-x-lg d-flex"></i>
                </button>
            </div>
            <div class="offcanvas-body scrollbar-thin">
                <div class="h-100 overflow-y-auto py-5 px-0">
                    <img src="{{ dynamicAsset('public/assets/admin-module/img/preview/blog-intro-section.png') }}"
                         alt="" class="img-fluid w-100 d-block">
                </div>
            </div>
        </form>
    </div>
@endsection

@push('script')
    <script src="{{ dynamicAsset('public/assets/admin-module/js/single-image-upload-new.js') }}"></script>
    <script>
        "use strict";
        let permission = false;
        @can('business_edit')
            permission = true;
        @endcan
    </script>
@endpush
