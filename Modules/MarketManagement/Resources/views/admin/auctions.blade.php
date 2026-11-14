@extends('adminmodule::layouts.master')

@section('title', translate('E-Ride Market auctions'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-22 mb-3">{{ translate('E-Ride Market') }}</h2>
            @include('marketmanagement::admin._nav', ['active' => 'auctions'])
            @if ($errors->any())
                <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif

            <div class="row g-3 mb-3">
                @foreach([
                    [$stats['live'], 'Running now'],
                    [$stats['sold'], 'Sold (30 days)'],
                    [$stats['unsold'], 'Ended without a sale (30 days)'],
                    [$stats['strikes'], 'Strikes (30 days)'],
                    [$stats['blocked'], 'People stopped from bidding'],
                    [$stats['private'], 'Private sellers'],
                ] as [$value, $label])
                    <div class="col-6 col-md-4 col-xl-2"><div class="card h-100"><div class="card-body">
                        <div class="fs-22 fw-bold">{{ $value }}</div><div class="fs-12 text-muted">{{ translate($label) }}</div>
                    </div></div></div>
                @endforeach
            </div>

            <div class="row g-3">
                <div class="col-xl-8">
                    <div class="card"><div class="card-body">
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            @foreach(['live' => 'Running', 'sold' => 'Sold', 'unsold' => 'Not sold'] as $value => $label)
                                <a href="{{ route('admin.market.auctions', ['status' => $value]) }}" class="btn btn-sm {{ $status === $value ? 'btn-dark' : 'btn-outline-secondary' }}">{{ translate($label) }}</a>
                            @endforeach
                        </div>
                        <div class="table-responsive">
                            <table class="table table-borderless align-middle fs-13">
                                <thead class="table-light"><tr>
                                    <th>{{ translate('Item') }}</th><th>{{ translate('Seller') }}</th><th>{{ translate('Bids') }}</th>
                                    <th>{{ translate('Current bid') }}</th><th>{{ translate('Reserve') }}</th><th>{{ translate('Ends') }}</th><th>{{ translate('Winner') }}</th>
                                </tr></thead>
                                <tbody>
                                @forelse($auctions as $auction)
                                    <tr class="border-top">
                                        <td><b>{{ $auction->item?->title }}</b><div class="fs-12 text-muted">{{ $auction->item?->code }}</div></td>
                                        <td>{{ $auction->item?->shop?->name }}<div class="fs-12 text-muted">{{ $auction->item?->shop?->isPrivate() ? translate('Private seller') : translate('Shop') }}</div></td>
                                        <td>{{ $auction->bid_count }}</td>
                                        <td>{{ $auction->current_bid !== null ? getCurrencyFormat($auction->current_bid) : '-' }}</td>
                                        <td>{{ $auction->reserve_price !== null ? getCurrencyFormat($auction->reserve_price) : '-' }}</td>
                                        <td>{{ $auction->ends_at?->format('d M H:i') }}</td>
                                        <td>{{ $auction->winner_id ? (optional(\Modules\UserManagement\Entities\User::find($auction->winner_id))->phone ?? '') : '-' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="text-center text-muted py-4">{{ translate('No auctions here') }}</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                        {{ $auctions->links() }}
                    </div></div>
                </div>

                <div class="col-xl-4">
                    <form action="{{ route('admin.market.auction-rules') }}" method="POST" class="card">@csrf
                        <div class="card-body d-flex flex-column gap-3">
                            <h5 class="mb-0">{{ translate('Private sellers') }}</h5>
                            <div>
                                <label class="form-label" for="a-private">{{ translate('Items a private seller can have for sale') }}</label>
                                <input type="number" id="a-private" name="private_max_items" class="form-control" min="1" max="100" required value="{{ $settings['private_max_items'] }}">
                                <div class="fs-12 text-muted mt-1">{{ translate('People selling things they own. Above this, they turn into a shop. They are never on the map.') }}</div>
                            </div>
                            <h5 class="mb-0 mt-2">{{ translate('Auctions') }}</h5>
                            <div>
                                <label class="form-label" for="a-who">{{ translate('Who can run an auction') }}</label>
                                <select id="a-who" name="auction_who" class="form-select">
                                    <option value="everyone" {{ $settings['auction_who'] === 'everyone' ? 'selected' : '' }}>{{ translate('Everyone') }}</option>
                                    <option value="verified" {{ $settings['auction_who'] === 'verified' ? 'selected' : '' }}>{{ translate('E-Ride Verified shops only') }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label" for="a-bid">{{ translate('Who can bid') }}</label>
                                <select id="a-bid" name="auction_bidders" class="form-select">
                                    <option value="phone_verified" {{ $settings['auction_bidders'] === 'phone_verified' ? 'selected' : '' }}>{{ translate('Phone-verified accounts') }}</option>
                                    <option value="everyone" {{ $settings['auction_bidders'] === 'everyone' ? 'selected' : '' }}>{{ translate('Everyone') }}</option>
                                </select>
                            </div>
                            <div class="row g-2">
                                <div class="col-6"><label class="form-label" for="a-days">{{ translate('Longest auction (days)') }}</label>
                                    <input type="number" id="a-days" name="auction_max_days" class="form-control" min="1" max="30" required value="{{ $settings['auction_max_days'] }}"></div>
                                <div class="col-6"><label class="form-label" for="a-ext">{{ translate('A late bid adds (minutes)') }}</label>
                                    <input type="number" id="a-ext" name="auction_extend_minutes" class="form-control" min="0" max="30" required value="{{ $settings['auction_extend_minutes'] }}"></div>
                                <div class="col-6"><label class="form-label" for="a-pay">{{ translate('Hours to arrange payment') }}</label>
                                    <input type="number" id="a-pay" name="auction_pay_hours" class="form-control" min="1" max="168" required value="{{ $settings['auction_pay_hours'] }}"></div>
                                <div class="col-6"><label class="form-label" for="a-offer">{{ translate('Hours to answer an offer') }}</label>
                                    <input type="number" id="a-offer" name="auction_offer_hours" class="form-control" min="1" max="72" required value="{{ $settings['auction_offer_hours'] }}"></div>
                                <div class="col-6"><label class="form-label" for="a-str">{{ translate('Strikes before bidding stops') }}</label>
                                    <input type="number" id="a-str" name="auction_strikes" class="form-control" min="1" max="10" required value="{{ $settings['auction_strikes'] }}"></div>
                                <div class="col-6"><label class="form-label" for="a-sdays">{{ translate('Strikes clear after (days)') }}</label>
                                    <input type="number" id="a-sdays" name="auction_strike_days" class="form-control" min="7" max="365" required value="{{ $settings['auction_strike_days'] }}"></div>
                            </div>
                            <div class="fs-12 text-muted">{{ translate('The winner gets a normal order: E-Ride never holds the money. A winner who does not arrange payment in time gets a strike, and the seller can offer the item to the next bidder.') }}</div>
                            <button class="btn btn-primary">{{ translate('Save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
