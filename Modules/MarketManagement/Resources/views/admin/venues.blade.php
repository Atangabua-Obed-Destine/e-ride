@extends('adminmodule::layouts.master')

@section('title', translate('E-Ride Market: markets'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <div class="mb-3">
                <h2 class="fs-22 mb-1">{{ translate('E-Ride Market') }}</h2>
                <p class="fs-12 text-muted mb-0">{{ translate('Markets like Bamenda Main Market. Any shop can list itself under a market with its shed number, directions and pin. A verified shop in a market means E-Ride confirmed the shed, its place and what it sells.') }}</p>
            </div>
            @include('marketmanagement::admin._nav', ['active' => 'venues'])

            <div class="d-flex justify-content-end mb-3">
                @can('place_edit')
                    <a href="{{ route('admin.market.venue-create') }}" class="btn btn-primary btn-sm">{{ translate('Add a market') }}</a>
                @endcan
            </div>

            <div class="card"><div class="card-body">
                <div class="table-responsive">
                    <table class="table table-borderless align-middle fs-13">
                        <thead class="table-light"><tr>
                            <th>{{ translate('Market') }}</th><th>{{ translate('Gates') }}</th><th>{{ translate('Shops') }}</th><th>{{ translate('State') }}</th><th class="text-end"></th>
                        </tr></thead>
                        <tbody>
                        @forelse($venues as $venue)
                            <tr>
                                <td><span class="fw-semibold">{{ $venue->name }}</span>
                                    <div class="fs-11 text-muted">{{ $venue->area_label ?: $venue->city }} ·
                                        <a href="https://www.google.com/maps?q={{ $venue->latitude }},{{ $venue->longitude }}" target="_blank" rel="noopener">{{ translate('Map') }}</a></div></td>
                                <td>{{ count($venue->entrances ?? []) }}<div class="fs-11 text-muted">{{ implode(', ', array_merge(array_map(fn($b) => $b['name'] . ' (' . $b['floors'] . ')', $venue->buildingList()), array_slice($venue->blockList(), 0, 4))) }}</div></td>
                                <td>{{ $venue->shops_count }} · <span class="text-info">{{ $venue->verified_count }} {{ translate('verified') }}</span>
                                    @if($venue->waiting_count)
                                        <div class="fs-11"><a href="{{ route('admin.market.shops', ['filter' => 'requests', 'venue' => $venue->id]) }}">{{ $venue->waiting_count }} {{ translate('asking to be verified') }}</a></div>
                                    @endif</td>
                                <td>@if($venue->is_active)<span class="badge badge-success">{{ translate('Shown') }}</span>@else<span class="badge badge-secondary">{{ translate('Hidden') }}</span>@endif</td>
                                <td class="text-end">@can('place_edit')<a href="{{ route('admin.market.venue-edit', $venue->id) }}" class="btn btn-sm btn-outline-primary">{{ translate('Edit') }}</a>@endcan</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">{{ translate('No markets yet.') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div></div>
        </div>
    </div>
@endsection
