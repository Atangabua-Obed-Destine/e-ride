<?php

namespace Modules\BusinessManagement\Http\Controllers\Web\Admin\BusinessSetup;

use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use Modules\BusinessManagement\Http\Requests\AppVersionSettingStoreOrUpdateRequest;
use Modules\BusinessManagement\Http\Requests\BusinessInfoStoreOrUpdateRequest;
use Modules\BusinessManagement\Service\Interfaces\BusinessSettingServiceInterface;
use Modules\BusinessManagement\Service\Interfaces\ExternalConfigurationServiceInterface;

class AppVersionSettingController extends BaseController
{
    use AuthorizesRequests;

    protected $businessSettingService;
    public function __construct(BusinessSettingServiceInterface $businessSettingService)
    {
        parent::__construct($businessSettingService);
        $this->businessSettingService = $businessSettingService;
    }

    public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        $this->authorize('business_view');
        $settings = $this->businessSettingService
            ->getBy(criteria: ['settings_type' => APP_VERSION]);

        return view('businessmanagement::admin.business-setup.index', compact('settings'));
    }

    public function store(AppVersionSettingStoreOrUpdateRequest $request)
    {
        $this->authorize('business_edit');
        $this->businessSettingService->storeAppVersion($request->validated());
        Toastr::success(BUSINESS_SETTING_UPDATE_200['message']);
        return back();
    }
}
