@extends('busmanagement::partner.layout')

@section('title', translate('Share'))

@section('content')
    <h1 class="b-h mb-1">{{ translate('Share your agency') }}</h1>
    <p class="text-muted mb-3" style="font-size:14px">{{ translate('Post your departures on your WhatsApp Status and in your groups. Travellers see your destinations and fares, and book their seat in the E-Ride app.') }}</p>
    @include('share-look.studio', [
        'urls' => $canEdit ? ['look' => route('bus-partner.share.look'), 'design' => route('bus-partner.share.design'), 'remove' => route('bus-partner.share.design.remove')] : null,
    ])
@endsection
