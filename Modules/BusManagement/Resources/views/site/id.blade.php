@extends('busmanagement::site._layout')

@section('title', $t('id_title'))

@section('body')
<div class="page">
    @if($errors->any())<div class="err" role="alert">{{ $errors->first() }}</div>@endif
    <div class="sec col" style="gap:10px;padding-top:24px">
        <div style="width:56px;height:56px;border-radius:16px;background:#EEF2FF;display:flex;align-items:center;justify-content:center">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#3730A3" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2.5"/><circle cx="9" cy="11" r="2.2"/><path d="M5.8 16c.6-1.6 1.8-2.4 3.2-2.4s2.6.8 3.2 2.4M14.5 10h4M14.5 13h3"/></svg>
        </div>
        <h1>{{ $t('id_title') }}</h1>
        <p class="sm muted" style="margin:0">{{ $t('id_body', ['agency' => $departure->agency->name]) }}</p>
    </div>
    <div class="card col" style="gap:14px;margin:4px 16px 0">
        @foreach([['1', $t('id_step1'), $t('id_step1_sub', ['phone' => $me->phone])], ['2', $t('id_step2'), $t('id_step2_sub')], ['3', $t('id_step3'), $t('id_step3_sub')]] as [$n, $title, $sub])
            <div class="row" style="align-items:flex-start;gap:12px"><span style="width:28px;height:28px;border-radius:50%;background:#1D2D2B;color:#fff;font-size:13px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0">{{ $n }}</span>
                <div class="col" style="gap:1px"><span class="sm b">{{ $title }}</span><span class="xs muted">{{ $sub }}</span></div></div>
        @endforeach
    </div>
    <div class="sec col" style="gap:10px;padding-top:20px">
        <a class="btn" href="{{ $appLink }}" data-fallback="{{ $appPage }}" id="open">{{ $t('verify_in_app') }}</a>
        <a class="btn ghost" href="{{ route('bus.web.search', ['from' => $departure->agency->origin_city, 'to' => $departure->destination?->city, 'date' => $departure->date->toDateString()]) }}">{{ $t('other_agencies') }}</a>
        <a class="xs b" href="{{ route('bus.web.travellers', ['id' => $departure->id]) }}" style="text-align:center;color:#5F6B6A;padding-top:4px">{{ $t('id_sent_continue') }}</a>
    </div>
</div>
@endsection

@push('scripts')
@include('busmanagement::site._open_app_js')
@endpush
