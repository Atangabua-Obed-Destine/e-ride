@extends('adminmodule::layouts.master')

@section('title', $store->name . ' · ' . translate('Payouts'))

@section('content')
    <div class="main-content">
        <div class="container-fluid" style="max-width: 980px">
            <div class="d-flex align-items-center gap-2 mb-3">
                <a href="{{ route('admin.store.index') }}" class="btn btn-light btn-sm"><i class="bi bi-arrow-left"></i></a>
                <h2 class="fs-22 mb-0">{{ $store->name }}</h2>
            </div>
            @include('storemanagement::admin.stores._tabs', ['active' => 'payouts'])

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <div class="card h-100"><div class="card-body">
                        <div class="fs-12 text-muted">{{ translate('Owed_to_partner') }}</div>
                        <div class="fs-24 fw-bold {{ $balance > 0 ? 'text-primary' : '' }}">{{ getCurrencyFormat($balance) }}</div>
                        <div class="fs-12 text-muted">{{ translate('Food_value_of_delivered_orders_minus_payouts') }}</div>
                    </div></div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100"><div class="card-body">
                        <div class="fs-12 text-muted">{{ translate('Delivered_orders') }}</div>
                        <div class="fs-24 fw-bold">{{ $deliveredCount }}</div>
                    </div></div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100"><div class="card-body">
                        <div class="fs-12 text-muted">{{ translate('Pay_to') }}</div>
                        @if($store->payout_number)
                            <div class="fw-semibold">{{ ['mtn_momo' => 'MTN MoMo', 'orange_money' => 'Orange Money', 'bank' => translate('Bank'), 'cash' => translate('Cash')][$store->payout_method] ?? $store->payout_method }}</div>
                            <div>{{ $store->payout_number }}</div>
                            <div class="fs-12 text-muted">{{ $store->payout_name }}</div>
                        @else
                            <div class="text-muted fs-12">{{ translate('No_payout_details_yet') }}</div>
                        @endif
                    </div></div>
                </div>
            </div>

            @can('store_edit')
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="mb-1">{{ translate('Record_a_payout') }}</h5>
                        <p class="fs-12 text-muted">{{ translate('Send the money first (MoMo, bank or cash), then record it here so both sides see the same balance.') }}</p>
                        <form action="{{ route('admin.store.payouts.record', $store) }}" method="POST" class="row g-2 align-items-end" onsubmit='return confirm(@json(translate("Record this payout? It reduces what E-Ride owes the partner.")))'>
                            @csrf
                            <div class="col-sm-3">
                                <label class="form-label">{{ translate('Amount') }} *</label>
                                <input type="number" name="amount" class="form-control" required min="1" step="1" value="{{ $balance > 0 ? round($balance) : '' }}">
                            </div>
                            <div class="col-sm-3">
                                <label class="form-label">{{ translate('Method') }}</label>
                                <input type="text" name="method" class="form-control" maxlength="30" value="{{ ['mtn_momo' => 'MTN MoMo', 'orange_money' => 'Orange Money'][$store->payout_method] ?? '' }}">
                            </div>
                            <div class="col-sm-3">
                                <label class="form-label">{{ translate('Reference') }}</label>
                                <input type="text" name="reference" class="form-control" maxlength="100" placeholder="{{ translate('Transaction_ID') }}">
                            </div>
                            <div class="col-sm-3">
                                <button type="submit" class="btn btn-primary w-100">{{ translate('Record') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endcan

            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">{{ translate('Payout_history') }}</h5>
                    <div class="table-responsive">
                        <table class="table table-borderless align-middle">
                            <thead class="table-light"><tr>
                                <th>{{ translate('Date') }}</th><th>{{ translate('Amount') }}</th><th>{{ translate('Method') }}</th><th>{{ translate('Reference') }}</th><th>{{ translate('By') }}</th>
                            </tr></thead>
                            <tbody>
                            @forelse($payouts as $payout)
                                <tr>
                                    <td>{{ $payout->created_at->format('d M Y, H:i') }}</td>
                                    <td class="fw-semibold">{{ getCurrencyFormat($payout->amount) }}</td>
                                    <td>{{ $payout->method }}</td>
                                    <td>{{ $payout->reference }}</td>
                                    <td class="fs-12">{{ $payout->author?->first_name }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">{{ translate('No payouts yet.') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    {!! $payouts->links() !!}
                </div>
            </div>
        </div>
    </div>
@endsection
