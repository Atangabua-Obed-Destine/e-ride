<?php

use Modules\TransactionManagement\Traits\TransactionTrait;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Service\Interfaces\DriverDetailServiceInterface;

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

        app(DriverDetailServiceInterface::class)->resumeIfCashInHandLimitCleared($driver);

        return true;
    }
}
