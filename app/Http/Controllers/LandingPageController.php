<?php

namespace App\Http\Controllers;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Modules\BusinessManagement\Http\Requests\NewsletterSubscriptionStoreOrUpdateRequest;
use Modules\BusinessManagement\Service\Interfaces\BusinessSettingServiceInterface;
use Modules\BusinessManagement\Service\Interfaces\LandingPageSectionServiceInterface;
use Modules\UserManagement\Service\Interfaces\NewsletterSubscriptionServiceInterface;

class LandingPageController extends Controller
{
    use AuthorizesRequests;

    protected $businessSetting;
    protected $landingPageSectionService;
    protected $newsletterSubscriptionService;

    public function __construct(BusinessSettingServiceInterface $businessSetting, LandingPageSectionServiceInterface $landingPageSectionService, NewsletterSubscriptionServiceInterface $newsletterSubscriptionService)
    {
        $this->businessSetting = $businessSetting;
        $this->landingPageSectionService = $landingPageSectionService;
        $this->newsletterSubscriptionService = $newsletterSubscriptionService;
    }

    public function index(): Factory|View|Application
    {
        $businessName = businessConfig(key: 'business_name', settingsType: BUSINESS_INFORMATION)?->value ?? null;

        //APP LINK
        $driverAppVersionControlForAndroid = businessConfig(key: DRIVER_APP_VERSION_CONTROL_FOR_ANDROID, settingsType: APP_VERSION)?->value ?? null;
        $driverAppVersionControlForIos = businessConfig(key: DRIVER_APP_VERSION_CONTROL_FOR_IOS, settingsType: APP_VERSION)?->value ?? null;
        $customerAppVersionControlForAndroid = businessConfig(key: CUSTOMER_APP_VERSION_CONTROL_FOR_ANDROID, settingsType: APP_VERSION)?->value ?? null;
        $customerAppVersionControlForIos = businessConfig(key: CUSTOMER_APP_VERSION_CONTROL_FOR_IOS, settingsType: APP_VERSION)?->value ?? null;

        $introSection = $this->landingPageSectionService->findOneBy(criteria: ['key_name' => INTRO_CONTENTS, 'settings_type' => INTRO_SECTION])?->value ?? null;

        //BUSINESS STATISTICS
        $isBusinessStatisticsEnabled = $this->landingPageSectionService->findOneBy(criteria: ['key_name' => 'is_business_statistics_enabled', 'settings_type' => BUSINESS_STATISTICS])?->value == 1;
        $businessStatistics = $this->landingPageSectionService->getBy(criteria: ['settings_type' => BUSINESS_STATISTICS, ['key_name', '!=', 'is_business_statistics_enabled']]);
        $activeBusinessStatistics = collect($businessStatistics)->filter(
            fn($item) => !empty($item?->value) && ($item?->value['status'] ?? false)
        );
        $showBusinessStatisticsSection =
            $isBusinessStatisticsEnabled && $activeBusinessStatistics->isNotEmpty();

        //OUR SOLUTIONS
        $isOurSolutionsEnabled = $this->landingPageSectionService->findOneBy(criteria: ['key_name' => 'is_our_solutions_enabled', 'settings_type' => OUR_SOLUTIONS_SECTION])?->value == 1;
        $ourSolutionsSectionContent = $this->landingPageSectionService->findOneBy(criteria: ['key_name' => INTRO_CONTENTS, 'settings_type' => OUR_SOLUTIONS_SECTION])?->value ?? null;
        $ourSolutions = $this->landingPageSectionService->getBy(criteria: ['key_name' => 'solutions', 'settings_type' => OUR_SOLUTIONS_SECTION]);
        $activeOurSolutions = collect($ourSolutions)->filter(
            fn($item) => !empty($item?->value) && ($item?->value['status'] ?? false)
        );
        $showOurSolutionsSection =
            $isOurSolutionsEnabled && $activeOurSolutions->isNotEmpty();


        //OUR SERVICES
        $isOurServicesEnabled = $this->landingPageSectionService->findOneBy(criteria: ['key_name' => 'is_our_services_enabled', 'settings_type' => OUR_SERVICES])?->value == 1;
        $ourServicesSectionContent = $this->landingPageSectionService->findOneBy(criteria: ['key_name' => INTRO_CONTENTS, 'settings_type' => OUR_SERVICES])?->value ?? null;
        $ourServices = $this->landingPageSectionService->getBy(criteria: ['settings_type' => OUR_SERVICES, ['key_name', '!=', 'is_our_services_enabled'], ['key_name', '!=', 'intro_contents']]);
        $activeOurServices = collect($ourServices)->filter(
            fn($item) => !empty($item?->value) && ($item?->value['status'] ?? false)
        );
        $showOurServicesSection =
            $isOurServicesEnabled && $activeOurServices->isNotEmpty();

        //GALLERY
        $isGalleryEnabled = $this->landingPageSectionService->findOneBy(criteria: ['key_name' => 'is_gallery_enabled', 'settings_type' => GALLERY])?->value == 1;
        $cardOneGallery = $this->landingPageSectionService->findOneBy(criteria: ['key_name' => 'card_1', 'settings_type' => GALLERY])?->value;
        $cardTwoGallery = $this->landingPageSectionService->findOneBy(criteria: ['key_name' => 'card_2', 'settings_type' => GALLERY])?->value;

        //CUSTOMER APP DOWNLOAD
        $isCustomerAppDownloadEnabled = $this->landingPageSectionService->findOneBy(criteria: ['key_name' => 'is_customer_app_download_enabled', 'settings_type' => CUSTOMER_APP_DOWNLOAD])?->value == 1;
        $customerAppDownloadSectionContent = $this->landingPageSectionService->findOneBy(criteria: ['key_name' => INTRO_CONTENTS, 'settings_type' => CUSTOMER_APP_DOWNLOAD])?->value ?? null;
        $customerAppDownloadButtonContent = $this->landingPageSectionService->findOneBy(criteria: ['key_name' => BUTTON_CONTENTS, 'settings_type' => CUSTOMER_APP_DOWNLOAD])?->value ?? null;

        //EARN MONEY
        $isEarnMoneyEnabled = $this->landingPageSectionService->findOneBy(criteria: ['key_name' => 'is_earn_money_enabled', 'settings_type' => EARN_MONEY])?->value == 1;
        $earnMoneySectionContent = $this->landingPageSectionService->findOneBy(criteria: ['key_name' => INTRO_CONTENTS, 'settings_type' => EARN_MONEY])?->value ?? null;
        $earnMoneyButtonContent = $this->landingPageSectionService->findOneBy(criteria: ['key_name' => BUTTON_CONTENTS, 'settings_type' => EARN_MONEY])?->value ?? null;

        //TESTIMONIAL
        $isTestimonialEnabled = $this->landingPageSectionService->findOneBy(criteria: ['key_name' => 'is_testimonial_enabled', 'settings_type' => TESTIMONIAL])?->value == 1;
        $testimonialSectionContent = $this->landingPageSectionService->findOneBy(criteria: ['key_name' => INTRO_CONTENTS, 'settings_type' => TESTIMONIAL])?->value ?? null;
        $testimonials = $this->landingPageSectionService->getBy(criteria: ['settings_type' => TESTIMONIAL, ['key_name', '!=', 'is_testimonial_enabled'], ['key_name', '!=', 'intro_contents']]);
        $activeTestimonials = collect($testimonials)->filter(
            fn($item) => !empty($item?->value) && ($item?->value['status'] ?? false)
        );
        $showTestimonialSection =
            $isTestimonialEnabled && $activeTestimonials->isNotEmpty();
        return view('landing-page.index',
            compact('businessName', 'introSection',
                'showBusinessStatisticsSection', 'businessStatistics',
                'ourSolutionsSectionContent', 'showOurSolutionsSection', 'activeOurSolutions',
                'ourServicesSectionContent', 'showOurServicesSection', 'activeOurServices',
                'isGalleryEnabled', 'cardOneGallery', 'cardTwoGallery',
                'isCustomerAppDownloadEnabled', 'customerAppDownloadSectionContent', 'customerAppDownloadButtonContent',
                'isEarnMoneyEnabled', 'earnMoneySectionContent', 'earnMoneyButtonContent',
                'showTestimonialSection', 'testimonialSectionContent', 'activeTestimonials',
                'driverAppVersionControlForAndroid', 'driverAppVersionControlForIos', 'customerAppVersionControlForAndroid', 'customerAppVersionControlForIos'));
    }

