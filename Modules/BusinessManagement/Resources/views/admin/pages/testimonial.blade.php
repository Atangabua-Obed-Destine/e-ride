@extends('adminmodule::layouts.master')

@section('title', translate('Testimonial'))

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
                        <h4 class="text-capitalize mb-1">{{ translate('Show Testimonial') }}</h4>
                        <div class="fs-14">
                            {{ translate('Allow this option to display the Testimonial Section on the landing page.') }}
                        </div>
                    </div>
                    <label
                        class="max-w300 w-100 form-control rounded d-flex align-items-center justify-content-between">
                        <label for="showTestimonial"
                               class="fs-14 lh-1 d-block cursor-pointer text-dark">{{ translate('Status') }}</label>
                        <label class="switcher cmn_focus rounded-pill">
                            <input class="switcher_input update-business-setting" id="showTestimonial"
                                   name="is_testimonial_enabled" type="checkbox" data-name="is_testimonial_enabled"
                                   data-type="{{ TESTIMONIAL }}" tabindex="4"
                                   data-url="{{ route('admin.business.pages-media.landing-page.update-landing-page-setting') }}"
                                   data-icon="{{ $isTestimonialEnabled == 0 ? dynamicAsset('public/assets/admin-module/img/svg/info-circle-green.svg') : dynamicAsset('public/assets/admin-module/img/svg/info-circle-red.svg') }}"
                                   data-title="{{ $isTestimonialEnabled == 0 ? translate('Want to enable Testimonial') : translate('Want to disable Testimonial') }}?"
                                   data-sub-title="{{ $isTestimonialEnabled == 0 ? translate(' If you turn on the Testimonial, users will see it in the landing page.') : translate('If you turn off the Testimonial, users will no longer see it in the landing page.') }}"
                                {{ $isTestimonialEnabled == 1 ? 'checked' : '' }}
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
                            {{ translate('Testimonial Section') }}
                        </h4>
                        <p class="fs-12 mb-0">{{ translate('See how your our testimonial section will look to customers.') }}</p>
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
                <h5 class="mb-1">{{ translate('section_Content') }}</h5>
                <p class="fs-12 mb-20">{{ translate('Configure the section content by setting the title, and adding testimonial of reviews.') }}</p>
                <form action="{{route('admin.business.pages-media.landing-page.testimonial.update-intro')}}"
                      id="banner_form" enctype="multipart/form-data" method="POST">
                    @csrf
                    <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                        <div>
                            <label for="title" class="mb-2">
                                {{ translate('title') }} <span class="text-danger">*</span>
                            </label>
                            <div class="character-count d-flex flex-column align-items-end">
                                <input type="text" class="form-control character-count-field" maxlength="100"
                                       data-max-character="100" id="" name="title"
                                       value="{{ $introContents?->value['title'] }}"
                                       placeholder="{{ translate('Ex: Title') }}" required tabindex="2">
                                <span>{{translate('0/100')}}</span>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-3 mt-4">
                        <button class="btn btn-secondary cmn_focus min-w-120" tabindex="8" type="reset">
                            {{ translate('reset') }}
                        </button>
                        <button class="btn btn-primary cmn_focus min-w-120" tabindex="9" type="submit">
                            {{ translate('save') }}
                        </button>
                    </div>
                </form>
            </div>

            <div class="card card-body">
                <h5 class="mb-20">{{ translate('Add_Testimonial') }}</h5>

                <form action="{{ route('admin.business.pages-media.landing-page.testimonial.update') }}"
                      id="banner_form" enctype="multipart/form-data" method="POST">
                    @csrf
                    @php($maxSize = readableUploadMaxFileSize('image'))
                    <div class="row g-3">
                        <div class="col-lg-9">
                            <div class="p-lg-4 p-3 rounded bg-F6F6F6">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <div>
                                            <label for="reviewer_name"
                                                   class="mb-2">{{ translate('Reviewer_Name') }} <span
                                                    class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="reviewer_name"
                                                   name="reviewer_name" placeholder="{{ translate('Ex: Ahmed') }}"
                                                   required tabindex="3">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div>
                                            <label for="designation" class="mb-2">{{ translate('Designation') }}
                                                <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="designation" name="designation"
                                                   placeholder="{{ translate('Ex: Engineer') }}" required tabindex="4">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div>
                                            <label for="rating" class="mb-2">{{ translate('Rating') }} <span
                                                    class="text-danger">*</span></label>
                                            <select name="rating" id="rating" class="form-select bg-white" required
                                                    tabindex="5">
                                                <option value="1">1</option>
                                                <option value="2">2</option>
                                                <option value="3">3</option>
                                                <option value="4">4</option>
                                                <option value="5">5</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div>
                                            <label for="review" class="mb-2">{{ translate('Review') }} <span
                                                    class="text-danger">*</span></label>
                                            <div class="character-count d-flex flex-column align-items-end">
                                                <textarea name="review" id="review" rows="3"
                                                          class="form-control character-count-field" maxlength="200"
                                                          data-max-character="200"
                                                          placeholder="{{ translate('Ex: review ...') }}"
                                                          required tabindex="6"></textarea>
                                                <span>{{translate('0/200')}}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php
                        $maxSize = readableUploadMaxFileSize('image');
                        ?>
                        <div class="col-lg-3">
                            <div
                                class="p-lg-4 p-3 rounded bg-F6F6F6 d-flex flex-column justify-content-around gap-3 h-100">
                                <div class="text-center">
                                    <h5 class="mb-1">
                                        {{ translate('Reviewer Image') }} <span class="text-danger">*</span>
                                    </h5>
                                    <p class="fs-12 mb-0">{{ translate('Upload Reviewer Image') }}</p>
                                </div>

                                <div class="upload-file-new">
                                    <input type="file" class="upload-file-new__input single_file_input" tabindex="7"
                                           name="reviewer_image" accept="{{ IMAGE_ACCEPTED_EXTENSIONS }}"
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
                        <button class="btn btn-secondary cmn_focus min-w-120" tabindex="8" type="reset">
                            {{ translate('reset') }}
                        </button>
                        <button class="btn btn-primary cmn_focus min-w-120" tabindex="9" type="submit">
                            {{ translate('save') }}
                        </button>
                    </div>
                </form>

                <div class="mt-5">
                    <h5 class="mb-20">{{ translate('Testimonial_List') }}</h5>
                    <div class="table-responsive">
                        <table class="table table-borderless align-middle table-hover col-mx-w300 fs-12 text-dark">
                            <thead class="table-light align-middle text-capitalize">
                            <tr>
                                <th>{{ translate('SL') }}</th>
                                <th>{{ translate('Reviewer_Info') }}</th>
                                <th>{{ translate('Rating') }}</th>
                                <th>{{ translate('Review') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th class="text-center">{{ translate('Action') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($testimonials as $key => $testimonial)
                                <tr>
                                    <td>{{$key + $testimonials->firstItem()}}</td>
                                    <td>
                                        <div class="d-flex justify-content-start align-items-center gap-2">
                                            <div
                                                class="aspect-1 rounded w-50px flex-shrink-0">
                                                <img class="h-100 fit-object rounded"
                                                     src="{{ $testimonial?->value['reviewer_image'] ? dynamicStorage('storage/app/public/business/landing-pages/testimonial/'.$testimonial?->value['reviewer_image']) : dynamicAsset('public/landing-page/assets/img/client/user.png') }}"
                                                     alt="">
                                            </div>
                                            <div>
                                                <div
                                                    class="fw-semibold mb-1">{{ $testimonial?->value['reviewer_name'] ?? "" }}</div>
                                                <div>{{ $testimonial?->value['designation'] ?? "" }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1 align-items-center"><i
                                                class="bi bi-star-fill text-warning"></i> {{ $testimonial?->value['rating'] ?? "" }}
                                        </div>
                                    </td>
                                    <td>
                                        <div
                                            class="truncate-line2 min-w-170">{{ $testimonial?->value['review'] ?? "" }}</div>
                                    </td>
                                    <td>
                                        <label class="switcher">
                                            <input class="switcher_input custom_status_change"
                                                   type="checkbox"
                                                   name="status"
                                                   id="{{ $testimonial->id }}"
                                                   data-url="{{ route('admin.business.pages-media.landing-page.testimonial.status') }}"
                                                   data-title="{{$testimonial->value['status'] == 1 ? translate('Are you sure to turn off this testimonial') : translate('Are you sure to turn on this testimonial') }} ?"
                                                   data-sub-title="{{$testimonial->value['status'] == 1 ? translate('Once you turn off this testimonial') . ', ' . translate(', the landing page will no longer have this testimonial.'): translate('Once you turn On this testimonial') . ', ' . translate(', the landing page will have this testimonial.')}}"
                                                   data-confirm-btn="{{$testimonial->value['status'] == 1  ? translate('Turn Off') : translate('Turn On')}}"
                                                {{ $testimonial->value['status'] == 1 ? "checked": ""  }}
                                            >
                                            <span class="switcher_control"></span>
                                        </label>
                                    </td>
                                    <td>
                                        <div class="d-flex justify-content-center gap-2 align-items-center">
                                            <a href="{{ route('admin.business.pages-media.landing-page.testimonial.edit',$testimonial->id) }}"
                                               class="btn btn-outline-primary btn-action" title="Edit coupon">
                                                <i class="bi bi-pencil-fill"></i>
                                            </a>
                                            <a
                                                data-url="{{ route('admin.business.pages-media.landing-page.testimonial.delete', ['id' => $testimonial->id]) }}"
                                                data-icon="{{ dynamicAsset('public/assets/admin-module/img/trash.png') }}"
                                                data-title="{{ translate('Are you sure to delete this Testimonial')."?" }}"
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
                                    <td colspan="6"
                                        class="text-center">{{ translate('no_data_available') }}</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end mt-3">
                {{ $testimonials->links() }}
            </div>

        </div>
    </div>
    <!-- End Main Content -->

    {{-- Section Preview Offcanvas --}}
    <div class="offcanvas offcanvas-end" id="sectionPriview-offcanvas" style="--bs-offcanvas-width: 755px;">
        <form action="javascript:" class="d-flex flex-column h-100">
            <div class="offcanvas-header">
                <h4 class="offcanvas-title flex-grow-1">
                    {{ translate('Testimonial  Section Preview') }}
                </h4>
                <button type="button" class="btn btn-circle btn-secondary text-white" data-bs-dismiss="offcanvas" aria-label="Close" style="--size: 20px;">
                    <i class="bi bi-x-lg d-flex"></i>
                </button>
            </div>
            <div class="offcanvas-body scrollbar-thin">
                <div class="h-100 overflow-y-auto py-5 px-0">
                    <img src="{{ dynamicAsset('public/assets/admin-module/img/preview/testimonial.png') }}" alt=""
                         class="img-fluid w-100 d-block">
                </div>
            </div>
        </form>
    </div>

    @include('businessmanagement::admin.pages.partials._note-modal',['page' => 'testimonial'])
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
