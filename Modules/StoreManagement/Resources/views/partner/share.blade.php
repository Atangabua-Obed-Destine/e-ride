@extends('storemanagement::partner.layout')

@section('title', translate('Share your store'))

@section('content')
    <h1 class="fs-4 fw-bold mb-1">{{ translate('Share your store') }}</h1>
    <p class="text-muted mb-3" style="font-size:14px">{{ translate('Post your store on your WhatsApp Status and in your groups. People see your menu and prices, and order in the E-Ride app.') }}</p>

    <div class="p-card p-3 mb-3">
        <div class="d-flex gap-3 text-center">
            <div class="flex-fill"><div class="fs-3 fw-bold">{{ $brand->link_visits }}</div><div class="text-muted" style="font-size:12px">{{ translate('Opened your page') }}</div></div>
            <div class="flex-fill"><div class="fs-3 fw-bold">{{ $brand->link_installs }}</div><div class="text-muted" style="font-size:12px">{{ translate('Joined E-Ride from your link') }}</div></div>
        </div>
    </div>

    @include('share-look.studio', [
        'urls' => ['look' => route('partner.share.look'), 'design' => route('partner.share.design'), 'remove' => route('partner.share.design.remove')],
    ])
@endsection
