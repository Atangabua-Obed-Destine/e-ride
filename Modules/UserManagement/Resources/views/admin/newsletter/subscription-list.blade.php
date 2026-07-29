@extends('adminmodule::layouts.master')

@section('title', translate('Newsletter Subscription List'))

@push('css_or_js')
@endpush

@section('content')
    @php($env = env('APP_MODE') == 'live' ? 'live' : 'test')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="fs-20 fw-bold mb-0 text-capitalize">{{translate('Newsletter Subscription List')}}</h2>
            </div>
            <div class="card card-body">
                <div class="table-top d-flex flex-wrap gap-10 justify-content-between mb-3">
                    <form action="javascript:;" class="search-form search-form_style-two"
                          method="GET">
                        <div class="input-group search-form__input_group">
                            <span class="search-form__icon px-2">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="search" class="theme-input-style search-form__input"
                                   value="{{ request()->get('search') }}" name="search" id="search"
                                   placeholder="{{ translate('search') }}">
                        </div>
                        <button type="submit" class="btn btn-primary search-submit"
                                data-url="{{ url()->full() }}">{{ translate('search') }}</button>
                    </form>

                    <div class="d-flex flex-wrap gap-3">
                        <a href="{{ route('admin.newsletter.index') }}"
                           class="btn btn-outline-primary px-3" data-bs-toggle="tooltip"
                           data-bs-title="{{ translate('refresh') }}">
                            <i class="bi bi-arrow-repeat"></i>
                        </a>
                        <div class="dropdown">
                            <button type="button" class="btn btn-outline-primary"
                                    data-bs-toggle="dropdown">
                                <i class="bi bi-download"></i>
                                {{ translate('download') }}
                                <i class="bi bi-caret-down-fill"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                                <li>
                                    <a class="dropdown-item" href="{{route('admin.newsletter.export',[
                                                'file'=>'excel',
                                                'search' =>request()->get('search'),
                                                ]
                                            )}}">{{ translate('excel') }}</a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-borderless align-middle table-hover col-mx-w300 text-dark text-nowrap">
                        <thead class="table-light align-middle text-capitalize">
                        <tr>
                            <th>{{ translate('SL') }}</th>
                            <th>{{ translate('Email') }}</th>
                            <th>{{ translate('Created At') }}</th>
                            <th>{{ translate('Updated At') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($subscriptionList as $key => $subscription)
                            <tr>
                                <td>{{$subscriptionList->firstItem() + $key}}</td>
                                <td>{{ $subscription->email }}</td>
                                <td>
                                    {{$subscription->created_at->format('d F, Y') }}
                                </td>
                                <td>
                                    {{$subscription->updated_at->format('d F, Y') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="14">
                                    <div
                                        class="d-flex flex-column justify-content-center align-items-center gap-2 py-3">
                                        <img
                                            src="{{ dynamicAsset('public/assets/admin-module/img/empty-icons/no-data-found.svg') }}"
                                            alt=""
                                            width="100">
                                        <p class="text-center">{{translate('no_data_available')}}</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-end mt-3">
                    {{ $subscriptionList->links() }}
                </div>
            </div>

        </div>
    </div>
    <!-- End Main Content -->
@endsection
