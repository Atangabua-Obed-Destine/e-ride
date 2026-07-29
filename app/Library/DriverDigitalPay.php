<?php

use Modules\TransactionManagement\Traits\TransactionTrait;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Enums\SuspendReasonEnum;

if (!function_exists('driverDigitalPay'))
{
    function driverDigitalPay($data)
    {
        $driver = User::where('id', $data['payer_id'])->with(['userAccount', 'driverDetails'])->first();
        if ($driver?->userAccount?->receivable_balance == 0) {
            (new class {
                use TransactionTrait;
            })->collectCashWithoutAdjustTransaction($driver, $data['payment_amount'], 'api');

        } elseif ($driver?->userAccount?->receivable_balance > 0 && $driver?->userAccount?->payable_balance > $driver?->userAccount?->receivable_balance) {
            (new class {
                use TransactionTrait;
            })->collectCashWithAdjustTransaction($driver, $data['payment_amount'], 'api');
        }

        $driver->load('userAccount', 'driverDetails');

        $maximumCashInHandLimit = businessConfig('max_amount_to_hold_cash')?->value ?? 0;
        $collectableAmount = $driver?->userAccount->payable_balance > $driver?->userAccount->receivable_balance ? ($driver?->userAccount->payable_balance - $driver?->userAccount->receivable_balance) : 0;

        if ($maximumCashInHandLimit > $collectableAmount && $driver->driverDetails->is_suspended && $driver->driverDetails->suspend_reason == SuspendReasonEnum::CASH_IN_HAND_LIMIT->value)
        {
            $driver->driverDetails->update(['is_suspended' => 0, 'suspend_reason' => null]);
        }

        return true;
    }
}
