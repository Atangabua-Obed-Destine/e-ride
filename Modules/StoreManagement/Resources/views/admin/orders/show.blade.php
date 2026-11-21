@extends('adminmodule::layouts.master')

@section('title', translate('Order') . ' #' . $order->ref_id)

@section('content')
    <div class="main-content">
        <div class="container-fluid" style="max-width: 1000px">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <a href="{{ route('admin.store-order.index') }}" class="btn btn-light btn-sm"><i class="bi bi-arrow-left"></i></a>
                <h2 class="fs-22 mb-0">{{ translate('Order') }} #{{ $order->ref_id }}</h2>
                @include('storemanagement::admin.orders._status', ['status' => $order->status])
            </div>

            <div class="row g-3">
                <div class="col-lg-7">
                    <div class="card mb-3"><div class="card-body">
                        <h5 class="mb-3">{{ $order->store?->name }}</h5>
                        @foreach($order->items as $item)
                            <div class="d-flex justify-content-between py-1"><span><b>{{ $item->quantity }}x</b> {{ $item->name }}</span><span>{{ getCurrencyFormat($item->line_total) }}</span></div>
                        @endforeach
                        <hr>
                        <div class="d-flex justify-content-between"><span>{{ translate('Food') }}</span><span>{{ getCurrencyFormat($order->items_subtotal) }}</span></div>
                        <div class="d-flex justify-content-between"><span>{{ $order->byStore() ? translate('Delivery_by_the_store_staff') : translate('Delivery') }}</span><span>{{ getCurrencyFormat($order->delivery_fee) }}</span></div>
                        @if($order->byStore() && $order->own_delivery_share > 0)
                            <div class="d-flex justify-content-between fs-12 text-muted"><span>{{ translate('E-Ride_share_of_that_fee') }}</span><span>{{ getCurrencyFormat($order->own_delivery_share) }}</span></div>
                        @endif
                        @if($order->store_covers > 0)
                            <div class="d-flex justify-content-between fs-12 text-muted"><span>{{ translate('Rider_fare') }} {{ getCurrencyFormat($order->rider_fee) }}: {{ translate('the_store_pays_the_difference') }}</span><span>{{ getCurrencyFormat($order->store_covers) }}</span></div>
                        @endif
                        <div class="d-flex justify-content-between fw-bold fs-16 mt-1"><span>{{ translate('Total_paid') }}</span><span>{{ getCurrencyFormat($order->total) }}</span></div>
                        <div class="fs-12 text-muted mt-1">{{ translate('Payment') }}: {{ $order->payment_method }} · {{ $order->payment_status }}{{ $order->paid_at ? ' · ' . $order->paid_at->format('d M H:i') : '' }}</div>
                        @if($order->refunded_amount > 0)
                            <div class="fs-12 text-success">{{ translate('Refunded_to_wallet') }}: {{ getCurrencyFormat($order->refunded_amount) }}</div>
                        @endif
                        @if($order->note)<div class="alert alert-warning fs-12 mt-3 mb-0">{{ $order->note }}</div>@endif
                    </div></div>

                    <div class="card mb-3"><div class="card-body">
                        <h5 class="mb-3">{{ translate('Timeline') }}</h5>
                        @foreach([
                            'created_at' => translate('Created'), 'placed_at' => translate('Paid_and_sent_to_store'), 'accepted_at' => translate('Accepted_by_store'),
                            'rider_requested_at' => translate('Rider_requested'), 'picked_up_at' => translate('Picked_up'), 'delivered_at' => translate('Delivered'), 'cancelled_at' => translate('Stopped'),
                        ] as $field => $label)
                            @if($order->$field)
                                <div class="d-flex gap-3 py-1"><span class="text-muted" style="min-width: 110px">{{ $order->$field->format('d M H:i') }}</span><span>{{ $label }}{{ $field === 'accepted_at' && $order->prep_minutes ? ' (' . $order->prep_minutes . ' min)' : '' }}{{ $field === 'cancelled_at' && $order->cancelled_by ? ' · ' . $order->cancelled_by : '' }}</span></div>
                            @endif
                        @endforeach
                        @if($order->rider_attempts > 1)<div class="fs-12 text-muted mt-2">{{ translate('Rider_requests') }}: {{ $order->rider_attempts }}</div>@endif
                        @if($order->cancel_reason)<div class="fs-12 mt-2">{{ translate('Reason') }}: {{ $order->cancel_reason }}</div>@endif
                    </div></div>

                    @if($order->review)
                        <div class="card mb-3"><div class="card-body">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <h5 class="mb-0">{{ translate('Customer_rating') }}</h5>
                                @if($order->review->is_hidden)<span class="badge bg-secondary">{{ translate('Hidden') }}</span>@endif
                            </div>
                            <div class="fs-5 text-warning">@for($i = 1; $i <= 5; $i++)<i class="bi {{ $i <= $order->review->rating ? 'bi-star-fill' : 'bi-star' }}"></i>@endfor</div>
                            @if($order->review->comment)<div class="mt-1">"{{ $order->review->comment }}"</div>@endif
                            <div class="fs-12 text-muted mt-1">{{ $order->review->created_at?->format('d M Y, H:i') }}</div>
                            @can('store_edit')
                                <form action="{{ route('admin.store-order.review-visibility', $order) }}" method="POST" class="mt-2">
                                    @csrf
                                    <input type="hidden" name="hidden" value="{{ $order->review->is_hidden ? 0 : 1 }}">
                                    <button class="btn btn-sm btn-outline-secondary">{{ $order->review->is_hidden ? translate('Show_rating_again') : translate('Hide_rating') }}</button>
                                </form>
                                <div class="fs-12 text-muted mt-1">{{ translate('Hidden ratings do not count in the store average.') }}</div>
                            @endcan
                        </div></div>
                    @endif
                </div>

                <div class="col-lg-5">
                    <div class="card mb-3"><div class="card-body">
                        <h5 class="mb-2">{{ translate('Deliver_to') }}</h5>
                        <div class="fw-semibold">{{ $order->receiver_name }}</div>
                        <div><a href="tel:{{ $order->receiver_phone }}">{{ $order->receiver_phone }}</a></div>
                        <div class="fs-12 text-muted">{{ $order->drop_address }}</div>
                        @if($order->byStore())
                            <hr>
                            <h6>{{ translate('The_shop_brings_it') }}</h6>
                            <div class="fs-12">{{ translate('The store sends its own staff: no rider, no trip.') }}
                                @if($order->out_at) {{ translate('Left the store') }}: {{ $order->out_at->format('d M H:i') }}. @endif
                                @if($order->collect_code && !in_array($order->status, \Modules\StoreManagement\Entities\StoreOrder::FINAL, true)) {{ translate('Customer code') }}: <b>{{ $order->collect_code }}</b> @endif
                            </div>
                        @endif
                        @if($order->trip)
                            <hr>
                            <h6>{{ translate('Delivery_trip') }}</h6>
                            <div class="fs-12">#{{ $order->trip->ref_id }} · {{ $order->trip->current_status }}</div>
                            @if($order->trip->driver)
                                <div class="fs-12">{{ $order->trip->driver->first_name }} {{ $order->trip->driver->last_name }} · {{ $order->trip->driver->phone }}</div>
                            @endif
                            @can('trip_view')
                                <a class="btn btn-sm btn-outline-primary mt-2" href="{{ route('admin.trip.show', ['id' => $order->trip->id]) }}">{{ translate('Open_trip') }}</a>
                            @endcan
                        @endif
                    </div></div>

                    @can('store_edit')
                        @if(in_array($order->status, ['placed', 'accepted', 'rider_requested', 'awaiting_payment']))
                            <div class="card mb-3"><div class="card-body">
                                <h5 class="mb-2">{{ translate('Cancel_order') }}</h5>
                                <form action="{{ route('admin.store-order.cancel', $order) }}" method="POST" onsubmit='return confirm(@json(translate("Cancel this order?")))'>
                                    @csrf
                                    <input type="text" name="reason" class="form-control mb-2" required maxlength="200" placeholder="{{ translate('Reason_shown_to_the_customer') }}">
                                    <input type="hidden" name="refund" value="0">
                                    @if(\Modules\StoreManagement\Lib\StoreSettings::refunds())
                                        <label class="d-flex align-items-center gap-2 fs-12 mb-2">
                                            <input type="checkbox" name="refund" value="1" checked> {{ translate('Refund_the_full_amount_to_the_customer_wallet') }}
                                        </label>
                                    @elseif($order->payment_status === 'paid')
                                        <p class="fs-12 text-muted mb-2">{{ translate('Refunds are off in Food & Shops settings: nothing is credited. Settle with the customer yourself.') }}</p>
                                    @endif
                                    <button type="submit" class="btn btn-outline-danger w-100">{{ translate('Cancel_order') }}</button>
                                </form>
                            </div></div>
                        @endif
                        @if($order->status === 'delivery_failed')
                            <div class="card mb-3 border-danger"><div class="card-body">
                                <h5 class="mb-1">{{ translate('Delivery_failed') }}</h5>
                                @if(\Modules\StoreManagement\Lib\StoreSettings::refunds())
                                    <p class="fs-12 text-muted">{{ translate('The delivery fee went back to the customer wallet automatically. Decide about the food.') }}</p>
                                @else
                                    <p class="fs-12 text-muted">{{ translate('Refunds are off in Food & Shops settings. Settle with the customer, then note what was agreed.') }}</p>
                                @endif
                                <form action="{{ route('admin.store-order.resolve', $order) }}" method="POST" class="d-grid gap-2">
                                    @csrf
                                    <input type="text" name="note" class="form-control" maxlength="150" placeholder="{{ translate('Note') }}">
                                    @if(\Modules\StoreManagement\Lib\StoreSettings::refunds())
                                        <button type="submit" name="refund_food" value="1" class="btn btn-primary">{{ translate('Refund_food_to_customer_wallet') }}</button>
                                        <button type="submit" name="refund_food" value="0" class="btn btn-outline-secondary">{{ translate('No_food_refund') }}</button>
                                    @else
                                        <button type="submit" name="refund_food" value="0" class="btn btn-primary">{{ translate('Save') }}</button>
                                    @endif
                                </form>
                            </div></div>
                        @endif
                    @endcan
                </div>
            </div>
        </div>
    </div>
@endsection
