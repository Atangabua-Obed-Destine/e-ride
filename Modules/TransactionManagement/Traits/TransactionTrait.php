<?php

namespace Modules\TransactionManagement\Traits;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Entities\UserAccount;
use Modules\TransactionManagement\Entities\Transaction;
use Modules\UserManagement\Lib\LevelUpdateCheckerTrait;

trait TransactionTrait
{

    use LevelUpdateCheckerTrait;

    public function digitalPaymentTransaction($trip): void
    {
        $adminUserId = User::where('user_type', ADMIN_USER_TYPES[0])->first()->id;

        DB::beginTransaction();
        $adminReceived = $trip->fee->admin_commission;//30
        $tripBalanceAfterRemoveCommission = $trip->paid_fare - $trip->fee->admin_commission; //70
        $riderEarning = $tripBalanceAfterRemoveCommission;

        //Admin account update (payable and wallet balance +)
        $adminAccount = UserAccount::where('user_id', $adminUserId)->first();
        $adminAccount->payable_balance += $tripBalanceAfterRemoveCommission;
        $adminAccount->received_balance += $adminReceived;
        $adminAccount->save();

        //Admin transaction 1 (payable)
        $adminTransaction1 = new Transaction();
        $adminTransaction1->attribute = 'driver_earning';
        $adminTransaction1->attribute_id = $trip->id;
        $adminTransaction1->credit = $tripBalanceAfterRemoveCommission;
        $adminTransaction1->balance = $adminAccount->payable_balance;
        $adminTransaction1->user_id = $adminUserId;
        $adminTransaction1->account = 'payable_balance';
        $adminTransaction1->trx_type = 'Driver earnings added to admin payable balance';
        $adminTransaction1->save();

        //Admin transaction 2 ( + received balance)
        $adminTransaction2 = new Transaction();
        $adminTransaction2->attribute = 'admin_commission';
        $adminTransaction2->attribute_id = $trip->id;
        $adminTransaction2->credit = $adminReceived;
        $adminTransaction2->balance = $adminAccount->received_balance;
        $adminTransaction2->user_id = $adminUserId;
        $adminTransaction2->account = 'received_balance';
        $adminTransaction2->trx_type = 'Ride commission added to admin received balance';
        $adminTransaction2->save();

        //Admin account update for coupon amount
        if ($trip->coupon_id !== null && $trip->coupon_amount > 0) {
            $this->adminAccountUpdateWithTransactionForCoupon($trip, $adminUserId);
        }

        //Admin account update for discount amount
        if ($trip->discount_amount !== null && $trip->discount_amount > 0) {
            $this->adminAccountUpdateWithTransactionForDiscount($trip, $adminUserId);
        }

        //Rider account update (+ receivable_balance)
        $riderAccount = UserAccount::where('user_id', $trip->driver->id)->first();
        $riderAccount->receivable_balance += $tripBalanceAfterRemoveCommission; //70
        $riderAccount->save();

        //Rider transaction 1
        $riderTransaction = new Transaction();
        $riderTransaction->attribute = 'driver_earning';
        $riderTransaction->attribute_id = $trip->id;
        $riderTransaction->credit = $tripBalanceAfterRemoveCommission;
        $riderTransaction->balance = $riderAccount->receivable_balance;
        $riderTransaction->user_id = $trip->driver->id;
        $riderTransaction->account = 'receivable_balance';
        $riderTransaction->trx_type = 'Ride earnings added to driver receivable balance';
        $riderTransaction->save();

        //Rider account update for coupon
        if ($trip->coupon_id !== null && $trip->coupon_amount > 0) {
            $this->riderAccountUpdateWithTransactionForCoupon($trip);
        }

        //Rider account update for discount
        if ($trip->discount_amount !== null && $trip->discount_amount > 0) {
            $this->riderAccountUpdateWithTransactionForDiscount($trip);
        }

        $this->driverLevelUpdateChecker($trip->driver);
        DB::commit();

    }

    public function cashTransaction($trip, $returnFee = false): void
    {
        $adminUserId = User::where('user_type', ADMIN_USER_TYPES[0])->first()->id;
        DB::beginTransaction();
        $adminReceived = $trip->fee->admin_commission;//30
        if ($returnFee) {
            $tripBalanceAfterRemoveCommission = ($trip->paid_fare - $trip->return_fee) - $trip->fee->admin_commission; //70
        } else {
            $tripBalanceAfterRemoveCommission = $trip->paid_fare - $trip->fee->admin_commission; //70
        }


        //Rider account update
        $riderAccount = UserAccount::where('user_id', $trip->driver->id)->first();
        $riderAccount->payable_balance += $adminReceived; //30
        $riderAccount->received_balance += $tripBalanceAfterRemoveCommission; //70
        $riderAccount->save();

        //Rider account update transaction 1
        $riderTransaction1 = new Transaction();
        $riderTransaction1->attribute = 'driver_earning';
        $riderTransaction1->attribute_id = $trip->id;
        $riderTransaction1->credit = $tripBalanceAfterRemoveCommission;
        $riderTransaction1->balance = $riderAccount->received_balance;
        $riderTransaction1->user_id = $trip->driver->id;
        $riderTransaction1->account = 'received_balance';
        $riderTransaction1->trx_type = 'Ride earnings added to the driver received balance';
        $riderTransaction1->save();

        //Rider account update transaction 2
        $riderTransaction2 = new Transaction();
        $riderTransaction2->attribute = 'admin_commission';
        $riderTransaction2->attribute_id = $trip->id;
        $riderTransaction2->credit = $adminReceived;
        $riderTransaction2->balance = $riderAccount->payable_balance;
        $riderTransaction2->user_id = $trip->driver->id;
        $riderTransaction2->account = 'payable_balance';
        $riderTransaction2->trx_ref_id = $riderTransaction1->id;
        $riderTransaction2->trx_type = 'Ride commission added to the driver payable balance';
        $riderTransaction2->save();

        //Rider account update for coupon
        if ($trip->coupon_id !== null && $trip->coupon_amount > 0) {
            $this->riderAccountUpdateWithTransactionForCoupon($trip);
        }

        //Rider account update for discount
        if ($trip->discount_amount !== null && $trip->discount_amount > 0) {
            $this->riderAccountUpdateWithTransactionForDiscount($trip);
        }

        //Admin account update
        $adminAccount = UserAccount::where('user_id', $adminUserId)->first();
        $adminAccount->receivable_balance += $adminReceived; //30
        $adminAccount->save();

        //Admin transaction 1
        $adminTransaction = new Transaction();
        $adminTransaction->attribute = 'admin_commission';
        $adminTransaction->attribute_id = $trip->id;
        $adminTransaction->credit = $adminReceived;
        $adminTransaction->balance = $adminAccount->receivable_balance;
        $adminTransaction->user_id = $adminUserId;
        $adminTransaction->account = 'receivable_balance';
        $adminTransaction->trx_ref_id = $riderTransaction2->id;
        $adminTransaction->trx_type = 'Ride commission added to admin receivable balance';
        $adminTransaction->save();

        //Admin account update for coupon amount
        if ($trip->coupon_id !== null && $trip->coupon_amount > 0) {
            $this->adminAccountUpdateWithTransactionForCoupon($trip, $adminUserId);
        }

        //Admin account update for discount amount
        if ($trip->discount_amount !== null && $trip->discount_amount > 0) {
            $this->adminAccountUpdateWithTransactionForDiscount($trip, $adminUserId);
        }

        $this->driverLevelUpdateChecker($trip->driver);

        DB::commit();
    }

