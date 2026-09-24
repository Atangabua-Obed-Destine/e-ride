<?php

namespace Modules\UserManagement\Service;


use App\Service\BaseService;
use Illuminate\Database\Eloquent\Model;
use Modules\UserManagement\Enums\PauseReasonEnum;
use Modules\UserManagement\Repository\DriverDetailRepositoryInterface;
use Modules\UserManagement\Service\Interfaces\DriverDetailServiceInterface;

class DriverDetailService extends BaseService implements DriverDetailServiceInterface
{
    protected $driverDetailRepository;

    public function __construct(DriverDetailRepositoryInterface $driverDetailRepository)
    {
        parent::__construct($driverDetailRepository);
        $this->driverDetailRepository = $driverDetailRepository;
    }

    public function updateAvailability(array $data = [])
    {
        $driver = $this->driverDetailRepository->findOneBy(criteria: ['user_id' => $data['user_id']]);
        $criteria = [];
        if ($data['trip_type'] != SCHEDULED) {
            $criteria = match ($data['trip_type']) {
                'ride_request' => ['ride_count' => max(0, $driver->ride_count - 1)],
                'parcel' => ['parcel_count' => max(0, $driver->parcel_count - 1)],
                default => ['ride_count' => $driver->ride_count],
            };
        }
        $criteria['availability_status'] = 'available';
        $this->driverDetailRepository->updatedBy(criteria: ['user_id' => $data['user_id']], data: $criteria);
    }

    public function resumeExpiredPauses(): int
    {
        return $this->driverDetailRepository->resumeExpiredPauses();
    }

    public function suspendOffline(int|string $userId): void
    {
        $this->driverDetailRepository->updatedBy(
            criteria: ['user_id' => $userId],
            data: $this->driverDetailRepository->suspendOfflinePayload()
        );
    }

    public function pauseIfCashInHandLimitExceeded(Model $trip): void
    {
        $maximumAmountToHoldCash = businessConfig('cash_in_hand_setup_status')?->value && businessConfig('max_amount_to_hold_cash')?->value ? businessConfig('max_amount_to_hold_cash')?->value : null;
        $payableBalance = $trip?->driver?->userAccount->payable_balance > $trip?->driver?->userAccount->receivable_balance ? ($trip?->driver?->userAccount->payable_balance - $trip?->driver?->userAccount->receivable_balance) : 0;
        if (!$maximumAmountToHoldCash || $payableBalance < $maximumAmountToHoldCash) {
            return;
        }

        $this->driverDetailRepository->updatedBy(
            criteria: ['user_id' => $trip->driver->id],
            data: $this->driverDetailRepository->pausePayload(PauseReasonEnum::CASH_IN_HAND_LIMIT->value)
        );

        $cashInHandLimitExceeds = getNotification('cash_in_hand_limit_exceeds');
        sendDeviceNotification(
            fcm_token: $trip->driver->fcm_token,
            title: translate(key: $cashInHandLimitExceeds['title'], locale: $trip->driver->current_language_key),
            description: textVariableDataFormat(value: $cashInHandLimitExceeds['description'], driverName: $trip->customer->first_name . ' ' . $trip->customer->last_name, locale: $trip->driver->current_language_key),
            status: $cashInHandLimitExceeds['status'],
            ride_request_id: $trip?->driver->id,
            notification_type: '',
            action: $cashInHandLimitExceeds['action'],
            user_id: $trip?->driver->id,
        );
    }

    public function resumeIfCashInHandLimitCleared(Model $driver): void
    {
        $maximumCashInHandLimit = businessConfig('max_amount_to_hold_cash')?->value ?? 0;
        $collectableAmount = $driver?->userAccount->payable_balance > $driver?->userAccount->receivable_balance ? ($driver?->userAccount->payable_balance - $driver?->userAccount->receivable_balance) : 0;

        if ($maximumCashInHandLimit > $collectableAmount && $driver->driverDetails->is_paused && $driver->driverDetails->pause_reason == PauseReasonEnum::CASH_IN_HAND_LIMIT->value) {
            $this->driverDetailRepository->updatedBy(
                criteria: ['user_id' => $driver->id],
                data: $this->driverDetailRepository->resumePayload()
            );
        }
    }
}
