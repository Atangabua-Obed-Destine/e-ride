{{-- Trip details: the booking deposit and any driver report. --}}
@if ($deposit || $report)
    <div class="card mb-3">
        <div class="card-body">
            @if ($deposit)
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">{{ translate('Booking_Deposit') }}</h6>
                    @include('tripmanagement::admin.booking-deposit._status', ['status' => $deposit->status])
                </div>
                <div class="d-flex flex-column gap-1 fs-12">
                    <div class="d-flex justify-content-between"><span>{{ translate('Amount') }}</span><span class="fw-semibold">{{ getCurrencyFormat($deposit->amount) }}</span></div>
                    @if ($deposit->payment_method)
                        <div class="d-flex justify-content-between"><span>{{ translate('Paid_with') }}</span><span class="text-capitalize">{{ translate($deposit->payment_method) }}</span></div>
                    @endif
                    <div class="d-flex justify-content-between"><span>{{ translate('Asked_at') }}</span><span>{{ $deposit->created_at->format('d M Y, H:i') }}</span></div>
                    @if ($deposit->paid_at)
                        <div class="d-flex justify-content-between"><span>{{ translate('Paid_at') }}</span><span>{{ $deposit->paid_at->format('d M Y, H:i') }}</span></div>
                    @elseif ($deposit->status === 'awaiting' && $deposit->due_at)
                        <div class="d-flex justify-content-between"><span>{{ translate('Pay_before') }}</span><span>{{ $deposit->due_at->format('H:i:s') }}</span></div>
                    @endif
                    @if ($deposit->applied_amount > 0)
                        <div class="d-flex justify-content-between"><span>{{ translate('Counted_toward_the_fare') }}</span><span>{{ getCurrencyFormat($deposit->applied_amount) }}</span></div>
                    @endif
                    @if ($deposit->status === 'forfeited')
                        <div class="d-flex justify-content-between"><span>{{ translate('To_the_driver') }}</span><span>{{ getCurrencyFormat($deposit->driver_share) }}</span></div>
                        <div class="d-flex justify-content-between"><span>{{ translate('To_E-Ride') }}</span><span>{{ getCurrencyFormat($deposit->admin_share) }}</span></div>
                    @endif
                    @if ($deposit->refunded_amount > 0)
                        <div class="d-flex justify-content-between"><span>{{ translate('Refunded_to_wallet') }}</span><span>{{ getCurrencyFormat($deposit->refunded_amount) }}</span></div>
                    @endif
                    @if ($deposit->note)
                        <div class="text-muted mt-1">{{ translate($deposit->note) }}</div>
                    @endif
                </div>
            @endif
            @if ($report)
                <div class="{{ $deposit ? 'border-top pt-3 mt-3' : '' }}">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0 text-danger">{{ translate('Driver_Report') }}</h6>
                        <span class="badge badge-{{ $report->status === 'open' ? 'danger' : ($report->status === 'reviewed' ? 'warning' : 'success') }} text-capitalize">{{ translate($report->status) }}</span>
                    </div>
                    <div class="fs-12 fw-semibold">{{ $report->reasonLabel() }}</div>
                    @if ($report->comment)
                        <div class="fs-12 text-muted mt-1">"{{ $report->comment }}"</div>
                    @endif
                    <a class="fs-12 d-inline-block mt-2" href="{{ route('admin.driver-report.index') }}">{{ translate('Open_driver_reports') }}</a>
                </div>
            @endif
        </div>
    </div>
@endif
