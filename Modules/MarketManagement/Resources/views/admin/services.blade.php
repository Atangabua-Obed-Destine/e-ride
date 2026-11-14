@extends('adminmodule::layouts.master')

@section('title', translate('E-Ride Market services'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-22 mb-3">{{ translate('E-Ride Market') }}</h2>
            @include('marketmanagement::admin._nav', ['active' => 'services'])
            @if ($errors->any())
                <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif

            <div class="row g-3 mb-3">
                @foreach([
                    [$stats['services'], 'Services offered'],
                    [$stats['today'], 'Bookings today'],
                    [$stats['waiting'], 'Waiting for an answer'],
                    [$stats['done'], 'Done with a code (30 days)'],
                    [$stats['quotes'], 'Quotes sent (30 days)'],
                    [$stats['missed'], 'No-shows and late cancels (30 days)'],
                ] as [$value, $label])
                    <div class="col-6 col-md-4 col-xl-2"><div class="card h-100"><div class="card-body">
                        <div class="fs-22 fw-bold">{{ $value }}</div><div class="fs-12 text-muted">{{ translate($label) }}</div>
                    </div></div></div>
                @endforeach
            </div>

            <div class="row g-3">
                <div class="col-xl-8">
                    <div class="card"><div class="card-body">
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            @foreach(['open' => 'Open', 'today' => 'Today', 'done' => 'Done', 'closed' => 'Closed'] as $value => $label)
                                <a href="{{ route('admin.market.services', ['tab' => $value]) }}" class="btn btn-sm {{ $tab === $value ? 'btn-dark' : 'btn-outline-secondary' }}">{{ translate($label) }}</a>
                            @endforeach
                        </div>
                        <div class="table-responsive">
                            <table class="table table-borderless align-middle fs-13">
                                <thead class="table-light"><tr>
                                    <th>{{ translate('Service') }}</th><th>{{ translate('Provider') }}</th><th>{{ translate('Client') }}</th>
                                    <th>{{ translate('When') }}</th><th>{{ translate('Price') }}</th><th>{{ translate('Status') }}</th>
                                </tr></thead>
                                <tbody>
                                @forelse($bookings as $booking)
                                    <tr class="border-top">
                                        <td><b>{{ $booking->item?->title }}</b><div class="fs-12 text-muted">{{ $booking->code }} · {{ $booking->kind === 'quote' ? translate('Quote') : translate('Booking') }}</div></td>
                                        <td>{{ $booking->shop?->name }}</td>
                                        <td>{{ $booking->client?->first_name }}<div class="fs-12 text-muted">{{ $booking->client?->phone }}</div></td>
                                        <td>{{ $booking->starts_at?->format('d M H:i') ?? '-' }}</td>
                                        <td>{{ $booking->shownPrice() !== null ? getCurrencyFormat($booking->shownPrice()) : '-' }}</td>
                                        <td><span class="badge bg-light text-dark">{{ translate(str_replace('_', ' ', $booking->status)) }}</span>
                                            @if($booking->late_cancel)<div class="fs-12 text-danger">{{ translate('Late cancel') }}</div>@endif</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted py-4">{{ translate('No bookings here') }}</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                        {{ $bookings->links() }}
                    </div></div>
                </div>

                <div class="col-xl-4">
                    <form action="{{ route('admin.market.service-rules') }}" method="POST" class="card">@csrf
                        <div class="card-body d-flex flex-column gap-3">
                            <h5 class="mb-0">{{ translate('Booking rules') }}</h5>
                            <div class="row g-2">
                                <div class="col-6"><label class="form-label" for="s-answer">{{ translate('Minutes for the provider to answer') }}</label>
                                    <input type="number" id="s-answer" name="service_answer_minutes" class="form-control" min="10" max="1440" required value="{{ $settings['service_answer_minutes'] }}"></div>
                                <div class="col-6"><label class="form-label" for="s-quote">{{ translate('Hours a price stays open') }}</label>
                                    <input type="number" id="s-quote" name="service_quote_hours" class="form-control" min="1" max="168" required value="{{ $settings['service_quote_hours'] }}"></div>
                                <div class="col-6"><label class="form-label" for="s-cancel">{{ translate('Free to change or cancel until (hours before)') }}</label>
                                    <input type="number" id="s-cancel" name="service_cancel_hours" class="form-control" min="0" max="72" required value="{{ $settings['service_cancel_hours'] }}"></div>
                                <div class="col-6"><label class="form-label" for="s-remind">{{ translate('Day-before reminder at (hour)') }}</label>
                                    <input type="number" id="s-remind" name="service_reminder_hour" class="form-control" min="0" max="23" required value="{{ $settings['service_reminder_hour'] }}"></div>
                                <div class="col-6"><label class="form-label" for="s-limit">{{ translate('No-shows and late cancels before booking pauses') }}</label>
                                    <input type="number" id="s-limit" name="service_noshow_limit" class="form-control" min="1" max="20" required value="{{ $settings['service_noshow_limit'] }}"></div>
                                <div class="col-6"><label class="form-label" for="s-days">{{ translate('Counted over (days)') }}</label>
                                    <input type="number" id="s-days" name="service_noshow_days" class="form-control" min="7" max="365" required value="{{ $settings['service_noshow_days'] }}"></div>
                            </div>
                            <div class="fs-12 text-muted">{{ translate('A service is booked for a time, or quoted. The client pays the provider after the job and gives a 4-digit code: E-Ride never holds the money. Reminders go the day before and 1 hour before.') }}</div>
                            <button class="btn btn-primary">{{ translate('Save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
