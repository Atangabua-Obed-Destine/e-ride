@section('title', 'Vehicle Attribute')

@extends('adminmodule::layouts.master')

@push('css_or_js')
@endpush

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">

            <div class="row g-4">
                <!------ Attribute Header------------------->
                @include('vehiclemanagement::admin.partials._attribute_header')

                @can('vehicle_add')
                    <div class="col-12">
                        <form action="{{ route('admin.vehicle.attribute-setup.brand.store') }}"
                              enctype="multipart/form-data" method="POST">
                            @csrf
                            @php
                                $maxSize = readableUploadMaxFileSize('image');
                            @endphp
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="text-primary text-uppercase mb-4">{{ translate('add_new_brand') }}</h5>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-4">
                                                <label for="brand_name"
                                                       class="mb-2">{{ translate('brand_name') }} <span class="text-danger">*</span></label>
                                                <input type="text" id="brand_name" name="brand_name"
                                                       class="form-control" placeholder="Ex: Brand"
                                                       value="{{ old('brand_name') }}" required tabindex="1">
                                            </div>
                                            <div class="mb-4">
                                                <label for="short_desc"
                                                       class="mb-2">{{ translate('short_description') }}<span class="text-danger">*</span></label>
                                                <div class="character-count">
                                                    <textarea id="short_desc" rows="5" name="short_desc"
                                                              class="form-control character-count-field" maxlength="800" tabindex="2"
                                                              data-max-character="800" placeholder="Ex: Description"
                                                              required>{{ old('short_desc') }}</textarea>
                                                    <span>{{translate('0/800')}}</span>
                                                </div>

                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="card-body d-flex flex-column gap-3">
                                                <h5 class="text-center text-capitalize">{{ translate('brand_logo') }}<span class="text-danger">*</span></h5>
                                                <div class="d-flex justify-content-center">
                                                    <div class="upload-file border-light-mode auto profile-image-upload-file cmn_focus rounded-10">
                                                        <input type="file" name="brand_logo" class="upload-file__input"
                                                               accept=".png" required tabindex="3" data-max-upload-size="{{ $maxSize }}">
                                                        <div
                                                            class="upload-file__img border-gray d-flex justify-content-center align-items-center w-150 h-150 aspect-1 p-0">
                                                            <div class="upload-file__textbox text-center">
                                                                <img width="34" height="34"
                                                                     src="{{ dynamicAsset('public/assets/admin-module/img/document-upload.png') }}"
                                                                     alt="" class="svg">
                                                                <h6 class="mt-2 fw-semibold fs-12">
                                                                    <span class="text-info">{{ translate('Click to upload') }}</span>
                                                                    <br>
                                                                    {{ translate('or drag and drop') }}
                                                                </h6>
                                                            </div>
                                                            <img class="upload-file__img__img h-100"
                                                                 loading="lazy" alt="">
                                                        </div>
                                                        <a href="javascript:void(0)" class="remove-img-icon d-none">
                                                            <i class="tio-clear"></i>
                                                        </a>
                                                    </div>
                                                </div>

                                                <p class="opacity-75 mx-auto text-center max-w220">
                                                    {{ translate(key: 'File Format - {format}, Image Size - Maximum {imageSize}', replace: ['format' => '.png', 'imageSize' => $maxSize]) }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-end gap-3">
                                        <button type="submit"
                                                class="btn btn-primary cmn_focus" tabindex="4">{{ translate('submit') }}</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                @endcan

                <div class="col-12">
                    <h2 class="fs-22 text-capitalize">{{ translate('brand_list') }}</h2>

                    <div class="d-flex flex-wrap justify-content-between align-items-center my-3 gap-3">
                        <ul class="nav nav--tabs p-1 rounded bg-white" role="tablist">
                            <li class="nav-item" role="presentation">
                                <a class="nav-link {{!request()->has('status') || request()->get('status') =='all'?'active':''}}"
                                   href="{{url()->current()}}?status=all">
                                    {{ translate('all') }}
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link {{request()->get('status')=='active'?'active':''}}"
                                   href="{{url()->current()}}?status=active">
                                    {{ translate('active') }}
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link {{request()->get('status')=='inactive'?'active':''}}"
                                   href="{{url()->current()}}?status=inactive">
                                    {{ translate('inactive') }}
                                </a>
                            </li>
                        </ul>
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-muted text-capitalize">{{ translate('total_brands') }} : </span>
                            <span class="text-primary fs-16 fw-bold"
                                  id="total_record_count">{{ $brands->total() }}</span>
                        </div>
                    </div>

                    <div class="tab-content">
                        <div class="tab-pane fade active show" id="all-tab-pane" role="tabpanel">
                            <div class="card">
                                <div class="card-body">
                                    <div class="table-top d-flex flex-wrap gap-10 justify-content-between">
                                        <form action="javascript:;"
                                              class="search-form search-form_style-two" method="GET">
                                            <div class="input-group search-form__input_group">
                                                <span class="search-form__icon">
                                                    <i class="bi bi-search"></i>
                                                </span>
                                                <input type="search" class="theme-input-style search-form__input"
                                                       value="{{request()->get('search')}}" name="search" id="search"
                                                       placeholder="{{translate('search_here_by_Brand_Name')}}" tabindex="5">
                                            </div>
                                            <button type="submit"
                                                    class="btn btn-primary search-submit"
                                                    data-url="{{ url()->full() }}" tabindex="6">{{ translate('search') }}</button>
                                        </form>

                                        <div class="d-flex flex-wrap gap-3">
                                            @can('super-admin')
                                                <a href="{{ route('admin.vehicle.attribute-setup.brand.index', ['status' => request('status')]) }}"
                                                   class="btn btn-outline-primary px-3" data-bs-toggle="tooltip"
                                                   data-bs-title="{{ translate('refresh') }}" tabindex="7">
                                                    <i class="bi bi-arrow-repeat"></i>
                                                </a>

                                                <a href="{{ route('admin.vehicle.attribute-setup.brand.trashed') }}"
                                                   class="btn btn-outline-primary px-3" data-bs-toggle="tooltip"
                                                   data-bs-title="{{ translate('manage_Trashed_Data') }}" tabindex="8">
                                                    <i class="bi bi-recycle"></i>
                                                </a>
                                            @endcan

                                            @can('vehicle_log')
                                                <a href="{{route('admin.vehicle.attribute-setup.brand.log')}}"
                                                   class="btn btn-outline-primary px-3" data-bs-toggle="tooltip"
                                                   data-bs-title="{{ translate('view_Log') }}" tabindex="9">
                                                    <i class="bi bi-clock-fill"></i>
                                                </a>
                                            @endcan

                                            @can('vehicle_export')
                                                <div class="dropdown">
                                                    <button type="button" class="btn btn-outline-primary"
                                                            data-bs-toggle="dropdown" tabindex="10">
                                                        <i class="bi bi-download"></i>
                                                        {{ translate('download') }}
                                                        <i class="bi bi-caret-down-fill"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                                                        <li><a class="dropdown-item"
                                                               href="{{route('admin.vehicle.attribute-setup.brand.export', ['status' => request()->get('status') ?? 'all', 'search' => request()->get('search'), 'file' => 'excel'])}}">{{translate('excel')}}</a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            @endcan
                                        </div>
                                    </div>

                                    <div class="tmodel/inable-responsive mt-3 text-center">
                                        <table class="table table-borderless align-middle">
                                            <thead class="table-light align-middle">
                                            <tr>
                                                <th>{{ translate('SL') }}</th>
                                                <th class="text-capitalize name">{{ translate('brand_name') }}</th>
                                                <th class="text-capitalize total-vehicle">{{ translate('total_vehicle') }}</th>
                                                @can('vehicle_edit')
                                                    <th class="status">{{ translate('status') }}</th>
                                                @endcan
                                                <th class="text-center action">{{ translate('action') }}</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            @forelse ($brands as $key => $brand)
                                                <tr id="hide-row-{{$brand->id}}" class="record-row">
                                                    <td>{{ $brands->firstItem() +$key }}</td>
                                                    <td class="name">{{ $brand->name }}</td>
                                                    <td class="total-vehicle">{{ $brand->vehicles->count() }}</td>
                                                    @can('vehicle_edit')
                                                        <td class="status">
                                                            <label class="switcher mx-auto">
                                                                <input class="switcher_input status-change"

                                                                       data-url={{ route('admin.vehicle.attribute-setup.brand.status') }} id="{{ $brand->id }}"
                                                                       type="checkbox" {{$brand->is_active?'checked':''}}>
                                                                <span class="switcher_control"></span>
                                                            </label>
                                                        </td>
                                                    @endcan
                                                    <td class="action">
                                                        <div
                                                            class="d-flex justify-content-center gap-2 align-items-center">
                                                            @can('vehicle_log')
                                                                <a href="{{route('admin.vehicle.attribute-setup.brand.log')}}?id={{$brand->id}}"
                                                                   class="btn btn-outline-primary btn-action">
                                                                    <i class="bi bi-clock-fill"></i>
                                                                </a>
                                                            @endcan
                                                            @can('vehicle_edit')
                                                                <a href="{{route('admin.vehicle.attribute-setup.brand.edit', ['id'=>$brand->id])}}"
                                                                   class="btn btn-outline-info btn-action">
                                                                    <i class="bi bi-pencil-fill"></i>
                                                                </a>
                                                            @endcan
                                                            @can('vehicle_delete')
                                                                <button
                                                                    data-url="{{ route('admin.vehicle.attribute-setup.brand.delete', ['id' => $brand->id]) }}"
                                                                    data-icon="{{ dynamicAsset('public/assets/admin-module/img/trash.png') }}"
                                                                    data-title="{{ translate('Are you sure to delete this Vehicle Brand') }}?"
                                                                    data-sub-title="{{ translate('Once you delete it') . ', ' . translate('This will be permanently removed from the list.') }}"
                                                                    data-confirm-btn="{{ translate('Yes, Delete') }}"
                                                                    data-cancel-btn="{{ translate('Not Now') }}"
                                                                    type="button"
                                                                    class="btn btn-outline-danger btn-action delete-button">
                                                                    <i class="bi bi-trash-fill"></i>
                                                                </button>
                                                            @endcan
                                                        </div>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="5">
                                                        <div
                                                            class="d-flex flex-column justify-content-center align-items-center gap-2 py-3">
                                                            <img
                                                                src="{{ dynamicAsset('public/assets/admin-module/img/empty-icons/no-data-found.svg') }}"
                                                                alt="" width="100">
                                                            <p class="text-center">{{translate('no_data_available')}}</p>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="d-flex justify-content-end">
                                        {!! $brands->links() !!}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- End Main Content -->
@endsection

@push('script')
    <script src="{{ dynamicAsset('public/assets/admin-module/js/single-image-upload.js') }}"></script>
    <script>
        "use strict";
        $('.js-select-ajax').select2({
            ajax: {
                url: '{{ route('admin.vehicle.attribute-setup.brand.all-brands') }}',
                data: function (params) {
                    return {
                        q: params.term, // search term
                        page: params.page
                    };
                },
                processResults: function (data) {
                    //
                    return {
                        results: data
                    };
                },
                __port: function (params, success, failure) {
                    var $request = $.ajax(params);
                    $request.then(success);
                    $request.fail(failure);
                    return $request;
                }
            }
        });
    </script>
@endpush
