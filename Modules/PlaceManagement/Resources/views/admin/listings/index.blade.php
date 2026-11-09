@extends('adminmodule::layouts.master')

@section('title', translate('Homes_&_spaces'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            @include('placemanagement::admin.listings._nav', ['active' => 'listings', 'navCounts' => $counts])

            <div class="d-flex flex-wrap justify-content-between align-items-center my-3 gap-3">
                <ul class="nav nav--tabs p-1 rounded bg-white" role="tablist">
                    @foreach(['review' => translate('To_review'), 'live' => translate('Live'), 'paused' => translate('Paused'), 'rented' => translate('Rented'), 'closed' => translate('Refused_or_removed'), 'all' => translate('all')] as $key => $label)
                        <li class="nav-item">
                            <a class="nav-link {{ $tab === $key ? 'active' : '' }}" href="{{ route('admin.listing.index', ['tab' => $key, 'search' => $search]) }}">{{ $label }}</a>
                        </li>
                    @endforeach
                </ul>
                <form action="{{ route('admin.listing.index') }}" method="GET" class="search-form search-form_style-two">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <div class="input-group search-form__input_group">
                        <span class="search-form__icon"><i class="bi bi-search"></i></span>
                        <input type="search" name="search" value="{{ $search }}" class="theme-input-style search-form__input" placeholder="{{ translate('Title,_area,_owner_name_or_phone') }}">
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
                                <th>{{ translate('Owner') }}</th>
                                <th>{{ translate('Rent') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th>{{ translate('Confirmed') }}</th>
                                <th class="text-center">{{ translate('action') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($items as $listing)
                                @php($cover = $listing->photos->first()?->url())
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            @if($cover)
                                                <img src="{{ $cover }}" width="56" height="40" class="rounded object-fit-cover" alt="">
                                            @else
                                                <span class="d-inline-flex align-items-center justify-content-center rounded bg-light" style="width:56px;height:40px"><i class="bi bi-house text-muted"></i></span>
                                            @endif
                                            <div>
                                                <div class="fw-semibold">{{ $listing->title }}</div>
                                                <div class="fs-12 text-muted">{{ translate($listing->type) }} · {{ $listing->neighbourhood }} · {{ $listing->photos->count() }} {{ translate('photos') }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="fs-12">{{ $listing->owner?->first_name }} {{ $listing->owner?->last_name }}<br><span class="text-muted">{{ $listing->owner?->phone }} · {{ translate($listing->owner_role) }}</span></td>
                                    <td class="fs-12">{{ getCurrencyFormat($listing->rent) }} / {{ translate($listing->rent_period) }}</td>
                                    <td>
                                        @include('placemanagement::admin.listings._status', ['listing' => $listing])
                                    </td>
                                    <td class="fs-12">{{ $listing->last_confirmed_at?->diffForHumans() ?? '-' }}</td>
                                    <td class="text-center">
                                        <a href="{{ route('admin.listing.show', $listing) }}" class="btn btn-outline-primary btn-sm">{{ translate('Open') }}</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">
                                        <div class="d-flex flex-column justify-content-center align-items-center gap-2 py-5">
                                            <img src="{{ dynamicAsset('public/assets/admin-module/img/empty-icons/no-data-found.svg') }}" alt="" width="100">
                                            <p class="text-center mb-0">{{ $tab === 'review' ? translate('Nothing to review.') : translate('No listings here.') }}</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-end">{!! $items->links() !!}</div>
                </div>
            </div>
        </div>
    </div>
@endsection
