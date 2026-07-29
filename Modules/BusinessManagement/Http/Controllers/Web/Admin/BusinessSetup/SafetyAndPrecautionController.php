<?php

namespace Modules\BusinessManagement\Http\Controllers\Web\Admin\BusinessSetup;

use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Modules\BusinessManagement\Http\Requests\EmergencyNumberForCallStoreOrUpdateRequest;
use Modules\BusinessManagement\Http\Requests\SafetyAlertStoreOrUpdateRequest;
use Modules\BusinessManagement\Http\Requests\SafetyFeatureStoreOrUpdateRequest;
use Modules\BusinessManagement\Http\Requests\SafetyPrecautionStoreOrUpdateRequest;
use Modules\BusinessManagement\Service\Interfaces\BusinessSettingServiceInterface;
use Modules\BusinessManagement\Service\Interfaces\ParcelRefundReasonServiceInterface;
use Modules\BusinessManagement\Service\Interfaces\SafetyAlertReasonServiceInterface;
use Modules\BusinessManagement\Service\Interfaces\SafetyPrecautionServiceInterface;

class SafetyAndPrecautionController extends BaseController
{
    protected $businessSettingService;
    protected $safetyPrecautionService;
    protected $safetyAlertReasonService;


    public function __construct(
        BusinessSettingServiceInterface   $businessSettingService,
        SafetyPrecautionServiceInterface  $safetyPrecautionService,
        SafetyAlertReasonServiceInterface $safetyAlertReasonService,
    )
    {
        parent::__construct($businessSettingService);
        $this->businessSettingService = $businessSettingService;
        $this->safetyPrecautionService = $safetyPrecautionService;
        $this->safetyAlertReasonService = $safetyAlertReasonService;
    }

    public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {

        $this->authorize('business_view');
        if (in_array($type, [SAFETY_ALERT, PRECAUTION])) {
            $settings = $this->businessSettingService->getBy(criteria: ['settings_type' => SAFETY_FEATURE_SETTINGS]);
            $safetyPrecautions = $this->safetyPrecautionService->getBy(orderBy: ['created_at' => 'desc'], limit: paginationLimit(), offset: $request?->page ?? 1);
            $safetyAlertReasons = $this->safetyAlertReasonService->getBy(orderBy: ['created_at' => 'desc'], limit: paginationLimit(), offset: $request?->page ?? 1);
            $emergencyNumbers = $settings->firstWhere('key_name', 'emergency_other_numbers_for_call')?->value;
            return view('businessmanagement::admin.business-setup.safety-precaution', compact('settings', 'safetyPrecautions', 'safetyAlertReasons', 'emergencyNumbers'));
        }
        abort(404);
    }

//      safety feature
    public function store(SafetyFeatureStoreOrUpdateRequest $request): RedirectResponse
    {
        $this->authorize('business_view');
        $this->businessSettingService->storeSafetyFeature($request->validated());
        Toastr::success(BUSINESS_SETTING_UPDATE_200['message']);
        return back();
    }

//    safety alert

    public function storeSafetyAlertReason(SafetyAlertStoreOrUpdateRequest $request): RedirectResponse
    {
        $this->authorize('business_view');
        $this->safetyAlertReasonService->create(data: $request->validated());
        Toastr::success(translate('Safety Alert stored successfully'));
        return redirect()->back();
    }

    public function editSafetyAlertReason($id): View
    {
        $this->authorize('business_edit');
        $safetyAlertReason = $this->safetyAlertReasonService->findOne(id: $id);
        if (!$safetyAlertReason) {
            Toastr::error(translate('Safety Alert not found'));
            return redirect()->back();
        }
        return view('businessmanagement::admin.business-setup.edit-safety-alert-reason', compact('safetyAlertReason'));
    }

    public function updateSafetyAlertReason($id, SafetyAlertStoreOrUpdateRequest $request): RedirectResponse
    {
        $this->authorize('business_edit');
        $this->safetyAlertReasonService->update(id: $id, data: $request->validated());
        Toastr::success(translate('Safety Alert updated successfully'));
        return redirect()->back();
    }

    public function destroySafetyAlertReason(string $id): RedirectResponse
    {
        $this->authorize('business_delete');
        $this->safetyAlertReasonService->delete(id: $id);
        Toastr::success(translate('Safety alert deleted successfully.'));
        return redirect()->route('admin.business.setup.safety-precaution.index', SAFETY_ALERT);
    }

    public function statusSafetyAlertReason(Request $request): JsonResponse
    {
        $this->authorize('business_edit');
        $request->validate([
            'status' => 'boolean'
        ]);
        $model = $this->safetyAlertReasonService->statusChange(id: $request->id, data: $request->all());
        return response()->json($model);
    }

//    safety precautions
    public function storeSafetyPrecaution(SafetyPrecautionStoreOrUpdateRequest $request): RedirectResponse
    {
        $this->authorize('business_view');
        $this->safetyPrecautionService->create(data: $request->validated());
        Toastr::success(translate('Safety Precaution stored successfully'));
        return redirect()->back();
    }

    public function editSafetyPrecaution($id): View
    {
        $this->authorize('business_edit');
        $safetyPrecaution = $this->safetyPrecautionService->findOne(id: $id);
        if (!$safetyPrecaution) {
            Toastr::error(translate('Safety Precaution not found'));
            return redirect()->back();
        }
        return view('businessmanagement::admin.business-setup.edit-safety-precaution', compact('safetyPrecaution'));
    }

    public function updateSafetyPrecaution($id, SafetyPrecautionStoreOrUpdateRequest $request): RedirectResponse
    {
        $this->authorize('business_edit');
        $this->safetyPrecautionService->update(id: $id, data: $request->validated());
        Toastr::success(translate('Safety Precaution updated successfully'));
        return redirect()->back();
    }

    public function destroySafetyPrecaution(string $id): RedirectResponse
    {
        $this->authorize('business_delete');
        $this->safetyPrecautionService->delete(id: $id);
        Toastr::success(translate('Safety precaution deleted successfully.'));
        return redirect()->route('admin.business.setup.safety-precaution.index', PRECAUTION);
    }


    public function statusSafetyPrecaution(Request $request): JsonResponse
    {
        $this->authorize('business_edit');
        $request->validate([
            'status' => 'boolean'
        ]);
        $model = $this->safetyPrecautionService->statusChange(id: $request->id, data: $request->all());
        return response()->json($model);
    }

    public function storeEmergencyNumberForCall(EmergencyNumberForCallStoreOrUpdateRequest $request): JsonResponse
    {
        try {
            $this->authorize('business_view');
            $this->businessSettingService->storeEmergencyNumberForCall(data: $request->validated());

            return response()->json([
                'status' => 'success',
                'message' => BUSINESS_SETTING_UPDATE_200['message'],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong. Please try again.',
            ], 500);
        }
    }


}
