<?php

namespace Modules\BusinessManagement\Http\Controllers\Web\Admin\BusinessSetup;

use App\Http\Controllers\BaseController;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Modules\BusinessManagement\Http\Requests\ScheduleTripStoreRequest;
use Modules\BusinessManagement\Service\Interfaces\BusinessSettingServiceInterface;

class ScheduleTripController extends BaseController
{
    protected $businessSettingService;

    public function __construct(
        BusinessSettingServiceInterface $businessSettingService,
    )
    {
        parent::__construct($businessSettingService);
        $this->businessSettingService = $businessSettingService;
    }

    public function store(ScheduleTripStoreRequest $request): RedirectResponse|JsonResponse
    {
        $this->authorize('business_edit');
        $this->businessSettingService->storeScheduleTrip(data: $request->validated());
        if ($request->expectsJson()) {
            return response()->json([
                'successMessage' => translate(BUSINESS_SETTING_UPDATE_200['message']),
                'redirectUrl' => url()->previous(),
            ]);
        }
        Toastr::success(BUSINESS_SETTING_UPDATE_200['message']);

        return back();
    }
}
