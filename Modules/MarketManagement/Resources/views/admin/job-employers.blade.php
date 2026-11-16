@extends('adminmodule::layouts.master')

@section('title', translate('E-Ride Jobs employers'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-22 mb-3">{{ translate('E-Ride Jobs') }}</h2>
            @include('marketmanagement::admin._jobs_nav', ['active' => 'employers'])
            @if ($errors->any())
                <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif

            <div class="d-flex flex-wrap gap-2 mb-3">
                @foreach(['pending' => 'Waiting', 'verified' => 'Verified', 'rejected' => 'Refused', 'frozen' => 'Closed'] as $value => $label)
                    <a href="{{ route('admin.jobs.employers', ['status' => $value]) }}" class="btn btn-sm {{ $status === $value ? 'btn-dark' : 'btn-outline-secondary' }}">{{ translate($label) }} ({{ $counts[$value] ?? 0 }})</a>
                @endforeach
            </div>

            <div class="row g-3">
                @forelse($employers as $e)
                    <div class="col-lg-6"><div class="card h-100"><div class="card-body">
                        <div class="d-flex justify-content-between gap-2">
                            <div>
                                <b class="fs-16">{{ $e->name }}</b>
                                <div class="fs-13 text-muted">{{ translate($e->kind === 'private' ? 'Private employer (home)' : 'Business') }} · {{ $e->area_label ?: '-' }} · {{ $e->jobs_count == 1 ? translate('1 job') : $e->jobs_count . ' ' . translate('jobs') }}@if($e->hires_count) · {{ $e->hires_count }} {{ translate('hired through E-Ride') }}@endif</div>
                                <div class="fs-13">{{ $e->user?->first_name }} {{ $e->user?->last_name }} · {{ $e->user?->phone }}</div>
                                @if($e->address)<div class="fs-12 text-muted">{{ $e->address }}@if($e->lat) · <a target="_blank" href="https://www.google.com/maps?q={{ $e->lat }},{{ $e->lng }}">{{ translate('map') }}</a>@endif</div>@endif
                            </div>
                            <div class="text-end fs-12 text-muted">{{ $e->updated_at?->diffForHumans() }}</div>
                        </div>
                        @if($e->about)<div class="fs-13 mt-2">{{ $e->about }}</div>@endif
                        <div class="mt-2 fs-13"><b>{{ translate('Document') }}</b>: {{ translate('job_doc_' . $e->doc_type) }}
                            @if($e->doc_file) · <a href="{{ route('admin.jobs.document', $e->id) }}" target="_blank">{{ translate('Open (staff only)') }}</a>@endif</div>
                        @if($e->review_note)<div class="alert alert-secondary fs-13 mt-2 mb-0">{{ $e->review_note }}</div>@endif

                        <form method="post" action="{{ route('admin.jobs.decide-employer', $e->id) }}" class="mt-3">@csrf
                            @if($e->status !== 'verified' && $e->status !== 'frozen')
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="doc_seen" value="1" id="doc{{ $e->id }}">
                                    <label class="form-check-label fs-13" for="doc{{ $e->id }}">{{ translate('Document seen, the name matches') }}</label></div>
                                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="called" value="1" id="call{{ $e->id }}">
                                    <label class="form-check-label fs-13" for="call{{ $e->id }}">{{ translate('Called them, the workplace is real') }}</label></div>
                                <button class="btn btn-success btn-sm" name="decision" value="verify">{{ translate('Verify') }}</button>
                            @endif
                            @if($e->status !== 'frozen')
                                <input name="note" class="form-control form-control-sm my-2" maxlength="300" placeholder="{{ translate('Reason they read (to refuse or close)') }}">
                                @if($e->status !== 'rejected')<button class="btn btn-outline-dark btn-sm" name="decision" value="refuse">{{ translate('Refuse, they can send again') }}</button>@endif
                                <button class="btn btn-outline-danger btn-sm" name="decision" value="freeze">{{ translate('Close: scam') }}</button>
                            @endif
                        </form>
                    </div></div></div>
                @empty
                    <div class="col-12"><div class="card"><div class="card-body text-center text-muted py-4">{{ translate('Nobody here') }}</div></div></div>
                @endforelse
            </div>
            <div class="mt-3">{{ $employers->links() }}</div>
        </div>
    </div>
@endsection