    public function cashReturnFeeTransaction($trip): void
    {
        $amount = $trip->due_amount;
        DB::beginTransaction();
        //Rider account update
        $riderAccount = UserAccount::where('user_id', $trip->driver->id)->first();
        $riderAccount->received_balance += $amount;
        $riderAccount->save();

        //Rider account update transaction 1
        $riderTransaction1 = new Transaction();
        $riderTransaction1->attribute = 'driver_earning';
        $riderTransaction1->attribute_id = $trip->id;
        $riderTransaction1->credit = $amount;
        $riderTransaction1->balance = $riderAccount->received_balance;
        $riderTransaction1->user_id = $trip->driver->id;
        $riderTransaction1->account = 'received_balance';
        $riderTransaction1->trx_type = 'Parcel return fee added to driver received balance';
        $riderTransaction1->save();

        $this->driverLevelUpdateChecker($trip->driver);

        DB::commit();
    }

//    public function driverParcelCancellationTransaction($trip): void
//    {
//
//        $adminUserId = User::where('user_type', ADMIN_USER_TYPES[0])->first()->id;
//        DB::beginTransaction();
//        $amount = $trip->cancellation_fee;
//
//        //Rider account update for reverse
//        $riderAccount = UserAccount::where('user_id', $trip->driver->id)->first();
//        $riderAccount->payable_balance += $amount;
//        $riderAccount->save();
//
//        //Rider account  update transaction 1 for reverse
//        $riderTransaction1 = new Transaction();
//        $riderTransaction1->attribute = 'driver_parcel_cancellation_fee';
//        $riderTransaction1->attribute_id = $trip->id;
//        $riderTransaction1->credit = $amount;
//        $riderTransaction1->balance = $riderAccount->payable_balance;
//        $riderTransaction1->user_id = $trip->driver->id;
//        $riderTransaction1->account = 'payable_balance';
//        $riderTransaction1->save();
//
//        //Admin account update for reverse
//        $adminAccount = UserAccount::where('user_id', $adminUserId)->first();
//        $adminAccount->receivable_balance += $amount;
//        $adminAccount->save();
//
//        //Admin transaction 1 for reverse
//        $adminTransaction = new Transaction();
//        $adminTransaction->attribute = 'driver_earning_reverse';
//        $adminTransaction->attribute_id = $trip->id;
//        $adminTransaction->credit = $amount;
//        $adminTransaction->balance = $adminAccount->receivable_balance;
//        $adminTransaction->user_id = $adminUserId;
//        $adminTransaction->account = 'receivable_balance';
//        $adminTransaction->trx_ref_id = $riderTransaction1->id;
//        $adminTransaction->save();
//        DB::commit();
//
//    }

    public function senderCashPaymentDriverParcelCancelReverseTransaction($trip): void
    {
        $adminUserId = User::where('user_type', ADMIN_USER_TYPES[0])->first()->id;
        DB::beginTransaction();
        $tripBalanceAfterRemoveCommission = $trip->paid_fare - $trip->fee->admin_commission; //70

        //Rider account update for reverse
        $riderAccount = UserAccount::where('user_id', $trip->driver->id)->first();
        $riderAccount->payable_balance += $tripBalanceAfterRemoveCommission; //30
        $riderAccount->received_balance -= $tripBalanceAfterRemoveCommission; //70
        $riderAccount->save();

        //Rider account  update transaction 1 for reverse
        $riderTransaction1 = new Transaction();
        $riderTransaction1->attribute = 'driver_earning_reverse';
        $riderTransaction1->attribute_id = $trip->id;
        $riderTransaction1->debit = $tripBalanceAfterRemoveCommission;
        $riderTransaction1->balance = $riderAccount->received_balance;
        $riderTransaction1->user_id = $trip->driver->id;
        $riderTransaction1->account = 'received_balance';
        $riderTransaction1->trx_type = 'Parcel delivery earnings deducted from driver received balance for cancellation';
        $riderTransaction1->save();

        //Rider account update transaction 2 for reverse
        $riderTransaction2 = new Transaction();
        $riderTransaction2->attribute = 'driver_earning_reverse';
        $riderTransaction2->attribute_id = $trip->id;
        $riderTransaction2->credit = $tripBalanceAfterRemoveCommission;
        $riderTransaction2->balance = $riderAccount->payable_balance;
        $riderTransaction2->user_id = $trip->driver->id;
        $riderTransaction2->account = 'payable_balance';
        $riderTransaction2->trx_ref_id = $riderTransaction1->id;
        $riderTransaction2->trx_type = 'Parcel delivery earnings added to driver payable balance';
        $riderTransaction2->save();

        //Rider account update for coupon reverse
        if ($trip->coupon_id !== null && $trip->coupon_amount > 0) {
            $this->riderAccountUpdateWithTransactionForCouponReverse($trip);
        }

        //Rider account update for discount reverse
        if ($trip->discount_amount !== null && $trip->discount_amount > 0) {
            $this->riderAccountUpdateWithTransactionForDiscountReverse($trip);
        }

        //Admin account update for reverse
        $adminAccount = UserAccount::where('user_id', $adminUserId)->first();
        $adminAccount->receivable_balance += $tripBalanceAfterRemoveCommission; //30
        $adminAccount->save();

        //Admin transaction 1 for reverse
        $adminTransaction = new Transaction();
        $adminTransaction->attribute = 'driver_earning_reverse';
        $adminTransaction->attribute_id = $trip->id;
        $adminTransaction->credit = $tripBalanceAfterRemoveCommission;
        $adminTransaction->balance = $adminAccount->receivable_balance;
        $adminTransaction->user_id = $adminUserId;
        $adminTransaction->account = 'receivable_balance';
        $adminTransaction->trx_ref_id = $riderTransaction2->id;
        $adminTransaction->trx_type = 'Ride earnings added to admin receivable balance';
        $adminTransaction->save();

        //Admin account update for coupon amount reverse
        if ($trip->coupon_id !== null && $trip->coupon_amount > 0) {
            $this->adminAccountUpdateWithTransactionForCouponReverse($trip, $adminUserId);
        }

        //Admin account update for discount amount reverse
        if ($trip->discount_amount !== null && $trip->discount_amount > 0) {
            $this->adminAccountUpdateWithTransactionForDiscountReverse($trip, $adminUserId);
        }

        //customer account credit parcel cancel driver return
        $customerAccount = UserAccount::where('user_id', $trip->customer->id)->first();
        $customerAccount->wallet_balance += $trip->paid_fare;
        $customerAccount->save();

        //customer transaction (debit)
        $customerTransaction = new Transaction();
        $customerTransaction->attribute = 'parcel_cancel_payment_reverse';
        $customerTransaction->attribute_id = $trip->id;
        $customerTransaction->credit = $trip->paid_fare;
        $customerTransaction->balance = $customerAccount->wallet_balance;
        $customerTransaction->user_id = $trip->customer->id;
        $customerTransaction->account = 'wallet_balance';
        $customerTransaction->trx_type = 'Delivery charge refunded to customer wallet due to parcel cancellation';
        $customerTransaction->save();
        DB::commit();

    }

