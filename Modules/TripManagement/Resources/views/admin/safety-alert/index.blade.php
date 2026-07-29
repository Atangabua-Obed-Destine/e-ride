@extends('adminmodule::layouts.master')

@section('title', translate('Safety Alert'))

@section('content')
    <div class="main-content">
        <div class="container-fluid">
            <h4 class="text-capitalize mb-4 ">{{ translate('solved_alert_list')}}</h4>
        </div>

        <div class="row mb-4">
            @include('tripmanagement::admin.safety-alert.partials._safety-alert-inline-menu')
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-top d-flex flex-wrap gap-10 justify-content-between">
                    <form action="{{url()->current()}}" class="search-form search-form_style-two">
                        <div class="input-group search-form__input_group">
                                    <span class="search-form__icon">
                                        <i class="bi bi-search"></i>
                                    </span>
                            <input type="text" name="search" value="{{request()->search}}"
                                   class="theme-input-style search-form__input"
                                   placeholder="{{translate('Search_here_by_safety_alert_id')}}">
                        </div>
                        <button type="submit" class="btn btn-primary cmn_focus">{{translate('search')}}</button>
                    </form>

                    <div class="d-flex flex-wrap gap-3">
                        @can('trip_export')
                            <div class="dropdown">
                                <button type="button" class="btn btn-outline-primary"
                                        data-bs-toggle="dropdown">
                                    <i class="bi bi-download"></i>
                                    {{translate('download')}}
                                    <i class="bi bi-caret-down-fill"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                                    <li>
                                        <a class="dropdown-item"
                                           href="{{route('admin.safety-alert.export', $type)}}?search={{request()->get('search')}}&&file=excel">{{ translate('excel') }}</a>
                                    </li>
                                </ul>
                            </div>
                        @endcan
                    </div>
                </div>
                <div id="trip-list-view">
                    @include('tripmanagement::admin.safety-alert.partials._safety-alert-list')
                </div>
            </div>
        </div>
    </div>
@endsection
