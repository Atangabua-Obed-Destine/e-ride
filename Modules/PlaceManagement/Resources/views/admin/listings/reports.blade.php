@extends('adminmodule::layouts.master')

@section('title', translate('Reports'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            @include('placemanagement::admin.listings._nav', ['active' => 'reports'])

            <ul class="nav nav--tabs p-1 rounded bg-white mb-3" role="tablist">
                <li class="nav-item"><a class="nav-link {{ $status === 'open' ? 'active' : '' }}" href="{{ route('admin.listing.reports', ['status' => 'open']) }}">{{ translate('Open') }}</a></li>
                <li class="nav-item"><a class="nav-link {{ $status === 'closed' ? 'active' : '' }}" href="{{ route('admin.listing.reports', ['status' => 'closed']) }}">{{ translate('Decided') }}</a></li>
            </ul>

            <div class="card">
                <div class="card-body">
                    <p class="fs-12 text-muted">{{ translate('Uphold = a strike for the owner (3 in 6 months pause their listings for 30 days). Scam = the listing is removed and the owner blocked. Dismiss = no effect.') }}</p>
                    <div class="table-responsive">
                        <table class="table table-borderless align-middle">
                            <thead class="table-light"><tr>
                                <th>{{ translate('Place') }}</th><th>{{ translate('Reason') }}</th><th>{{ translate('By') }}</th><th>{{ translate('When') }}</th><th class="text-center">{{ translate('Decision') }}</th>
                            </tr></thead>
                            <tbody>
                            @forelse($reports as $r)
                                <tr>
                                    <td>@if($r->listing)<a href="{{ route('admin.listing.show', $r->listing) }}" class="fw-semibold">{{ $r->listing->title }}</a>
                                        <div class="fs-12 text-muted">{{ $r->listing->owner?->first_name }} · {{ $r->listing->owner?->phone }}</div>@endif</td>
                                    <td class="fs-12"><b>{{ translate($r->reason) }}</b>@if($r->visit_id) · {{ translate('after_a_visit') }}@endif
                                        @if($r->note)<div class="text-muted">{{ $r->note }}</div>@endif</td>
                                    <td class="fs-12">{{ $r->reporter?->first_name }} {{ $r->reporter?->last_name }}<br>{{ $r->reporter?->phone }}</td>
                                    <td class="fs-12">{{ $r->created_at->format('d M H:i') }}</td>
                                    <td class="text-center">
                                        @if($r->status === 'open')
                                            @can('place_edit')
                                                <form action="{{ route('admin.listing.decide', $r) }}" method="POST" class="d-flex flex-wrap gap-1 justify-content-center">@csrf
                                                    <button name="decision" value="upheld" class="btn btn-sm btn-warning">{{ translate('Uphold') }}</button>
                                                    <button name="decision" value="blocked" class="btn btn-sm btn-danger" onclick="return confirm('{{ translate('Remove_the_listing_and_block_the_owner') }}')">{{ translate('Scam') }}</button>
                                                    <button name="decision" value="dismissed" class="btn btn-sm btn-outline-secondary">{{ translate('Dismiss') }}</button>
                                                </form>
                                            @endcan
                                        @else
                                            <span class="badge bg-light text-dark">{{ translate($r->status) }}</span>
                                            <div class="fs-12 text-muted">{{ $r->handled_at?->format('d M') }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">{{ translate('No reports.') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-end">{!! $reports->links() !!}</div>
                </div>
            </div>
        </div>
    </div>
@endsection