    public function senderDigitalPaymentDriverParcelCancelReverseTransaction($trip): void
    {
        $adminUserId = User::where('user_type', ADMIN_USER_TYPES[0])->first()->id;
        DB::beginTransaction();
        $tripBalanceAfterRemoveCommission = $trip->paid_fare - $trip->fee->admin_commission; //70

        //customer account credit parcel cancel driver return
        $customerAccount = UserAccount::where('user_id', $trip->customer->id)->first();
        $customerAccount->wallet_balance += $trip->paid_fare;
        $customerAccount->save();

        //customer transaction (debit)
        $customerTransaction = new Transaction();
        $customerTransaction->attribute = 'parcel_cancel_payment_reverse';
        $customerTransaction->attribute_id = $trip->id;
        $customerTransaction->credit = $trip->paid_fare;
        $customerTransaction->balance = $customerAccount->wallet_balance;
        $customerTransaction->user_id = $trip->customer->id;
        $customerTransaction->account = 'wallet_balance';
        $customerTransaction->trx_type = 'Delivery charge refunded to customer wallet due to parcel cancellation';
        $customerTransaction->save();

        //Admin account update (payable and wallet balance +)
        $adminAccount = UserAccount::where('user_id', $adminUserId)->first();
        $adminAccount->payable_balance -= $tripBalanceAfterRemoveCommission;
        $adminAccount->save();

        //Admin transaction 1 (payable)
        $adminTransaction1 = new Transaction();
        $adminTransaction1->attribute = 'driver_earning_reverse';
        $adminTransaction1->attribute_id = $trip->id;
        $adminTransaction1->debit = $tripBalanceAfterRemoveCommission;
        $adminTransaction1->balance = $adminAccount->payable_balance;
        $adminTransaction1->user_id = $adminUserId;
        $adminTransaction1->account = 'payable_balance';
        $adminTransaction1->trx_type = 'Parcel delivery earnings deducted from admin payable balance for cancellation';
        $adminTransaction1->save();

        //Admin account update for coupon amount
        if ($trip->coupon_id !== null && $trip->coupon_amount > 0) {
            $this->adminAccountUpdateWithTransactionForCouponReverse($trip, $adminUserId);
        }

        //Admin account update for discount amount
        if ($trip->discount_amount !== null && $trip->discount_amount > 0) {
            $this->adminAccountUpdateWithTransactionForDiscountReverse($trip, $adminUserId);
        }

        //Rider account update (- receivable_balance)
        $riderAccount = UserAccount::where('user_id', $trip->driver->id)->first();
        $riderAccount->receivable_balance -= $tripBalanceAfterRemoveCommission; //70
        $riderAccount->save();

        //Rider transaction 1
        $riderTransaction = new Transaction();
        $riderTransaction->attribute = 'driver_earning_reverse';
        $riderTransaction->attribute_id = $trip->id;
        $riderTransaction->debit = $tripBalanceAfterRemoveCommission;
        $riderTransaction->balance = $riderAccount->receivable_balance;
        $riderTransaction->user_id = $trip->driver->id;
        $riderTransaction->account = 'receivable_balance';
        $riderTransaction->trx_type = 'Parcel delivery earnings deducted from driver receivable balance for cancellation';
        $riderTransaction->save();

        //Rider account update for coupon
        if ($trip->coupon_id !== null && $trip->coupon_amount > 0) {
            $this->riderAccountUpdateWithTransactionForCouponReverse($trip);
        }

        //Rider account update for discount
        if ($trip->discount_amount !== null && $trip->discount_amount > 0) {
            $this->riderAccountUpdateWithTransactionForDiscountReverse($trip);
        }
        DB::commit();
    }

    public function senderWalletPaymentDriverParcelCancelReverseTransaction($trip): void
    {
        $adminUserId = User::where('user_type', ADMIN_USER_TYPES[0])->first()->id;

        DB::beginTransaction();
        $tripBalanceAfterRemoveCommission = $trip->paid_fare - $trip->fee->admin_commission; //70

        //customer account credit parcel cancel driver return
        $customerAccount = UserAccount::where('user_id', $trip->customer->id)->first();
        $customerAccount->wallet_balance += $trip->paid_fare;
        $customerAccount->save();

        //customer transaction (debit)
        $customerTransaction = new Transaction();
        $customerTransaction->attribute = 'parcel_cancel_payment_reverse';
        $customerTransaction->attribute_id = $trip->id;
        $customerTransaction->credit = $trip->paid_fare;
        $customerTransaction->balance = $customerAccount->wallet_balance;
        $customerTransaction->user_id = $trip->customer->id;
        $customerTransaction->account = 'wallet_balance';
        $customerTransaction->trx_type = 'Delivery charge refunded to customer wallet for parcel cancellation';
        $customerTransaction->save();

        //Admin account update (payable and wallet balance +)
        $adminAccount = UserAccount::where('user_id', $adminUserId)->first();
        $adminAccount->payable_balance -= $tripBalanceAfterRemoveCommission;
        $adminAccount->save();

        //Admin transaction 1 (payable)
        $adminTransaction1 = new Transaction();
        $adminTransaction1->attribute = 'driver_earning_reverse';
        $adminTransaction1->attribute_id = $trip->id;
        $adminTransaction1->debit = $tripBalanceAfterRemoveCommission;
        $adminTransaction1->balance = $adminAccount->payable_balance;
        $adminTransaction1->user_id = $adminUserId;
        $adminTransaction1->account = 'payable_balance';
        $adminTransaction1->trx_ref_id = $customerTransaction->id;
        $adminTransaction1->trx_type = 'Parcel delivery earnings deducted from admin payable balance';
        $adminTransaction1->save();

        //Admin account update for coupon amount
        if ($trip->coupon_id !== null && $trip->coupon_amount > 0) {
            $this->adminAccountUpdateWithTransactionForCouponReverse($trip, $adminUserId);
        }

        //Admin account update for discount amount
        if ($trip->discount_amount !== null && $trip->discount_amount > 0) {
            $this->adminAccountUpdateWithTransactionForDiscountReverse($trip, $adminUserId);
        }

        //Rider account update (- receivable_balance)
        $riderAccount = UserAccount::where('user_id', $trip->driver->id)->first();
        $riderAccount->receivable_balance -= $tripBalanceAfterRemoveCommission; //70
        $riderAccount->save();

        //Rider transaction 1
        $riderTransaction = new Transaction();
        $riderTransaction->attribute = 'driver_earning_reverse';
        $riderTransaction->attribute_id = $trip->id;
        $riderTransaction->debit = $tripBalanceAfterRemoveCommission;
        $riderTransaction->balance = $riderAccount->receivable_balance;
        $riderTransaction->user_id = $trip->driver->id;
        $riderTransaction->account = 'receivable_balance';
        $riderTransaction->trx_type = 'Parcel delivery earnings deducted from driver receivable balance';
        $riderTransaction->save();

        //Rider account update for coupon
        if ($trip->coupon_id !== null && $trip->coupon_amount > 0) {
            $this->riderAccountUpdateWithTransactionForCouponReverse($trip);
        }

        //Rider account update for discount
        if ($trip->discount_amount !== null && $trip->discount_amount > 0) {
            $this->riderAccountUpdateWithTransactionForDiscountReverse($trip);
        }
        DB::commit();
    }

