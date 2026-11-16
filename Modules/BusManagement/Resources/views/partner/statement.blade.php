@extends('busmanagement::partner.layout')

@section('title', translate('Statement'))

@section('content')
<a href="{{ route('bus-partner.payments') }}" class="small">&larr; {{ translate('Payments') }}</a>
<div class="d-flex flex-wrap align-items-center gap-2 my-2">
    <h1 class="fs-5 fw-bold mb-0 flex-grow-1">{{ translate(key: 'E-Ride statement, {month}', replace: ['month' => $statement->month->translatedFormat('F Y')]) }}</h1>
    <a class="btn btn-soft btn-sm" href="{{ route('bus-partner.payments.statement.csv', $statement->id) }}"><i class="bi bi-download"></i> CSV</a>
    @if($statement->status === 'due')
        <form method="POST" action="{{ route('bus-partner.payments.statement.pay', $statement->id) }}" class="m-0">@csrf<button class="btn btn-primary btn-sm">{{ translate('Pay with Mobile Money') }}</button></form>
    @endif
</div>
<div class="b-card p-3 mb-3 d-flex flex-wrap gap-4">
    <div><div class="b-hint">{{ translate('App tickets') }}</div><b>{{ $statement->app_tickets }}</b> · {{ getCurrencyFormat($statement->app_fares) }} → <b>{{ getCurrencyFormat($statement->app_commission) }}</b></div>
    <div><div class="b-hint">{{ translate('Counter tickets') }}</div><b>{{ $statement->counter_tickets }}</b> · {{ getCurrencyFormat($statement->counter_fares) }} → <b>{{ getCurrencyFormat($statement->counter_commission) }}</b></div>
    <div><div class="b-hint">{{ translate('To pay E-Ride') }}</div><b class="fs-5">{{ getCurrencyFormat($statement->total) }}</b></div>
    <div><div class="b-hint">{{ $statement->status === 'paid' ? translate('Paid') : translate('Due') }}</div><b>{{ $statement->status === 'paid' ? $statement->paid_at?->format('d/m/Y') . ($statement->reference ? ' · ' . $statement->reference : '') : $statement->due_on->translatedFormat('j F Y') }}</b></div>
</div>
<div class="b-card p-0" style="overflow-x:auto">
    <table class="table table-sm mb-0 align-middle" style="min-width:720px">
        <thead><tr><th class="ps-3">{{ translate('Departure') }}</th><th>{{ translate('Booking') }}</th><th>{{ translate('Sold') }}</th><th>{{ translate('Seats') }}</th><th class="text-end">{{ translate('Kept') }}</th><th class="text-end">%</th><th class="text-end pe-3">{{ translate('Commission') }}</th></tr></thead>
        <tbody>
        @forelse($lines as $b)
            <tr>
                <td class="ps-3">{{ $b->departure?->leavesAt()->format('d/m H:i') }} · {{ $b->departure?->destination?->city }}</td>
                <td><code>{{ $b->code }}</code>@if($b->status === 'cancelled') <span class="b-tag mute">{{ translate('Cancelled, part refunded') }}</span>@endif</td>
                <td>{{ $b->source === 'app' ? translate('App') : translate('Counter') }}</td>
                <td>{{ $b->tickets->whereIn('status', ['valid', 'boarded', 'no_show'])->sortBy(fn($t) => (int)$t->seat)->pluck('seat')->implode(', ') }}</td>
                <td class="text-end">{{ getCurrencyFormat($b->amount - $b->refund_amount) }}</td>
                <td class="text-end">{{ rtrim(rtrim(number_format($b->commission_pct, 2), '0'), '.') }}</td>
                <td class="text-end pe-3">{{ getCurrencyFormat($b->commission) }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="ps-3 b-hint">{{ translate('No ticket this month.') }}</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
