@extends('adminmodule::layouts.master')

@section('title', translate('Reservations'))

@section('content')
    @php($badge = ['awaiting' => 'bg-light text-dark', 'held' => 'bg-warning text-dark', 'reserved' => 'bg-info text-dark', 'problem' => 'bg-danger',
        'released' => 'bg-success', 'refunded' => 'bg-secondary', 'lapsed' => 'bg-light text-muted'])
    <div class="main-content">
        <div class="container-fluid">
            @include('placemanagement::admin.listings._nav', ['active' => 'reservations'])

            <div class="row g-3 mb-3">
                @foreach ([
                    ['held', translate('Held by E-Ride now'), translate('Paid by seekers, not yet released or refunded')],
                    ['to_pay', translate('Payouts to send'), translate('Owners and mobile money refunds')],
                    ['released', translate('Released to owners this month'), translate('After the E-Ride fee')],
                    ['fees', translate('E-Ride fees this month'), translate('Income')],
                ] as [$key, $label, $hint])
                    <div class="col-sm-6 col-lg-3">
                        <div class="card h-100"><div class="card-body">
                            <div class="fs-12 text-muted">{{ $label }}</div>
                            <div class="fs-20 fw-bold">{{ getCurrencyFormat($totals[$key]) }}</div>
                            <div class="fs-12 text-muted">{{ $hint }}</div>
                        </div></div>
                    </div>
                @endforeach
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <ul class="nav nav--tabs p-1 rounded bg-white mb-0" role="tablist">
                    @foreach (['problems' => translate('Problems'), 'waiting' => translate('Waiting_for_payment_or_owner'), 'reserved' => translate('Reserved'), 'closed' => translate('Closed'), 'all' => translate('All')] as $key => $label)
                        <li class="nav-item"><a class="nav-link {{ $tab === $key ? 'active' : '' }}" href="{{ route('admin.listing.reservations', ['tab' => $key]) }}">{{ $label }}</a></li>
                    @endforeach
                </ul>
                <form class="d-flex gap-2" method="GET">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <input type="search" name="search" value="{{ $search }}" class="form-control" placeholder="{{ translate('Place, name or phone') }}">
                    <button class="btn btn-primary">{{ translate('Search') }}</button>
                </form>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-borderless align-middle">
                            <thead class="table-light"><tr>
                                <th>{{ translate('Place') }}</th><th>{{ translate('Seeker') }}</th><th>{{ translate('Owner') }}</th>
                                <th>{{ translate('Amount') }}</th><th>{{ translate('Move_in') }}</th><th>{{ translate('Status') }}</th><th class="text-center">{{ translate('Action') }}</th>
                            </tr></thead>
                            <tbody>
                            @forelse($reservations as $r)
                                <tr>
                                    <td>@if($r->listing)<a href="{{ route('admin.listing.show', $r->listing) }}" class="fw-semibold">{{ $r->listing->title }}</a>
                                        <div class="fs-12 text-muted">{{ $r->listing->neighbourhood }}</div>@endif</td>
                                    <td class="fs-12">{{ $r->seeker?->first_name }} {{ $r->seeker?->last_name }}<br>{{ $r->seeker?->phone }}</td>
                                    <td class="fs-12">{{ $r->owner?->first_name }} {{ $r->owner?->last_name }}<br>{{ $r->owner?->phone }}</td>
                                    <td>{{ getCurrencyFormat($r->amount) }}<div class="fs-12 text-muted">{{ $r->payment_method ? translate($r->payment_method) : '' }}</div></td>
                                    <td class="fs-12">{{ $r->move_in_date?->format('d M Y') }}</td>
                                    <td><span class="badge {{ $badge[$r->status] ?? 'bg-light text-dark' }}">{{ translate('reservation_' . $r->status) }}</span>
                                        @if($r->status === 'problem')<div class="fs-12 text-danger">{{ translate('problem_' . $r->problem_reason) }} · {{ translate($r->problem_by) }}</div>@endif
                                        @if($r->status === 'held' && $r->accept_due_at)<div class="fs-12 text-muted">{{ translate('owner_answers_by') }} {{ $r->accept_due_at->format('d M H:i') }}</div>@endif</td>
                                    <td class="text-center"><a href="{{ route('admin.listing.reservation', $r) }}" class="btn btn-sm {{ $r->status === 'problem' ? 'btn-danger' : 'btn-outline-primary' }}">{{ $r->status === 'problem' ? translate('Decide') : translate('Open') }}</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted py-4">{{ translate('No reservations here.') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-end">{!! $reservations->links() !!}</div>
                </div>
            </div>
        </div>
    </div>
@endsection
