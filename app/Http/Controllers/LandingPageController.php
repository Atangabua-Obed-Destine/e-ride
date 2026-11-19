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

    /** Dark, light, or the visitor's phone setting: Landing Page Setup > Home page look. */
    public const THEMES = ['dark', 'light', 'auto'];

    public static function theme(): string
    {
        $theme = businessConfig(key: 'landing_theme', settingsType: 'landing_page_look')?->value;
        return in_array($theme, self::THEMES, true) ? $theme : 'dark';
    }

    /**
     * The home page: fixed words in English and French (App\Lib\LandingWords), the look the admin chose,
     * and what comes from the settings: contact details, the apps' Play Store pages, the bus website.
     */
    public function index(\Illuminate\Http\Request $request): \Illuminate\Http\Response
    {
        $lang = $request->query('lang');
        if (in_array($lang, \App\Lib\LandingWords::LANGS, true)) {
            // The bus website follows the same choice.
            $request->session()->put('site_lang', $lang);
            $request->session()->put('bus_lang', $lang);
        } else {
            $lang = $request->session()->get('site_lang') ?? $request->session()->get('bus_lang');
        }
        if (!in_array($lang, \App\Lib\LandingWords::LANGS, true)) {
            $lang = str_starts_with(strtolower((string)$request->getPreferredLanguage(['en', 'fr'])), 'fr') ? 'fr' : 'en';
        }
        $t = \App\Lib\LandingWords::for($lang);

        // ?theme= shows another look (to preview it from the admin); otherwise the admin's choice.
        $theme = in_array($request->query('theme'), self::THEMES, true) ? $request->query('theme') : self::theme();

        $storeUrl = function (string $key, string $package): string {
            $url = trim((string)(businessConfig(key: $key, settingsType: APP_VERSION)?->value['app_url'] ?? ''));
            return filter_var($url, FILTER_VALIDATE_URL) ? $url : 'https://play.google.com/store/apps/details?id=' . $package;
        };
        $playUrl = $storeUrl(CUSTOMER_APP_VERSION_CONTROL_FOR_ANDROID, config('marketmanagement.android_package', 'com.i_nnova.eride'));
        $driverUrl = $storeUrl(DRIVER_APP_VERSION_CONTROL_FOR_ANDROID, 'com.i_nnova.eridedriver');

        $contact = [
            'phone' => businessConfig('business_contact_phone', BUSINESS_INFORMATION)?->value,
            'email' => businessConfig('business_contact_email', BUSINESS_INFORMATION)?->value,
            'address' => businessConfig('business_address', BUSINESS_INFORMATION)?->value,
        ];
        // Partners write to the team on WhatsApp, with what they want already typed; email when there is no phone.
        $waNumber = preg_replace('/\D+/', '', (string)$contact['phone']);
        $partnerLink = fn(string $message) => $waNumber !== ''
            ? 'https://wa.me/' . $waNumber . '?text=' . rawurlencode($message)
            : ($contact['email'] ? 'mailto:' . $contact['email'] . '?subject=' . rawurlencode($message) : null);

        $busWeb = \Modules\BusManagement\Lib\BusSettings::enabled() && \Modules\BusManagement\Lib\BusSettings::web()['booking'] ? route('bus.web.home') : null;
        $qr = (string)\SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(144)->margin(0)->errorCorrection('M')->generate($playUrl);
        $favicon = getSession('favicon');
        $favicon = $favicon ? dynamicStorage(path: 'storage/app/public/business/' . $favicon) : dynamicAsset(path: 'public/landing-page/assets/img/favicon.png');

        return response()->view('landing-page.home', compact('t', 'theme', 'playUrl', 'driverUrl', 'contact', 'partnerLink', 'busWeb', 'qr', 'favicon'));
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
