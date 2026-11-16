@extends('adminmodule::layouts.master')

@section('title', translate('ID verification'))

@section('content')
<div class="main-content">
    <div class="container-fluid">
        <h2 class="fs-22 mb-1">{{ translate('ID verification') }}</h2>
        <p class="fs-13 text-muted mb-3">{{ translate('People send their ID card or passport with a selfie once. Verified, they carry the E-Ride ID Verified badge everywhere in E-Ride until the document expires or their name changes.') }}</p>
        @if ($errors->any())
            <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <div class="row g-3">
            <div class="col-lg-5">
                <div class="card mb-3">
                    <div class="card-header d-flex gap-2 flex-wrap">
                        @foreach(['pending' => translate('To review'), 'verified' => translate('Verified'), 'refused' => translate('Refused')] as $key => $label)
                            <a href="{{ route('admin.id-verification.index', ['tab' => $key]) }}" class="btn btn-sm {{ $tab === $key ? 'btn-primary' : 'btn-outline-primary' }}">{{ $label }} <span class="badge bg-light text-dark">{{ $counts[$key] ?? 0 }}</span></a>
                        @endforeach
                    </div>
                    <div class="list-group list-group-flush">
                        @forelse($records as $r)
                            <a href="{{ route('admin.id-verification.index', ['tab' => $tab, 'id' => $r->id, 'page' => $records->currentPage()]) }}"
                               class="list-group-item list-group-item-action {{ $current && $current->id === $r->id ? 'active' : '' }}">
                                <div class="d-flex justify-content-between"><b>{{ $r->full_name }}</b><span class="fs-12">{{ $r->created_at?->diffForHumans() }}</span></div>
                                <div class="fs-12">{{ $r->doc_type === 'passport' ? translate('Passport') : translate('ID card') }} · {{ $r->doc_number }} · {{ $r->user?->phone }}</div>
                            </a>
                        @empty
                            <div class="list-group-item text-muted">{{ translate('Nothing here.') }}</div>
                        @endforelse
                    </div>
                    <div class="card-footer">{{ $records->links() }}</div>
                </div>

                <form method="POST" action="{{ route('admin.id-verification.settings') }}" class="card">
                    @csrf
                    <div class="card-header"><h5 class="mb-0">{{ translate('How IDs are checked') }}</h5></div>
                    <div class="card-body">
                        <div class="form-check mb-2"><input class="form-check-input" type="radio" name="method" id="m_staff" value="staff" @checked($settings['method'] === 'staff')>
                            <label class="form-check-label" for="m_staff"><b>{{ translate('E-Ride staff') }}</b><br><span class="fs-12 text-muted">{{ translate('Someone compares the document, the name and the selfie here, and approves or refuses.') }}</span></label></div>
                        <div class="form-check mb-2"><input class="form-check-input" type="radio" name="method" id="m_auto" value="auto" @checked($settings['method'] === 'auto') @disabled(!$autoAvailable)>
                            <label class="form-check-label" for="m_auto"><b>{{ translate('Automatic face match') }}</b><br><span class="fs-12 text-muted">{{ $autoAvailable
                                ? translate('The selfie is compared with the face on the document (the face verification API). Below the threshold it is refused. If the service fails, it waits here for staff.')
                                : translate('Set up and switch on the face verification API first (Configurations, 3rd party).') }}</span></label></div>
                        <label class="form-label mt-2" for="threshold">{{ translate('Face match threshold (%)') }}</label>
                        <input id="threshold" type="number" name="threshold" min="70" max="99" class="form-control" style="max-width:120px" value="{{ $settings['threshold'] }}">
                    </div>
                    <div class="card-footer text-end"><button class="btn btn-primary">{{ translate('Save') }}</button></div>
                </form>
            </div>

            <div class="col-lg-7">
                @if($current)
                    <div class="card">
                        <div class="card-header d-flex align-items-center gap-2">
                            <h5 class="mb-0 flex-grow-1">{{ $current->full_name }}</h5>
                            <span class="badge {{ ['pending' => 'bg-warning-subtle text-warning-emphasis', 'verified' => 'bg-success-subtle text-success-emphasis', 'refused' => 'bg-danger-subtle text-danger-emphasis'][$current->status] ?? 'bg-light' }}">{{ translate(ucfirst($current->status)) }}</span>
                        </div>
                        <div class="card-body">
                            <div class="row g-2 mb-3 fs-13">
                                <div class="col-sm-4"><div class="text-muted">{{ translate('Document') }}</div><b>{{ $current->doc_type === 'passport' ? translate('Passport') : translate('ID card') }}</b></div>
                                <div class="col-sm-4"><div class="text-muted">{{ translate('Number') }}</div><b>{{ $current->doc_number }}</b></div>
                                <div class="col-sm-4"><div class="text-muted">{{ translate('Expires') }}</div><b>{{ $current->expires_on?->format('d/m/Y') ?? '-' }}</b></div>
                                <div class="col-sm-8"><div class="text-muted">{{ translate('Name on the E-Ride profile') }}</div><b>{{ trim(($current->user?->first_name ?? '') . ' ' . ($current->user?->last_name ?? '')) }}</b> · {{ $current->user?->phone }}</div>
                                @if($current->similarity !== null)<div class="col-sm-4"><div class="text-muted">{{ translate('Face match') }}</div><b>{{ round($current->similarity) }}%</b></div>@endif
                            </div>
                            <div class="row g-2">
                                <div class="col-md-6"><div class="fs-12 text-muted mb-1">{{ translate('Selfie') }}</div>
                                    <a href="{{ route('admin.id-verification.file', [$current->id, 'selfie']) }}" target="_blank" rel="noopener"><img src="{{ route('admin.id-verification.file', [$current->id, 'selfie']) }}" alt="{{ translate('Selfie') }}" class="img-fluid rounded border" style="max-height:340px;object-fit:contain;width:100%"></a></div>
                                <div class="col-md-6"><div class="fs-12 text-muted mb-1">{{ translate('Document, front') }}</div>
                                    <a href="{{ route('admin.id-verification.file', [$current->id, 'front']) }}" target="_blank" rel="noopener"><img src="{{ route('admin.id-verification.file', [$current->id, 'front']) }}" alt="{{ translate('Document, front') }}" class="img-fluid rounded border" style="max-height:340px;object-fit:contain;width:100%"></a></div>
                                @if($current->back_path)
                                    <div class="col-md-6"><div class="fs-12 text-muted mb-1">{{ translate('Document, back') }}</div>
                                        <a href="{{ route('admin.id-verification.file', [$current->id, 'back']) }}" target="_blank" rel="noopener"><img src="{{ route('admin.id-verification.file', [$current->id, 'back']) }}" alt="{{ translate('Document, back') }}" class="img-fluid rounded border" style="max-height:340px;object-fit:contain;width:100%"></a></div>
                                @endif
                            </div>
                            @if($current->status === 'refused')<div class="alert alert-danger mt-3 mb-0">{{ $current->refuse_reason }}</div>@endif
                        </div>
                        @if($current->status === 'pending')
                            <div class="card-footer">
                                <div class="fs-12 text-muted mb-2">{{ translate('Check that the selfie is the same person as the document, the name matches the profile, and the document is not expired or edited.') }}</div>
                                <div class="d-flex flex-wrap gap-2 align-items-start">
                                    <form method="POST" action="{{ route('admin.id-verification.approve', $current->id) }}">@csrf<button class="btn btn-success">{{ translate('Verify') }}</button></form>
                                    <form method="POST" action="{{ route('admin.id-verification.refuse', $current->id) }}" class="d-flex gap-2 flex-grow-1">@csrf
                                        <select name="reason" class="form-select" required aria-label="{{ translate('Why') }}">
                                            <option value="">{{ translate('Refuse because...') }}</option>
                                            @foreach([translate('The photos are blurred or cut off.'), translate('The selfie does not match the document.'), translate('The name does not match your E-Ride profile.'), translate('The document is expired.'), translate('This is not an ID card or passport.')] as $reason)
                                                <option value="{{ $reason }}">{{ $reason }}</option>
                                            @endforeach
                                        </select>
                                        <button class="btn btn-outline-danger text-nowrap">{{ translate('Refuse') }}</button></form>
                                </div>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="card"><div class="card-body text-muted">{{ translate('Nothing to review.') }}</div></div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
