<?php

namespace Modules\BusinessManagement\Http\Controllers\Web\Admin\Configuration;

use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use Aws\Exception\AwsException;
use Aws\Rekognition\RekognitionClient;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Modules\BusinessManagement\Http\Requests\FaceVerificationApiStoreOrUpdateRequest;
use Modules\BusinessManagement\Service\Interfaces\BusinessSettingServiceInterface;

class FaceVerificationApiSetupController extends BaseController
{
    use AuthorizesRequests;

    protected $businessSettingService;

    public function __construct(BusinessSettingServiceInterface $businessSettingService)
    {
        parent::__construct($businessSettingService);
        $this->businessSettingService = $businessSettingService;
    }

    public function faceVerificationApi()
    {
        $this->authorize('business_view');
        $faceVerificationApi = $this->businessSettingService->findOneBy(criteria: ['key_name' => 'face_verification_api']);

        return view('businessmanagement::admin.configuration.face-verification-api', compact('faceVerificationApi'));
    }

    public function updateFaceVerificationApi(FaceVerificationApiStoreOrUpdateRequest $request)
    {
        $this->authorize('business_edit');
        $data = $request->validated();
        $status = array_key_exists('status', $request->validated()) ? 1 : 0;
        $data['status'] = $status;
        $faceVerificationApi = $this->businessSettingService->findOneBy(criteria: ['key_name' => 'face_verification_api']);
        $faceVerificationApiData = [
            'key_name' => 'face_verification_api',
            'value' => $data,
            'settings_type' => FACE_VERIFICATION_SETTINGS
        ];

        $rekognition = new RekognitionClient([
            'version' => 'latest',
            'region'  => $data['region'],
            'credentials' => [
                'key'    => $data['access_key'],
                'secret' => $data['secret_access_key'],
            ],
        ]);

        try {
            $rekognition->listCollections(['MaxResults' => 1]);
        } catch (AwsException $e) {
            Toastr::error(INVALID_FACE_VERIFICATION_API_CREDENTIALS['message']);
            return back();
        }

        if ($faceVerificationApi)
        {
            $this->businessSettingService->update(id: $faceVerificationApi->id, data: $faceVerificationApiData);
        } else {
            $this->businessSettingService->create(data: $faceVerificationApiData);
        }

        Toastr::success(FACE_VERIFICATION_API_UPDATE['message']);

        return back();
    }
}
