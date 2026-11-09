@extends('adminmodule::layouts.master')

@section('title', translate('Reservation'))

@section('content')
    @php($r = $reservation)
    @php($fmt = fn($d) => $d ? $d->format('d M Y, H:i') : null)
    <div class="main-content">
        <div class="container-fluid" style="max-width: 1100px">
            @include('placemanagement::admin.listings._nav', ['active' => 'reservations'])
            @if ($errors->any())
                <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif

            <div class="row g-3">
                <div class="col-lg-7">
                    <div class="card mb-3"><div class="card-body">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                <h4 class="mb-1">{{ getCurrencyFormat($r->amount) }} · {{ translate('reservation_' . $r->status) }}</h4>
                                @if($r->listing)<a href="{{ route('admin.listing.show', $r->listing) }}" class="fw-semibold">{{ $r->listing->title }}</a>
                                    <span class="fs-12 text-muted">· {{ $r->listing->neighbourhood }} · {{ translate('listing') }} {{ translate($r->listing->status) }}</span>@endif
                            </div>
                            <span class="fs-12 text-muted">{{ $r->payment_method ? translate('paid_by') . ' ' . translate($r->payment_method) : '' }}</span>
                        </div>
                        @if($r->status === 'problem')
                            <div class="alert alert-danger mt-3 mb-0">
                                <b>{{ translate('problem_' . $r->problem_reason) }}</b> · {{ translate('reported_by') }} {{ translate($r->problem_by) }}
                                @if($r->problem_note)<div class="mt-1">{{ $r->problem_note }}</div>@endif
                            </div>
                        @endif
                        @if($r->decision_note)
                            <div class="alert alert-light mt-3 mb-0"><b>{{ translate('Decision') }}:</b> {{ $r->decision_note }}</div>
                        @endif
                    </div></div>

                    <div class="card mb-3"><div class="card-body">
                        <h5 class="mb-3">{{ translate('Timeline') }}</h5>
                        <table class="table table-sm table-borderless mb-0 fs-14">
                            @foreach ([
                                [translate('Started'), $fmt($r->created_at)],
                                [translate('Paid'), $fmt($r->paid_at)],
                                [translate('Owner must answer by'), $r->status === 'held' ? $fmt($r->accept_due_at) : null],
                                [translate('Owner accepted'), $fmt($r->accepted_at)],
                                [translate('Move-in date'), $r->move_in_date?->format('d M Y')],
                                [translate('Automatic release'), $r->status === 'reserved' ? $fmt($r->release_due_at) : null],
                                [translate('Move-in push sent'), $fmt($r->move_in_pushed_at)],
                                [translate('Settled'), $r->settled_at ? $fmt($r->settled_at) . ' · ' . translate('closed_' . $r->closed_reason) : null],
                            ] as [$label, $value])
                                @if($value)<tr><th class="text-muted fw-normal" style="width: 45%">{{ $label }}</th><td>{{ $value }}</td></tr>@endif
                            @endforeach
                            @if($r->fee > 0 || $r->owner_amount > 0 || $r->refunded_amount > 0)
                                <tr><th class="text-muted fw-normal">{{ translate('Money') }}</th><td>
                                    @if($r->refunded_amount > 0){{ translate('Refunded to seeker') }} {{ getCurrencyFormat($r->refunded_amount) }}<br>@endif
                                    @if($r->owner_amount > 0){{ translate('To the owner') }} {{ getCurrencyFormat($r->owner_amount) }}<br>@endif
                                    @if($r->fee > 0){{ translate('E-Ride fee') }} {{ getCurrencyFormat($r->fee) }}@endif
                                </td></tr>
                            @endif
                        </table>
                    </div></div>

                    @if($r->payouts->isNotEmpty())
                        <div class="card mb-3"><div class="card-body">
                            <h5 class="mb-3">{{ translate('Payouts') }}</h5>
                            @foreach($r->payouts as $p)
                                <div class="d-flex justify-content-between border-bottom py-2 fs-14">
                                    <span>{{ translate('payout_' . $p->kind) }} · {{ $p->phone }} · {{ getCurrencyFormat($p->amount) }}</span>
                                    <span>@if($p->status === 'paid'){{ translate('Paid') }} {{ $p->paid_at?->format('d M') }} · {{ $p->reference }} · {{ $p->paidBy?->first_name }}
                                        @else<a href="{{ route('admin.listing.payouts') }}">{{ translate('Waiting to be sent') }}</a>@endif</span>
                                </div>
                            @endforeach
                        </div></div>
                    @endif
                </div>

                <div class="col-lg-5">
                    <div class="card mb-3"><div class="card-body fs-14">
                        <h5 class="mb-3">{{ translate('The_two_sides') }}</h5>
                        <div class="mb-2"><span class="text-muted">{{ translate('Seeker') }}:</span> {{ $r->seeker?->first_name }} {{ $r->seeker?->last_name }} · {{ $r->seeker?->phone }}</div>
                        <div class="mb-2"><span class="text-muted">{{ translate('Owner') }}:</span> {{ $r->owner?->first_name }} {{ $r->owner?->last_name }} · {{ $r->owner?->phone }}</div>
                        <div class="mb-2"><span class="text-muted">{{ translate('Owner_payout_number') }}:</span> {{ $r->listing?->payout_phone ?: '-' }} {{ $r->listing?->payout_name ? '(' . $r->listing->payout_name . ')' : '' }}</div>
                        @if($r->visit)<div><span class="text-muted">{{ translate('Visit') }}:</span> {{ $r->visit->visit_date?->format('d M') }}, {{ translate($r->visit->time_window) }} · {{ translate($r->visit->status) }}
                            @if($r->visit->outcome) · {{ translate($r->visit->outcome) }}@endif</div>@endif
                    </div></div>

                    @if($r->isHolding())
                        @can('place_edit')
                            <div class="card mb-3 {{ $r->status === 'problem' ? 'border-danger' : '' }}"><div class="card-body">
                                <h5 class="mb-1">{{ translate('Decide') }}</h5>
                                <p class="fs-12 text-muted">{{ translate('Refund: everything back to the seeker wallet. Release: the owner is paid, minus the E-Ride fee. Split: the seeker gets the amount you type, the owner the rest minus the fee. Both sides see your reason.') }}</p>
                                <form action="{{ route('admin.listing.reservation.decide', $r) }}" method="POST">@csrf
                                    <div class="mb-2">
                                        @foreach (['refund' => translate('Refund_the_seeker'), 'release' => translate('Release_to_the_owner'), 'split' => translate('Split')] as $value => $label)
                                            <div class="form-check"><input class="form-check-input" type="radio" name="decision" id="d-{{ $value }}" value="{{ $value }}" {{ old('decision') === $value ? 'checked' : '' }} required>
                                                <label class="form-check-label" for="d-{{ $value }}">{{ $label }}</label></div>
                                        @endforeach
                                    </div>
                                    <input type="number" step="any" name="seeker_amount" class="form-control mb-2" value="{{ old('seeker_amount') }}" placeholder="{{ translate('For a split: amount to the seeker (FCFA)') }}">
                                    <textarea name="note" class="form-control mb-2" rows="2" maxlength="500" required placeholder="{{ translate('Reason both sides will see') }}">{{ old('note') }}</textarea>
                                    <label class="fs-12 d-block mb-3"><input type="checkbox" name="strike" value="1"> {{ translate('Also_count_a_strike_for_the_owner') }}</label>
                                    <button class="btn btn-primary" onclick="return confirm('{{ translate('This_moves_money._Continue') }}')">{{ translate('Save_decision') }}</button>
                                </form>
                            </div></div>
                        @endcan
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