    public function aboutUs()
    {

        $data = $this->businessSetting->findOneBy(criteria: ['key_name' => 'about_us', 'settings_type' => PAGES_SETTINGS]);
        //    NewMessage::dispatch("test");
        return view('landing-page.about', compact('data'));
    }

    public function contactUs()
    {
        return view('landing-page.contact');
    }

    public function privacy()
    {
        $data = $this->businessSetting->findOneBy(criteria: ['key_name' => 'privacy_policy', 'settings_type' => PAGES_SETTINGS]);
        return view('landing-page.privacy', compact('data'));
    }

    public function terms()
    {
        $data = $this->businessSetting->findOneBy(criteria: ['key_name' => 'terms_and_conditions', 'settings_type' => PAGES_SETTINGS]);
        return view('landing-page.terms', compact('data'));
    }

    public function blogList()
    {
        $data = $this->businessSetting->findOneBy(criteria: ['key_name' => 'blog', 'settings_type' => PAGES_SETTINGS]);
        return view('landing-page.blog.blog-list', compact('data'));
    }

    public function blogDetails()
    {
        $data = $this->businessSetting->findOneBy(criteria: ['key_name' => 'blog', 'settings_type' => PAGES_SETTINGS]);
        return view('blog.blog-details', compact('data'));
    }

    public function storeNewsletterSubscription(NewsletterSubscriptionStoreOrUpdateRequest $request)
    {
        $this->newsletterSubscriptionService->create(data: $request->validated());

        Toastr::success('Subscription successful');

        return redirect()->back();
    }
}
