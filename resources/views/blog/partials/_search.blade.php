<form action="{{ route('blog.search') }}" method="GET" id="search-form" class="blog-search-form" data-suggestion-url="{{ route('blog.search-suggestions') }}">
    <div class="input-group-overlay input-group-sm position-relative">
        <input
            class="cz-filter-search form-control form-control-sm appended-form-control h-45px rounded blog-search-input"
            placeholder="{{ translate('Easily find our blog with a simple search.') }}"
            type="search" value="{{ request()->get('search') ?? '' }}" name="search" id="search" required=""
            autocomplete="off">
        <button type="submit"
                class="input-group-append-overlay p-0 shadow-none bg-transparent border-0 d-inline-block lh-1 d-flex align-items-center h-100 justify-content-center top-0 blog-search-btn">
            <i class="bi bi-search fs-14"></i>
        </button>
        <div class="blog-search-suggestions d-none"></div>
    </div>
</form>