    public function walletTransaction($trip): void
    {
        $adminUserId = User::where('user_type', ADMIN_USER_TYPES[0])->first()->id;

        DB::beginTransaction();
        $adminReceived = $trip->fee->admin_commission;//30
        $tripBalanceAfterRemoveCommission = $trip->paid_fare - $trip->fee->admin_commission; //70
        $riderEarning = $tripBalanceAfterRemoveCommission;

        //customer account debit
        $customerAccount = UserAccount::where('user_id', $trip->customer->id)->first();
        $customerAccount->wallet_balance -= $trip->paid_fare;
        $customerAccount->save();

        //customer transaction (debit)
        $customerTransaction = new Transaction();
        $customerTransaction->attribute = 'wallet_payment';
        $customerTransaction->attribute_id = $trip->id;
        $customerTransaction->debit = $trip->paid_fare;
        $customerTransaction->balance = $customerAccount->wallet_balance;
        $customerTransaction->user_id = $trip->customer->id;
        $customerTransaction->account = 'wallet_balance';
        $customerTransaction->trx_type = 'Trip amount deducted from customer wallet';
        $customerTransaction->save();

        //Admin account update (payable and wallet balance +)
        $adminAccount = UserAccount::where('user_id', $adminUserId)->first();
        $adminAccount->payable_balance += $tripBalanceAfterRemoveCommission;
        $adminAccount->received_balance += $adminReceived;
        $adminAccount->save();

        //Admin transaction 1 (payable)
        $adminTransaction1 = new Transaction();
        $adminTransaction1->attribute = 'driver_earning';
        $adminTransaction1->attribute_id = $trip->id;
        $adminTransaction1->credit = $tripBalanceAfterRemoveCommission;
        $adminTransaction1->balance = $adminAccount->payable_balance;
        $adminTransaction1->user_id = $adminUserId;
        $adminTransaction1->account = 'payable_balance';
        $adminTransaction1->trx_ref_id = $customerTransaction->id;
        $adminTransaction1->trx_type = 'Trip earnings deducted from admin payable balance';
        $adminTransaction1->save();

        //Admin transaction 2 ( + received balance)
        $adminTransaction2 = new Transaction();
        $adminTransaction2->attribute = 'admin_commission';
        $adminTransaction2->attribute_id = $trip->id;
        $adminTransaction2->credit = $adminReceived;
        $adminTransaction2->balance = $adminAccount->received_balance;
        $adminTransaction2->user_id = $adminUserId;
        $adminTransaction2->account = 'received_balance';
        $adminTransaction2->trx_ref_id = $customerTransaction->id;
        $adminTransaction2->trx_type = 'Trip commission added to admin received balance';
        $adminTransaction2->save();

        //Admin account update for coupon amount
        if ($trip->coupon_id !== null && $trip->coupon_amount > 0) {
            $this->adminAccountUpdateWithTransactionForCoupon($trip, $adminUserId);
        }

        //Admin account update for discount amount
        if ($trip->discount_amount !== null && $trip->discount_amount > 0) {
            $this->adminAccountUpdateWithTransactionForDiscount($trip, $adminUserId);
        }

        //Rider account update (+ receivable_balance)
        $riderAccount = UserAccount::where('user_id', $trip->driver->id)->first();
        $riderAccount->receivable_balance += $tripBalanceAfterRemoveCommission; //70
        $riderAccount->save();

        //Rider transaction 1
        $riderTransaction = new Transaction();
        $riderTransaction->attribute = 'driver_earning';
        $riderTransaction->attribute_id = $trip->id;
        $riderTransaction->credit = $tripBalanceAfterRemoveCommission;
        $riderTransaction->balance = $riderAccount->receivable_balance;
        $riderTransaction->user_id = $trip->driver->id;
        $riderTransaction->account = 'receivable_balance';
        $riderTransaction->trx_type = 'Trip earnings added to driver receivable balance';
        $riderTransaction->save();

        //Rider account update for coupon
        if ($trip->coupon_id !== null && $trip->coupon_amount > 0) {
            $this->riderAccountUpdateWithTransactionForCoupon($trip);
        }

        //Rider account update for discount
        if ($trip->discount_amount !== null && $trip->discount_amount > 0) {
            $this->riderAccountUpdateWithTransactionForDiscount($trip);
        }

        $this->driverLevelUpdateChecker($trip->driver);
        DB::commit();


    }

    private function adminAccountUpdateWithTransactionForCoupon($trip, $adminUserId)
    {
        $adminAccountForCoupon = UserAccount::where('user_id', $adminUserId)->first();
        $adminAccountForCoupon->payable_balance += $trip->coupon_amount; //30
        $adminAccountForCoupon->save();

        //Admin transaction for coupon amount
        $adminTransactionForCoupon = new Transaction();
        $adminTransactionForCoupon->attribute = 'driver_earning';
        $adminTransactionForCoupon->attribute_id = $trip->id;
        $adminTransactionForCoupon->credit = $trip->coupon_amount;
        $adminTransactionForCoupon->balance = $adminAccountForCoupon->payable_balance;
        $adminTransactionForCoupon->user_id = $adminUserId;
        $adminTransactionForCoupon->transaction_type = COUPON;
        $adminTransactionForCoupon->account = 'payable_balance';
        $adminTransactionForCoupon->trx_type = 'Coupon discount added to admin payable balance';
        $adminTransactionForCoupon->save();
    }

    private function adminAccountUpdateWithTransactionForDiscount($trip, $adminUserId)
    {
        $adminAccountForDiscount = UserAccount::where('user_id', $adminUserId)->first();
        $adminAccountForDiscount->payable_balance += $trip->discount_amount; //30
        $adminAccountForDiscount->save();

        //Admin transaction for coupon amount
        $adminTransactionForDiscount = new Transaction();
        $adminTransactionForDiscount->attribute = 'driver_earning';
        $adminTransactionForDiscount->attribute_id = $trip->id;
        $adminTransactionForDiscount->credit = $trip->discount_amount;
        $adminTransactionForDiscount->balance = $adminAccountForDiscount->payable_balance;
        $adminTransactionForDiscount->user_id = $adminUserId;
        $adminTransactionForDiscount->transaction_type = DISCOUNT;
        $adminTransactionForDiscount->account = 'payable_balance';
        $adminTransactionForDiscount->trx_type = 'Trip discount added to admin payable balance';
        $adminTransactionForDiscount->save();
    }

    private function riderAccountUpdateWithTransactionForCoupon($trip)
    {
        $riderAccountForCoupon = UserAccount::where('user_id', $trip->driver->id)->first();
        $riderAccountForCoupon->receivable_balance += $trip->coupon_amount;
        $riderAccountForCoupon->save();

        //Rider transaction for coupon
        $riderTransactionForCoupon = new Transaction();
        $riderTransactionForCoupon->attribute = 'driver_earning';
        $riderTransactionForCoupon->attribute_id = $trip->id;
        $riderTransactionForCoupon->credit = $trip->coupon_amount;
        $riderTransactionForCoupon->balance = $riderAccountForCoupon->receivable_balance;
        $riderTransactionForCoupon->user_id = $trip->driver->id;
        $riderTransactionForCoupon->transaction_type = COUPON;
        $riderTransactionForCoupon->account = 'receivable_balance';
        $riderTransactionForCoupon->trx_type = 'Coupon discount added to driver receivable balance';
        $riderTransactionForCoupon->save();
    }

    private function riderAccountUpdateWithTransactionForDiscount($trip)
    {
        $riderAccountForCoupon = UserAccount::where('user_id', $trip->driver->id)->first();
        $riderAccountForCoupon->receivable_balance += $trip->discount_amount;
        $riderAccountForCoupon->save();

        //Rider transaction for discount
        $riderTransactionForDiscount = new Transaction();
        $riderTransactionForDiscount->attribute = 'driver_earning';
        $riderTransactionForDiscount->attribute_id = $trip->id;
        $riderTransactionForDiscount->credit = $trip->discount_amount;
        $riderTransactionForDiscount->balance = $riderAccountForCoupon->receivable_balance;
        $riderTransactionForDiscount->user_id = $trip->driver->id;
        $riderTransactionForDiscount->transaction_type = DISCOUNT;
        $riderTransactionForDiscount->account = 'receivable_balance';
        $riderTransactionForDiscount->trx_type = 'Trip discount added to driver receivable balance.';
        $riderTransactionForDiscount->save();
    }

    private function adminAccountUpdateWithTransactionForCouponReverse($trip, $adminUserId)
    {
        $adminAccountForCoupon = UserAccount::where('user_id', $adminUserId)->first();
        $adminAccountForCoupon->payable_balance -= $trip->coupon_amount; //30
        $adminAccountForCoupon->save();

        //Admin transaction for coupon amount
        $adminTransactionForCoupon = new Transaction();
        $adminTransactionForCoupon->attribute = 'driver_earning_reverse';
        $adminTransactionForCoupon->attribute_id = $trip->id;
        $adminTransactionForCoupon->debit = $trip->coupon_amount;
        $adminTransactionForCoupon->balance = $adminAccountForCoupon->payable_balance;
        $adminTransactionForCoupon->user_id = $adminUserId;
        $adminTransactionForCoupon->transaction_type = COUPON;
        $adminTransactionForCoupon->account = 'payable_balance';
        $adminTransactionForCoupon->trx_type = 'Coupon discount deducted from admin payable balance for cancellation';
        $adminTransactionForCoupon->save();
    }

