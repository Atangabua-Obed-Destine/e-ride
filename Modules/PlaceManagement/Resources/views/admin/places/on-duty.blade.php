@extends('adminmodule::layouts.master')

@section('title', translate('On-duty_pharmacies'))

@section('content')
    <div class="main-content">
        <div class="container-fluid" style="max-width: 900px">
            <div class="mb-3">
                <h2 class="fs-22 mb-1">{{ translate('Explore_Bamenda') }}</h2>
                <p class="fs-12 text-muted mb-0">{{ translate('Each week, tick the pharmacies on the official on-duty (garde) rota and set the dates. Customers see them first under Pharmacies, marked On duty. When the end date passes they disappear on their own, so an old list is never shown.') }}</p>
            </div>

            @include('placemanagement::admin.places._nav', ['active' => 'on-duty'])

            @if ($errors->any())
                <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif

            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="mb-1">{{ translate('On_duty_now') }}</h5>
                    @forelse($current as $p)
                        <div class="d-flex justify-content-between border-bottom py-2">
                            <span class="fw-semibold">{{ $p->name }}</span>
                            <span class="fs-12 text-muted">{{ $p->on_duty_from?->format('d M') }} - {{ $p->on_duty_until?->format('d M Y') }}</span>
                        </div>
                    @empty
                        <p class="text-muted mb-0">{{ translate('None. The app does not show an on-duty section.') }}</p>
                    @endforelse
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">{{ translate('Set_this_week') }}</h5>
                    @if($pharmacies->isEmpty())
                        <div class="alert alert-info mb-0">
                            {{ translate('Add the pharmacies as verified listings first (category Pharmacies). Tip: open Google results, choose Pharmacies, and tap Make a verified listing.') }}
                        </div>
                    @else
                        <form action="{{ route('admin.place.on-duty.save') }}" method="POST">
                            @csrf
                            <div class="row g-3 mb-3">
                                <div class="col-sm-6">
                                    <label class="form-label">{{ translate('From') }}</label>
                                    <input type="date" name="from" class="form-control" required value="{{ old('from', $from) }}">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label">{{ translate('Until_(included)') }}</label>
                                    <input type="date" name="until" class="form-control" required value="{{ old('until', $until) }}">
                                </div>
                            </div>
                            <div class="row g-2 mb-3">
                                @foreach($pharmacies as $p)
                                    <div class="col-sm-6">
                                        <label class="form-control d-flex align-items-center gap-2 mb-0">
                                            <input type="checkbox" name="place_ids[]" value="{{ $p->id }}" {{ $p->isOnDuty() ? 'checked' : '' }}>
                                            <span>{{ $p->name }}</span>
                                            <span class="ms-auto fs-12 text-muted text-truncate" style="max-width: 45%">{{ $p->address }}</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            @can('place_edit')
                                <div class="d-flex justify-content-end">
                                    <button class="btn btn-primary">{{ translate('Save_on-duty_list') }}</button>
                                </div>
                            @endcan
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
