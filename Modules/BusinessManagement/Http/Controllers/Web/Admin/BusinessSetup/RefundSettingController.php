<?php

namespace Modules\BusinessManagement\Http\Controllers\Web\Admin\BusinessSetup;

use App\Http\Controllers\BaseController;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Modules\BusinessManagement\Http\Requests\ParcelRefundReasonStoreOrUpdateRequest;
use Modules\BusinessManagement\Http\Requests\ParcelRefundSettingStoreOrUpdateRequest;
use Modules\BusinessManagement\Http\Requests\ParcelSettingStoreOrUpdateRequest;
use Modules\BusinessManagement\Service\Interfaces\BusinessSettingServiceInterface;
use Modules\BusinessManagement\Service\Interfaces\ParcelRefundReasonServiceInterface;

class RefundSettingController extends BaseController
{
    protected $businessSettingService;
    protected $parcelRefundReasonService;

    public function __construct(BusinessSettingServiceInterface $businessSettingService, ParcelRefundReasonServiceInterface $parcelRefundReasonService)
    {
        parent::__construct($businessSettingService);
        $this->businessSettingService = $businessSettingService;
        $this->parcelRefundReasonService = $parcelRefundReasonService;
    }

    public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        $this->authorize('business_view');

        $settings = $this->businessSettingService->getBy(criteria: ['settings_type' => PARCEL_SETTINGS]);
        $parcelRefundReasons = $this->parcelRefundReasonService->getBy(orderBy: ['created_at'=>'desc'], limit: paginationLimit(),offset: $request?->page??1);
        return view('businessmanagement::admin.business-setup.parcel-refund', compact('settings','parcelRefundReasons'));
    }

    public function store(ParcelRefundSettingStoreOrUpdateRequest $request): RedirectResponse|Renderable
    {
        $this->authorize('business_view');
        $this->businessSettingService->storeParcelRefundSetting($request->validated());
        Toastr::success(BUSINESS_SETTING_UPDATE_200['message']);
        return back();
    }

    #cancellation reason
    public function storeRefundReason(ParcelRefundReasonStoreOrUpdateRequest $request)
    {
        $this->authorize('business_edit');
        $this->parcelRefundReasonService->create(data: $request->validated());
        Toastr::success(translate('Refund reason stored successfully'));
        return redirect()->back();
    }
    public function editRefundReason($id)
    {
        $this->authorize('business_edit');
        $parcelRefundReason = $this->parcelRefundReasonService->findOne(id: $id);
        if (!$parcelRefundReason){
            Toastr::error(translate('Refund reason not found'));
            return redirect()->back();
        }
        return view('businessmanagement::admin.business-setup.edit-parcel-refund-reason', compact('parcelRefundReason'));
    }
    public function updateRefundReason($id, ParcelRefundReasonStoreOrUpdateRequest $request)
    {
        $this->authorize('business_edit');
        $this->parcelRefundReasonService->update(id: $id,data: $request->validated());
        Toastr::success(translate('Refund reason updated successfully'));
        return redirect()->back();
    }

    public function destroyRefundReason(string $id)
    {
        $this->authorize('vehicle_delete');
        $this->parcelRefundReasonService->delete(id: $id);
        Toastr::success(translate('Refund reason deleted successfully.'));
        return redirect()->route('admin.business.setup.parcel-refund.index');
    }

    public function statusRefundReason(Request $request): JsonResponse
    {
        $this->authorize('vehicle_edit');
        $request->validate([
            'status' => 'boolean'
        ]);
        $model = $this->parcelRefundReasonService->statusChange(id: $request->id, data: $request->all());
        return response()->json($model);
    }
}