    private function adminAccountUpdateWithTransactionForDiscountReverse($trip, $adminUserId)
    {
        $adminAccountForDiscount = UserAccount::where('user_id', $adminUserId)->first();
        $adminAccountForDiscount->payable_balance -= $trip->discount_amount; //30
        $adminAccountForDiscount->save();

        //Admin transaction for coupon amount
        $adminTransactionForDiscount = new Transaction();
        $adminTransactionForDiscount->attribute = 'driver_earning_reverse';
        $adminTransactionForDiscount->attribute_id = $trip->id;
        $adminTransactionForDiscount->debit = $trip->discount_amount;
        $adminTransactionForDiscount->balance = $adminAccountForDiscount->payable_balance;
        $adminTransactionForDiscount->user_id = $adminUserId;
        $adminTransactionForDiscount->transaction_type = DISCOUNT;
        $adminTransactionForDiscount->account = 'payable_balance';
        $adminTransactionForDiscount->trx_type = 'Discount deducted from admin payable balance for cancellation';
        $adminTransactionForDiscount->save();
    }

    private function riderAccountUpdateWithTransactionForCouponReverse($trip)
    {
        $riderAccountForCoupon = UserAccount::where('user_id', $trip->driver->id)->first();
        $riderAccountForCoupon->receivable_balance -= $trip->coupon_amount;
        $riderAccountForCoupon->save();

        //Rider transaction for coupon
        $riderTransactionForCoupon = new Transaction();
        $riderTransactionForCoupon->attribute = 'driver_earning_reverse';
        $riderTransactionForCoupon->attribute_id = $trip->id;
        $riderTransactionForCoupon->debit = $trip->coupon_amount;
        $riderTransactionForCoupon->balance = $riderAccountForCoupon->receivable_balance;
        $riderTransactionForCoupon->user_id = $trip->driver->id;
        $riderTransactionForCoupon->transaction_type = COUPON;
        $riderTransactionForCoupon->account = 'receivable_balance';
        $riderTransactionForCoupon->trx_type = 'Coupon discount deducted from driver receivable balance for cancellation';
        $riderTransactionForCoupon->save();
    }

    private function riderAccountUpdateWithTransactionForDiscountReverse($trip)
    {
        $riderAccountForCoupon = UserAccount::where('user_id', $trip->driver->id)->first();
        $riderAccountForCoupon->receivable_balance -= $trip->discount_amount;
        $riderAccountForCoupon->save();

        //Rider transaction for discount
        $riderTransactionForDiscount = new Transaction();
        $riderTransactionForDiscount->attribute = 'driver_earning_reverse';
        $riderTransactionForDiscount->attribute_id = $trip->id;
        $riderTransactionForDiscount->debit = $trip->discount_amount;
        $riderTransactionForDiscount->balance = $riderAccountForCoupon->receivable_balance;
        $riderTransactionForDiscount->user_id = $trip->driver->id;
        $riderTransactionForDiscount->transaction_type = DISCOUNT;
        $riderTransactionForDiscount->account = 'receivable_balance';
        $riderTransactionForDiscount->trx_type = 'Trip discount deducted from driver receivable balance for cancellation';
        $riderTransactionForDiscount->save();
    }

    public function customerLoyaltyPointsTransaction($user, $amount): Model|Builder|null
    {
        DB::beginTransaction();
        //Customer account update
        $customer = UserAccount::query()->firstWhere('user_id', $user->id);
        $customer->wallet_balance += $amount;
        $customer->save();

        //customer transaction (credit)
        $primary_transaction = new Transaction();
        $primary_transaction->attribute = 'point_conversion';
        $primary_transaction->credit = $amount;
        $primary_transaction->balance = $customer->wallet_balance;
//        $primary_transaction->wallet_balance = $customer->wallet_balance;
        $primary_transaction->user_id = $user->id;
        $primary_transaction->account = 'wallet_balance';
        $primary_transaction->trx_type = 'Points converted to wallet balance added to customer account';
        $primary_transaction->save();

        DB::commit();

        return $customer;
    }

    public function driverLoyaltyPointsTransaction($user, $amount): Model|Builder|null
    {
        DB::beginTransaction();
        //Customer account update
        $driver = UserAccount::query()->firstWhere('user_id', $user->id);
        $driver->receivable_balance += $amount;
        $driver->save();

        //Driver transaction (credit)
        $primary_transaction = new Transaction();
        $primary_transaction->attribute = 'point_conversion';
        $primary_transaction->credit = $amount;
        $primary_transaction->balance = $driver->receivable_balance;
//        $primary_transaction->wallet_balance = $customer->wallet_balance;
        $primary_transaction->user_id = $user->id;
        $primary_transaction->account = 'receivable_balance';
        $primary_transaction->trx_type = 'Points converted to driver receivable balance';
        $primary_transaction->save();

        DB::commit();

        return $driver;
    }

    public function withdrawRequestWithAdjustTransaction($user, $amount, $attribute)
    {
        DB::beginTransaction();

        //Driver account update
        $driver = UserAccount::where('user_id', $user->id)->first();
        $payableBalance = $driver->payable_balance;

        $driver->receivable_balance -= ($payableBalance + $amount);
        $driver->payable_balance -= $payableBalance;
        $driver->pending_balance += $amount;
        $driver->save();

        //Admin account update
        $adminUserId = User::where('user_type', ADMIN_USER_TYPES[0])->first()->id;
        $account = UserAccount::query()->firstWhere('user_id', $adminUserId);
        $account->received_balance += $payableBalance;
        $account->receivable_balance -= $payableBalance;
        $account->save();

        //Driver transaction (credit)
        $first_trx = new Transaction();
        $first_trx->attribute = 'pending_withdrawn';
        $first_trx->attribute_id = $attribute->id;
        $first_trx->credit = $amount;
        $first_trx->balance = $driver->pending_balance;
        $first_trx->user_id = $user->id;
        $first_trx->account = 'pending_withdraw_balance';
        $first_trx->trx_type = 'Requested withdrawal added to driver pending balance';
        $first_trx->save();


        #adjust driver payable balance

        //Driver transaction (debit)
        $driver_transaction = new Transaction();
        $driver_transaction->attribute = 'adjust_payable_balance';
        $driver_transaction->debit = $payableBalance;
        $driver_transaction->balance = $driver->payable_balance;
        $driver_transaction->user_id = $user->id;
        $driver_transaction->account = 'payable_balance';
        $driver_transaction->trx_ref_id = $first_trx->id;
        $driver_transaction->trx_type = 'Amount deducted from payable balance after withdrawal';
        $driver_transaction->save();
        //Admin transaction (credit)
        $admin_transaction = new Transaction();
        $admin_transaction->attribute = 'adjust_received_balance';
        $admin_transaction->credit = $payableBalance;
        $admin_transaction->balance = $account->received_balance;
        $admin_transaction->user_id = $adminUserId;
        $admin_transaction->account = 'received_balance';
        $admin_transaction->trx_ref_id = $driver_transaction->id;
        $admin_transaction->trx_type = 'Amount added to admin received balance';
        $admin_transaction->save();
        //Admin transaction 2 (debit)
        $admin_transaction_2 = new Transaction();
        $admin_transaction_2->attribute = 'adjust_receiveable_balance';
        $admin_transaction_2->debit = $payableBalance;
        $admin_transaction_2->balance = $account->receivable_balance;
        $admin_transaction_2->user_id = $adminUserId;
        $admin_transaction_2->account = 'receivable_balance';
        $admin_transaction_2->trx_ref_id = $driver_transaction->id;
        $admin_transaction_2->trx_type = 'Payable amount deducted from driver receivable balance';
        $admin_transaction_2->save();

        DB::commit();

        return $driver;
    }

