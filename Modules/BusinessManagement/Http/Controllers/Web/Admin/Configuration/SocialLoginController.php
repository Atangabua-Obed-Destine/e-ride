<?php

namespace Modules\BusinessManagement\Http\Controllers\Web\Admin\Configuration;

use App\Http\Controllers\BaseController;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Modules\BusinessManagement\Http\Requests\SocialLoginStoreOrUpdateRequest;
use Modules\BusinessManagement\Service\Interfaces\BusinessSettingServiceInterface;

class SocialLoginController extends BaseController
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

        $apple = $this->businessSettingService
            ->findOneBy(criteria: ['key_name' => 'apple_login', 'settings_type' => SOCIAL_LOGIN])?->value;

        return view('businessmanagement::admin.configuration.social-login', compact('apple'));
    }

    public function update(SocialLoginStoreOrUpdateRequest $request): RedirectResponse
    {
        $this->authorize('business_edit');

        $this->businessSettingService->storeSocialLoginConfig($request->validated());
        Toastr::success(DEFAULT_UPDATE_200['message']);

        return back();
    }
}
