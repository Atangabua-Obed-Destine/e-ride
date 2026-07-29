<?php

namespace Modules\BusinessManagement\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\BusinessManagement\Service\Interfaces\BusinessSettingServiceInterface;
use Modules\BusinessManagement\Service\Interfaces\ExternalConfigurationServiceInterface;

class ConfigurationController extends Controller
{
    protected $externalConfigurationService;
    protected $businessSettingService;

    public function __construct(ExternalConfigurationServiceInterface $externalConfigurationService,BusinessSettingServiceInterface $businessSettingService)
    {
        $this->externalConfigurationService = $externalConfigurationService;
        $this->businessSettingService = $businessSettingService;
    }

    public function getConfiguration()
    {
        $cta = $this->businessSettingService->findOneBy(criteria: ['key_name' => CTA, 'settings_type' => LANDING_PAGES_SETTINGS]);

        $configs = [
            'business_name' => businessConfig('business_name', BUSINESS_INFORMATION)?->value ?? "DriveMond",
            'logo' => businessConfig('header_logo', BUSINESS_INFORMATION)?->value ? asset(businessConfig('header_logo', BUSINESS_INFORMATION)?->value) : dynamicAsset('public/assets/admin-module/img/logo.png'),
            'app_url_android' => $cta?->value && $cta?->value['play_store']['user_download_link'] ? $cta?->value['play_store']['user_download_link'] : "",
            'app_url_ios' => $cta?->value && $cta?->value['app_store']['user_download_link'] ? $cta?->value['app_store']['user_download_link'] : "",
        ];
        return response()->json($configs);
    }

    public function updateConfiguration(Request $request)
    {
        $this->externalConfigurationService->updateExternalConfiguration(data: $request->all());
        return response()->json(['message' => 'Configuration updated successfully.']);
    }

    public function getExternalConfiguration(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mart_base_url' => 'required',
            'mart_token' => 'required',
            'drivemond_token' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false]);
        }
        $activationMode = externalConfig('activation_mode')?->value;
        $martBaseUrl = externalConfig('mart_base_url')?->value;
        $martToken = externalConfig('mart_token')?->value;
        $systemSelfToken = externalConfig('system_self_token')?->value;
        if ($activationMode == 1 && $request->mart_base_url == $martBaseUrl && $request->mart_token == $martToken && $request->drivemond_token == $systemSelfToken) {
            return response()->json(['status' => true]);
        }

        return response()->json(['status' => false]);
    }

}
