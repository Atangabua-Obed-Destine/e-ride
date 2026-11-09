@extends('adminmodule::layouts.master')

@section('title', translate('Payouts'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            @include('placemanagement::admin.listings._nav', ['active' => 'payouts'])

            <ul class="nav nav--tabs p-1 rounded bg-white mb-3" role="tablist">
                <li class="nav-item"><a class="nav-link {{ $status === 'pending' ? 'active' : '' }}" href="{{ route('admin.listing.payouts') }}">{{ translate('To_send') }}</a></li>
                <li class="nav-item"><a class="nav-link {{ $status === 'paid' ? 'active' : '' }}" href="{{ route('admin.listing.payouts', ['status' => 'paid']) }}">{{ translate('Sent') }}</a></li>
            </ul>

            <div class="card">
                <div class="card-body">
                    <p class="fs-12 text-muted">{{ translate('Send each amount by mobile money to the number shown, then type the transaction reference and mark it paid. The customer gets a notification with the reference. Oldest first: aim to send the same working day.') }}</p>
                    <div class="table-responsive">
                        <table class="table table-borderless align-middle">
                            <thead class="table-light"><tr>
                                <th>{{ translate('For') }}</th><th>{{ translate('Send_to') }}</th><th>{{ translate('Amount') }}</th><th>{{ translate('Why') }}</th>
                                <th>{{ $status === 'pending' ? translate('Waiting_since') : translate('Sent') }}</th><th class="text-center">{{ translate('Action') }}</th>
                            </tr></thead>
                            <tbody>
                            @forelse($payouts as $p)
                                <tr>
                                    <td class="fs-12">{{ $p->user?->first_name }} {{ $p->user?->last_name }}<br>{{ $p->user?->phone }}</td>
                                    <td><b>{{ $p->phone }}</b><div class="fs-12 text-muted">{{ $p->name }}</div></td>
                                    <td class="fw-bold">{{ getCurrencyFormat($p->amount) }}</td>
                                    <td class="fs-12">{{ translate('payout_' . $p->kind) }}
                                        @if($p->reservation)<br><a href="{{ route('admin.listing.reservation', $p->reservation) }}">{{ $p->reservation->listing?->title }}</a>@endif</td>
                                    <td class="fs-12">@if($status === 'pending'){{ $p->created_at->format('d M H:i') }} <span class="text-muted">({{ $p->created_at->diffForHumans(null, true) }})</span>
                                        @else{{ $p->paid_at?->format('d M H:i') }} · {{ $p->reference }}<br><span class="text-muted">{{ $p->paidBy?->first_name }}</span>@endif</td>
                                    <td class="text-center">
                                        @if($status === 'pending')
                                            @can('place_edit')
                                                <form action="{{ route('admin.listing.payout.paid', $p) }}" method="POST" class="d-flex gap-1 justify-content-center">@csrf
                                                    <input type="text" name="reference" class="form-control form-control-sm" style="width: 150px" maxlength="60" required placeholder="{{ translate('MoMo_reference') }}">
                                                    <button class="btn btn-sm btn-success" onclick="return confirm('{{ translate('Only_after_the_money_was_sent._Mark_paid') }}')">{{ translate('Mark_paid') }}</button>
                                                </form>
                                            @endcan
                                        @else
                                            <span class="badge bg-success">{{ translate('Paid') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-4">{{ $status === 'pending' ? translate('Nothing to send.') : translate('No payouts yet.') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-end">{!! $payouts->links() !!}</div>
                </div>
            </div>
        </div>
    </div>
@endsection
