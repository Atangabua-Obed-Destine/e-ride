<?php

namespace Modules\UserManagement\Service;

use App\Repository\EloquentRepositoryInterface;
use App\Service\BaseService;
use Illuminate\Database\Eloquent\Model;
use Modules\UserManagement\Repository\ReferralCustomerRepositoryInterface;
use Modules\UserManagement\Service\Interfaces\ReferralCustomerServiceInterface;

class ReferralCustomerService extends BaseService implements Interfaces\ReferralCustomerServiceInterface
{
    protected $referralCustomerRepository;

    public function __construct(ReferralCustomerRepositoryInterface $referralCustomerRepository)
    {
        parent::__construct($referralCustomerRepository);
        $this->referralCustomerRepository = $referralCustomerRepository;
    }

    public function createReferralEarning(Model $newUser, Model $referralUser): void
    {
        if (!referralEarningSetting('referral_earning_status', CUSTOMER)?->value) {
            return;
        }

        $data = [
            'customer_id' => $newUser->id,
            'ref_by' => $referralUser->id,
            'ref_by_earning_amount' => (double)referralEarningSetting('share_code_earning', CUSTOMER)?->value,
        ];

        $useCodeEarning = referralEarningSetting('use_code_earning', CUSTOMER)?->value;
        if ($useCodeEarning && array_key_exists('first_ride_discount_status', $useCodeEarning) && $useCodeEarning['first_ride_discount_status']) {
            $data = array_merge($data, [
                'customer_discount_amount' => array_key_exists('discount_amount', $useCodeEarning) && $useCodeEarning['discount_amount'] ? $useCodeEarning['discount_amount'] : 0,
                'customer_discount_amount_type' => array_key_exists('discount_amount_type', $useCodeEarning) && $useCodeEarning['discount_amount_type'] ? $useCodeEarning['discount_amount_type'] : null,
                'customer_discount_validity' => array_key_exists('discount_validity', $useCodeEarning) && $useCodeEarning['discount_validity'] ? $useCodeEarning['discount_validity'] : 0,
                'customer_discount_validity_type' => $useCodeEarning['discount_validity'] && array_key_exists('discount_validity_type', $useCodeEarning) && $useCodeEarning['discount_validity_type'] ? $useCodeEarning['discount_validity_type'] : null,
            ]);
        }

        $this->create($data);

        $push = getNotification('someone_used_your_code');
        sendDeviceNotification(fcm_token: $referralUser?->fcm_token,
            title: translate(key: $push['title'], locale: $referralUser?->current_language_key),
            description: textVariableDataFormat(value: $push['description'], locale: $referralUser?->current_language_key),
            status: $push['status'],
            ride_request_id: $referralUser?->id,
            notification_type: 'referral_code',
            action: $push['action'],
            user_id: $referralUser?->id
        );
    }
}