    public function withdrawRequestWithoutAdjustTransaction($user, $amount, $attribute)
    {
        DB::beginTransaction();
        //Driver account update
        $driver = UserAccount::where('user_id', $user->id)->first();
        $driver->receivable_balance -= $amount;
        $driver->pending_balance += $amount;
        $driver->save();

        //Driver transaction (credit)
        $second_trx = new Transaction();
        $second_trx->attribute = 'pending_withdrawn';
        $second_trx->attribute_id = $attribute->id;
        $second_trx->credit = $amount;
        $second_trx->balance = $driver->pending_balance;
        $second_trx->user_id = $user->id;
        $second_trx->account = 'pending_withdraw_balance';
        $second_trx->trx_type = 'Requested withdrawal added to driver pending withdraw balance account';
        $second_trx->save();
        DB::commit();

        return $driver;
    }

    public function withdrawRequestReverseTransaction($user, $amount, $attribute)
    {
        //Driver account update
        $driver = UserAccount::where('user_id', $user->id)->first();
        if ($attribute->status == DENIED) {
            $driver->receivable_balance -= $amount;
            $driver->pending_balance += $amount;
            $driver->save();

//Driver transaction (debit)
            $second_trx = new Transaction();
            $second_trx->attribute = 'pending_withdraw_reverse';
            $second_trx->attribute_id = $attribute->id;
            $second_trx->debit = $amount;
            $second_trx->balance = $driver->pending_balance;
            $second_trx->user_id = $user->id;
            $second_trx->account = 'withdraw_balance_reverse';
            $second_trx->trx_type = 'Requested withdrawal deducted from driver withdraw balance reverse account';
            $second_trx->save();
        } elseif ($attribute->status == SETTLED) {
            $driver->total_withdrawn -= $amount;
            $driver->pending_balance += $amount;
            $driver->save();

            //Driver transaction (debit)
            $second_trx = new Transaction();
            $second_trx->attribute = 'pending_withdraw_reverse';
            $second_trx->attribute_id = $attribute->id;
            $second_trx->debit = $amount;
            $second_trx->balance = $driver->pending_balance;
            $second_trx->user_id = $user->id;
            $second_trx->account = 'withdraw_balance_reverse';
            $second_trx->trx_type = 'Requested withdrawal deducted from driver withdraw balance reverse account';
            $second_trx->save();


            //Admin account update
            $admin = User::query()->where('user_type', 'super-admin')->first();
            $admin_user = UserAccount::query()->where('user_id', $admin->id)->first();
            $admin_user->payable_balance += $amount;
            $admin_user->save();

            //admin transaction (credit)
            $third_trx = new Transaction();
            $third_trx->attribute = 'withdraw_request_reverse';
            $third_trx->attribute_id = $attribute->id;
            $third_trx->credit = $amount;
            $third_trx->balance = $admin_user->payable_balance;
            $third_trx->user_id = $admin->id;
            $third_trx->account = 'withdraw_balance_reverse';
            $third_trx->trx_ref_id = $second_trx->id;
            $third_trx->trx_type = 'Requested withdrawal added to admin withdraw balance reverse account';
            $third_trx->save();
        }

        return $driver;
    }

    public function withdrawRequestCancelTransaction($user, $amount, $attribute)
    {
        DB::beginTransaction();
        //Driver account update
        $driver = UserAccount::where('user_id', $user->id)->first();
        $driver->receivable_balance += $amount;
        $driver->pending_balance -= $amount;
        $driver->save();

        //Driver transaction (debit)
        $second_trx = new Transaction();
        $second_trx->attribute = 'pending_withdraw_revoked';
        $second_trx->attribute_id = $attribute->id;
        $second_trx->debit = $amount;
        $second_trx->balance = $driver->pending_balance;
        $second_trx->user_id = $user->id;
        $second_trx->account = 'withdraw_balance_rejected';
        $second_trx->trx_type = 'Withdrawal deducted from driver withdraw balance rejected account';
        $second_trx->save();

        DB::commit();
        return $driver;
    }

    public function withdrawRequestAcceptTransaction($user, $amount, $attribute)
    {
        DB::beginTransaction();
        //driver account update
        $customer = UserAccount::where('user_id', $user->id)->first();
        $customer->pending_balance -= $amount;
        $customer->total_withdrawn += $amount;
        $customer->save();

        //driver transaction (credit)
        $second_trx = new Transaction();
        $second_trx->attribute = 'withdraw_request_accepted';
        $second_trx->attribute_id = $attribute->id;
        $second_trx->credit = $amount;
        $second_trx->balance = $customer->total_withdrawn;
        $second_trx->user_id = $user->id;
        $second_trx->account = 'received_withdraw_balance';
        $second_trx->trx_type = 'Withdrawal added to driver received withdraw balance account';
        $second_trx->save();


        //Admin account update
        $admin = User::query()->where('user_type', 'super-admin')->first();
        $admin_user = UserAccount::query()->where('user_id', $admin->id)->first();
        $admin_user->payable_balance -= $amount;
        $admin_user->save();

        //admin transaction (debit)
        $third_trx = new Transaction();
        $third_trx->attribute = 'withdraw_request_approved';
        $third_trx->attribute_id = $attribute->id;
        $third_trx->debit = $amount;
        $third_trx->balance = $admin_user->payable_balance;
        $third_trx->user_id = $admin->id;
        $third_trx->account = 'withdraw_balance_paid';
        $third_trx->trx_ref_id = $second_trx->id;
        $third_trx->trx_type = 'Requested withdrawal deducted from admin withdraw balance paid account';
        $third_trx->save();

        DB::commit();

        return $customer;
    }


    public function customerLevelRewardTransaction($user, $amount): void
    {
        DB::beginTransaction();
        //Customer account update
        $customer = UserAccount::query()->firstWhere('user_id', $user->id);
        $customer->wallet_balance += $amount;
        $customer->save();

        //customer transaction (credit)
        $primary_transaction = new Transaction();
        $primary_transaction->attribute = 'level_reward';
        $primary_transaction->credit = $amount;
        $primary_transaction->balance = $customer->wallet_balance;
        $primary_transaction->user_id = $user->id;
        $primary_transaction->account = 'wallet_balance';
        $primary_transaction->trx_type = 'Level-up reward added to customer wallet';
        $primary_transaction->save();

        DB::commit();
    }

    public function driverLevelRewardTransaction($user, $amount): void
    {
        DB::beginTransaction();
        //Customer account update
        $driver = UserAccount::query()->firstWhere('user_id', $user->id);
        $driver->receivable_balance += $amount;
        $driver->save();

        //customer transaction (credit)
        $primary_transaction = new Transaction();
        $primary_transaction->attribute = 'level_reward';
        $primary_transaction->credit = $amount;
        $primary_transaction->balance = $driver->receivable_balance;
        $primary_transaction->user_id = $user->id;
        $primary_transaction->account = 'receivable_balance';
        $primary_transaction->trx_type = 'Level-up reward added to driver receivable balance';
        $primary_transaction->save();

        DB::commit();
    }

    #referral earning transaction
    public function customerReferralEarningTransaction($user, $amount): void
    {
        DB::beginTransaction();
        //Customer account update
        $customer = UserAccount::query()->firstWhere('user_id', $user->id);
        $customer->wallet_balance += $amount;
        $customer->referral_earn += $amount;
        $customer->save();

        //customer transaction (credit)
        $primary_transaction = new Transaction();
        $primary_transaction->attribute = 'referral_earning';
        $primary_transaction->credit = $amount;
        $primary_transaction->balance = $customer->wallet_balance;
        $primary_transaction->user_id = $user->id;
        $primary_transaction->account = 'wallet_balance';
        $primary_transaction->trx_type = 'Referral earnings added to customer wallet';
        $primary_transaction->save();

        DB::commit();
    }

