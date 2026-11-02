@extends('storemanagement::partner.layout')

@section('title', translate('Earnings'))

@section('content')
    <h1 class="fs-4 fw-bold mb-3">{{ translate('Earnings') }}</h1>
    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
            <div class="p-card p-3 h-100" style="background:#111114;color:#fff;border-color:#111114">
                <div class="fs-12" style="color:#a3a3ad">{{ translate('E-Ride owes you') }}</div>
                <div class="fs-2 fw-bold">{{ getCurrencyFormat($balance) }}</div>
                <div class="fs-12" style="color:#a3a3ad">{{ translate('The full food price of delivered orders, minus payouts received.') }}</div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="p-card p-3 h-100">
                <div class="fs-12 text-muted">{{ translate('Today') }}</div>
                <div class="fs-4 fw-bold">{{ getCurrencyFormat($today) }}</div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="p-card p-3 h-100">
                <div class="fs-12 text-muted">{{ translate('This week') }}</div>
                <div class="fs-4 fw-bold">{{ getCurrencyFormat($week) }}</div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="p-card p-3">
                <h2 class="fs-6 fw-bold mb-2">{{ translate('Delivered orders') }}</h2>
                @forelse($orders as $order)
                    <div class="d-flex align-items-center gap-2 py-2 border-bottom">
                        <b>#{{ $order->ref_id }}</b>
                        <span class="text-muted fs-12">{{ $order->delivered_at?->format('d M, H:i') }}</span>
                        <span class="ms-auto fw-bold">{{ getCurrencyFormat($order->partner_earning) }}</span>
                    </div>
                @empty
                    <p class="text-muted mb-0">{{ translate('No delivered orders yet.') }}</p>
                @endforelse
                <div class="mt-2">{!! $orders->links() !!}</div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="p-card p-3">
                <h2 class="fs-6 fw-bold mb-2">{{ translate('Payouts received') }}</h2>
                @forelse($payouts as $payout)
                    <div class="d-flex align-items-center gap-2 py-2 border-bottom">
                        <span>{{ $payout->created_at->format('d M Y') }}</span>
                        <span class="text-muted fs-12">{{ $payout->method }} {{ $payout->reference }}</span>
                        <span class="ms-auto fw-bold">{{ getCurrencyFormat($payout->amount) }}</span>
                    </div>
                @empty
                    <p class="text-muted mb-0">{{ translate('No payouts yet.') }}</p>
                @endforelse
            </div>
            <p class="text-center mt-3"><a href="{{ route('partner.orders.history') }}" class="text-muted">{{ translate('All past orders') }}</a></p>
        </div>
    </div>
@endsection
