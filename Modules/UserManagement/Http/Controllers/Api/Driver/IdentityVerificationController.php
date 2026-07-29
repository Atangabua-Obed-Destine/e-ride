<?php

namespace Modules\UserManagement\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\UserManagement\Http\Requests\VerifyIdentityStoreOrUpdateRequest;
use Modules\UserManagement\Service\Interfaces\DriverIdentityVerificationServiceInterface;

class IdentityVerificationController extends Controller
{
    public $driverIdentityService;

    public function __construct(DriverIdentityVerificationServiceInterface $driverIdentityService)
    {
        $this->driverIdentityService = $driverIdentityService;
    }

    public function skipVerification()
    {
        $user = auth('api')->user()->load(['driverIdentityVerification', 'driverDetails']);
        $this->driverIdentityService->skip(user: $user);

        return response()->json(responseFormatter(constant: FACE_VERIFICATION_SKIP));
    }

    public function verify(VerifyIdentityStoreOrUpdateRequest $request)
    {
        $faceVerificationApiCred = businessConfig(key: 'face_verification_api', settingsType: FACE_VERIFICATION_SETTINGS)?->value;
        $verifyDriverIdentity = $faceVerificationApiCred['status'] && businessConfig(key: 'driver_identity_verification_status', settingsType: FACE_VERIFICATION_SETTINGS)?->value ?? 0;
        if (!$verifyDriverIdentity)
        {
            return response()->json(responseFormatter(constant: FACE_VERIFICATION_FEATURE_NOT_ACTIVE_403), 403);
        }

        $data = array_merge($request->validated(), ['user' => auth('api')->user()->load(['driverIdentityVerification', 'driverDetails']), 'faceVerificationApiCred' => $faceVerificationApiCred]);
        $result = $this->driverIdentityService->verify(data: $data);

        if ($result['response_code'] == 'ok') {
            if ($data['user']->fcm_token) {
                $push = getNotification('face_verification_completed_successfully');
                sendDeviceNotification(fcm_token: $data['user']->fcm_token,
                    title: translate(key: $push['title'], locale: $data['user']->current_language_key),
                    description: textVariableDataFormat(value: $push['description'], businessName: getSession('business_name'), locale: $data['user']->current_language_key),
                    status: $push['status'],
                    type: $data['user']->type,
                    notification_type: 'parcel',
                    action: $push['action'],
                    user_id: $data['user']->id
                );
            }
        }

        return response()->json(responseFormatter(constant: $result), $result['response_code'] == 'failed' ? 403 : 200);
    }
}
