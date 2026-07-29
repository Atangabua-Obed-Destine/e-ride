<?php

namespace Modules\BusinessManagement\Http\Controllers\Web\Admin\AdditionalDataSetup;

use App\Http\Controllers\BaseController;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Modules\BusinessManagement\Lib\AdditionalDataFieldNormalizer;
use Modules\BusinessManagement\Http\Requests\AdditionalDataSetupStoreOrUpdateRequest;
use Modules\BusinessManagement\Service\Interfaces\BusinessSettingServiceInterface;

class AdditionalDataSetupController extends BaseController
{
    use AuthorizesRequests;

    protected $businessSettingService;

    public function __construct(BusinessSettingServiceInterface $businessSettingService)
    {
        parent::__construct($businessSettingService);
        $this->businessSettingService = $businessSettingService;
    }

    public function getAdditionalData($userType = 'customer')
    {
        $this->authorize('business_view');

        if (!in_array($userType, ['customer', 'driver'], true)) {
            $userType = 'customer';
        }

        $defaultInputFields = AdditionalDataSetupStoreOrUpdateRequest::defaultFieldsFor($userType);

        $userAdditionalRegistrationFormFields = businessConfig(
            $userType . '_additional_registration_form_fields',
            ADDITIONAL_DATA_SETUP
        )?->value ?? [];

        if (!is_array($userAdditionalRegistrationFormFields)) {
            $userAdditionalRegistrationFormFields = [];
        }
        $userAdditionalRegistrationFormFields = array_values(array_filter(array_map(
            fn ($field) => is_array($field) ? AdditionalDataFieldNormalizer::normalizeField($field) : null,
            $userAdditionalRegistrationFormFields
        )));

        return view('businessmanagement::admin.pages.additional-data-setup.index', compact(
            'userType',
            'defaultInputFields',
            'userAdditionalRegistrationFormFields'
        ));
    }

    public function update(AdditionalDataSetupStoreOrUpdateRequest $request)
    {
        $this->authorize('business_edit');

        $data = $request->validated();
        $this->businessSettingService->storeAdditionalDataSetup($data);

        return response()->json([
            'success' => true,
            'successMessage' => translate('Information Updated Successfully!'),
            'redirectUrl' => route('admin.business.pages-media.additional-data-setup.index', ['userType' => $data['user_type']]),
        ]);
    }
}
