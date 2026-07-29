@extends('adminmodule::layouts.master')

@section('title', translate('Test Design'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ dynamicAsset('public/assets/admin-module/plugins/summernote/summernote-lite.min.css') }}"/>
@endpush

@section('content')
@endsection
