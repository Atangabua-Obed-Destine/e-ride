<?php

use Carbon\Carbon;
use Modules\TransactionManagement\Traits\TransactionTrait;
use Modules\UserManagement\Entities\UserAccount;
use Modules\UserManagement\Entities\WalletBonus;

if (!function_exists('customerWalletUpdate'))
{
    function customerWalletUpdate($data)
    {
        $bonusAmount = calculateBonusAmount($data['payment_amount']) ?? 0;
        $totalAmount = $data['payment_amount'] + $bonusAmount;
        $customer = UserAccount::with('user')->where('user_id', $data['payer_id'])->first();
        $customer?->increment('wallet_balance', $totalAmount);
        $customer->refresh();
        $fundData = [
            'amount' => $data['payment_amount'],
            'reference' => null,
            'added_bonus' => $bonusAmount,
        ];

        return (new class {use TransactionTrait; })->addWalletFundDigitally(customer: $customer, data: $fundData, attribute: 'fund_added_digitally');
    }
}

if (!function_exists('calculateBonusAmount'))
{
    function calculateBonusAmount($amount)
    {

        $currentDate = Carbon::today()->toDateString();
        $percentageBonusModel = WalletBonus::where('is_active', 1)
            ->where('min_add_amount', '<=', $amount)
            ->where('start_date', '<=', $currentDate)
            ->where('end_date', '>=', $currentDate)
            ->where('amount_type', PERCENTAGE)
            ->orderBy('bonus_amount', 'desc')
            ->first();
        $percentageBonus = 0;
        if ($percentageBonusModel) {
            $percentageBonus = ($amount * $percentageBonusModel->bonus_amount) / 100;
            $percentageBonus = min($percentageBonus, $percentageBonusModel->max_bonus_amount);
        }

        $amountBonus = WalletBonus::where('is_active', 1)
            ->where('min_add_amount', '<=', $amount)
            ->where('start_date', '<=', $currentDate)
            ->where('end_date', '>=', $currentDate)
            ->where('amount_type', AMOUNT)
            ->orderBy('bonus_amount', 'desc')
            ->first()?->bonus_amount ?? 0;

        return max($percentageBonus, $amountBonus);
    }

}
