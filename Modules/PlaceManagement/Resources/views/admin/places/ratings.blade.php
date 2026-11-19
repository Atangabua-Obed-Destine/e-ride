@extends('adminmodule::layouts.master')

@section('title', translate('Riders_ratings'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <div class="mb-3">
                <h2 class="fs-22 mb-1">{{ translate('Riders_ratings') }}</h2>
                <p class="fs-12 text-muted mb-0">{{ translate('Stars and short reviews E-Ride riders gave places in Things to do. They show next to Google\'s rating. Hide a rating that is rude, fake or not about the place; phone numbers and emails are removed automatically.') }}</p>
            </div>

            @include('placemanagement::admin.places._nav', ['active' => 'ratings'])

            <div class="d-flex flex-wrap justify-content-between align-items-center my-3 gap-3">
                <ul class="nav nav--tabs p-1 rounded bg-white" role="tablist">
                    @foreach(['all' => translate('all'), 'written' => translate('With_a_review'), 'low' => translate('1_or_2_stars'), 'hidden' => translate('Hidden')] as $key => $label)
                        <li class="nav-item"><a class="nav-link {{ $filter === $key ? 'active' : '' }}" href="{{ route('admin.place.ratings', ['show' => $key, 'search' => $search]) }}">{{ $label }}</a></li>
                    @endforeach
                </ul>
                <form action="{{ route('admin.place.ratings') }}" method="GET" class="search-form search-form_style-two">
                    <input type="hidden" name="show" value="{{ $filter }}">
                    <div class="input-group search-form__input_group">
                        <span class="search-form__icon"><i class="bi bi-search"></i></span>
                        <input type="search" name="search" value="{{ $search }}" class="theme-input-style search-form__input" placeholder="{{ translate('Search_by_place_or_review') }}">
                    </div>
                    <button type="submit" class="btn btn-primary">{{ translate('search') }}</button>
                </form>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-borderless align-middle">
                            <thead class="table-light align-middle">
                            <tr>
                                <th>{{ translate('Place') }}</th>
                                <th>{{ translate('Rider') }}</th>
                                <th>{{ translate('Stars') }}</th>
                                <th>{{ translate('Review') }}</th>
                                <th>{{ translate('Date') }}</th>
                                <th class="text-center">{{ translate('action') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($ratings as $r)
                                <tr class="{{ $r->is_visible ? '' : 'opacity-50' }}">
                                    <td>
                                        <div class="fw-semibold">{{ $r->place_name ?? translate('Unknown_place') }}</div>
                                        <div class="fs-12 text-muted">{{ str_starts_with($r->place_key, 'g:') ? translate('Google_place') : translate('Verified_place') }}</div>
                                    </td>
                                    <td class="fs-12">{{ trim(($r->user?->first_name ?? '') . ' ' . ($r->user?->last_name ?? '')) ?: '-' }}<br><span class="text-muted">{{ $r->user?->phone }}</span></td>
                                    <td class="text-nowrap">{{ str_repeat('★', $r->stars) }}<span class="text-muted">{{ str_repeat('☆', 5 - $r->stars) }}</span></td>
                                    <td class="fs-12" style="max-width: 380px">{{ $r->comment ?? '-' }}
                                        @if(!$r->is_visible && $r->hidden_reason)<div class="text-danger">{{ translate('Hidden') }}: {{ $r->hidden_reason }}</div>@endif
                                    </td>
                                    <td class="fs-12 text-nowrap">{{ $r->updated_at?->format('d M Y') }}</td>
                                    <td class="text-center">
                                        @can('place_edit')
                                            <form action="{{ route('admin.place.ratings.visibility', $r) }}" method="POST" class="d-flex gap-1 justify-content-center">
                                                @csrf
                                                @if($r->is_visible)
                                                    <input type="hidden" name="visible" value="0">
                                                    <input type="text" name="reason" maxlength="150" class="form-control form-control-sm" style="width: 140px" placeholder="{{ translate('Reason') }}">
                                                    <button class="btn btn-outline-danger btn-sm">{{ translate('Hide') }}</button>
                                                @else
                                                    <input type="hidden" name="visible" value="1">
                                                    <button class="btn btn-outline-success btn-sm">{{ translate('Show') }}</button>
                                                @endif
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-5">{{ translate('No ratings yet.') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    {{ $ratings->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection
