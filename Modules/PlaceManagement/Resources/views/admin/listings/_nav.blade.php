@php($navCounts = $navCounts ?? [
    'review' => \Modules\PlaceManagement\Entities\Listing::where(fn($q) => $q->where('status', 'pending')->orWhere('needs_review', 1))->count(),
    'visits' => \Modules\PlaceManagement\Entities\ListingVerification::whereIn('status', ['requested', 'scheduled'])->count(),
    'reports' => \Modules\PlaceManagement\Entities\ListingReport::where('status', 'open')->count(),
    'problems' => \Modules\PlaceManagement\Entities\ListingReservation::where('status', 'problem')->count(),
    'payouts' => \Modules\PlaceManagement\Entities\ListingPayout::where('status', 'pending')->count(),
])
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
    <div>
        <h2 class="fs-22 mb-1">{{ translate('Homes_&_spaces') }}</h2>
        <p class="fs-12 text-muted mb-0">{{ translate('Places to rent that customers list in the app. Nothing is public until you approve it. Staff visits make a place E-Ride Verified.') }}</p>
    </div>
</div>
<ul class="nav nav--tabs p-1 rounded bg-white mb-3 flex-wrap" role="tablist">
    <li class="nav-item">
        <a class="nav-link {{ $active === 'listings' ? 'active' : '' }}" href="{{ route('admin.listing.index') }}">{{ translate('Listings') }}
            @if($navCounts['review'])<span class="badge bg-danger ms-1">{{ $navCounts['review'] }}</span>@endif</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $active === 'visits' ? 'active' : '' }}" href="{{ route('admin.listing.visits') }}">{{ translate('Visits_to_do') }}
            @if($navCounts['visits'])<span class="badge bg-warning text-dark ms-1">{{ $navCounts['visits'] }}</span>@endif</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $active === 'reports' ? 'active' : '' }}" href="{{ route('admin.listing.reports') }}">{{ translate('Reports') }}
            @if($navCounts['reports'])<span class="badge bg-danger ms-1">{{ $navCounts['reports'] }}</span>@endif</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $active === 'reservations' ? 'active' : '' }}" href="{{ route('admin.listing.reservations') }}">{{ translate('Reservations') }}
            @if($navCounts['problems'])<span class="badge bg-danger ms-1">{{ $navCounts['problems'] }}</span>@endif</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $active === 'payouts' ? 'active' : '' }}" href="{{ route('admin.listing.payouts') }}">{{ translate('Payouts') }}
            @if($navCounts['payouts'])<span class="badge bg-warning text-dark ms-1">{{ $navCounts['payouts'] }}</span>@endif</a>
    </li>
    @can('place_edit')
        <li class="nav-item">
            <a class="nav-link {{ $active === 'settings' ? 'active' : '' }}" href="{{ route('admin.listing.settings') }}">{{ translate('Policy') }}</a>
        </li>
    @endcan
</ul>
