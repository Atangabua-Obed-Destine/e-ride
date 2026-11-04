@extends('adminmodule::layouts.master')

@section('title', translate('Driver_Reports'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h2 class="fs-22 mb-0">{{ translate('Driver_Reports') }}</h2>
                <span class="fs-12 text-muted">{{ translate('Reports customers sent about a driver, from the trip details in the app.') }}</span>
            </div>
            <ul class="nav nav--tabs p-1 rounded bg-white mb-3">
                @foreach ([null => 'All', 'open' => 'Open', 'reviewed' => 'Reviewed', 'resolved' => 'Resolved'] as $key => $label)
                    <li class="nav-item">
                        <a class="nav-link {{ $status === ($key ?: null) ? 'active' : '' }}" href="{{ route('admin.driver-report.index', $key ? ['status' => $key] : []) }}">
                            {{ translate($label) }} @if ($key === 'open' && $openCount) <span class="badge badge-danger">{{ $openCount }}</span> @endif
                        </a>
                    </li>
                @endforeach
            </ul>

            @forelse ($reports as $report)
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-lg-7">
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                    <span class="badge badge-{{ $report->status === 'open' ? 'danger' : ($report->status === 'reviewed' ? 'warning' : 'success') }} text-capitalize">{{ translate($report->status) }}</span>
                                    <h6 class="mb-0">{{ $report->reasonLabel() }}</h6>
                                    <span class="fs-12 text-muted">{{ $report->created_at->format('d M Y, H:i') }}</span>
                                </div>
                                @if ($report->comment)
                                    <p class="fs-14 mb-2">"{{ $report->comment }}"</p>
                                @endif
                                <div class="fs-12 d-flex flex-wrap gap-3">
                                    <span>{{ translate('Trip') }}:
                                        @if ($report->trip)
                                            <a href="{{ route('admin.trip.show', ['id' => $report->trip_request_id, 'page' => 'summary']) }}">#{{ $report->trip->ref_id }}</a>
                                            ({{ translate($report->trip_status) }}{{ $report->deposit_status ? ', ' . translate('deposit') . ' ' . translate($report->deposit_status) : '' }})
                                        @endif
                                    </span>
                                    <span>{{ translate('Customer') }}: {{ trim($report->customer?->first_name . ' ' . $report->customer?->last_name) }} {{ $report->customer?->phone }}</span>
                                </div>
                                @if ($report->trip?->trip_cancellation_reason)
                                    <div class="fs-12 text-muted mt-1">{{ translate('Cancellation_reason') }}: {{ $report->trip->trip_cancellation_reason }}</div>
                                @endif
                            </div>
                            <div class="col-lg-5">
                                <div class="bg-light rounded p-2 mb-2 fs-12">
                                    <div class="fw-semibold">{{ translate('Driver') }}:
                                        <a href="{{ route('admin.driver.show', ['id' => $report->driver_id]) }}">{{ trim($report->driver?->first_name . ' ' . $report->driver?->last_name) }}</a>
                                        {{ $report->driver?->phone }}
                                    </div>
                                    <div>{{ translate('Reports_received') }}: <b>{{ $reportCounts[$report->driver_id] ?? 1 }}</b>
                                        · {{ translate('Cancelled_after_a_paid_deposit') }}: <b>{{ $cancelCounts[$report->driver_id] ?? 0 }}</b></div>
                                </div>
                                <form action="{{ route('admin.driver-report.update', $report->id) }}" method="POST" class="d-flex flex-column gap-2">
                                    @csrf @method('PUT')
                                    <textarea name="admin_note" rows="2" maxlength="1000" class="form-control fs-12" placeholder="{{ translate('Note_for_the_team_(what_you_did)') }}">{{ $report->admin_note }}</textarea>
                                    <div class="d-flex gap-2">
                                        <select name="status" class="form-select form-select-sm w-auto">
                                            @foreach (['open', 'reviewed', 'resolved'] as $s)
                                                <option value="{{ $s }}" {{ $report->status === $s ? 'selected' : '' }}>{{ translate(ucfirst($s)) }}</option>
                                            @endforeach
                                        </select>
                                        <button class="btn btn-sm btn-primary">{{ translate('Save') }}</button>
                                    </div>
                                    @if ($report->handler)
                                        <div class="fs-12 text-muted">{{ translate('Updated_by') }} {{ $report->handler->first_name }}, {{ $report->handled_at?->format('d M Y, H:i') }}</div>
                                    @endif
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="card"><div class="card-body text-center text-muted py-5">{{ translate('No_reports') }}</div></div>
            @endforelse
            {{ $reports->links() }}
        </div>
    </div>
@endsection
