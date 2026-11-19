@extends('busmanagement::site._layout')

@section('title', $t('continue_app'))

@section('body')
<div class="page">
    <div class="sec col" style="gap:12px;padding-top:28px">
        <div aria-hidden="true" style="width:64px;height:64px;border-radius:18px;background:#E02020;color:#fff;display:flex;align-items:center;justify-content:center;font-size:26px;font-weight:900">E</div>
        <h1>{{ $t('continue_app') }}</h1>
        <p class="sm muted" style="margin:0">{{ $me ? $t('continue_app_sub') . ' ' . $me->phone : $t('continue_app_sub_guest') }}</p>
        <ul class="col" style="gap:10px;margin:6px 0 0;padding:0;list-style:none">
            @foreach(['app_li1', 'app_li2', 'app_li3', 'app_li4'] as $key)
                <li class="row" style="gap:10px"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#15803D" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5 9-10"/></svg>{{ $t($key) }}</li>
            @endforeach
        </ul>
    </div>
    <div class="sec col" style="gap:10px;padding-top:20px">
        <a class="btn dark" href="{{ $playUrl }}">{{ $t('google_play') }}</a>
        <a class="btn line" href="{{ $openApp }}" id="open-app">{{ $t('open_in_app') }}</a>
        <a class="sm b" href="{{ $back }}" style="text-align:center;padding-top:6px">{{ $t('back_web') }}</a>
    </div>
</div>
@endsection

@push('scripts')
<script>
// The "open it" link is an Android intent: hide it elsewhere.
if (!/Android/i.test(navigator.userAgent)) { var o = document.getElementById('open-app'); if (o) o.style.display = 'none'; }
</script>
@endpush
