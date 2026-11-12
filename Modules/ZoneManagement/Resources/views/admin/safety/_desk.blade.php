{{-- Support desk on duty now: shown on the Safety map and on Lost and found. --}}
<div class="alert {{ $desk['open_now'] ? 'alert-success' : 'alert-warning' }} d-flex flex-wrap align-items-center gap-2 py-2 fs-13 mb-3">
    <i class="bi bi-headset"></i>
    <b>{{ $desk['name'] }}</b>
    <span>{{ $desk['open_now'] ? translate('open now') : translate('closed now') }} ({{ $desk['hours'] }})</span>
    @if($desk['phone'])<span>· <a href="tel:{{ $desk['phone'] }}">{{ $desk['phone'] }}</a></span>@endif
    @if($desk['staff'])<span class="text-muted">· {{ $desk['staff'] }}</span>@endif
    <a class="ms-auto" href="{{ route('admin.safety.index') }}#support-desk">{{ translate('Change') }}</a>
</div>
