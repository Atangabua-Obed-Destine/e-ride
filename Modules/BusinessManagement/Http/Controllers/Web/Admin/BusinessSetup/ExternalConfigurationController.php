<?php

namespace Modules\BusinessManagement\Http\Controllers\Web\Admin\BusinessSetup;

use App\Http\Controllers\BaseController;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Modules\BusinessManagement\Http\Requests\ExternalConfigurationStoreOrUpdateRequest;
use Modules\BusinessManagement\Service\Interfaces\ExternalConfigurationServiceInterface;

class ExternalConfigurationController extends BaseController
{
    use AuthorizesRequests;

    protected $externalConfigurationService;

    public function __construct(ExternalConfigurationServiceInterface $externalConfigurationService)
    {
        parent::__construct($externalConfigurationService);
        $this->externalConfigurationService = $externalConfigurationService;
    }

    public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        $this->authorize('business_view');
        $settings = $this->externalConfigurationService
            ->getAll();
        return view('businessmanagement::admin.business-setup.external', compact('settings'));
    }

    public function store(ExternalConfigurationStoreOrUpdateRequest $request)
    {
        if (env('APP_MODE') == 'demo') {
            Toastr::info(translate('update_option_is_disable_for_demo'));
            return back();
        }
        $this->authorize('business_edit');
        $data = $this->externalConfigurationService->storeExternalInfo($request->validated());
        if (!$data) {
            Toastr::warning(translate('something_went_wrong,please_check_mart_base_url'));
            return back();
        }
        Toastr::success(SYSTEM_SETTING_UPDATE_200['message']);
        return back();
    }
}
