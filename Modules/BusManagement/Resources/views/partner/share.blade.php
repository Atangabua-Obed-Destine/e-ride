@extends('busmanagement::partner.layout')

@section('title', translate('Share'))

@section('content')
    <h1 class="b-h mb-1">{{ translate('Share your agency') }}</h1>
    <p class="text-muted mb-3" style="font-size:14px">{{ translate('Post your departures on WhatsApp, Instagram, TikTok and Facebook. Travellers see your destinations and fares, and book their seat in the E-Ride app.') }}</p>
    @if($canEdit)
        <div class="sl-logo b-card p-3 mb-3 d-flex flex-wrap gap-3 align-items-center" style="border-radius:6px">
            @if($agency->logo)
                <img src="{{ dynamicStorage('storage/app/public/bus/logo/' . $agency->logo) }}" alt="{{ translate('Your logo') }}" width="64" height="64" style="border-radius:6px;object-fit:cover;flex:none">
            @else
                <div aria-hidden="true" style="width:64px;height:64px;border-radius:6px;background:#F4F6F6;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:24px;color:#6B7675;flex:none">{{ mb_strtoupper(mb_substr($agency->name, 0, 1)) }}</div>
            @endif
            <div style="flex:1 1 220px;min-width:0">
                <div class="fw-bold">{{ translate('Your logo') }}</div>
                <div class="text-muted" style="font-size:13px">{{ translate('Shown in the E-Ride app, on your page and on your pictures. A square picture works best.') }}</div>
                @if(session('logo_saved'))<div style="font-size:13px;color:#12A150">{{ session('logo_saved') }}</div>@endif
                @error('logo')<div style="font-size:13px;color:#C0262D">{{ $message }}</div>@enderror
            </div>
            <form method="POST" action="{{ route('bus-partner.share.logo') }}" enctype="multipart/form-data" class="d-flex gap-2 align-items-center">@csrf
                <label class="btn btn-outline-primary btn-sm mb-0" style="border-radius:6px">
                    <i class="bi bi-upload"></i> {{ $agency->logo ? translate('Change the logo') : translate('Upload your logo') }}
                    <input type="file" name="logo" accept="image/jpeg,image/png,image/webp" hidden onchange="this.form.submit()">
                </label>
            </form>
            @if($agency->logo)
                <form method="POST" action="{{ route('bus-partner.share.logo') }}" onsubmit="return confirm(@js(translate('Remove your logo?')))">@csrf
                    <input type="hidden" name="remove" value="1">
                    <button type="submit" class="btn btn-link btn-sm text-danger p-0">{{ translate('Remove') }}</button>
                </form>
            @endif
        </div>
    @endif
    @include('share-look.studio', [
        'urls' => $canEdit ? ['look' => route('bus-partner.share.look'), 'design' => route('bus-partner.share.design'), 'remove' => route('bus-partner.share.design.remove')] : null,
    ])
@endsection
