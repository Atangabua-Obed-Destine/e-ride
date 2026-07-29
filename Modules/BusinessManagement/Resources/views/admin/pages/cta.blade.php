@extends('adminmodule::layouts.master')

@section('title', translate('Landing_Page'))

@push('css_or_js')
@endpush

@section('content')
    @php($env = env('APP_MODE') == 'live' ? 'live' : 'test')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-22 mb-4 text-capitalize">{{translate('landing_page')}}</h2>
            @include('businessmanagement::admin.pages.partials._landing_page_inline_menu')


            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-3">
                        <h5 class="text-primary text-uppercase">{{ translate('CTA') }}</h5>
                    </div>

                    <form action="{{ route('admin.business.pages-media.landing-page.cta.update') }}" id="banner_form"
                          enctype="multipart/form-data" method="POST">
                        @csrf
                        <input type="hidden" name="type" value="{{CTA}}">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label for="title" class="mb-2">{{ translate('title') }}</label>
                                    <div class="character-count">
                                        <input type="text" class="form-control character-count-field" maxlength="100"
                                               data-max-character="100" id="title"
                                               value="{{ $data?->value['title'] ?? "" }}" name="title"
                                               placeholder="{{ translate('Ex: Title') }}" required tabindex="1">
                                        <span>{{translate('0/100')}}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <label for="subTitle" class="mb-2">{{ translate('sub_Title') }}</label>
                                    <div class="character-count">
                                        <input type="text" class="form-control character-count-field" maxlength="200"
                                               data-max-character="200" id="subTitle"
                                               value="{{ $data?->value['sub_title'] ?? "" }}" name="sub_title"
                                               placeholder="{{ translate('Ex: Sub_Title') }}" required tabindex="2">
                                        <span>{{translate('0/200')}}</span>
                                    </div>

                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <h6 class="d-flex align-items-center gap-2 mb-3">
                                        <img width="22"
                                             src="{{ dynamicAsset('public/assets/admin-module/img/media/play-store.png') }}"
                                             alt="">
                                        {{ translate('Playstore_Button') }}
                                    </h6>
                                    <div class="rounded bg-light p-3 p-lg-4">
                                        <div class="mb-4">
                                            <div class="d-flex justify-content-between gap-2">
                                                <div class="d-flex align-items-center gap-2 mb-2">
                                                    <label for="playStoreUserDownloadLink"
                                                           class="mb-0">{{ translate('User Download Link') }}</label>
                                                    <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip"
                                                       title="{{translate('User Download Link')}}"></i>
                                                </div>
                                            </div>
                                            <input type="url" class="form-control" id="playStoreUserDownloadLink"
                                                   name="play_store_user_download_link"
                                                   value="{{ $data?->value['play_store']['user_download_link'] ?? "" }}"
                                                   placeholder="{{ translate('Ex: https://play.google.com/store/apps') }}"
                                                   required tabindex="3">
                                        </div>
                                        <div class="">
                                            <div class="d-flex align-items-center gap-2 mb-2">
                                                <label for="playStoreDriverDownloadLink"
                                                       class="mb-0">{{ translate('Driver Download Link') }}</label>
                                                <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip"
                                                   title="{{translate('Driver Download Link')}}"></i>
                                            </div>
                                            <input type="url" class="form-control" id="playStoreDriverDownloadLink"
                                                   name="play_store_driver_download_link"
                                                   value="{{ $data?->value['play_store']['driver_download_link'] ?? "" }}"
                                                   placeholder="{{ translate('Ex: https://play.google.com/store/apps') }}"
                                                   required tabindex="4">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <h6 class="d-flex align-items-center gap-2 mb-3">
                                        <img width="22"
                                             src="{{ dynamicAsset('public/assets/admin-module/img/media/app-store.png') }}"
                                             alt="">
                                        {{ translate('app_Store_Button') }}
                                    </h6>
                                    <div class="rounded bg-light p-3 p-lg-4">
                                        <div class="mb-4">
                                            <div class="d-flex justify-content-between gap-2">
                                                <div class="d-flex align-items-center gap-2 mb-2">
                                                    <label for="appStoreUserDownloadLink"
                                                           class="mb-0">{{ translate('User Download Link') }}</label>
                                                    <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip"
                                                       title="{{translate('User Download Link')}}"></i>
                                                </div>
                                            </div>
                                            <input type="url" class="form-control" id="appStoreUserDownloadLink"
                                                   name="app_store_user_download_link"
                                                   value="{{ $data?->value['app_store']['user_download_link'] ?? "" }}"
                                                   placeholder="{{ translate('Ex: https://play.google.com/store/apps') }}"
                                                   required tabindex="5">
                                        </div>
                                        <div class="">
                                            <div class="d-flex align-items-center gap-2 mb-2">
                                                <label for="appStoreDriverDownloadLink"
                                                       class="mb-0">{{ translate('Driver Download Link') }}</label>
                                                <i class="bi bi-info-circle-fill text-primary cursor-pointer" data-bs-toggle="tooltip"
                                                   title="{{translate('Driver Download Link')}}"></i>
                                            </div>
                                            <input type="url" class="form-control" id="appStoreDriverDownloadLink"
                                                   name="app_store_driver_download_link"
                                                   value="{{ $data?->value['app_store']['driver_download_link'] ?? "" }}"
                                                   placeholder="{{ translate('Ex: https://play.google.com/store/apps') }}"
                                                   required tabindex="6">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="d-flex justify-content-end gap-3">
                                    <button class="btn btn-secondary text-uppercase cmn_focus" tabindex="7"
                                            type="reset">{{ translate('reset') }}</button>
                                    <button class="btn btn-primary cmn_focus text-uppercase" tabindex="8"
                                            type="submit">{{ translate('save') }}</button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="card mt-3">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-30">
                        <h5 class="text-primary text-uppercase">{{ translate('Image_Section') }}</h5>
                    </div>

                    <form action="{{ route('admin.business.pages-media.landing-page.cta.update') }}" id="banner_form"
                          enctype="multipart/form-data" method="POST">
                        @csrf
                        @php($maxSize = readableUploadMaxFileSize('image'))
                        <input type="hidden" name="type" value="{{CTA_IMAGE}}">
                        <div class="row">
                            <div class="col-md-4 col-xl-3">
                                <div class="d-flex flex-column gap-3 mb-4">
                                    <div class="d-flex align-items-center gap-2">
                                        <h5 class="text-capitalize">{{ translate('image') }}</h5>
                                        <span class="badge badge-primary">{{ translate('1:1') }}</span>
                                    </div>

                                    <div class="d-flex">
                                        <div class="upload-file cmn_focus rounded-10">
                                            <input type="file" class="upload-file__input" name="image"
                                                   accept="{{ IMAGE_ACCEPTED_EXTENSIONS }}" tabindex="9" data-max-upload-size="{{ $maxSize }}">
                                            <span class="edit-btn">
                                                    <i class="bi bi-pencil-square text-primary"></i>
                                                </span>
                                            <div class="upload-file__img">
                                                <img
                                                        src="{{ $data1?->value['image'] ? dynamicStorage('storage/app/public/business/landing-pages/cta/'.$data1?->value['image']) : dynamicAsset('public/assets/admin-module/img/media/upload-file.png') }}"
                                                        alt="">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex flex-column gap-1">
                                        <p class="mb-0 title-color">{{ translate('Min Size for Better Resolution 408x408 px') }}</p>
                                        <p class="fs-12">
                                            {{ translate(key: 'File Format - {format}, Image Size - Maximum {imageSize}', replace: ['format' => IMAGE_ACCEPTED_EXTENSIONS, 'imageSize' => $maxSize]) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-8 col-xl-9">
                                <div class="d-flex flex-column gap-3 mb-4">
                                    <div class="d-flex align-items-center gap-2">
                                        <h5 class="text-capitalize">{{ translate('background_Image') }}</h5>
                                        <span class="badge badge-primary">{{ translate('3:1') }}</span>
                                    </div>

                                    <div class="d-flex">
                                        <div class="upload-file cmn_focus rounded">
                                            <input type="file" class="upload-file__input" name="background_image"
                                                   accept="{{ IMAGE_ACCEPTED_EXTENSIONS }}" tabindex="10" data-max-upload-size="{{ $maxSize }}">
                                            <span class="edit-btn">
                                                    <i class="bi bi-pencil-square text-primary"></i>
                                                </span>
                                            <div class="upload-file__img upload-file__img_banner">
                                                <img
                                                        src="{{ $data1?->value['background_image'] ? dynamicStorage('storage/app/public/business/landing-pages/cta/'.$data1?->value['background_image']) : dynamicAsset('public/assets/admin-module/img/media/upload-file.png') }}"
                                                        alt="">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="d-flex justify-content-end gap-3">
                                    <button class="btn btn-secondary text-uppercase cmn_focus" tabindex="11"
                                            type="reset">{{ translate('reset') }}</button>
                                    <button class="btn btn-primary cmn_focus text-uppercase" tabindex="12"
                                            type="submit">{{ translate('save') }}</button>
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


