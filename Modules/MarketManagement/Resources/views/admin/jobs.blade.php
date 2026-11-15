@extends('adminmodule::layouts.master')

@section('title', translate('E-Ride Jobs'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-22 mb-3">{{ translate('E-Ride Jobs') }}</h2>
            @include('marketmanagement::admin._jobs_nav', ['active' => 'jobs'])
            @if ($errors->any())
                <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            @if(!$settings['enabled'])
                <div class="alert alert-warning">{{ translate('E-Ride Jobs is off: people cannot see or post jobs. Turn it on in the settings below.') }}</div>
            @endif

            <div class="row g-3 mb-3">
                @foreach([
                    [$stats['review'], 'Jobs to check'],
                    [$stats['live'], 'Live jobs'],
                    [$stats['employers_waiting'], 'Employers waiting'],
                    [$stats['reports_open'], 'Open reports'],
                    [$stats['applications_30'], 'Applications (30 days)'],
                    [$stats['next_steps_30'], 'Next steps sent (30 days)'],
                ] as [$value, $label])
                    <div class="col-6 col-md-4 col-xl-2"><div class="card h-100"><div class="card-body">
                        <div class="fs-22 fw-bold">{{ $value }}</div><div class="fs-12 text-muted">{{ translate($label) }}</div>
                    </div></div></div>
                @endforeach
            </div>

            <div class="row g-3">
                <div class="col-xl-5">
                    <div class="card"><div class="card-body">
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            @foreach(['review' => 'To check', 'live' => 'Live', 'closed' => 'Closed and sent back'] as $value => $label)
                                <a href="{{ route('admin.jobs.index', ['tab' => $value]) }}" class="btn btn-sm {{ $tab === $value ? 'btn-dark' : 'btn-outline-secondary' }}">{{ translate($label) }}</a>
                            @endforeach
                        </div>
                        @if($tab === 'review')<div class="fs-12 text-muted mb-2">{{ translate('Oldest first. Target: within :h hours.', ['h' => $settings['review_hours']]) }}</div>@endif
                        @forelse($jobs as $job)
                            <a href="{{ route('admin.jobs.index', ['tab' => $tab, 'job' => $job->id]) }}" class="d-block text-reset text-decoration-none border rounded p-2 mb-2 {{ $open?->id === $job->id ? 'border-dark' : '' }} {{ collect($job->flags)->contains(fn($f) => str_starts_with($f, 'fee_words') || str_starts_with($f, 'banned')) ? 'border-danger' : '' }}">
                                <div class="d-flex justify-content-between gap-2">
                                    <b>{{ $job->title }}</b>
                                    <span class="fs-12 text-muted">{{ ($job->submitted_at ?? $job->created_at)?->diffForHumans(null, true) }}</span>
                                </div>
                                <div class="fs-12 text-muted">{{ $job->employer?->name }} · {{ $job->employer?->isVerified() ? translate('verified employer') : translate('employer not verified') }} · {{ $pay($job) }}</div>
                                <div class="d-flex flex-wrap gap-1 mt-1">
                                    @if($job->status === 'hidden')<span class="badge bg-danger">{{ translate('Reported, taken down') }}</span>@endif
                                    @forelse((array)$job->flags as $flag)
                                        <span class="badge {{ str_starts_with($flag, 'fee_words') || str_starts_with($flag, 'banned') ? 'bg-danger' : 'bg-warning text-dark' }}">{{ translate('flag_' . strtok($flag, ':')) }}@if(str_contains($flag, ':')) "{{ substr($flag, strpos($flag, ':') + 1) }}"@endif</span>
                                    @empty
                                        <span class="badge bg-success">{{ translate('No flags') }}</span>
                                    @endforelse
                                </div>
                            </a>
                        @empty
                            <div class="text-center text-muted py-4">{{ translate('Nothing here') }}</div>
                        @endforelse
                        {{ $jobs->links() }}
                    </div></div>
                </div>

                <div class="col-xl-7">
                    @if($open)
                        <div class="card mb-3"><div class="card-body">
                            <div class="d-flex justify-content-between flex-wrap gap-2">
                                <div>
                                    <h3 class="fs-18 mb-1">{{ $open->title }}</h3>
                                    <div class="text-muted fs-13">{{ $open->employer?->name }} ({{ translate($open->employer?->kind === 'private' ? 'Private employer' : 'Business') }}) · {{ $open->employer?->user?->phone }}</div>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-light text-dark">{{ translate(str_replace('_', ' ', $open->status)) }}</span>
                                    <div class="fs-12 text-muted mt-1"><a href="{{ route('jobs.page', ['code' => $open->code]) }}" target="_blank">/j/{{ $open->code }}</a></div>
                                </div>
                            </div>
                            <hr>
                            <div class="row g-2 fs-13">
                                <div class="col-sm-6"><b>{{ translate('Pay') }}</b><div>{{ $pay($open) }}</div></div>
                                <div class="col-sm-6"><b>{{ translate('Type') }}</b><div>{{ translate('job_type_' . $open->type) }} · {{ translate('job_cat_' . $open->category) }} · {{ $open->places }} {{ translate('places') }}</div></div>
                                <div class="col-sm-6"><b>{{ translate('Hours') }}</b><div>{{ $open->schedule ?: '-' }}</div></div>
                                <div class="col-sm-6"><b>{{ translate('Where') }}</b><div>{{ $open->area_label }}@if($open->address) · {{ $open->address }}@endif
                                    @if($open->lat) · <a target="_blank" href="https://www.google.com/maps?q={{ $open->lat }},{{ $open->lng }}">{{ translate('map') }}</a>@endif</div></div>
                            </div>
                            <div class="mt-3 fs-13" style="white-space:pre-line">{{ $open->description }}</div>
                            @if($open->requirements)<div class="mt-2 fs-13"><b>{{ translate('Requirements') }}</b><div style="white-space:pre-line">{{ $open->requirements }}</div></div>@endif
                            @if($open->skills)<div class="mt-2 d-flex flex-wrap gap-1">@foreach($open->skills as $s)<span class="badge bg-light text-dark">{{ $s }}</span>@endforeach</div>@endif
                            @if($open->questionList())
                                <div class="mt-2 fs-13"><b>{{ translate('Questions for applicants') }}</b><ul class="mb-0 ps-3">@foreach($open->questionList() as $q)<li>{{ $q }}</li>@endforeach</ul></div>
                            @endif
                            @if($open->review_note)<div class="alert alert-secondary mt-3 mb-0 fs-13">{{ translate('Last note') }}: {{ $open->review_note }}</div>@endif
                        </div></div>

                        @if($reports->isNotEmpty())
                            <div class="card mb-3"><div class="card-body">
                                <b>{{ translate('Reports') }}</b>
                                @foreach($reports as $r)
                                    <div class="fs-13 border-top pt-2 mt-2">{{ translate('job_report_' . $r->reason) }} · {{ $r->reporter?->first_name }} · {{ $r->created_at?->diffForHumans() }} · <span class="text-muted">{{ $r->status }}</span>
                                        @if($r->note)<div class="text-muted">"{{ $r->note }}"</div>@endif</div>
                                @endforeach
                            </div></div>
                        @endif

                        <div class="card"><div class="card-body">
                            <b>{{ translate('Checklist') }}</b>
                            <ul class="fs-13 mb-3 ps-3">
                                <li class="{{ $open->employer?->isVerified() ? 'text-success' : 'text-danger' }}">{{ translate('Employer verified') }}
                                    @if(!$open->employer?->isVerified()) · <a href="{{ route('admin.jobs.employers', ['status' => $open->employer?->status]) }}">{{ translate('verify the employer first') }}</a>@endif</li>
                                <li>{{ translate('Pay shown and realistic') }}</li>
                                <li>{{ translate('No fee or deposit asked') }}</li>
                                <li>{{ translate('A real place') }}</li>
                                <li>{{ translate('Contact only through E-Ride') }}</li>
                            </ul>
                            @if(in_array($open->status, ['review', 'hidden']))
                                <form method="post" action="{{ route('admin.jobs.decide', $open->id) }}" class="d-inline">@csrf
                                    <input type="hidden" name="decision" value="approve"><input type="hidden" name="tab" value="{{ $tab }}">
                                    <button class="btn btn-success" @disabled(!$open->employer?->isVerified())>{{ translate('Approve and go live') }}</button>
                                </form>
                            @endif
                            @if(in_array($open->status, ['review', 'hidden', 'live']))
                                <form method="post" action="{{ route('admin.jobs.decide', $open->id) }}" class="mt-3">@csrf
                                    <input type="hidden" name="tab" value="{{ $tab }}">
                                    <label class="form-label fs-13">{{ translate('Reason the employer reads (for send back or reject)') }}</label>
                                    <input name="reason" class="form-control mb-2" maxlength="300" placeholder="{{ translate('e.g. Give the pay as a number, not "attractive salary".') }}">
                                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="freeze" value="1" id="freeze">
                                        <label class="form-check-label fs-13" for="freeze">{{ translate('Scam: also close this employer and all their jobs') }}</label></div>
                                    <button class="btn btn-outline-dark" name="decision" value="send_back">{{ translate('Send back to fix') }}</button>
                                    <button class="btn btn-outline-danger" name="decision" value="reject">{{ translate('Reject') }}</button>
                                    @if($open->status === 'live')<button class="btn btn-outline-secondary" name="decision" value="close">{{ translate('Take down') }}</button>@endif
                                </form>
                            @endif
                        </div></div>
                    @else
                        <div class="card"><div class="card-body text-center text-muted py-5">{{ translate('Choose a job on the left.') }}</div></div>
                    @endif
                </div>
            </div>

            <div class="card mt-3"><div class="card-body">
                <h3 class="fs-16 mb-3">{{ translate('Settings') }}</h3>
                <form method="post" action="{{ route('admin.jobs.settings') }}">@csrf
                    <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="enabled" value="1" id="jobs_on" @checked($settings['enabled'])>
                        <label class="form-check-label" for="jobs_on">{{ translate('E-Ride Jobs is on') }}</label></div>
                    <div class="row g-3">
                        @foreach([
                            ['job_days', 'Days a job stays open', 3, 90],
                            ['max_open_jobs', 'Open jobs per employer', 1, 100],
                            ['stale_days', 'Close unanswered applications after (days)', 3, 90],
                            ['alerts_per_day', 'Job alerts per person per day', 1, 10],
                            ['min_month_pay', 'Flag full-time pay under (FCFA per month)', 0, 10000000],
                            ['review_hours', 'Review target shown to employers (hours)', 1, 72],
                            ['hide_after_reports', 'Take a job down after this many reports', 1, 20],
                        ] as [$key, $label, $min, $max])
                            <div class="col-sm-6 col-lg-3"><label class="form-label fs-13">{{ translate($label) }}</label>
                                <input type="number" class="form-control" name="{{ $key }}" min="{{ $min }}" max="{{ $max }}" value="{{ $settings[$key] }}" required></div>
                        @endforeach
                        <div class="col-12"><label class="form-label fs-13">{{ translate('Words that flag a job (asking applicants for money), one per line') }}</label>
                            <textarea class="form-control" name="fee_words" rows="5">{{ $settings['fee_words'] }}</textarea></div>
                    </div>
                    <button class="btn btn-primary mt-3">{{ translate('Save') }}</button>
                </form>
            </div></div>
        </div>
    </div>
@endsection
