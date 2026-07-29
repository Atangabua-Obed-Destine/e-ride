@extends('adminmodule::layouts.master')

@section('title', translate('Our Solutions'))

@push('css_or_js')
@endpush

@section('content')
    @php($env = env('APP_MODE') == 'demo' ? 'demo' : 'live')
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <h2 class="fs-20 fw-bold mb-3 text-capitalize">{{translate('landing_page_setup')}}</h2>
            <div class="mb-3">
                <div class="">
                    @include('businessmanagement::admin.pages.partials._landing_page_inline_menu')
                </div>
            </div>
            <div class="card card-body mb-3">
                <div class="d-flex flex-md-nowrap flex-wrap align-items-center justify-content-between gap-3">
                    <div class="w-0 flex-grow-1">
                        <h4 class="text-capitalize mb-1">{{ translate('Show Our Solutions') }}</h4>
                        <div class="fs-14">
                            {{ translate('If you turn off the availability status, this section will not be shown in the website') }}
                        </div>
                    </div>
                    <label
                        class="max-w300 w-100 form-control rounded d-flex align-items-center justify-content-between">
                        <label for="showOurSolutions"
                               class="fs-14 lh-1 d-block cursor-pointer text-dark">{{ translate('Status') }}</label>
                        <label class="switcher cmn_focus rounded-pill">
                            <input class="switcher_input update-business-setting" id="showOurSolutions"
                                   name="is_our_solutions_enabled" type="checkbox" data-name="is_our_solutions_enabled"
                                   data-type="{{ OUR_SOLUTIONS_SECTION }}" tabindex="4"
                                   data-url="{{ route('admin.business.pages-media.landing-page.update-landing-page-setting') }}"
                                   data-icon="{{$isOurSolutionsEnabled == 0 ? dynamicAsset('public/assets/admin-module/img/svg/info-circle-green.svg') : dynamicAsset('public/assets/admin-module/img/svg/info-circle-red.svg') }}"
                                   data-title="{{ $isOurSolutionsEnabled == 0  ? translate('Want to enable Our Solutions section') : translate('Want to disable Our Solutions section') }}?"
                                   data-sub-title="{{ $isOurSolutionsEnabled == 0 ? translate(' If you turn on the Our Solutions section, users will see it in the landing page.') : translate('If you turn off the Our Solutions section, users will no longer see it in the landing page.') }}"
                                {{ $isOurSolutionsEnabled == 1 ? 'checked' : '' }}
                            >
                            <span class="switcher_control"></span>
                        </label>
                    </label>
                </div>
            </div>

            <div class="card card-body mb-3">
                <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                    <div>
                        <h4 class="mb-1">
                            {{ translate('Our_Solutions_Section') }}
                        </h4>
                        <p class="fs-12 mb-0">{{ translate('See how your our solutions Section will look to customers.') }}</p>
                    </div>
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <button type="button" class="btn btn-outline-primary text-capitalize cmn_focus px-3" tabindex="1"
                                data-bs-toggle="modal" data-bs-target="#noteModal">
                            <i class="bi bi-journal-text"></i>
                            {{ translate('Note') }}
                        </button>
                        <button type="button" class="btn btn-outline-primary text-capitalize cmn_focus px-3"
                                tabindex="1" data-bs-toggle="offcanvas" data-bs-target="#sectionPriview-offcanvas">
                            <i class="bi bi-eye-fill"></i>
                            {{ translate('Section_Preview') }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="card card-body mb-3">
                <h5 class="mb-20">{{ translate('section_Content') }}</h5>
                <form action="{{ route('admin.business.pages-media.landing-page.our-solutions.update-intro') }}"
                      id="banner_form" enctype="multipart/form-data" method="POST">
                    @csrf
                    <input type="hidden" name="key_name" value="{{ INTRO_CONTENTS }}">
                    <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div>
                                    <label for="title" class="mb-2">
                                        {{ translate('title') }}
                                        <span class="text-danger">*</span>
                                    </label>
                                    <div class="character-count d-flex flex-column align-items-end">
                                        <input type="text" class="form-control character-count-field" maxlength="100"
                                               data-max-character="100" id="title" name="title"
                                               value="{{ $introContents?->value['title'] ?? '' }}"
                                               placeholder="{{ translate('Ex: Title') }}" required tabindex="1">
                                        <span>{{translate('0/100')}}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div>
                                    <label for="subTitle" class="mb-2">
                                        {{ translate('sub_Title') }}
                                        <span class="text-danger">*</span>
                                    </label>
                                    <div class="character-count d-flex flex-column align-items-end">
                                        <input type="text" class="form-control character-count-field" maxlength="200"
                                               data-max-character="200" id="subTitle" name="sub_title"
                                               value="{{ $introContents?->value['sub_title'] ?? '' }}"
                                               placeholder="{{ translate('Ex: Sub_Title') }}" required tabindex="2">
                                        <span>{{translate('0/200')}}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-3 mt-4">
                        <button class="btn btn-secondary cmn_focus min-w-120" tabindex="3" type="reset">
                            {{ translate('reset') }}
                        </button>
                        <button class="btn btn-primary cmn_focus min-w-120" tabindex="4" type="submit">
                            {{ translate('save') }}
                        </button>
                    </div>
                </form>
            </div>

            <div class="card card-body">
                <h5 class="mb-20">{{ translate('create_Solution') }}</h5>

                <form action="{{ route('admin.business.pages-media.landing-page.our-solutions.update') }}"
                      id="banner_form" enctype="multipart/form-data" method="POST">
                    @csrf
                    <input type="hidden" name="key_name" value="{{ SOLUTIONS }}">
                    <div class="row g-3">
                        <div class="col-lg-8">
                            <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                                <div class="mb-3">
                                    <label for="solution_title" class="mb-2">
                                        {{ translate('Title') }}
                                        <span class="text-danger">*</span>
                                    </label>
                                    <div class="character-count d-flex flex-column align-items-end">
                                        <input type="text" class="form-control character-count-field"
                                               maxlength="50" data-max-character="50" id="solution_title"
                                               name="title" tabindex="5"
                                               placeholder="{{ translate('ex') }}: {{ translate('Ride_Sharing') }}"
                                               required>
                                        <span>{{translate('0/50')}}</span>
                                    </div>
                                </div>
                                <div>
                                    <label for="solution_description" class="mb-2">
                                        {{ translate('Description') }}
                                        <span class="text-danger">*</span>
                                    </label>
                                    <div class="character-count d-flex flex-column align-items-end">
                                        <textarea name="description" id="solution_description" rows="2" tabindex="6"
                                                  class="form-control character-count-field" maxlength="100"
                                                  data-max-character="100"
                                                  placeholder="{{ translate('ex') }}: {{ translate('Section_Description') }}"
                                                  required></textarea>
                                        <span>{{translate('0/100')}}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php
                        $maxSize = readableUploadMaxFileSize('image');
                        ?>
                        <div class="col-lg-4">
                            <div
                                class="p-lg-4 p-3 rounded bg-F6F6F6 d-flex flex-column justify-content-center gap-3 h-100">
                                <div class="text-center">
                                    <h5 class="mb-1">
                                        {{ translate('Icon / Image') }} <span class="text-danger">*</span>
                                    </h5>
                                    <p class="fs-12 mb-0">{{ translate('Upload your Solutions Section Icon/Image') }}</p>
                                </div>

                                <div class="upload-file-new my-0">
                                    <input type="file" class="upload-file-new__input single_file_input" tabindex="4"
                                           name="image" accept="{{ IMAGE_ACCEPTED_EXTENSIONS }}"
                                           data-max-upload-size="{{ $maxSize }}" required>
                                    <label class="upload-file-new__wrapper ratio-1-1">
                                        <div class="upload-file-new-textbox text-center">
                                            <img width="34" height="34" class="svg"
                                                 src="{{ dynamicAsset('public/assets/admin-module/img/document-upload.svg') }}"
                                                 alt="image upload">
                                            <h6 class="mt-2 fw-medium text-center text-info mb-0">{{ translate('Add') }}</h6>
                                        </div>
                                        <img class="upload-file-new-img" loading="lazy"
                                             src=""
                                             data-default-src=""
                                             alt="">
                                    </label>
                                    <div class="overlay">
                                        <div class="d-flex gap-10 justify-content-center align-items-center h-100">
                                            <button type="button" class="btn btn-outline-info icon-btn edit_btn">
                                                <i class="bi bi-camera"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <p class="text-dark fs-10 text-center">
                                    <span
                                        class="opacity-75">{{ translate(key: '{format}, Image Size - Max {imageSize}', replace: ['format' => IMAGE_ACCEPTED_EXTENSIONS, 'imageSize' => $maxSize]) }}</span>
                                    <span>(1:1)</span>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-3 mt-4">
                        <button class="btn btn-secondary cmn_focus min-w-120" tabindex="3" type="reset">
                            {{ translate('reset') }}
                        </button>
                        <button class="btn btn-primary cmn_focus min-w-120" tabindex="4" type="submit">
                            {{ translate('save') }}
                        </button>
                    </div>
                </form>

                <div class="mt-5">
                    <h5 class="mb-20">{{ translate('Solutions_List') }}</h5>
                    <div class="table-responsive">
                        <table class="table table-borderless align-middle table-hover col-mx-w300">
                            <thead class="table-light align-middle text-capitalize">
                            <tr>
                                <th>{{ translate('SL') }}</th>
                                <th>{{ translate('Image') }}</th>
                                <th>{{ translate('title') }}</th>
                                <th>{{ translate('sub_Title') }}</th>
                                <th class="text-center">{{ translate('Status') }}</th>
                                <th class="text-center">{{ translate('Action') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($solutions as $key => $singleSolution)
                                <tr>
                                    <td>{{$key + $solutions->firstItem()}}</td>
                                    <td>
                                        <div
                                            class="aspect-1 d-flex align-items-center overflow-hidden rounded w-50px">
                                            <img class="h-100 fit-object"
                                                 src="{{ $singleSolution?->value['image'] ? dynamicStorage('storage/app/public/business/landing-pages/our-solutions/'.$singleSolution?->value['image']) : dynamicAsset('public/assets/admin-module/img/media/banner-upload-file.png') }}"
                                                 alt="">
                                        </div>

                                    </td>
                                    <td>
                                        <div class="min-w-150">
                                            {{ $singleSolution?->value['title'] ?? "" }}
                                        </div>
                                    </td>
                                    <td>
                                        <div class="min-w-150">
                                            {{ $singleSolution?->value['description'] ?? "" }}
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <label class="switcher">
                                            <input class="switcher_input custom_status_change"
                                                   type="checkbox"
                                                   name="status"
                                                   id="{{ $singleSolution->id }}"
                                                   data-url="{{ route('admin.business.pages-media.landing-page.our-solutions.status') }}"
                                                   data-title="{{$singleSolution->value['status'] == 1 ? translate('Are you sure to turn off this solution') : translate('Are you sure to turn on this solution') }} ?"
                                                   data-sub-title="{{$singleSolution->value['status'] == 1 ? translate('Once you turn off this solution') . ', ' . translate(', the landing page will no longer have this solution.'): translate('Once you turn On this solution') . ', ' . translate(', the landing page will have this solution.')}}"
                                                   data-confirm-btn="{{$singleSolution->value['status'] == 1  ? translate('Turn Off') : translate('Turn On')}}"
                                                {{ $singleSolution->value['status'] == 1 ? "checked": ""  }}
                                            >
                                            <span class="switcher_control"></span>
                                        </label>
                                    </td>
                                    <td>
                                        <div class="d-flex justify-content-center gap-2 align-items-center">
                                            <a href="{{ route('admin.business.pages-media.landing-page.our-solutions.edit',$singleSolution?->id) }}"
                                               class="btn btn-outline-primary btn-action" title="Edit coupon">
                                                <i class="bi bi-pencil-fill"></i>
                                            </a>
                                            <a
                                                data-url="{{ route('admin.business.pages-media.landing-page.our-solutions.delete', ['id' => $singleSolution?->id]) }}"
                                                data-icon="{{ dynamicAsset('public/assets/admin-module/img/trash.png') }}"
                                                data-title="{{ translate('Are you sure to delete this Solution')."?" }}"
                                                data-sub-title="{{ translate('Once you delete it') . ', ' . translate('This will be permanently removed from the list.') }}"
                                                data-confirm-btn="{{translate("Yes, Delete")}}"
                                                data-cancel-btn="{{translate("Not Now")}}"
                                                class="btn btn-outline-danger btn-action d-flex justify-content-center align-items-center delete-button"
                                                data-bs-toggle="tooltip" title="{{translate("Delete")}}">
                                                <i class="bi bi-trash-fill"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">
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
                </div>
            </div>

            <div class="d-flex justify-content-end mt-3">
                {{ $solutions->links() }}
            </div>
        </div>
    </div>
    <!-- End Main Content -->

    {{-- Section Preview Offcanvas --}}
    <div class="offcanvas offcanvas-end" id="sectionPriview-offcanvas" style="--bs-offcanvas-width: 755px;">
        <form action="javascript:" class="d-flex flex-column h-100">
            <div class="offcanvas-header">
                <h4 class="offcanvas-title flex-grow-1">
                    {{ translate('Our Solution Section Preview') }}
                </h4>
                <button type="button" class="btn btn-circle btn-secondary text-white" data-bs-dismiss="offcanvas" aria-label="Close" style="--size: 20px;">
                    <i class="bi bi-x-lg d-flex"></i>
                </button>
            </div>
            <div class="offcanvas-body scrollbar-thin">
                <div class="h-100 overflow-y-auto py-5 px-0">
                    <img src="{{ dynamicAsset('public/assets/admin-module/img/preview/our-solution.png') }}" alt=""
                         class="img-fluid w-100 d-block">
                </div>
            </div>
        </form>
    </div>

    @include('businessmanagement::admin.pages.partials._note-modal',['page' => 'our_solutions'])
@endsection

@push('script')
    <script src="{{ dynamicAsset('public/assets/admin-module/js/single-image-upload-new.js') }}"></script>
    <script>
        "use strict";
        let permission = false;
        @can('business_edit')
            permission = true;
        @endcan
    </script>
@endpush
