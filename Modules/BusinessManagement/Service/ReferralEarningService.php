<?php

namespace Modules\BusinessManagement\Service;

use App\Service\BaseService;
use Modules\BusinessManagement\Repository\ReferralEarningSettingRepositoryInterface;
use Modules\BusinessManagement\Service\Interfaces\ReferralEarningServiceInterface;

class ReferralEarningService extends BaseService implements Interfaces\ReferralEarningServiceInterface
{
    protected $referralEarningSettingRepository;

    public function __construct(ReferralEarningSettingRepositoryInterface $referralEarningSettingRepository)
    {
        parent::__construct($referralEarningSettingRepository);
        $this->referralEarningSettingRepository = $referralEarningSettingRepository;
    }

    public function storeInfo(array $data)
    {
        if ($data['user_type'] == CUSTOMER)
        {
            $customerShareCodeEarning = $this->referralEarningSettingRepository->findOneBy(criteria: [
                'key_name' => 'share_code_earning',
                'settings_type' => CUSTOMER
            ]);
            $customerShareCodeEarningValue = $data["customer_share_code_earning"]??"";
            if ($customerShareCodeEarning) {
                $this->referralEarningSettingRepository->update(id: $customerShareCodeEarning->id, data: ['key_name' => 'share_code_earning', 'settings_type' => CUSTOMER, 'value' => $customerShareCodeEarningValue]);
            } else {
                $this->referralEarningSettingRepository->create(data: ['key_name' => 'share_code_earning', 'settings_type' => CUSTOMER, 'value' => $customerShareCodeEarningValue]);
            }


            $customerUseCodeEarning = $this->referralEarningSettingRepository->findOneBy(criteria: [
                'key_name' => 'use_code_earning',
                'settings_type' => CUSTOMER
            ]);
            $customerUseCodeEarningValue = [];
            if (array_key_exists('customer_first_ride_discount_status', $data)) {
                $customerUseCodeEarningValue['first_ride_discount_status'] = 1;
            } else {
                $customerUseCodeEarningValue['first_ride_discount_status'] = 0;
            }
            $customerUseCodeEarningValue['discount_amount'] = $data['customer_discount_amount'] ?? '';
            $customerUseCodeEarningValue['discount_amount_type'] = $data['customer_discount_amount_type'] ?? "";
            $customerUseCodeEarningValue['discount_validity'] = $data['customer_discount_validity'] ?? "";
            $customerUseCodeEarningValue['discount_validity_type'] = $data['customer_discount_validity_type'] ?? "";
            if ($customerUseCodeEarning) {
                $this->referralEarningSettingRepository->update(id: $customerUseCodeEarning->id, data: ['key_name' => 'use_code_earning', 'settings_type' => CUSTOMER, 'value' => $customerUseCodeEarningValue]);
            } else {
                $this->referralEarningSettingRepository->create(data: ['key_name' => 'use_code_earning', 'settings_type' => CUSTOMER, 'value' => $customerUseCodeEarningValue]);
            }
        } else {
            $driverShareCodeEarning = $this->referralEarningSettingRepository->findOneBy(criteria: [
                'key_name' => 'share_code_earning',
                'settings_type' => DRIVER
            ]);
            $driverShareCodeEarningValue = $data["driver_share_code_earning"]??"";
            if ($driverShareCodeEarning) {
                $this->referralEarningSettingRepository->update(id: $driverShareCodeEarning->id, data: ['key_name' => 'share_code_earning', 'settings_type' => DRIVER, 'value' => $driverShareCodeEarningValue]);
            } else {
                $this->referralEarningSettingRepository->create(data: ['key_name' => 'share_code_earning', 'settings_type' => DRIVER, 'value' => $driverShareCodeEarningValue]);
            }

            $driverUseCodeEarning = $this->referralEarningSettingRepository->findOneBy(criteria: [
                'key_name' => 'use_code_earning',
                'settings_type' => DRIVER
            ]);
            $driverUseCodeEarningValue = $data["driver_use_code_earning"]??"";
            if ($driverUseCodeEarning) {
                $this->referralEarningSettingRepository->update(id: $driverUseCodeEarning->id, data: ['key_name' => 'use_code_earning', 'settings_type' => DRIVER, 'value' => $driverUseCodeEarningValue]);
            } else {
                $this->referralEarningSettingRepository->create(data: ['key_name' => 'use_code_earning', 'settings_type' => DRIVER, 'value' => $driverUseCodeEarningValue]);
            }
        }
    }
}
