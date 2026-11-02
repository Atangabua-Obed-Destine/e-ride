@extends('adminmodule::layouts.master')

@section('title', translate('Food_&_Shop_Orders'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-22 mb-3">{{ translate('Food_&_Shop_Orders') }}</h2>

            <div class="d-flex flex-wrap justify-content-between align-items-center my-3 gap-3">
                <ul class="nav nav--tabs p-1 rounded bg-white" role="tablist">
                    @foreach(['active' => translate('In_progress'), 'attention' => translate('Needs_attention'), 'delivered' => translate('Delivered'), 'all' => translate('all')] as $key => $label)
                        <li class="nav-item">
                            <a class="nav-link {{ $filters['status'] === $key ? 'active' : '' }}" href="{{ route('admin.store-order.index', array_merge($filters, ['status' => $key])) }}">{{ $label }}</a>
                        </li>
                    @endforeach
                </ul>
                <form action="{{ route('admin.store-order.index') }}" method="GET" class="d-flex flex-wrap gap-2">
                    <input type="hidden" name="status" value="{{ $filters['status'] }}">
                    <select name="store" class="form-select" style="max-width: 200px" onchange="this.form.submit()">
                        <option value="">{{ translate('All_stores') }}</option>
                        @foreach($stores as $s)
                            <option value="{{ $s->id }}" {{ $filters['store'] === $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                        @endforeach
                    </select>
                    <input type="search" name="search" value="{{ $filters['search'] }}" class="form-control" style="max-width: 220px" placeholder="{{ translate('Order_#,_name_or_phone') }}">
                    <button type="submit" class="btn btn-primary">{{ translate('search') }}</button>
                </form>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-borderless align-middle">
                            <thead class="table-light"><tr>
                                <th>{{ translate('Order') }}</th>
                                <th>{{ translate('Store') }}</th>
                                <th>{{ translate('Customer') }}</th>
                                <th>{{ translate('Items') }}</th>
                                <th class="text-end">{{ translate('Food') }}</th>
                                <th class="text-end">{{ translate('Delivery') }}</th>
                                <th class="text-end">{{ translate('Paid') }}</th>
                                <th>{{ translate('Status') }}</th>
                            </tr></thead>
                            <tbody>
                            @forelse($orders as $order)
                                <tr onclick="location.href='{{ route('admin.store-order.show', $order) }}'" style="cursor:pointer">
                                    <td><b>#{{ $order->ref_id }}</b><div class="fs-12 text-muted">{{ $order->created_at->format('d M, H:i') }}</div></td>
                                    <td>{{ $order->store?->name }}</td>
                                    <td>{{ $order->receiver_name }}<div class="fs-12 text-muted">{{ $order->receiver_phone }}</div></td>
                                    <td class="fs-12" style="max-width: 260px">{{ \Illuminate\Support\Str::limit($order->itemsSummary(), 70) }}</td>
                                    <td class="text-end">{{ getCurrencyFormat($order->items_subtotal) }}</td>
                                    <td class="text-end">{{ getCurrencyFormat($order->delivery_fee) }}</td>
                                    <td class="text-end">
                                        {{ getCurrencyFormat($order->total) }}
                                        <div class="fs-12 text-muted">{{ $order->payment_method }}{{ $order->refunded_amount > 0 ? ' · ' . translate('refunded') . ' ' . getCurrencyFormat($order->refunded_amount) : '' }}</div>
                                    </td>
                                    <td>@include('storemanagement::admin.orders._status', ['status' => $order->status])</td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center text-muted py-5">{{ translate('No orders here.') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-end">{!! $orders->links() !!}</div>
                </div>
            </div>
        </div>
    </div>
@endsection
