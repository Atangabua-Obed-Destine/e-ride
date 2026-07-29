<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Modules\BusinessManagement\Service\Interfaces\BusinessSettingServiceInterface;

class LandingPageControllerOld extends Controller
{
    use AuthorizesRequests;

    protected $businessSetting;
    public function __construct(BusinessSettingServiceInterface $businessSetting)
    {
        $this->businessSetting = $businessSetting;
    }

    public function index(): Factory|View|Application
    {
        $introSection = $this->businessSetting->findOneBy(criteria: ['key_name' => INTRO_SECTION, 'settings_type' => LANDING_PAGES_SETTINGS]);
        $introSectionImage = $this->businessSetting->findOneBy(criteria: ['key_name' => INTRO_SECTION_IMAGE, 'settings_type' => LANDING_PAGES_SETTINGS]);
        $ourSolutionSection = $this->businessSetting->findOneBy(criteria: ['key_name' => OUR_SOLUTIONS_SECTION, 'settings_type' => LANDING_PAGES_SETTINGS]);
        $ourSolutionSectionList = $this->businessSetting->getBy(criteria: ['key_name' => OUR_SOLUTIONS_DATA, 'settings_type' => LANDING_PAGES_SETTINGS]);
        $businessStatistics = $this->businessSetting->findOneBy(criteria: ['key_name' => BUSINESS_STATISTICS, 'settings_type' => LANDING_PAGES_SETTINGS]);
        $earnMoney = $this->businessSetting->findOneBy(criteria: ['key_name' => EARN_MONEY, 'settings_type' => LANDING_PAGES_SETTINGS]);
        $earnMoneyImage = $this->businessSetting->findOneBy(criteria: ['key_name' => EARN_MONEY_IMAGE, 'settings_type' => LANDING_PAGES_SETTINGS]);
        $testimonials = $this->businessSetting->getBy(criteria: ['key_name' => TESTIMONIAL, 'settings_type' => LANDING_PAGES_SETTINGS], limit: paginationLimit(), offset: 1);
        $cta = $this->businessSetting->findOneBy(criteria: ['key_name' => CTA, 'settings_type' => LANDING_PAGES_SETTINGS]);
        $ctaImage = $this->businessSetting->findOneBy(criteria: ['key_name' => CTA_IMAGE, 'settings_type' => LANDING_PAGES_SETTINGS]);
        $business_name = $this->businessSetting->findOneBy(criteria: ['key_name' => 'business_name', 'settings_type' => BUSINESS_INFORMATION]);

        $ourSolutionSectionListCount = 0;
        foreach ($ourSolutionSectionList as $ourSolutionSingle) {
            if ($ourSolutionSingle?->value['status'] == 1) {
                $ourSolutionSectionListCount++;
            }
        }
        $testimonialListCount = 0;
        foreach ($testimonials as $testimonialSingle) {
            if ($testimonialSingle?->value['status'] == 1) {
                $testimonialListCount++;
            }
        }

        return view('landing-page.index',
            compact('introSection', 'introSectionImage', 'ourSolutionSection', 'ourSolutionSectionListCount' , 'ourSolutionSectionList', 'business_name', 'businessStatistics', 'earnMoney', 'earnMoneyImage', 'testimonials', 'testimonialListCount', 'cta', 'ctaImage'));
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
        return view('landing-page.blog.blog-details', compact('data'));
    }
}
