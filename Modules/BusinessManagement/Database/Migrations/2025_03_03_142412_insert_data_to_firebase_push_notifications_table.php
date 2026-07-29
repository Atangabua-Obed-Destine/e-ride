<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('firebase_push_notifications')) {
            DB::table('firebase_push_notifications')->truncate();
            DB::table('firebase_push_notifications')->insert([
                ['name' => 'trip_started', 'value' => 'Your trip is started.', 'status' => 1, 'type' => 'regular_trip', 'group' => 'customer', 'action' => 'trip_started', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'trip_completed', 'value' => 'Your trip is completed.', 'status' => 1, 'type' => 'regular_trip', 'group' => 'customer', 'action' => 'trip_completed', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'trip_canceled', 'value' => 'Your trip is cancelled.', 'status' => 1, 'type' => 'regular_trip', 'group' => 'customer', 'action' => 'trip_canceled', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'trip_paused', 'value' => 'Trip request is paused.', 'status' => 1, 'type' => 'regular_trip', 'group' => 'customer', 'action' => 'trip_paused', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'trip_resumed', 'value' => 'Trip request is resumed.', 'status' => 1, 'type' => 'regular_trip', 'group' => 'customer', 'action' => 'trip_resumed', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'another_driver_assigned', 'value' => 'Another driver already accepted the trip request.', 'status' => 1, 'type' => 'regular_trip', 'group' => 'customer', 'action' => 'another_driver_assigned', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'driver_on_the_way', 'value' => 'Driver accepted your trip request.', 'status' => 1, 'type' => 'regular_trip', 'group' => 'customer', 'action' => 'driver_on_the_way', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'bid_request_from_driver', 'value' => 'Driver sent a bid request', 'status' => 1, 'type' => 'regular_trip', 'group' => 'customer', 'action' => 'bid_request_from_driver', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'driver_canceled_ride_request', 'value' => 'Driver has canceled your ride.', 'status' => 1, 'type' => 'regular_trip', 'group' => 'customer', 'action' => 'driver_canceled_ride_request', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'payment_successful', 'value' => '{paidAmount} payment successful on this trip by {methodName}.', 'status' => 1, 'type' => 'regular_trip', 'group' => 'customer', 'action' => 'payment_successful', 'created_at' => now(), 'updated_at' => now()],

                ['name' => 'new_ride_request', 'value' => 'You have a new ride request.', 'status' => 1, 'type' => 'regular_trip', 'group' => 'driver', 'action' => 'new_ride_request', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'bid_accepted', 'value' => 'Customer confirmed your bid.', 'status' => 1, 'type' => 'regular_trip', 'group' => 'driver', 'action' => 'bid_accepted', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'trip_request_canceled', 'value' => 'A trip request is cancelled.', 'status' => 1, 'type' => 'regular_trip', 'group' => 'driver', 'action' => 'trip_request_canceled', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'customer_canceled_trip', 'value' => 'Customer just declined a request.', 'status' => 1, 'type' => 'regular_trip', 'group' => 'driver', 'action' => 'customer_canceled_trip', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'bid_request_canceled_by_customer', 'value' => 'Customer has canceled your bid request.', 'status' => 1, 'type' => 'regular_trip', 'group' => 'driver', 'action' => 'bid_request_canceled_by_customer', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'tips_from_customer', 'value' => 'Customer has given the tips {tipsAmount} with payment.', 'status' => 1, 'type' => 'regular_trip', 'group' => 'driver', 'action' => 'tips_from_customer', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'received_new_bid', 'value' => 'Received a new bid request.', 'status' => 1, 'type' => 'regular_trip', 'group' => 'driver', 'action' => 'received_new_bid', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'customer_rejected_bid', 'value' => 'We regret to inform you that your bid request for trip ID {tripId} has been rejected by the customer.', 'status' => 1, 'type' => 'regular_trip', 'group' => 'driver', 'action' => 'customer_rejected_bid', 'created_at' => now(), 'updated_at' => now()],

                ['name' => 'new_parcel', 'value' => 'You have a new parcel request.', 'status' => 1, 'type' => 'parcel', 'group' => 'customer', 'action' => 'new_parcel', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'parcel_picked_up', 'value' => 'Parcel Picked-up.', 'status' => 1, 'type' => 'parcel', 'group' => 'customer', 'action' => 'parcel_picked_up', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'parcel_on_the_way', 'value' => 'Parcel on the way.', 'status' => 1, 'type' => 'parcel', 'group' => 'customer', 'action' => 'parcel_on_the_way', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'parcel_delivery_completed', 'value' => 'Parcel delivered successfully.', 'status' => 1, 'type' => 'parcel', 'group' => 'customer', 'action' => 'parcel_delivery_completed', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'parcel_canceled', 'value' => 'Parcel Cancel.', 'status' => 1, 'type' => 'parcel', 'group' => 'customer', 'action' => 'parcel_canceled', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'parcel_returned', 'value' => 'Parcel returned successfully.', 'status' => 1, 'type' => 'parcel', 'group' => 'customer', 'action' => 'parcel_returned', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'parcel_returning_otp', 'value' => 'Your parcel returning OTP is {otp}.', 'status' => 1, 'type' => 'parcel', 'group' => 'customer', 'action' => 'parcel_returning_otp', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'refund_accepted', 'value' => 'For parcel ID #{parcelId} your refund request has been approved by admin. You will be refunded soon.', 'status' => 1, 'type' => 'parcel', 'group' => 'customer', 'action' => 'refund_accepted', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'refund_denied', 'value' => 'For parcel ID #{parcelId} your refund request has been denied by admin. You can check the denied reason from parcel details.', 'status' => 1, 'type' => 'parcel', 'group' => 'customer', 'action' => 'refund_denied', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'refunded_to_wallet', 'value' => 'For parcel ID # {parcelId}, your refund request has been approved by admin and {approximateAmount} refunded to your Wallet.', 'status' => 1, 'type' => 'parcel', 'group' => 'customer', 'action' => 'refunded_to_wallet', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'refunded_as_coupon', 'value' => 'For parcel ID # {parcelId}, your refund request has been approved by admin and {approximateAmount} has been issued as a coupon. You can use this coupon for your trip whenever you like.', 'status' => 1, 'type' => 'parcel', 'group' => 'customer', 'action' => 'refunded_as_coupon', 'created_at' => now(), 'updated_at' => now()],

                ['name' => 'new_parcel_request', 'value' => 'New Parcel Request.', 'status' => 1, 'type' => 'parcel', 'group' => 'driver', 'action' => 'new_parcel_request', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'parcel_amount_deducted', 'value' => 'Due to a damage parcel ID #{parcelId} claimed by customer, {approximateAmount} will be deducted from your wallet. If you want to avoid the fine contact with admin.', 'status' => 1, 'type' => 'parcel', 'group' => 'driver', 'action' => 'parcel_amount_deducted', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'refund_accepted', 'value' => 'Refund request of parcel ID #{parcelId} has been approved by Admin. If you have any quarries please contact with admin.', 'status' => 1, 'type' => 'parcel', 'group' => 'driver', 'action' => 'refund_accepted', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'refund_denied', 'value' => 'Refund request of parcel ID #{parcelId} has been denied by Admin. You don’t need to worry.', 'status' => 1, 'type' => 'parcel', 'group' => 'driver', 'action' => 'refund_denied', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'parcel_amount_debited', 'value' => 'Due to a damaged parcel, {approximateAmount} has been deducted from your wallet. Please settle the amount as soon as possible and check the parcel details.', 'status' => 1, 'type' => 'parcel', 'group' => 'driver', 'action' => 'parcel_amount_debited', 'created_at' => now(), 'updated_at' => now()],

                ['name' => 'registration_approved', 'value' => 'Admin approved your registration. You can login now.', 'status' => 1, 'type' => 'driver_registration', 'group' => 'driver', 'action' => 'registration_approved', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'vehicle_request_approved', 'value' => 'Your vehicle is approved by admin.', 'status' => 1, 'type' => 'driver_registration', 'group' => 'driver', 'action' => 'vehicle_request_approved', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'vehicle_request_denied', 'value' => 'Your vehicle request is denied.', 'status' => 1, 'type' => 'driver_registration', 'group' => 'driver', 'action' => 'vehicle_request_denied', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'identity_image_rejected', 'value' => 'Your identity image update request is rejected.', 'status' => 1, 'type' => 'driver_registration', 'group' => 'driver', 'action' => 'identity_image_rejected', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'identity_image_approved', 'value' => 'Your identity image update request is approved.', 'status' => 1, 'type' => 'driver_registration', 'group' => 'driver', 'action' => 'identity_image_approved', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'vehicle_active', 'value' => 'Your vehicle status has been activated by admin.', 'status' => 1, 'type' => 'driver_registration', 'group' => 'driver', 'action' => 'vehicle_active', 'created_at' => now(), 'updated_at' => now()],

                ['name' => 'coupon_applied', 'value' => 'Customer got discount of.', 'status' => 1, 'type' => 'others', 'group' => 'coupon', 'action' => 'coupon_applied', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'coupon_removed', 'value' => 'Customer removed previously applied coupon.', 'status' => 1, 'type' => 'others', 'group' => 'coupon', 'action' => 'coupon_removed', 'created_at' => now(), 'updated_at' => now()],

                ['name' => 'review_from_customer', 'value' => 'New review from a customer! See what they had to say about your service.', 'status' => 1, 'type' => 'others', 'group' => 'review', 'action' => 'review_from_customer', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'review_from_driver', 'value' => 'New review from a driver! See what he had to say about your trip.', 'status' => 1, 'type' => 'others', 'group' => 'review', 'action' => 'review_from_driver', 'created_at' => now(), 'updated_at' => now()],

                ['name' => 'someone_used_your_code', 'value' => 'Your code was successfully used by a friend. You\'ll receive your reward after their first ride is completed.', 'status' => 1, 'type' => 'others', 'group' => 'referral', 'action' => 'someone_used_your_code', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'referral_reward_received', 'value' => 'You\'ve successfully received {referralRewardAmount} reward. You can use this amount on your next ride.', 'status' => 1, 'type' => 'others', 'group' => 'referral', 'action' => 'referral_reward_received', 'created_at' => now(), 'updated_at' => now()],

                ['name' => 'safety_alert_sent', 'value' => 'Safety Alert Sent.', 'status' => 1, 'type' => 'others', 'group' => 'safety_alert', 'action' => 'safety_alert_sent', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'safety_problem_resolved', 'value' => 'Safety Problem Resolved.', 'status' => 1, 'type' => 'others', 'group' => 'safety_alert', 'action' => 'safety_problem_resolved', 'created_at' => now(), 'updated_at' => now()],

                ['name' => 'terms_and_conditions_updated', 'value' => 'Admin just updated system terms and conditions.', 'status' => 1, 'type' => 'others', 'group' => 'business_page', 'action' => 'terms_and_conditions_updated', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'privacy_policy_updated', 'value' => 'Admin just updated our privacy policy.', 'status' => 1, 'type' => 'others', 'group' => 'business_page', 'action' => 'privacy_policy_updated', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'legal_updated', 'value' => 'We have updated our legal.', 'status' => 1, 'type' => 'others', 'group' => 'business_page', 'action' => 'legal_updated', 'created_at' => now(), 'updated_at' => now()],

                ['name' => 'new_message', 'value' => 'You got a new message from {userName}.', 'status' => 1, 'type' => 'others', 'group' => 'chatting', 'action' => 'new_message', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'admin_message', 'value' => 'You got a new message from admin.', 'status' => 1, 'type' => 'others', 'group' => 'chatting', 'action' => 'admin_message', 'created_at' => now(), 'updated_at' => now()],

                ['name' => 'level_up', 'value' => 'You have completed your challenges and reached level {levelName}.', 'status' => 1, 'type' => 'others', 'group' => 'level', 'action' => 'level_up', 'created_at' => now(), 'updated_at' => now()],

                ['name' => 'fund_added_by_admin', 'value' => 'Admin has added {walletAmount} to your wallet.', 'status' => 1, 'type' => 'others', 'group' => 'fund', 'action' => 'fund_added_by_admin', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'admin_collected_cash', 'value' => 'Admin collected cash.', 'status' => 1, 'type' => 'others', 'group' => 'fund', 'action' => 'admin_collected_cash', 'created_at' => now(), 'updated_at' => now()],

                ['name' => 'withdraw_request_rejected', 'value' => 'Unfortunately, your withdrawal request has been rejected. {withdrawNote}.', 'status' => 1, 'type' => 'others', 'group' => 'withdraw_request', 'action' => 'withdraw_request_rejected', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'withdraw_request_approved', 'value' => 'We are pleased to inform you that your withdrawal request has been approved. The funds will be transferred to your account shortly.', 'status' => 1, 'type' => 'others', 'group' => 'withdraw_request', 'action' => 'withdraw_request_approved', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'withdraw_request_settled', 'value' => 'Your withdrawal request has been successfully settled. The funds have been transferred to your account.', 'status' => 1, 'type' => 'others', 'group' => 'withdraw_request', 'action' => 'withdraw_request_settled', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'withdraw_request_reversed', 'value' => 'Your withdrawal request has been successfully settled. The funds have been transferred to your account.', 'status' => 1, 'type' => 'others', 'group' => 'withdraw_request', 'action' => 'withdraw_request_reversed', 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('firebase_push_notifications')->truncate();
    }
};