    public function driverReferralEarningTransaction($user, $amount): void
    {
        DB::beginTransaction();
        $driver = UserAccount::query()->firstWhere('user_id', $user->id);
        $driver->receivable_balance += $amount;
        $driver->referral_earn += $amount;
        $driver->save();

        //driver transaction (credit)
        $primary_transaction = new Transaction();
        $primary_transaction->attribute = 'referral_earning';
        $primary_transaction->credit = $amount;
        $primary_transaction->balance = $driver->receivable_balance;
        $primary_transaction->user_id = $user->id;
        $primary_transaction->account = 'receivable_balance';
        $primary_transaction->trx_type = 'Referral earnings added to driver receivable account';
        $primary_transaction->save();
        DB::commit();
    }

    #end referral earning transaction

    public function collectCashWithoutAdjustTransaction($user, $amount, $platform = null)
    {
        DB::beginTransaction();

        //Driver account update
        $driverAccount = UserAccount::query()->firstWhere('user_id', $user->id);
        $driverAccount->payable_balance -= $amount;
        $driverAccount->save();
        //Driver transaction (debit)
        $driverAccount_transaction = new Transaction();
        $driverAccount_transaction->attribute = 'admin_cash_collect';
        $driverAccount_transaction->debit = $amount;
        $driverAccount_transaction->balance = $driverAccount->payable_balance;
        $driverAccount_transaction->user_id = $user->id;
        $driverAccount_transaction->account = 'payable_balance';
        $driverAccount_transaction->trx_type = 'Cash collected by admin deducted from driver payable balance';
        $driverAccount_transaction->save();

        //Admin account update
        $adminUserId = User::where('user_type', ADMIN_USER_TYPES[0])->first()->id;
        $account = UserAccount::query()->firstWhere('user_id', $adminUserId);
        $account->received_balance += $amount;
        $account->save();


        //Admin transaction (credit)
        $admin_transaction = new Transaction();
        $admin_transaction->attribute = 'admin_cash_collect';
        $admin_transaction->credit = $amount;
        $admin_transaction->balance = $account->received_balance;
        $admin_transaction->user_id = $adminUserId;
        $admin_transaction->account = 'received_balance';
        $admin_transaction->trx_ref_id = $driverAccount_transaction->id;
        $admin_transaction->trx_type = 'Collected cash added to admin received balance';
        $admin_transaction->save();
        $push = ($platform == 'api') ? getNotification('digital_payment_successful') : getNotification('admin_collected_cash');
        sendDeviceNotification(fcm_token: $user->fcm_token,
            title: translate(key: $push['title'], locale: $user?->current_language_key),
            description: textVariableDataFormat(value: $push['description'], paidAmount: getCurrencyFormat($amount), locale: $user?->current_language_key),
            status: $push['status'],
            notification_type: 'fund',
            action: $push['action'],
            user_id: $user->id,
        );
        DB::commit();
    }

    public function collectCashWithAdjustTransaction($user, $amount, $platform = null)
    {
        DB::beginTransaction();
        //Driver account update
        $driverAccount = UserAccount::query()->firstWhere('user_id', $user->id);
        $receivableAmount = $driverAccount?->receivable_balance;

        // driver account update
        $driverAccount->payable_balance -= ($receivableAmount + $amount);
        $driverAccount->receivable_balance -= $receivableAmount;
        $driverAccount->received_balance += $receivableAmount;
        $driverAccount->save();

        //Admin account update
        $adminUserId = User::where('user_type', ADMIN_USER_TYPES[0])->first()->id;
        $account = UserAccount::query()->firstWhere('user_id', $adminUserId);
        $account->received_balance += ($receivableAmount + $amount);
        $account->receivable_balance -= $receivableAmount;
        $account->payable_balance -= $receivableAmount;
        $account->save();

        //Driver transaction (debit) for payable balance
        $driverAccount_transaction = new Transaction();
        $driverAccount_transaction->attribute = 'admin_cash_collect';
        $driverAccount_transaction->debit = $amount;
        $driverAccount_transaction->balance = $driverAccount->payable_balance;
        $driverAccount_transaction->user_id = $user->id;
        $driverAccount_transaction->account = 'payable_balance';
        $driverAccount_transaction->trx_type = 'Collected cash by admin deducted from driver payable balance';
        $driverAccount_transaction->save();

        //Admin transaction (credit)
        $admin_transaction = new Transaction();
        $admin_transaction->attribute = 'admin_cash_collect';
        $admin_transaction->credit = $amount;
        $admin_transaction->balance = $account->received_balance;
        $admin_transaction->user_id = $adminUserId;
        $admin_transaction->account = 'received_balance';
        $admin_transaction->trx_ref_id = $driverAccount_transaction->id;
        $admin_transaction->trx_type = 'Collected cash by admin added to admin received balance';
        $admin_transaction->save();

        //Admin transaction 2 (debit)
        $admin_transaction_2 = new Transaction();
        $admin_transaction_2->attribute = 'admin_cash_collect';
        $admin_transaction_2->debit = $amount;
        $admin_transaction_2->balance = $account->receivable_balance;
        $admin_transaction_2->user_id = $adminUserId;
        $admin_transaction_2->account = 'receivable_balance';
        $admin_transaction_2->trx_ref_id = $driverAccount_transaction->id;
        $admin_transaction_2->trx_type = 'Collected cash deducted from admin receivable balance';
        $admin_transaction_2->save();

        #Adjustment transaction

        //admin transaction (debit)
        $third_trx = new Transaction();
        $third_trx->attribute = 'adjust_payable_balance';
        $third_trx->debit = $receivableAmount;
        $third_trx->balance = $account->payable_balance;
        $third_trx->user_id = $user->id;
        $third_trx->account = 'payable_balance';
        $third_trx->trx_ref_id = $driverAccount_transaction->id;
        $third_trx->trx_type = 'Receivable amount deducted from admin payable balance';
        $third_trx->save();

        //Driver transaction (debit)
        $driverAccount_transaction2 = new Transaction();
        $driverAccount_transaction2->attribute = 'adjust_receivable_balance';
        $driverAccount_transaction2->debit = $receivableAmount;
        $driverAccount_transaction2->balance = $driverAccount->receivable_balance;
        $driverAccount_transaction2->user_id = $user->id;
        $driverAccount_transaction2->account = 'receivable_balance';
        $driverAccount_transaction2->trx_ref_id = $third_trx->id;
        $driverAccount_transaction2->trx_type = 'Receivable amount deducted from driver receivable balance';
        $driverAccount_transaction2->save();

        $driverAccount_transaction3 = new Transaction();
        $driverAccount_transaction3->attribute = 'adjust_received_balance';
        $driverAccount_transaction3->credit = $receivableAmount;
        $driverAccount_transaction3->balance = $driverAccount->received_balance;
        $driverAccount_transaction3->user_id = $user->id;
        $driverAccount_transaction3->account = 'received_balance';
        $driverAccount_transaction3->trx_ref_id = $third_trx->id;
        $driverAccount_transaction3->trx_type = 'Receivable amount added to driver received balance';
        $driverAccount_transaction3->save();

        $push = ($platform == 'api') ? getNotification('digital_payment_successful') : getNotification('admin_collected_cash');
        sendDeviceNotification(fcm_token: $user->fcm_token,
            title: translate(key: $push['title'], locale: $user?->current_language_key),
            description: textVariableDataFormat(value: $push['description'], paidAmount: getCurrencyFormat($amount), locale: $user?->current_language_key),
            status: $push['status'],
            notification_type: 'fund',
            action: $push['action'],
            user_id: $user->id,
        );
        DB::commit();
    }

