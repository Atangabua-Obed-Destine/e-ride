@extends('adminmodule::layouts.master')

@section('title', translate('Priority Setup'))

@push('css_or_js')
@endpush

@section('content')
    @php($env = env('APP_MODE') == 'live' ? 'live' : 'test')
    <!-- Main Content -->
    <div class="main-content">
        <form action="{{ route('admin.blog.priority-setup.update') }}" id="" enctype="multipart/form-data" method="POST">
            @csrf
            <div class="container-fluid">
                <h2 class="fs-20 fw-bold mb-3 text-capitalize">{{translate('Blog')}}</h2>
                <div class="mb-3">
                    <div class="">
                        @include('blogmanagement::admin.blog.setting.partials._blog_inline_menu')
                    </div>
                </div>
                <div class="card card-body">
                    <div class="row g-3">
                        <div class="col-lg-4">
                            <div>
                                <h5 class="mb-2">{{ translate('Categories') }}</h5>
                                <p class="mb-0">{{ translate('Set up and manage categories with sorting and visibility for both admin and website users.') }}</p>
                            </div>
                        </div>
                        <div class="col-lg-8">
                            <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                                <h5 class="mb-3">{{ translate('Category sorting list') }}</h5>
                                <div class="card card-body border-0 shadow-none">
                                    <div class="d-flex flex-column gap-4">
                                        <div class="checked-label-wrapper">
                                            <input type="radio" name="category_sorting" value="latest" id="cat_sort_default" tabindex="1" {{ $categorySortingPriority == 'latest' || !isset($categorySortingPriority) ? 'checked' : ''  }}>
                                            <label for="cat_sort_default" class="media gap-2 align-items-center checked-label-bold">
                                                <span class="media-body">{{ translate('Default - Sort by Date (Newest First)') }}</span>
                                            </label>
                                        </div>
                                        <div class="checked-label-wrapper">
                                            <input type="radio" name="category_sorting" value="oldest" id="cat_sort_date" tabindex="2" {{ $categorySortingPriority == 'oldest' ? 'checked' : ''  }}>
                                            <label for="cat_sort_date" class="media gap-2 align-items-center checked-label-bold">
                                                <span class="media-body">{{ translate('Sort by Date (Oldest First)') }}</span>
                                            </label>
                                        </div>
                                        <div class="checked-label-wrapper">
                                            <input type="radio" name="category_sorting" value="popular" id="cat_sort_polular" tabindex="3" {{ $categorySortingPriority == 'popular' ? 'checked' : ''  }}>
                                            <label for="cat_sort_polular" class="media gap-2 align-items-center checked-label-bold">
                                                <span class="media-body">{{ translate('Sort by Popularity (Show Most Clicked First)') }}</span>
                                            </label>
                                        </div>
                                        <div class="checked-label-wrapper">
                                            <input type="radio" name="category_sorting" value="a2z" id="cat_sort_alphabet" tabindex="4" {{ $categorySortingPriority == 'a2z' ? 'checked' : ''  }}>
                                            <label for="cat_sort_alphabet" class="media gap-2 align-items-center checked-label-bold">
                                                <span class="media-body">{{ translate('Sort by Alphabetical (A To Z)') }}</span>
                                            </label>
                                        </div>
                                        <div class="checked-label-wrapper">
                                            <input type="radio" name="category_sorting" value="z2a" id="cat_sort_alphabet_reverse" tabindex="5" {{ $categorySortingPriority == 'z2a' ? 'checked' : ''  }}>
                                            <label for="cat_sort_alphabet_reverse" class="media gap-2 align-items-center checked-label-bold">
                                                <span class="media-body">{{ translate('Sort by Alphabetical (Z To A)') }}</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <hr>
                        </div>
                        <div class="col-lg-4">
                            <div>
                                <h5 class="mb-2">{{ translate('Blog List') }}</h5>
                                <p class="mb-0">{{ translate('Manage and display blogs with sorting options for both admin and website users.') }}</p>
                            </div>
                        </div>
                        <div class="col-lg-8">
                            <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                                <h5 class="mb-3">{{ translate('Blog sorting list') }}</h5>
                                <div class="card card-body border-0 shadow-none">
                                    <div class="d-flex flex-column gap-4">
                                        <div class="checked-label-wrapper">
                                            <input type="radio" name="blog_sorting" value="latest" id="blog_sort_default" tabindex="6" {{ $blogSortingPriority == 'latest' || !isset($blogSortingPriority) ? 'checked' : ''  }}>
                                            <label for="blog_sort_default" class="media gap-2 align-items-center checked-label-bold">
                                                <span class="media-body">{{ translate('Default - Sort by Date (Newest First)') }}</span>
                                            </label>
                                        </div>
                                        <div class="checked-label-wrapper">
                                            <input type="radio" name="blog_sorting" value="oldest" id="blog_sort_date" tabindex="7" {{ $blogSortingPriority == 'oldest' ? 'checked' : ''  }}>
                                            <label for="blog_sort_date" class="media gap-2 align-items-center checked-label-bold">
                                                <span class="media-body">{{ translate('Sort by Date (Oldest First)') }}</span>
                                            </label>
                                        </div>
                                        <div class="checked-label-wrapper">
                                            <input type="radio" name="blog_sorting" value="popular" id="blog_sort_popular" tabindex="8" {{ $blogSortingPriority == 'popular' ? 'checked' : ''  }}>
                                            <label for="blog_sort_popular" class="media gap-2 align-items-center checked-label-bold">
                                                <span class="media-body">{{ translate('Sort by Popularity (Show Most Clicked First)') }}</span>
                                            </label>
                                        </div>
                                        <div class="checked-label-wrapper">
                                            <input type="radio" name="blog_sorting" value="a2z" id="blog_sort_alphabet" tabindex="9" {{ $blogSortingPriority == 'a2z' ? 'checked' : ''  }}>
                                            <label for="blog_sort_alphabet" class="media gap-2 align-items-center checked-label-bold">
                                                <span class="media-body">{{ translate('Sort by Alphabetical (A To Z)') }}</span>
                                            </label>
                                        </div>
                                        <div class="checked-label-wrapper">
                                            <input type="radio" name="blog_sorting" value="z2a" id="blog_sort_alphabet_reverse" tabindex="10" {{ $blogSortingPriority == 'z2a' ? 'checked' : ''  }}>
                                            <label for="blog_sort_alphabet_reverse" class="media gap-2 align-items-center checked-label-bold">
                                                <span class="media-body">{{ translate('Sort by Alphabetical (Z To A)') }}</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="footer--sticky">
                <div class="container-fluid">
                    <div class="btn--container justify-content-end py-4">
                        <button type="reset" class="btn btn-secondary text-capitalize cmn_focus min-w-120"
                                tabindex="11">{{ translate('reset') }}</button>
                        <button type="submit" class="btn btn-primary text-capitalize cmn_focus"
                                tabindex="12">{{ translate('save_information') }}</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <!-- End Main Content -->
@endsection
