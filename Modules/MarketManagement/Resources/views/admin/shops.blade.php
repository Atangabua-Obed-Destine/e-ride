@extends('adminmodule::layouts.master')

@section('title', translate('E-Ride Market shops'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <div class="mb-3">
                <h2 class="fs-22 mb-1">{{ translate('E-Ride Market') }}</h2>
                <p class="fs-12 text-muted mb-0">{{ translate('Any rider can open a shop. E-Ride Verified means staff saw the owner\'s ID and checked the shop (a visit or a video call).') }}</p>
            </div>
            @include('marketmanagement::admin._nav', ['active' => 'shops'])

            <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                <a href="{{ route('admin.market.shops') }}" class="btn btn-sm {{ !$filter ? 'btn-primary' : 'btn-outline-secondary' }}">{{ translate('All') }}</a>
                <a href="{{ route('admin.market.shops', ['filter' => 'requests']) }}" class="btn btn-sm {{ $filter === 'requests' ? 'btn-primary' : 'btn-outline-secondary' }}">{{ translate('Asking for verification') }} ({{ $requests }})</a>
                <a href="{{ route('admin.market.shops', ['filter' => 'verified']) }}" class="btn btn-sm {{ $filter === 'verified' ? 'btn-primary' : 'btn-outline-secondary' }}">{{ translate('Verified') }}</a>
                <a href="{{ route('admin.market.shops', ['filter' => 'closed']) }}" class="btn btn-sm {{ $filter === 'closed' ? 'btn-primary' : 'btn-outline-secondary' }}">{{ translate('Closed') }}</a>
                <form class="ms-auto d-flex gap-2" method="GET">
                    @if($filter)<input type="hidden" name="filter" value="{{ $filter }}">@endif
                    <input type="search" name="search" value="{{ $search }}" class="form-control form-control-sm" placeholder="{{ translate('Shop name') }}" aria-label="{{ translate('Shop name') }}">
                    <button class="btn btn-sm btn-outline-primary">{{ translate('Search') }}</button>
                </form>
            </div>

            <div class="card"><div class="card-body">
                <div class="table-responsive">
                    <table class="table table-borderless align-middle fs-13">
                        <thead class="table-light"><tr>
                            <th>{{ translate('Shop') }}</th><th>{{ translate('Owner') }}</th><th>{{ translate('Activity') }}</th><th>{{ translate('State') }}</th><th class="text-end">{{ translate('Verification') }}</th>
                        </tr></thead>
                        <tbody>
                        @forelse($shops as $shop)
                            <tr>
                                <td><a href="{{ \Modules\MarketManagement\Lib\MarketLinks::shop($shop) }}" target="_blank" rel="noopener" class="fw-semibold">{{ $shop->name }}</a>
                                    <div class="fs-11 text-muted">{{ $shop->category ? translate('market_category_' . $shop->category) : '' }} {{ $shop->area_label ? '· ' . $shop->area_label : '' }}</div></td>
                                <td>{{ trim($shop->owner?->first_name . ' ' . $shop->owner?->last_name) }}
                                    <div class="fs-11 text-muted">{{ $shop->owner?->phone }} · {{ translate('rider since') }} {{ $shop->owner?->created_at?->format('M Y') }}</div></td>
                                <td>{{ $shop->items_count }} {{ translate('items') }}<div class="fs-11 text-muted">{{ $shop->visits }} {{ translate('visits') }} · {{ $shop->chats }} {{ translate('chats') }} · {{ $shop->new_users }} {{ translate('new riders') }}</div></td>
                                <td>
                                    @if($shop->status === 'closed')
                                        <span class="badge badge-danger">{{ translate('Closed') }}</span><div class="fs-11 text-muted">{{ $shop->closed_reason }}</div>
                                    @elseif($shop->isVerified())
                                        <span class="badge badge-info">{{ translate('Verified') }}</span>
                                    @else
                                        <span class="badge badge-success">{{ translate('Open') }}</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @can('place_edit')
                                        @if($shop->status === 'closed')
                                            <form action="{{ route('admin.market.reopen', $shop->id) }}" method="POST">@csrf<button class="btn btn-sm btn-outline-secondary">{{ translate('Reopen') }}</button></form>
                                        @elseif($shop->isVerified())
                                            <form action="{{ route('admin.market.verify', $shop->id) }}" method="POST">@csrf<button name="decision" value="unverify" class="btn btn-sm btn-outline-secondary">{{ translate('Remove the badge') }}</button></form>
                                        @else
                                            <form action="{{ route('admin.market.verify', $shop->id) }}" method="POST" class="d-inline-flex flex-column align-items-end gap-1">@csrf
                                                @if($shop->verification_requested_at)<div class="fs-11 text-primary">{{ translate('Asked on') }} {{ $shop->verification_requested_at->format('d M') }}</div>@endif
                                                <label class="fs-12"><input type="checkbox" name="id_seen" value="1"> {{ translate('ID seen') }}</label>
                                                <label class="fs-12"><input type="checkbox" name="shop_checked" value="1"> {{ translate('Shop visited or video call') }}</label>
                                                <div class="d-flex gap-1">
                                                    @if($shop->verification_requested_at)<button name="decision" value="decline" class="btn btn-sm btn-outline-secondary">{{ translate('Not yet') }}</button>@endif
                                                    <button name="decision" value="verify" class="btn btn-sm btn-primary">{{ translate('Verify') }}</button>
                                                </div>
                                            </form>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">{{ translate('No shops yet.') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $shops->links() }}
            </div></div>
        </div>
    </div>
@endsection
