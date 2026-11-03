@extends('adminmodule::layouts.master')

@section('title', translate('Hotels_&_Stays'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                <div>
                    <h2 class="fs-22 mb-1">{{ translate('Hotels_&_Stays') }}</h2>
                    <p class="fs-12 text-muted mb-0">{{ translate('Places E-Ride has checked. They appear first in the app, with your photos, prices and a Verified badge. Nearby hotels from Google appear below them.') }}</p>
                </div>
                @can('place_add')
                    <a href="{{ route('admin.place.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ translate('Add_listing') }}</a>
                @endcan
            </div>

            @include('placemanagement::admin.places._nav', ['active' => 'listings'])

            <div class="d-flex flex-wrap justify-content-between align-items-center my-3 gap-3">
                <ul class="nav nav--tabs p-1 rounded bg-white" role="tablist">
                    @foreach(['all' => translate('all'), 'hotel' => translate('Hotels'), 'guest_house' => translate('Guest_houses'), 'lodging' => translate('Other_lodging')] as $key => $label)
                        <li class="nav-item">
                            <a class="nav-link {{ $filters['category'] === $key ? 'active' : '' }}" href="{{ route('admin.place.index', ['category' => $key, 'status' => $filters['status'], 'search' => $filters['search']]) }}">{{ $label }}</a>
                        </li>
                    @endforeach
                </ul>
                <form action="{{ route('admin.place.index') }}" method="GET" class="search-form search-form_style-two">
                    <input type="hidden" name="category" value="{{ $filters['category'] }}">
                    <input type="hidden" name="status" value="{{ $filters['status'] }}">
                    <div class="input-group search-form__input_group">
                        <span class="search-form__icon"><i class="bi bi-search"></i></span>
                        <input type="search" name="search" value="{{ $filters['search'] }}" class="theme-input-style search-form__input" placeholder="{{ translate('Search_by_name,_phone_or_address') }}">
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
                                <th>{{ translate('Price_per_night') }}</th>
                                <th>{{ translate('Zone') }}</th>
                                <th class="text-center">{{ translate('Photos') }}</th>
                                <th class="text-center">{{ translate('Google') }}</th>
                                <th class="text-center">{{ translate('Featured') }}</th>
                                <th class="text-center">{{ translate('Active') }}</th>
                                <th class="text-center">{{ translate('action') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($places as $place)
                                @php($cover = $place->photoUrls()[0] ?? null)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            @if($cover)
                                                <img src="{{ $cover }}" width="56" height="40" class="rounded object-fit-cover" alt="">
                                            @else
                                                <span class="d-inline-flex align-items-center justify-content-center rounded bg-light" style="width:56px;height:40px"><i class="bi bi-building text-muted"></i></span>
                                            @endif
                                            <div>
                                                <div class="fw-semibold">{{ $place->name }}</div>
                                                <div class="fs-12 text-muted">{{ translate($place->category) }}@if($place->phone) · {{ $place->phone }}@endif</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="fs-12">
                                        @if($place->price_min || $place->price_max)
                                            {{ $place->price_min ? getCurrencyFormat($place->price_min) : '' }}{{ $place->price_min && $place->price_max ? ' - ' : '' }}{{ $place->price_max ? getCurrencyFormat($place->price_max) : '' }}
                                        @else
                                            <span class="text-muted">{{ translate('Not_set') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($place->zone)
                                            {{ $place->zone->name }}
                                        @else
                                            <span class="badge bg-danger">{{ translate('Outside_all_zones') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if(count($place->photos ?? []))
                                            {{ count($place->photos) }}
                                        @else
                                            <span class="badge bg-warning text-dark">{{ translate('None') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($place->google_place_id)
                                            <i class="bi bi-link-45deg text-success fs-5" title="{{ translate('Linked_to_its_Google_listing') }}"></i>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($place->is_featured)<i class="bi bi-star-fill text-warning"></i>@else<span class="text-muted">-</span>@endif
                                    </td>
                                    <td class="text-center">
                                        <form action="{{ route('admin.place.status', $place) }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="is_active" value="{{ $place->is_active ? 0 : 1 }}">
                                            <label class="switcher mx-auto">
                                                <input class="switcher_input" type="checkbox" onchange="this.form.submit()" {{ $place->is_active ? 'checked' : '' }} @cannot('place_edit') disabled @endcannot>
                                                <span class="switcher_control"></span>
                                            </label>
                                        </form>
                                    </td>
                                    <td>
                                        <div class="d-flex justify-content-center gap-2">
                                            @can('place_edit')
                                                <a href="{{ route('admin.place.edit', $place) }}" class="btn btn-outline-info btn-action" title="{{ translate('edit') }}"><i class="bi bi-pencil-fill"></i></a>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8">
                                        <div class="d-flex flex-column justify-content-center align-items-center gap-2 py-5">
                                            <img src="{{ dynamicAsset('public/assets/admin-module/img/empty-icons/no-data-found.svg') }}" alt="" width="100">
                                            <p class="text-center mb-0">{{ translate('No verified listings yet.') }}</p>
                                            <p class="text-center fs-12 text-muted mb-0">{{ translate('Tip: open Google results, find a hotel you know, and tap Make a verified listing.') }}</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-end">{!! $places->links() !!}</div>
                </div>
            </div>
        </div>
    </div>
@endsection
