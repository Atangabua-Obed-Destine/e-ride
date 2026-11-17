@extends('storemanagement::partner.layout')

@section('title', translate('Menu'))

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-1">
        <h1 class="fs-4 fw-bold m-0">{{ translate('Menu') }}</h1>
    </div>
    <p class="text-muted fs-14 mb-3">
        @if($canEditMenu)
            {{ translate('Each morning, check Today: set a dish to Sold out today when it runs out, it comes back by itself tomorrow. In Full menu, choose the days and hours each dish or section is served.') }}
        @else
            {{ translate('Each morning, check Today: set a dish to Sold out today when it runs out, it comes back by itself tomorrow. The owner changes the menu and prices.') }}
        @endif
    </p>
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @include('storemanagement::shared._menu', ['routePrefix' => 'partner.', 'routeBase' => []])
@endsection
