<?php

namespace Modules\BusinessManagement\Http\Controllers\Web\Admin\BusinessSetup;

use App\Http\Controllers\BaseController;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Modules\BusinessManagement\Http\Requests\CashInHandSetupStoreOrUpdateRequest;
use Modules\BusinessManagement\Http\Requests\DriverSettingStoreOrUpdateRequest;
use Modules\BusinessManagement\Http\Requests\IdentityVerificationStoreOrUpdateRequest;
use Modules\BusinessManagement\Service\Interfaces\BusinessSettingServiceInterface;

class DriverSettingController extends BaseController
{
    protected $businessSettingService;

    public function __construct(BusinessSettingServiceInterface $businessSettingService)
    {
        parent::__construct($businessSettingService);
        $this->businessSettingService = $businessSettingService;
    }

    public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        $this->authorize('business_view');

        $settings = $this->businessSettingService->getBy(criteria: ['settings_type' => DRIVER_SETTINGS]);
        $driverSelfRegistrationStatus = $this->businessSettingService->findOneBy(criteria: ['key_name'  => 'driver_self_registration'])?->value ?? 0;
        $driverVerificationStatus = $this->businessSettingService->findOneBy(criteria: ['key_name'  => 'driver_verification'])?->value ?? 0;
        $faceVerificationSettings = $this->businessSettingService->getBy(criteria: ['settings_type' => FACE_VERIFICATION_SETTINGS]);
        $faceVerificationApiStatus = $this->businessSettingService->findOneBy(criteria: ['key_name' => 'face_verification_api'])?->value['status'];

        return view('businessmanagement::admin.business-setup.driver', compact('settings', 'driverSelfRegistrationStatus', 'driverVerificationStatus', 'faceVerificationSettings', 'faceVerificationApiStatus'));
    }

    public function store(DriverSettingStoreOrUpdateRequest $request): RedirectResponse|Renderable
    {
        $this->authorize('business_view');
        $this->businessSettingService->storeDriverSetting($request->validated());
        Toastr::success(BUSINESS_SETTING_UPDATE_200['message']);
        return back();
    }
    public function vehicleUpdate(Request $request): RedirectResponse|Renderable
    {
        $this->authorize('business_view');
        $this->businessSettingService->storeVehicleUpdateDriverSetting($request->all());
        Toastr::success(BUSINESS_SETTING_UPDATE_200['message']);
        return back();
    }

    public function updateCashInHandSetup(CashInHandSetupStoreOrUpdateRequest $request) {
        $this->authorize('business_view');
        $this->businessSettingService->updateCashInHand($request->all());
        Toastr::success(BUSINESS_SETTING_UPDATE_200['message']);
        return back();
    }

    public function updateIdentityVerification(IdentityVerificationStoreOrUpdateRequest $request)
    {
        $data = $request->validated();
        $this->businessSettingService->updateIdentityVerification(data: $data);

        Toastr::success(BUSINESS_SETTING_UPDATE_200['message']);
        return back();
    }
}
