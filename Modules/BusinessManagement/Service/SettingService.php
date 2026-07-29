<?php

namespace Modules\BusinessManagement\Service;

use App\Repository\EloquentRepositoryInterface;
use App\Service\BaseService;
use Modules\BusinessManagement\Repository\BusinessSettingRepositoryInterface;
use Modules\BusinessManagement\Repository\SettingRepositoryInterface;
use Modules\BusinessManagement\Service\Interfaces\BusinessSettingServiceInterface;
use Modules\BusinessManagement\Service\Interfaces\SettingServiceInterface;
use Modules\Gateways\Traits\Processor;

class SettingService extends BaseService implements Interfaces\SettingServiceInterface
{
    protected $settingRepository;
    protected $businessSettingService;

    public function __construct(SettingRepositoryInterface $settingRepository, BusinessSettingServiceInterface $businessSettingService)
    {
        parent::__construct($settingRepository);
        $this->settingRepository = $settingRepository;
        $this->businessSettingService = $businessSettingService;
    }

    public function storeOrUpdatePaymentSetting(array $data)
    {
        $criteria = ['key_name' => $data['gateway'], 'settings_type' => PAYMENT_CONFIG];
        $paymentSetting = $this->settingRepository->findOneBy(criteria: $criteria);

        $additionalDataImage = $paymentSetting['additional_data'] != null ? json_decode($paymentSetting['additional_data']) : null;

        if (array_key_exists('gateway_image', $data)) {
            $gatewayImage = fileUploader('payment_modules/gateway_image/', APPLICATION_IMAGE_FORMAT, $data['gateway_image'], $additionalDataImage != null ? $additionalDataImage->gateway_image : '');
        } else {
            $gatewayImage = $additionalDataImage != null ? $additionalDataImage->gateway_image : '';
        }

        $paymentAdditionalData = [
            'gateway_title' => $data['gateway_title'],
            'gateway_image' => $gatewayImage,
        ];
        unset($data['gateway_image']);
        $paymentSettingData = [
            'key_name' => $data['gateway'],
            'live_values' => $data,
            'test_values' => $data,
            'settings_type' => PAYMENT_CONFIG,
            'mode' => $data['mode'],
            'is_active' => $data['status'],
            'additional_data' => json_encode($paymentAdditionalData),
        ];
        if ($paymentSetting) {
            $this->settingRepository->update(id: $paymentSetting->id, data: $paymentSettingData);
        } else {
            $this->settingRepository->create(data: $paymentSettingData);
        }
    }

    public function storeOrUpdateSMSSetting(array $data)
    {
        $criteria = ['key_name' => $data['gateway'], 'settings_type' => SMS_CONFIG];
        $smsSetting = $this->settingRepository->findOneBy(criteria: $criteria);
        $smsSettingData = [
            'key_name' => $data['gateway'],
            'live_values' => $data,
            'test_values' => $data,
            'settings_type' => SMS_CONFIG,
            'mode' => $data['mode'],
            'is_active' => $data['status'],
        ];
        if ($smsSetting) {
            $this->settingRepository->update(id: $smsSetting->id, data: $smsSettingData);
        } else {
            $this->settingRepository->create(data: $smsSettingData);
        }

        if (!isOtpEnabled()) {
            $this->businessSettingService->updateUserLoginToDefaultOptions();
        }
    }
}
