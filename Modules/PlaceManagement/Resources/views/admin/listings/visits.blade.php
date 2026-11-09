@extends('adminmodule::layouts.master')

@section('title', translate('Visits_to_do'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            @include('placemanagement::admin.listings._nav', ['active' => 'visits'])

            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="mb-1">{{ translate('Visits_to_do') }}</h5>
                    <p class="fs-12 text-muted">{{ translate('Book a date with the owner, then open the visit page on your phone when you are at the place. It checks you are at the pin before the place can be Verified.') }}</p>
                    <div class="table-responsive">
                        <table class="table table-borderless align-middle">
                            <thead class="table-light"><tr>
                                <th>{{ translate('Place') }}</th><th>{{ translate('Owner') }}</th><th>{{ translate('Asked') }}</th><th>{{ translate('Staff_and_date') }}</th><th class="text-center">{{ translate('At_the_place') }}</th>
                            </tr></thead>
                            <tbody>
                            @forelse($open as $v)
                                <tr>
                                    <td><a href="{{ route('admin.listing.show', $v->listing) }}" class="fw-semibold">{{ $v->listing->title }}</a>
                                        <div class="fs-12 text-muted">{{ translate($v->listing->type) }} · {{ $v->listing->neighbourhood }}</div></td>
                                    <td class="fs-12">{{ $v->listing->owner?->first_name }}<br>{{ $v->listing->owner?->phone }}</td>
                                    <td class="fs-12">{{ $v->created_at->diffForHumans() }}</td>
                                    <td style="min-width: 300px">
                                        @can('place_edit')
                                            <form action="{{ route('admin.listing.assign', $v) }}" method="POST" class="d-flex flex-wrap gap-2">@csrf
                                                <select name="staff_id" class="form-select form-select-sm" style="max-width: 150px" required>
                                                    <option value="">{{ translate('Staff') }}</option>
                                                    @foreach($staff as $s)
                                                        <option value="{{ $s->id }}" {{ $v->staff_id === $s->id ? 'selected' : '' }}>{{ $s->first_name }} {{ $s->last_name }}</option>
                                                    @endforeach
                                                </select>
                                                <input type="datetime-local" name="scheduled_for" class="form-control form-control-sm" style="max-width: 190px" required value="{{ $v->scheduled_for?->format('Y-m-d\TH:i') }}">
                                                <button class="btn btn-sm btn-outline-primary">{{ $v->scheduled_for ? translate('Change') : translate('Book') }}</button>
                                            </form>
                                        @else
                                            {{ $v->staff?->first_name ?? '-' }} {{ $v->scheduled_for?->format('d M H:i') }}
                                        @endcan
                                    </td>
                                    <td class="text-center">
                                        @can('place_edit')
                                            <a href="{{ route('admin.listing.visit', $v) }}" class="btn btn-sm btn-primary">{{ translate('Open_visit_page') }}</a>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">{{ translate('No visits to do.') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">{{ translate('Recent_visits') }}</h5>
                    <table class="table table-sm fs-12">
                        @forelse($done as $v)
                            <tr><td><a href="{{ route('admin.listing.show', $v->listing) }}">{{ $v->listing->title }}</a></td><td>{{ translate($v->status) }}</td>
                                <td>{{ $v->staff?->first_name }}</td><td>{{ $v->completed_at->format('d M Y H:i') }}</td><td>{{ $v->distance_m }} m</td></tr>
                        @empty
                            <tr><td class="text-muted">{{ translate('None_yet') }}</td></tr>
                        @endforelse
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
