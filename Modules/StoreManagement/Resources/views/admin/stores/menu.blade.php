@extends('adminmodule::layouts.master')

@section('title', $store->name . ' · ' . translate('Menu'))

@section('content')
    <div class="main-content">
        <div class="container-fluid" style="max-width: 980px">
            <div class="d-flex align-items-center gap-2 mb-3">
                <a href="{{ route('admin.store.index') }}" class="btn btn-light btn-sm"><i class="bi bi-arrow-left"></i></a>
                <h2 class="fs-22 mb-0">{{ $store->name }}</h2>
            </div>
            @include('storemanagement::admin.stores._tabs', ['active' => 'menu'])
            @if ($errors->any())
                <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            @include('storemanagement::shared._menu', ['routePrefix' => 'admin.store.', 'routeBase' => ['store' => $store]])
        </div>
    </div>
@endsection
