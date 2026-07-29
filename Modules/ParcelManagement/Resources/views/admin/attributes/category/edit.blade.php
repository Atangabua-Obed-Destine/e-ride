@section('title', translate('edit_Parcel_Category'))

@extends('adminmodule::layouts.master')

@push('css_or_js')
@endpush

@section('content')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="row g-4">
                <div class="col-12">
                    <form action="{{ route('admin.parcel.attribute.category.update', ['id' => $category->id]) }}"
                        enctype="multipart/form-data" method="POST">
                        @csrf
                        @method('PUT')
                        @php
                            $maxSize = readableUploadMaxFileSize('image');
                        @endphp
                        <div class="card">
                            <div class="card-body">
                                <h5 class="text-primary text-uppercase mb-4">{{ translate('edit_parcel_category') }}
                                </h5>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-4">
                                            <label for="category_name"
                                                class="mb-2">{{ translate('category_name') }}</label>
                                            <input type="text" id="category_name" name="category_name"
                                                class="form-control" value="{{ $category->name }}"
                                                placeholder="Ex: Category name" tabindex="1">
                                        </div>
                                        <div class="mb-4">
                                            <label for="short_desc"
                                                class="mb-2">{{ translate('short_description') }}</label>
                                            <div class="character-count">
                                                    <textarea required id="short_desc" rows="5" name="short_desc"
                                                              class="form-control character-count-field" maxlength="800"
                                                              data-max-character="800"
                                                              placeholder="Ex: Description" tabindex="2">{{ $category->description }}</textarea>
                                                <span>{{translate('0/800')}}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card-body d-flex flex-column gap-3">
                                            <h5 class="text-center text-capitalize">{{ translate('category_icon') }}
                                            </h5>

                                            <div class="d-flex justify-content-center">
                                                <div class="upload-file border-light-mode auto profile-image-upload-file cmn_focus rounded-10">
                                                    <input type="file" name="category_icon" class="upload-file__input"
                                                            accept=".png" tabindex="3" data-max-upload-size="{{ $maxSize }}">
                                                        <span class="edit-btn">
                                                            <i class="bi bi-pencil-square text-primary"></i>
                                                        </span>
                                                    <div
                                                        class="upload-file__img border-gray d-flex justify-content-center align-items-center w-150 h-150 aspect-1 p-0">
                                                        <img class="upload-file__img__img h-100 d-block"
                                                            src="{{ onErrorImage(
                                                                $category?->image,
                                                                dynamicStorage('storage/app/public/parcel/category') . '/' . $category?->image,
                                                                dynamicAsset('public/assets/admin-module/img/media/upload-file.png'),
                                                                'parcel/category/',
                                                            ) }}"
                                                             loading="lazy" alt="">
                                                    </div>
                                                </div>
                                            </div>

                                            <p class="opacity-75 mx-auto text-center max-w220">
                                                {{ translate(key: 'File Format - {format}, Image Size - Maximum {imageSize}', replace: ['format' => '.png', 'imageSize' => $maxSize]) }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end gap-3">
                                    <button type="submit" class="btn btn-primary cmn_focus" tabindex="4">{{ translate('update') }}</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- End Main Content -->
@endsection

@push('script')
@endpush
