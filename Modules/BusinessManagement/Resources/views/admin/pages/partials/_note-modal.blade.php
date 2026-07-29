<?php
$title = match ($page) {
    'intro_section' => translate("For Intro Section Title & Subtitle"),
    'business_statistics' => translate("For Business Statistics Total Download Count, Total Complete Ride Count, Total Happy Customer Count, Title & Subtitle "),
    'our_solutions' => translate('For Our Solutions Section Content Title, Subtitle & For Create & Edit Solution Title, Description'),
    'testimonial' => translate('For Testimonial Section Content Title & For Create & Edit Testimonial Title, Description'),
    'our_services' => translate('For Our Services Section Content Title, Subtitle & For Tab Section Tab Name, Title'),
    'gallery' => translate('For Gallery all Card Title, Subtitle'),
    'customer_app_download' => translate('For Customer App Download Section Content & Section Button Title, Subtitle'),
    'earn_money' => translate('For Driver App Download Section Content & Section Button Title, Subtitle'),
    'newsletter' => translate('For Newsletter Section Title, Subtitle'),
    'footer' => translate('For Footer Section Title'),
    default => translate('For Title & Subtitle')
};
?>


<div class="modal fade" id="noteModal">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 justify-content-end p-2">
                <button type="button" class="btn-close m-0" data-bs-toggle="modal">
                </button>
            </div>
            <div class="modal-body pb-5 pt-0">
                <div class="text-center mx-auto">
                    <img width="56" height="56" class="w-50px aspect-1"
                         src="{{ dynamicAsset('public/assets/admin-module/img/modal/note.png') }}"
                         alt="note">
                    <div class="mt-4">
                        <h4 class="fs-18 mb-3" id="title">{{$title}}</h4>
                        <p class="mb-5">
                            {{ translate('To highlight text with the primary color (as shown in the example), wrap the text with in this format') }}
                            <span class="fw-medium">** {{ translate('on both sides') }} **.</span>
                        </p>
                        <h4 class="fs-18 mb-3 text-muted" id="title">{{translate("View Example")}}</h4>
                        <div class="border border-DEE2E6 rounded p-3 fs-24 fw-bolder w-max-content mx-auto">
                            {{ translate('Easily') }} <span
                                class="text-primary">{{ translate('Share') }}</span> {{ translate('Your Ride') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