    public function returnTimeExceedFeeTransaction($trip)
    {
        $parcelReturnTimeFeeStatus = businessConfig('parcel_return_time_fee_status', PARCEL_SETTINGS)?->value ?? false;
        if ($parcelReturnTimeFeeStatus) {
            $returnTimeExceedFee = (double)businessConfig('return_fee_for_driver_time_exceed', PARCEL_SETTINGS)?->value ?? 0;
            if ((Carbon::parse($trip->return_time))->lessThan(Carbon::now()) && $returnTimeExceedFee > 0) {
                $adminUserId = User::where('user_type', ADMIN_USER_TYPES[0])->first()->id;
                DB::beginTransaction();

                //Rider account update
                $riderAccount = UserAccount::where('user_id', $trip->driver->id)->first();
                $riderAccount->payable_balance += $returnTimeExceedFee;
                $riderAccount->save();

                //Rider account update transaction 1
                $riderTransaction1 = new Transaction();
                $riderTransaction1->attribute = 'return_time_exceed_fee';
                $riderTransaction1->attribute_id = $trip->id;
                $riderTransaction1->debit = $returnTimeExceedFee;
                $riderTransaction1->balance = $riderAccount->payable_balance;
                $riderTransaction1->user_id = $trip->driver->id;
                $riderTransaction1->account = 'payable_balance';
                $riderTransaction1->trx_type = 'Return time exceed fee deducted from driver receivable balance';
                $riderTransaction1->save();

                //Admin account update
                $adminAccount = UserAccount::where('user_id', $adminUserId)->first();
                $adminAccount->receivable_balance += $returnTimeExceedFee; //30
                $adminAccount->save();

                //Admin transaction 1
                $adminTransaction = new Transaction();
                $adminTransaction->attribute = 'return_time_exceed_fee';
                $adminTransaction->attribute_id = $trip->id;
                $adminTransaction->credit = $returnTimeExceedFee;
                $adminTransaction->balance = $adminAccount->receivable_balance;
                $adminTransaction->user_id = $adminUserId;
                $adminTransaction->account = 'receivable_balance';
                $adminTransaction->trx_ref_id = $riderTransaction1->id;
                $adminTransaction->trx_type= 'Return time exceed fee added to admin receivable balance';
                $adminTransaction->save();
                DB::commit();
            }
        }

    }

    public function parcelRefundDriverTransaction($trip,$amount)
    {
        $adminUserId = User::where('user_type', ADMIN_USER_TYPES[0])->first()->id;
        //Rider account update (+ payable)
        $riderAccount = UserAccount::where('user_id', $trip->driver->id)->first();
        $riderAccount->payable_balance += $amount;
        $riderAccount->save();
        //Rider transaction 1
        $riderTransaction = new Transaction();
        $riderTransaction->attribute = 'parcel_refund_amount_deducted';
        $riderTransaction->attribute_id = $trip->id;
        $riderTransaction->credit = $amount;
        $riderTransaction->balance = $riderAccount->payable_balance;
        $riderTransaction->user_id = $trip->driver->id;
        $riderTransaction->account = 'payable_balance';
        $riderTransaction->trx_type = 'Refunded amount added to driver payable balance';
        $riderTransaction->save();

        //Admin account update (receivable_balance +)
        $adminAccount = UserAccount::where('user_id', $adminUserId)->first();
//        $adminAccount->payable_balance += $amount;
        $adminAccount->receivable_balance += $amount;
        $adminAccount->save();


        //Admin transaction 1 ( + receiveable balance)
        $adminTransaction1 = new Transaction();
        $adminTransaction1->attribute = 'parcel_refund_amount_deducted';
        $adminTransaction1->attribute_id = $trip->id;
        $adminTransaction1->credit = $amount;
        $adminTransaction1->balance = $adminAccount->receivable_balance;
        $adminTransaction1->user_id = $adminUserId;
        $adminTransaction1->account = 'received_balance';
        $adminTransaction1->trx_ref_id = $riderTransaction->id;
        $adminTransaction1->trx_type = 'Refund amount added to admin receivable balance';
        $adminTransaction1->save();

//        //Admin transaction 2 ( + receiveable balance)
//        $adminTransaction2 = new Transaction();
//        $adminTransaction2->attribute = 'parcel_refund_amount_deducted';
//        $adminTransaction2->attribute_id = $trip->id;
//        $adminTransaction2->credit = $amount;
//        $adminTransaction2->balance = $adminAccount->payable_balance;
//        $adminTransaction2->user_id = $adminUserId;
//        $adminTransaction2->account = 'payable_balance';
//        $adminTransaction2->trx_ref_id = $riderTransaction->id;
//        $adminTransaction2->save();
    }

    public function parcelRefundWalletTransaction($trip,$amount)
    {
        $adminUserId = User::where('user_type', ADMIN_USER_TYPES[0])->first()->id;

        DB::beginTransaction();
        //customer account credit
        $customerAccount = UserAccount::where('user_id', $trip->customer->id)->first();
        $customerAccount->wallet_balance += $amount;
        $customerAccount->save();

        //customer transaction (credit)
        $customerTransaction = new Transaction();
        $customerTransaction->attribute = 'parcel_refund_wallet';
        $customerTransaction->attribute_id = $trip->id;
        $customerTransaction->credit = $amount;
        $customerTransaction->balance = $customerAccount->wallet_balance;
        $customerTransaction->user_id = $trip->customer->id;
        $customerTransaction->account = 'wallet_balance';
        $customerTransaction->trx_type = 'Refund amount added to customer wallet balance';
        $customerTransaction->save();

        //Admin account update (payable and wallet balance +)
        $adminAccount = UserAccount::where('user_id', $adminUserId)->first();
        $adminAccount->payable_balance -= $amount;
        $adminAccount->save();

        //Admin transaction 1 (payable)
        $adminTransaction1 = new Transaction();
        $adminTransaction1->attribute = 'parcel_refund_wallet';
        $adminTransaction1->attribute_id = $trip->id;
        $adminTransaction1->debit = $amount;
        $adminTransaction1->balance = $adminAccount->payable_balance;
        $adminTransaction1->user_id = $adminUserId;
        $adminTransaction1->account = 'payable_balance';
        $adminTransaction1->trx_ref_id = $customerTransaction->id;
        $adminTransaction1->trx_type = 'Refund amount deducted from admin payable balance';
        $adminTransaction1->save();
        DB::commit();
    }

    public function addWalletFundDigitally($customer, array $data, $attribute)
    {
        $addedBonus = $data['added_bonus'] ?? 0;

        $baseTransaction = [
            'id' => Str::uuid(),
            'balance' => $customer->wallet_balance - $addedBonus,
            'attribute' => $attribute,
            'account' => 'wallet_balance',
            'credit' => $data['amount'],
            'added_bonus' => $addedBonus,
            'user_id' => $customer->user_id,
            'reference' => $data['reference'] ?? null,
            'trx_type' => 'Amount added to customer wallet balance',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::transaction(function () use ($baseTransaction, $customer, $addedBonus, $data) {
            $mainTransaction = Transaction::create($baseTransaction);

            if ($addedBonus > 0) {
                Transaction::create([
                    'id' => Str::uuid(),
                    'balance' => $customer->wallet_balance,
                    'attribute' => 'wallet_bonus',
                    'account' => 'wallet_balance',
                    'credit' => $addedBonus,
                    'added_bonus' => 0,
                    'user_id' => $customer->user_id,
                    'reference' => $data['reference'] ?? null,
                    'trx_ref_id' => $mainTransaction->id,
                    'trx_type' => 'Bonus for transaction: ' . $mainTransaction->id . ' added to wallet balance',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        $push = getNotification('fund_added_digitally');
        sendDeviceNotification(fcm_token: $customer->user->fcm_token,
            title: translate(key: $push['title'], locale: $customer->user?->current_language_key),
            description: textVariableDataFormat(value: $push['description'], paidAmount: set_currency_symbol($data['amount']), bonusAmount: set_currency_symbol($addedBonus), totalAmount: set_currency_symbol($data['amount'] + $addedBonus), locale: $customer->user?->current_language_key),
            status: $push['status'],
            notification_type: 'fund',
            action: $push['action'],
            user_id: $customer->user->id,
        );

        return true;
    }

}
