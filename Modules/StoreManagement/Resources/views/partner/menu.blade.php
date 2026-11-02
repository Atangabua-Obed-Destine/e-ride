@extends('storemanagement::partner.layout')

@section('title', translate('Menu'))

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-1">
        <h1 class="fs-4 fw-bold m-0">{{ translate('Menu') }}</h1>
    </div>
    <p class="text-muted fs-14 mb-3">{{ translate('Switch an item off when it runs out. Customers see the change immediately.') }}</p>
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @include('storemanagement::shared._menu', ['routePrefix' => 'partner.', 'routeBase' => []])
@endsection
