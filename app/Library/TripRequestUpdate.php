<?php


use App\Events\CustomerTripPaymentSuccessfulEvent;
use Modules\TripManagement\Entities\TripRequest;
use Modules\TransactionManagement\Traits\TransactionTrait;
use Modules\UserManagement\Enums\SuspendReasonEnum;
use Modules\UserManagement\Lib\LevelHistoryManagerTrait;

if (!function_exists('tripRequestUpdate'))
{
    function tripRequestUpdate($data)
    {
        $trip = TripRequest::query()
            ->with(['driver.userAccount', 'customer', 'driver.driverDetails'])
            ->find($data->attribute_id);
        $trip->paid_fare = ($trip->paid_fare +$trip->tips);
        $trip->payment_status = PAID;
        $trip->save();
        $tripType = ($trip->ride_request_type ?? 'regular') === 'regular' ? 'regular_trip' : 'schedule_trip';
        $push = getNotification(key: 'payment_successful', type: $tripType);
        sendDeviceNotification(
            fcm_token: $trip->driver->fcm_token,
            title: translate(key: $push['title'], locale: $trip?->driver?->current_language_key),
            description: textVariableDataFormat(value: $push['description'], paidAmount: getCurrencyFormat($trip->paid_fare), methodName: $trip->payment_method, locale: $trip?->driver?->current_language_key),
            status: $push['status'],
            ride_request_id: $trip->id,
            type: $trip->type,
            action: $push['action'],
            user_id: $trip->driver->id
        );
        if ($trip->tips > 0)
        {
            $pushTips = getNotification('tips_from_customer');
            sendDeviceNotification(
                fcm_token: $trip->driver->fcm_token,
                title: translate($pushTips['title']),
                description: translate(textVariableDataFormat(value: $pushTips['description'],tipsAmount: $trip->tips)),
                status: $pushTips['status'],
                ride_request_id: $trip->id,
                type: $trip->type,
                action: $pushTips['action'],
                user_id: $trip->driver->id
            );
        }

        $maximumAmountToHoldCash = businessConfig('cash_in_hand_setup_status')?->value && businessConfig('max_amount_to_hold_cash')?->value ? businessConfig('max_amount_to_hold_cash')?->value : null;
        $payableBalance = $trip?->driver?->userAccount->payable_balance > $trip?->driver?->userAccount->receivable_balance ? ($trip?->driver?->userAccount->payable_balance - $trip?->driver?->userAccount->receivable_balance) : 0;
        if ($maximumAmountToHoldCash && $payableBalance >= $maximumAmountToHoldCash)
        {
            $trip->driver->driverDetails->update(['is_suspended' => 1, 'suspend_reason' => SuspendReasonEnum::CASH_IN_HAND_LIMIT->value]);
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

        if (!empty($trip)) {
            try {
                checkReverbConnection() && CustomerTripPaymentSuccessfulEvent::broadcast($trip);
            }catch(Exception $exception){

            }
        }

        (new class {
            use TransactionTrait;
        })->digitalPaymentTransaction($trip);

        return $trip;
    }
}
