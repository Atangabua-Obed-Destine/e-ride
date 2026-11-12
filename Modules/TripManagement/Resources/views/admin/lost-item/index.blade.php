@extends('adminmodule::layouts.master')

@section('title', translate('Lost and found'))

@section('content')
    @php($statusBadge = ['reported' => 'warning', 'found' => 'info', 'not_found' => 'secondary', 'returned' => 'success', 'cancelled' => 'light', 'closed' => 'dark'])
    @php($statusLabel = ['reported' => 'Waiting for the driver', 'found' => 'Found', 'not_found' => 'Not in the car', 'returned' => 'Returned', 'cancelled' => 'Closed by the rider', 'closed' => 'Closed by E-Ride'])
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                <div>
                    <h2 class="fs-22 mb-1">{{ translate('Lost and found') }}</h2>
                    <p class="fs-12 text-muted mb-0">{{ translate('Items riders left in a car, reported from the trip in the app. The driver answers Found it or Not in my car; once found, the rider and the driver can call each other to get it back. Cases come here first when they need support.') }}</p>
                </div>
                @if(!$settings['enabled'])
                    <span class="badge bg-warning text-dark fs-12">{{ translate('Off: turn it on in Settings') }}</span>
                @endif
            </div>

            <div class="row g-3">
                <div class="col-xl-8">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <ul class="nav nav--tabs p-1 rounded bg-white">
                            @foreach(\Modules\TripManagement\Http\Controllers\Web\LostItemAdminController::TABS as $key => $label)
                                <li class="nav-item"><a class="nav-link {{ $tab === $key ? 'active' : '' }}" href="{{ route('admin.lost-item.index', ['tab' => $key]) }}">
                                    {{ translate($label) }}
                                    @if(isset($counts[$key]) && $counts[$key] > 0)<span class="badge badge-{{ $key === 'support' ? 'danger' : 'primary' }}">{{ $counts[$key] }}</span>@endif
                                </a></li>
                            @endforeach
                        </ul>
                        <form class="d-flex gap-2" method="GET">
                            <input type="hidden" name="tab" value="{{ $tab }}">
                            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ translate('Item or trip number') }}">
                            <button class="btn btn-sm btn-primary">{{ translate('Search') }}</button>
                        </form>
                    </div>

                    @forelse($items as $item)
                        <div class="card mb-3 {{ $item->needsSupport() ? 'border-danger' : '' }}">
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-lg-7">
                                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                            <span class="badge badge-{{ $statusBadge[$item->status] ?? 'secondary' }}">{{ translate($statusLabel[$item->status] ?? $item->status) }}</span>
                                            @if($item->needsSupport())<span class="badge badge-danger">{{ translate('Needs support') }}</span>@endif
                                            <span class="fs-12 text-muted">{{ $item->created_at->format('d M Y, H:i') }}</span>
                                        </div>
                                        <div class="d-flex gap-3">
                                            @if($item->photoUrl())
                                                <a href="{{ $item->photoUrl() }}" target="_blank"><img src="{{ $item->photoUrl() }}" alt="" class="rounded" style="width: 84px; height: 84px; object-fit: cover"></a>
                                            @endif
                                            <div>
                                                <p class="fs-14 mb-1">"{{ $item->description }}"</p>
                                                @if($item->driver_note)<p class="fs-12 mb-1"><b>{{ translate('Driver') }}:</b> {{ $item->driver_note }}</p>@endif
                                                @if($item->return_method)
                                                    <p class="fs-12 mb-1"><b>{{ translate('Return') }}:</b>
                                                        {{ $item->return_method === 'deliver' ? translate('The driver brings it') . ' (' . set_currency_symbol($item->return_fee) . ', ' . translate('cash') . ')' : translate('The rider collects it') }}</p>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="fs-12 d-flex flex-wrap gap-3 mt-2">
                                            <span>{{ translate('Trip') }}: <a href="{{ route('admin.trip.show', ['id' => $item->trip_request_id, 'page' => 'summary']) }}">#{{ $item->trip?->ref_id }}</a>
                                                <span class="text-muted">{{ $item->trip?->created_at?->format('d M') }}, {{ \Illuminate\Support\Str::limit($item->trip?->coordinate?->destination_address, 40) }}</span></span>
                                        </div>
                                        <div class="fs-12 d-flex flex-wrap gap-3 mt-1">
                                            <span>{{ translate('Rider') }}: {{ trim($item->customer?->first_name . ' ' . $item->customer?->last_name) }} <a href="tel:{{ $item->customer?->phone }}">{{ $item->customer?->phone }}</a></span>
                                            <span>{{ translate('Driver') }}: <a href="{{ route('admin.driver.show', ['id' => $item->driver_id]) }}">{{ trim($item->driver?->first_name . ' ' . $item->driver?->last_name) }}</a> <a href="tel:{{ $item->driver?->phone }}">{{ $item->driver?->phone }}</a></span>
                                        </div>
                                        <div class="fs-11 text-muted mt-2">
                                            {{ translate('Reported') }} {{ $item->created_at->format('d M H:i') }}
                                            @if($item->reminded_at) · {{ translate('Driver reminded') }} {{ $item->reminded_at->format('d M H:i') }}@endif
                                            @if($item->answered_at) · {{ translate('Driver answered') }} {{ $item->answered_at->format('d M H:i') }}@endif
                                            @if($item->driver_returned_at) · {{ translate('Driver says returned') }} {{ $item->driver_returned_at->format('d M H:i') }}@endif
                                            @if($item->escalated_at) · {{ translate('To support') }} {{ $item->escalated_at->format('d M H:i') }}@endif
                                            @if($item->closed_at) · {{ translate('Closed') }} {{ $item->closed_at->format('d M H:i') }}@endif
                                        </div>
                                    </div>
                                    <div class="col-lg-5">
                                        @can('trip_edit')
                                            <form action="{{ route('admin.lost-item.act', $item->id) }}" method="POST" class="d-flex flex-column gap-2">@csrf
                                                <textarea name="admin_note" rows="2" maxlength="2000" class="form-control fs-12" placeholder="{{ translate('Note for the team (who you called, what was agreed)') }}">{{ $item->admin_note }}</textarea>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <button name="action" value="note" class="btn btn-sm btn-outline-secondary">{{ translate('Save note') }}</button>
                                                    @if($item->isOpen())
                                                        @if($item->status !== 'found')<button name="action" value="found" class="btn btn-sm btn-outline-primary">{{ translate('Mark found') }}</button>@endif
                                                        @if($item->status === 'reported')<button name="action" value="not_found" class="btn btn-sm btn-outline-primary">{{ translate('Mark not found') }}</button>@endif
                                                        <button name="action" value="returned" class="btn btn-sm btn-outline-success" onclick="return confirm('{{ translate('Mark this item as back with the rider') }}')">{{ translate('Mark returned') }}</button>
                                                        <button name="action" value="close" class="btn btn-sm btn-outline-danger" onclick="return confirm('{{ translate('Close this case. The rider and the driver are told.') }}')">{{ translate('Close case') }}</button>
                                                    @endif
                                                </div>
                                            </form>
                                        @endcan
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="card"><div class="card-body text-center text-muted py-5">{{ $tab === 'support' ? translate('No case needs support right now.') : translate('No lost items here.') }}</div></div>
                    @endforelse
                    {{ $items->links() }}
                </div>

                <div class="col-xl-4">
                    @can('trip_edit')
                        <div class="card mb-3"><div class="card-body">
                            <h5 class="mb-3">{{ translate('Settings') }}</h5>
                            <form action="{{ route('admin.lost-item.settings') }}" method="POST">@csrf
                                <div class="form-check form-switch mb-3">
                                    <input type="hidden" name="lost_items_enabled" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="lost-enabled" name="lost_items_enabled" value="1" {{ $settings['enabled'] ? 'checked' : '' }}>
                                    <label class="form-check-label" for="lost-enabled">{{ translate('Lost and found is on') }}</label>
                                </div>
                                @foreach([
                                    ['lost_items_report_days', 'Days after a ride a rider can report', $settings['report_days'], 1, 30, '1'],
                                    ['lost_items_answer_hours', 'Hours the driver has to answer, then support', $settings['answer_hours'], 1, 72, '1'],
                                    ['lost_items_return_fee', 'Fee when the driver brings it, paid in cash', $settings['return_fee'], 0, 100000, 'any'],
                                    ['lost_items_stale_days', 'Days found but not back, then support', $settings['stale_days'], 1, 14, '1'],
                                    ['lost_items_confirm_hours', 'Hours the rider has to confirm, then closed as returned', $settings['confirm_hours'], 1, 168, '1'],
                                ] as [$name, $label, $value, $min, $max, $step])
                                    <div class="mb-2"><label class="form-label fs-12 mb-1">{{ translate($label) }}</label>
                                        <input type="number" name="{{ $name }}" min="{{ $min }}" max="{{ $max }}" step="{{ $step }}" class="form-control form-control-sm" value="{{ $value }}" required></div>
                                @endforeach
                                <button class="btn btn-outline-primary w-100 mt-2">{{ translate('Save settings') }}</button>
                            </form>
                        </div></div>
                    @endcan
                    <div class="card"><div class="card-body fs-12">
                        <h6 class="mb-2">{{ translate('When a case needs support') }}</h6>
                        <ul class="ps-3 mb-0 text-muted">
                            <li>{{ translate('The driver did not answer in time (they are reminded halfway).') }}</li>
                            <li>{{ translate('The driver said not in the car and the rider asked for help.') }}</li>
                            <li>{{ translate('The item was found but is not back after some days.') }}</li>
                        </ul>
                    </div></div>
                </div>
            </div>
        </div>
    </div>
@endsection
