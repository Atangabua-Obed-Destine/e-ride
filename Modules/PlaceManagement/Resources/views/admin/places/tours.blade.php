@extends('adminmodule::layouts.master')

@section('title', translate('Tours'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                <div>
                    <h2 class="fs-22 mb-1">{{ translate('Tours') }}</h2>
                    <p class="fs-12 text-muted mb-0">{{ translate('Ready-made routes in one city. Riders see the tours of the city they are in, and each stop is a normal E-Ride ride. Stops are verified places: add the place first, then build the tour.') }}</p>
                </div>
                @can('place_edit')
                    <a href="{{ route('admin.place.tours.create', ['zone' => $zoneId ?: null]) }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ translate('New_tour') }}</a>
                @endcan
            </div>

            @include('placemanagement::admin.places._nav', ['active' => 'tours'])

            <div class="d-flex flex-wrap align-items-center my-3 gap-2">
                <ul class="nav nav--tabs p-1 rounded bg-white" role="tablist">
                    <li class="nav-item"><a class="nav-link {{ $zoneId === '' ? 'active' : '' }}" href="{{ route('admin.place.tours') }}">{{ translate('All_cities') }}</a></li>
                    @foreach($zones as $z)
                        <li class="nav-item"><a class="nav-link {{ $zoneId === $z->id ? 'active' : '' }}" href="{{ route('admin.place.tours', ['zone' => $z->id]) }}">{{ $z->name }}{{ $z->is_active ? '' : ' (' . translate('inactive') . ')' }}</a></li>
                    @endforeach
                </ul>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-borderless align-middle">
                            <thead class="table-light align-middle">
                            <tr>
                                <th>{{ translate('Tour') }}</th>
                                <th>{{ translate('City') }}</th>
                                <th>{{ translate('Stops') }}</th>
                                <th>{{ translate('Time_and_rides_(estimate)') }}</th>
                                <th class="text-center">{{ translate('Status') }}</th>
                                <th class="text-center">{{ translate('action') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($tours as $tour)
                                @php($card = $cards[$tour->id])
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $tour->title }} @if($tour->is_featured)<span class="badge bg-warning text-dark ms-1">{{ translate('Featured') }}</span>@endif</div>
                                        <div class="fs-12 text-muted">{{ $tour->title_fr ?: translate('No_French_title_yet') }}</div>
                                    </td>
                                    <td>{{ $tour->zone?->name ?? '-' }}</td>
                                    <td class="fs-12">{{ implode(' → ', $card['stop_names']) ?: translate('No_stops_yet') }}</td>
                                    <td class="fs-12">
                                        @if($card['stops_count'])
                                            {{ translate('About') }} {{ intdiv($card['minutes'], 60) }} h {{ str_pad($card['minutes'] % 60, 2, '0', STR_PAD_LEFT) }}
                                            @if($card['ride_fare'])<br>{{ translate('Rides_about') }} {{ getCurrencyFormat($card['ride_fare']) }}@endif
                                        @else - @endif
                                    </td>
                                    <td class="text-center">
                                        @php($badge = ['draft' => 'bg-secondary', 'published' => 'bg-success', 'hidden' => 'bg-dark'][$tour->status] ?? 'bg-secondary')
                                        <span class="badge {{ $badge }}">{{ translate(ucfirst($tour->status)) }}</span>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center gap-2">
                                            @can('place_edit')
                                                <a href="{{ route('admin.place.tours.edit', $tour) }}" class="btn btn-outline-info btn-sm" title="{{ translate('Edit') }}"><i class="bi bi-pencil-fill"></i></a>
                                                @if($tour->status !== 'published')
                                                    <form action="{{ route('admin.place.tours.status', $tour) }}" method="POST">@csrf<input type="hidden" name="status" value="published"><button class="btn btn-outline-success btn-sm">{{ translate('Publish') }}</button></form>
                                                @else
                                                    <form action="{{ route('admin.place.tours.status', $tour) }}" method="POST">@csrf<input type="hidden" name="status" value="hidden"><button class="btn btn-outline-secondary btn-sm">{{ translate('Hide') }}</button></form>
                                                @endif
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-5">{{ translate('No tours yet. Build the first one: pick verified places in a city, let the AI write the texts, check them and publish.') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
