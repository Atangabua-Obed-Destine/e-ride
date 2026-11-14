@extends('adminmodule::layouts.master')

@section('title', translate('E-Ride Market orders'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-22 mb-3">{{ translate('E-Ride Market') }}</h2>
            @include('marketmanagement::admin._nav', ['active' => 'orders'])
            @if ($errors->any())
                <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif

            <div class="d-flex flex-wrap gap-2 mb-3">
                @foreach(['7' => '7 days', '30' => '30 days', '90' => '90 days'] as $value => $label)
                    <a href="{{ route('admin.market.orders', ['days' => $value, 'status' => $status]) }}" class="btn btn-sm {{ (string)$days === (string)$value ? 'btn-primary' : 'btn-outline-primary' }}">{{ translate($label) }}</a>
                @endforeach
            </div>

            <div class="row g-3 mb-3">
                @foreach([
                    [$stats['requests'], 'Order requests'],
                    [$stats['confirmed_rate'] . '%', 'Confirmed'],
                    [$stats['expired_rate'] . '%', 'Expired, no answer'],
                    [$stats['completed'], 'Completed'],
                    [$stats['reply'] !== null ? $stats['reply'] . ' min' : '-', 'Average seller reply'],
                    [$stats['rating'] !== null ? number_format($stats['rating'], 1) : '-', 'Average shop rating'],
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
                            @foreach(['' => 'All', 'open' => 'In progress', 'completed' => 'Completed', 'declined' => 'Declined', 'expired' => 'Expired', 'cancelled' => 'Cancelled'] as $value => $label)
                                <a href="{{ route('admin.market.orders', ['days' => $days, 'status' => $value]) }}" class="btn btn-sm {{ $status === $value ? 'btn-dark' : 'btn-outline-secondary' }}">{{ translate($label) }}</a>
                            @endforeach
                        </div>
                        <div class="table-responsive">
                            <table class="table table-borderless align-middle fs-13">
                                <thead class="table-light"><tr>
                                    <th>{{ translate('Order') }}</th><th>{{ translate('Shop') }}</th><th>{{ translate('Buyer') }}</th>
                                    <th>{{ translate('Total') }}</th><th>{{ translate('How') }}</th><th>{{ translate('State') }}</th><th></th>
                                </tr></thead>
                                <tbody>
                                @forelse($orders as $order)
                                    <tr class="border-top">
                                        <td><b>{{ $order->code }}</b><div class="fs-12 text-muted">{{ $order->created_at?->format('d M H:i') }}</div></td>
                                        <td>{{ $order->shop?->name }}<div class="fs-12 text-muted">{{ $order->seller?->phone }}</div></td>
                                        <td>{{ trim($order->buyer?->first_name . ' ' . $order->buyer?->last_name) }}<div class="fs-12 text-muted">{{ $order->buyer?->phone }}</div></td>
                                        <td>{{ getCurrencyFormat($order->confirmed_total ?? $order->requested_total) }}
                                            @if($order->confirmed_total !== null && $order->confirmed_total != $order->requested_total)
                                                <div class="fs-12 text-muted"><s>{{ getCurrencyFormat($order->requested_total) }}</s></div>
                                            @endif
                                        </td>
                                        <td class="fs-12">{{ translate('market_fulfilment_' . $order->fulfilment) }}<br>{{ translate('market_pay_' . $order->pay_method) }}</td>
                                        <td><span class="badge {{ in_array($order->status, ['completed', 'confirmed', 'ready', 'on_the_way']) ? 'bg-success' : (in_array($order->status, ['requested', 'changes']) ? 'bg-warning text-dark' : 'bg-secondary') }}">{{ translate('market_order_' . $order->status) }}</span>
                                            @if($order->decline_reason)<div class="fs-12 text-muted">{{ translate('market_decline_' . $order->decline_reason) }}</div>@endif
                                            @if($order->cancel_reason)<div class="fs-12 text-muted">{{ $order->cancel_reason }}</div>@endif
                                        </td>
                                        <td><button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#o-{{ $order->id }}" aria-expanded="false">{{ translate('Lines') }}</button></td>
                                    </tr>
                                    <tr class="collapse" id="o-{{ $order->id }}"><td colspan="7" class="bg-light">
                                        @foreach($order->lines as $line)
                                            <div class="d-flex gap-3 fs-12 py-1">
                                                <span class="flex-grow-1">{{ $line->title }} {{ collect([$line->colour, $line->size])->filter()->implode(' · ') }}</span>
                                                <span>{{ translate('asked') }} {{ $line->qty }}</span>
                                                <span>{{ translate('confirmed') }} {{ $line->qty_confirmed ?? '-' }}</span>
                                                <span>{{ getCurrencyFormat($line->unit_price) }}{{ $line->wholesale ? ' (' . translate('wholesale') . ')' : '' }}</span>
                                            </div>
                                        @endforeach
                                        @if($order->note)<div class="fs-12 mt-1"><b>{{ translate('Buyer note') }}:</b> {{ $order->note }}</div>@endif
                                        @if($order->review)
                                            <div class="fs-12 mt-2 d-flex align-items-center gap-2">
                                                <b>{{ str_repeat('★', $order->review->stars) }}</b> {{ $order->review->comment }}
                                                <form method="POST" action="{{ route('admin.market.review-hide', $order->review->id) }}" class="ms-auto">@csrf
                                                    <input type="hidden" name="hidden" value="{{ $order->review->hidden ? 0 : 1 }}">
                                                    <button class="btn btn-sm btn-outline-danger">{{ $order->review->hidden ? translate('Show the rating') : translate('Hide the rating') }}</button>
                                                </form>
                                            </div>
                                        @endif
                                    </td></tr>
                                @empty
                                    <tr><td colspan="7" class="text-center text-muted py-4">{{ translate('No orders yet') }}</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                        {{ $orders->links() }}
                    </div></div>
                </div>

                <div class="col-xl-4">
                    <form action="{{ route('admin.market.order-rules') }}" method="POST" class="card">@csrf
                        <div class="card-body d-flex flex-column gap-3">
                            <h5 class="mb-0">{{ translate('Order rules') }}</h5>
                            <div>
                                <label class="form-label" for="r-answer">{{ translate('Minutes the seller has to answer') }}</label>
                                <input type="number" id="r-answer" name="answer_minutes" class="form-control" min="10" max="1440" required value="{{ $settings['answer_minutes'] }}">
                                <div class="fs-12 text-muted mt-1">{{ translate('Unanswered orders close by themselves. Outside a shop\'s opening hours the time starts when it opens.') }}</div>
                            </div>
                            <div>
                                <label class="form-label" for="r-agree">{{ translate('Minutes the buyer has to agree to changes') }}</label>
                                <input type="number" id="r-agree" name="agree_minutes" class="form-control" min="10" max="1440" required value="{{ $settings['agree_minutes'] }}">
                            </div>
                            <div>
                                <label class="form-label" for="r-open">{{ translate('Orders a buyer can have in progress') }}</label>
                                <input type="number" id="r-open" name="open_orders_per_buyer" class="form-control" min="1" max="50" required value="{{ $settings['open_orders_per_buyer'] }}">
                            </div>
                            <div>
                                <label class="form-label" for="r-demote">{{ translate('Shops drop in the feed for a week after this many expired orders in 7 days') }}</label>
                                <input type="number" id="r-demote" name="demote_after_expired" class="form-control" min="1" max="50" required value="{{ $settings['demote_after_expired'] }}">
                            </div>
                            <div class="form-check form-switch">
                                <input type="hidden" name="before_pay_verified_only" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" id="r-before" name="before_pay_verified_only" value="1" {{ $settings['before_pay_verified_only'] ? 'checked' : '' }}>
                                <label class="form-check-label" for="r-before">{{ translate('"Pay before delivery" only for E-Ride Verified shops') }}</label>
                            </div>
                            <div class="fs-12 text-muted">{{ translate('E-Ride never holds the money: buyers pay sellers directly, and close the order with their 4-digit code.') }}</div>
                            <button class="btn btn-primary">{{ translate('Save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
