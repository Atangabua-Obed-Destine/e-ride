@extends('storemanagement::partner.layout')

@section('title', translate('All past orders'))

@section('content')
    <h1 class="fs-4 fw-bold mb-3">{{ translate('All past orders') }}</h1>
    @php($labels = [
        'placed' => translate('New'), 'accepted' => translate('Preparing'), 'rider_requested' => translate('Rider'), 'picked_up' => translate('On the way'),
        'delivered' => translate('Delivered'), 'rejected' => translate('Declined'), 'cancelled' => translate('Cancelled'), 'expired' => translate('Expired'), 'delivery_failed' => translate('Not delivered'),
    ])
    <div class="p-card">
        @forelse($orders as $order)
            <div class="p-3 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <b>#{{ $order->ref_id }}</b>
                    <span class="text-muted fs-12">{{ $order->placed_at?->format('d M Y, H:i') }}</span>
                    <span class="ms-auto badge {{ $order->status === 'delivered' ? 'bg-success' : 'bg-secondary' }}">{{ $labels[$order->status] ?? $order->status }}</span>
                </div>
                <div class="fs-14 mt-1">{{ $order->itemsSummary() }}</div>
                <div class="d-flex justify-content-between fs-12 text-muted mt-1">
                    <span>{{ $order->receiver_name }}</span>
                    <span class="fw-bold text-dark">{{ getCurrencyFormat($order->items_subtotal) }}</span>
                </div>
                @if($order->cancel_reason)<div class="fs-12 text-muted">{{ $order->cancel_reason }}</div>@endif
            </div>
        @empty
            <p class="p-3 text-muted mb-0">{{ translate('No orders yet.') }}</p>
        @endforelse
    </div>
    <div class="mt-3">{!! $orders->links() !!}</div>
@endsection
