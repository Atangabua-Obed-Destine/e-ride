@extends('adminmodule::layouts.master')

@section('title', $listing->title)

@section('content')
    @php($yes = fn($v) => $v ? translate('Yes') : translate('No'))
    <div class="main-content">
        <div class="container-fluid" style="max-width: 1100px">
            <a href="{{ route('admin.listing.index') }}" class="fs-12"><i class="bi bi-arrow-left"></i> {{ translate('Back_to_listings') }}</a>
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 my-3">
                <div>
                    <h2 class="fs-22 mb-1">{{ $listing->title }}</h2>
                    <div class="fs-12 text-muted mb-2">{{ translate($listing->type) }} · {{ $listing->neighbourhood }} · {{ translate('Listed') }} {{ $listing->created_at->format('d M Y') }}</div>
                    @include('placemanagement::admin.listings._status', ['listing' => $listing])
                </div>
                @can('place_edit')
                    <div class="d-flex flex-wrap gap-2">
                        @if(in_array($listing->status, ['pending', 'changes', 'refused']))
                            <form action="{{ route('admin.listing.approve', $listing) }}" method="POST">@csrf
                                <button class="btn btn-success"><i class="bi bi-check-lg"></i> {{ translate('Approve') }}</button></form>
                        @endif
                        @if($listing->needs_review && $listing->status !== 'pending')
                            <form action="{{ route('admin.listing.reviewed', $listing) }}" method="POST">@csrf
                                <button class="btn btn-outline-success">{{ translate('Edit_looks_fine') }}</button></form>
                        @endif
                    </div>
                @endcan
            </div>

            @if($listing->review_note)
                <div class="alert alert-info fs-12">{{ translate('Last_note_to_the_owner') }}: {{ $listing->review_note }}</div>
            @endif

            <div class="row g-3">
                <div class="col-lg-7">
                    <div class="card mb-3"><div class="card-body">
                        <h5 class="mb-3">{{ translate('Photos') }}</h5>
                        <div class="d-flex flex-wrap gap-2">
                            @forelse($listing->photos as $photo)
                                <a href="{{ $photo->url() }}" target="_blank" class="position-relative">
                                    <img src="{{ $photo->url() }}" width="150" height="110" class="rounded object-fit-cover" alt="">
                                    <span class="badge {{ $photo->byEride() ? 'bg-success' : 'bg-dark' }} position-absolute bottom-0 start-0 m-1">{{ $photo->byEride() ? translate('By_E-Ride') : translate('Owner') }}</span>
                                </a>
                            @empty
                                <span class="text-muted fs-12">{{ translate('No_photos') }}</span>
                            @endforelse
                        </div>
                    </div></div>

                    <div class="card mb-3"><div class="card-body">
                        <h5 class="mb-3">{{ translate('Details') }}</h5>
                        <p class="fs-14">{{ $listing->description ?: '-' }}</p>
                        <table class="table table-sm fs-12 mb-0">
                            <tr><th>{{ translate('Rent') }}</th><td>{{ getCurrencyFormat($listing->rent) }} / {{ translate($listing->rent_period) }}</td></tr>
                            <tr><th>{{ translate('Advance') }}</th><td>{{ $listing->advance_months !== null ? $listing->advance_months . ' ' . translate('months') : '-' }}</td></tr>
                            <tr><th>{{ translate('Caution') }}</th><td>{{ $listing->caution !== null ? getCurrencyFormat($listing->caution) : '-' }}</td></tr>
                            <tr><th>{{ translate('Free') }}</th><td>{{ $listing->units_available }} · {{ translate('from') }} {{ $listing->available_from?->format('d M Y') ?? translate('now') }}</td></tr>
                            <tr><th>{{ translate('Furnished') }}</th><td>{{ $yes($listing->furnished) }}</td></tr>
                            <tr><th>{{ translate('Water') }}</th><td>{{ $listing->water ? translate($listing->water) : '-' }}</td></tr>
                            <tr><th>{{ translate('Light') }}</th><td>{{ $listing->power ? translate($listing->power) : '-' }} · {{ translate('Backup_power') }}: {{ $yes($listing->backup_power) }}</td></tr>
                            <tr><th>{{ translate('Toilet_and_kitchen') }}</th><td>{{ $listing->toilet ? translate($listing->toilet) : '-' }} / {{ $listing->kitchen ? translate($listing->kitchen) : '-' }}</td></tr>
                            <tr><th>{{ translate('Fence_and_security') }}</th><td>{{ $yes($listing->fenced) }} / {{ $yes($listing->security) }}</td></tr>
                            @php($amenityLabels = \Modules\PlaceManagement\Entities\Listing::amenityLabels())
                            <tr><th>{{ translate('Extras') }}</th><td>{{ collect($listing->amenities ?? [])->map(fn($a) => $amenityLabels[$a] ?? $a)->implode(', ') ?: '-' }}</td></tr>
                            <tr><th>{{ translate('Address') }}</th><td>{{ $listing->address ?: '-' }} · <a href="https://www.google.com/maps/search/?api=1&query={{ $listing->latitude }},{{ $listing->longitude }}" target="_blank">{{ translate('Open_the_pin_in_Maps') }}</a>
                                @if(!$listing->zone_id)<span class="badge bg-danger ms-1">{{ translate('Outside_all_zones') }}</span>@endif</td></tr>
                            <tr><th>{{ translate('Views') }}</th><td>{{ $listing->views }}</td></tr>
                        </table>
                    </div></div>

                    <div class="card mb-3"><div class="card-body">
                        <h5 class="mb-3">{{ translate('Visits_by_seekers') }} ({{ $listing->visits->count() }})</h5>
                        <table class="table table-sm fs-12 mb-0">
                            @forelse($listing->visits->sortByDesc('created_at') as $visit)
                                <tr><td>{{ $visit->seeker?->first_name }} {{ $visit->seeker?->last_name }}</td><td>{{ $visit->visit_date?->format('d M') }}, {{ translate($visit->time_window) }}</td>
                                    <td>{{ translate($visit->status) }}@if($visit->outcome) · {{ translate($visit->outcome) }}@endif @if($visit->taking) · <b>{{ translate('Taking_it') }}</b>@endif</td></tr>
                            @empty
                                <tr><td class="text-muted">{{ translate('No_visits_yet') }}</td></tr>
                            @endforelse
                        </table>
                    </div></div>
                </div>

                <div class="col-lg-5">
                    <div class="card mb-3"><div class="card-body fs-12">
                        <h5 class="mb-3">{{ translate('Owner') }}</h5>
                        <div class="fw-semibold fs-14">{{ $listing->owner?->first_name }} {{ $listing->owner?->last_name }}</div>
                        <div>{{ $listing->owner?->phone }} · {{ translate($listing->owner_role) }}</div>
                        <div class="text-muted mt-1">{{ translate('Confirmed_available') }}: {{ $listing->last_confirmed_at?->format('d M Y') ?? '-' }}
                            @if($listing->confirmDueAt()) · {{ translate('next_due') }} {{ $listing->confirmDueAt()->format('d M') }}@endif</div>
                    </div></div>

                    @can('place_edit')
                        <div class="card mb-3"><div class="card-body">
                            <h5 class="mb-3">{{ translate('Tell_the_owner') }}</h5>
                            @if(in_array($listing->status, ['pending', 'live', 'paused']))
                                <form action="{{ route('admin.listing.changes', $listing) }}" method="POST" class="mb-3">@csrf
                                    <textarea name="note" class="form-control mb-2" rows="2" maxlength="300" required placeholder="{{ translate('What_should_the_owner_change') }}"></textarea>
                                    <button class="btn btn-outline-primary btn-sm">{{ translate('Ask_for_changes') }}</button>
                                </form>
                            @endif
                            @if($listing->status === 'pending')
                                <form action="{{ route('admin.listing.refuse', $listing) }}" method="POST" class="mb-3">@csrf
                                    <textarea name="note" class="form-control mb-2" rows="2" maxlength="300" required placeholder="{{ translate('Why_it_cannot_be_listed') }}"></textarea>
                                    <button class="btn btn-outline-danger btn-sm">{{ translate('Refuse') }}</button>
                                </form>
                            @endif
                            @if(!in_array($listing->status, ['removed']))
                                <form action="{{ route('admin.listing.remove', $listing) }}" method="POST" onsubmit="return confirm('{{ translate('Remove_this_listing') }}')">@csrf
                                    <textarea name="note" class="form-control mb-2" rows="2" maxlength="300" required placeholder="{{ translate('Reason_shown_to_the_owner') }}"></textarea>
                                    <label class="fs-12 d-block mb-2"><input type="checkbox" name="block" value="1"> {{ translate('Also_block_the_owner_(scam)') }}</label>
                                    <button class="btn btn-danger btn-sm">{{ translate('Remove') }}</button>
                                </form>
                            @endif
                        </div></div>
                    @endcan

                    <div class="card mb-3"><div class="card-body fs-12">
                        <h5 class="mb-3">{{ translate('E-Ride_visits') }}</h5>
                        @forelse($listing->verifications as $v)
                            <div class="mb-2">{{ translate($v->status) }} · {{ $v->staff?->first_name ?? translate('not_assigned') }}
                                · {{ ($v->completed_at ?? $v->scheduled_for)?->format('d M Y H:i') ?? $v->created_at->format('d M Y') }}
                                @if($v->distance_m !== null) · {{ $v->distance_m }} m {{ translate('from_the_pin') }}@endif
                                @if($v->note)<div class="text-muted">{{ $v->note }}</div>@endif
                                @if($v->isOpen())<a href="{{ route('admin.listing.visits') }}">{{ translate('Manage') }}</a>@endif</div>
                        @empty
                            <span class="text-muted">{{ translate('The_owner_has_not_asked_for_a_visit.') }}</span>
                        @endforelse
                    </div></div>

                    <div class="card mb-3"><div class="card-body fs-12">
                        <h5 class="mb-3">{{ translate('Reports') }}</h5>
                        @forelse($listing->reports as $r)
                            <div class="mb-2"><b>{{ translate($r->reason) }}</b> · {{ $r->reporter?->first_name }} · {{ $r->created_at->format('d M') }} · {{ translate($r->status) }}
                                @if($r->note)<div class="text-muted">{{ $r->note }}</div>@endif</div>
                        @empty
                            <span class="text-muted">{{ translate('No_reports') }}</span>
                        @endforelse
                    </div></div>
                </div>
            </div>
        </div>
    </div>
@endsection
