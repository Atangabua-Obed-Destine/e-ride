<div class="row g-4 sticky-top-nav-search">
    <div class="col-lg-8 order-1 order-lg-0">
        <div class="position-relative">
            <ul class="blog-top-nav d-flex gap-3">
                <li class="{{ is_null(request()->route('category_slug')) || request()->route('category_slug') === 'all' ? 'active' : '' }}">
                    <a href="{{ route('blog.category', 'all') }}" class="border rounded-10 px-4 py-2 fs-12-mobile">
                        <span class="opacity-70">{{ translate('All') }}</span>
                    </a>
                </li>
                @foreach($blogCategories as $blogCategory)
                    <li class="{{ request()->route('category_slug') === $blogCategory->slug ? 'active' : '' }}">
                    <a href="{{ route('blog.category', $blogCategory->slug) }}"
                           class="border rounded-10 px-4 py-2 fs-12-mobile">
                            <span class="opacity-70">{{ $blogCategory->name }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="blog-top-nav_prev-btn align-items-center" style="display: none;">
                <div class="previous-button">
                    <button class="btn rounded-circle aspect-1">
                        <i class="text-white bi bi-chevron-left"></i>
                    </button>
                </div>
            </div>

            <div class="blog-top-nav_next-btn align-items-center" style="display: none;">
                <div class="next-button d-flex justify-content-end">
                    <button class="btn rounded-circle aspect-1">
                        <i class="text-white bi bi-chevron-right"></i>
                    </button>
                </div>

            </div>
        </div>
    </div>
    <div class="col-lg-4">
        @include('blog.partials._search')
    </div>
</div>
