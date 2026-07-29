<?php

namespace Modules\AuthManagement\Traits;

use Illuminate\Http\Request;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Lib\AdditionalDataForm;

trait AdditionalRegistrationDataTrait
{
    protected function additionalRegistrationFields(string $userType): array
    {
        return AdditionalDataForm::fields($userType);
    }

    protected function validateAdditionalRegistrationData(Request $request, string $userType): array
    {
        return AdditionalDataForm::validateRequest($request, $userType);
    }

    protected function storeAdditionalRegistrationData(?User $user, Request $request, string $userType): void
    {
        AdditionalDataForm::storeFromRequest($user, $request, $userType);
    }
}
