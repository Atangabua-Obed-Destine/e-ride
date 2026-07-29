<?php

namespace Modules\BusinessManagement\Http\Controllers\Web\Admin\Configuration;

use App\Http\Controllers\BaseController;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Modules\BusinessManagement\Http\Requests\LoginSettingsStoreOrUpdateRequest;
use Modules\BusinessManagement\Http\Requests\OtpAndLoginAttemptsStoreOrUpdateRequest;
use Modules\BusinessManagement\Service\Interfaces\BusinessSettingServiceInterface;
use Modules\BusinessManagement\Service\Interfaces\SettingServiceInterface;

class LoginSettingsController extends BaseController
{
    protected $businessSettingService;
    protected $settingService;

    public function __construct(BusinessSettingServiceInterface $businessSettingService, SettingServiceInterface $settingService)
    {
        parent::__construct($businessSettingService);
        $this->businessSettingService = $businessSettingService;
        $this->settingService = $settingService;
    }


    public function index(?Request $request, ?string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
       $isOtpEnabled = isOtpEnabled();
        $isDriverVerificationEnabled = businessConfig(key: 'driver_verification', settingsType: BUSINESS_INFORMATION)?->value;
        $isCustomerVerificationEnabled = businessConfig(key: 'customer_verification', settingsType: BUSINESS_INFORMATION)?->value;

        return view('businessmanagement::admin.configuration.login', compact('isOtpEnabled', 'isDriverVerificationEnabled', 'isCustomerVerificationEnabled'));
    }

    public function store(LoginSettingsStoreOrUpdateRequest $request) {
       $this->businessSettingService->updateLoginSettingsData(data: $request->validated());

        $activeTab = $request->input('active_tab') === 'driver' ? 'driver' : 'customer';
        $redirectUrl = route('admin.business.configuration.login-settings.index', ['tab' => $activeTab]);

        return response()->json(['success' => true, 'successMessage' => 'Information Updated Successfully!', 'redirectUrl' => $redirectUrl]);
    }

    public function otpLoginAttemptsIndex() {
        $settings = $this->businessSettingService
            ->getBy(criteria: ['settings_type' => 'business_settings']);

        return view('businessmanagement::admin.configuration.otp-login-attempts', compact('settings'));
    }

    public function otpLoginAttemptsStore(OtpAndLoginAttemptsStoreOrUpdateRequest $request)
    {
        $this->businessSettingService->updateOtpLoginAttemptsData($request->validated());

        return response()->json(['success' => true, 'successMessage' => 'Information Updated Successfully!', 'redirectUrl' => route('admin.business.configuration.login-settings.otp-login-attempts-index')]);
    }
}
