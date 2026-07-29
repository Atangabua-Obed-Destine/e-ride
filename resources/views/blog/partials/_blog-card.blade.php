<div class="blog-card card rounded-10 border-0 h-100">
    <div class="blog-card_image">
        <a href="{{ route('blog.details', $blog->slug) }}">
            <img class="max-h-200px overflow-hidden w-100 aspect-2"
                 src="{{ $blog->thumbnail ? dynamicStorage('storage/app/public/blog/' . $blog->thumbnail) : '' }}" alt=" {{ $blog?->title }}">
        </a>
    </div>
    <div class="p-3 d-flex flex-column gap-12">
        @if(!is_null($blog?->category?->name) && $blog?->category->status)
            <a href="{{ route('blog.category', $blog?->category?->slug) }}" title="{{ $blog?->category?->name }}"
               class="text--base bg--base bg-opacity-10 rounded px-3 py-1 lh-base mb-0 fs-12 line-clamp-1 w-fit-content">
                <span>{{ $blog?->category?->name }}</span>
            </a>
        @endif
        <h3 class="mb-4 mb-sm-30 fw-medium fs-20 fs-16-mobile line-clamp-2">
            <a href="{{ route('blog.details', $blog->slug) }}" class="text-dark line-clamp-2 text-hover-primary">
                {{ $blog->title }}
            </a>
        </h3>

        <div class="pb-3">
            <div class="blog-card_footer">
                <div class="d-flex justify-content-between align-content-center gap-3 text-dark opacity-70">
                    @php($authorName = $blog?->author?->name)
                    @php($authorSlug = $blog?->author?->slug)
                    @if($authorName)
                        <span class="fs-14 d-flex gap-1 fs-12-mobile text-nowrap text-secondary">
                        {{ translate('By') }}
                        <a href="{{ route('blog.author', $authorSlug) }}"
                           class="max-w-20ch line-clamp-1 fs-12-mobile text-secondary text-hover-primary"
                           title="{{ $authorName }}">
                            {{ $authorName }}
                        </a>
                    </span>
                    @endif
                    <span class="text-nowrap fs-14 fs-12-mobile flex-grow-1 text-end text-secondary">{{ $blog->published_at->format('d F Y') }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
