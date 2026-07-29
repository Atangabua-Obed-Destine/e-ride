@extends('landing-page.layouts.master')
@section('title', 'Blog Search')
@section('content')
    @php
        $count = '<b>' .  translate($blogs->count())  . '</b>';
    @endphp
    <div class="blog-root-container">
        <div class="container pt-3">
            <!-- Page Header Start -->
            @include('blog.partials._header')
            <!-- Page Header End -->
            <section class="blog-section">
                @include('blog.partials._nav')
                <div class="row g-4 mt-0">
                    <div class="col-lg-12">
                        {!! translate('{count} Search Result Found', ['count' => $count]) !!}
                        <div class="row g-4">
                            @forelse($blogs as $blog)
                                <div class="col-md-4">
                                    @include('blog.partials._blog-card', ['blog' => $blog])
                                </div>
                            @empty
                                <div class="col-12">
                                    @include('blog.partials._no-result-found')
                                </div>
                            @endforelse
                        </div>
                        @if ($blogs->hasPages())
                            <div class="d-flex justify-content-center mt-3">
                                {{ $blogs->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </section>
        </div>
    </div>
@endsection

@push('script')
    <script src="{{ dynamicAsset(path: 'public/landing-page/assets/js/blog.js') }}"></script>
@endpush
