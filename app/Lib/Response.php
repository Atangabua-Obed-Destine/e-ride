<?php

//default responses
const DEFAULT_200 = [
    'response_code' => 'default_200',
    'message' => 'Successfully loaded'
];

const DEFAULT_SENT_OTP_200 = [
    'response_code' => 'default_200',
    'message' => 'Successfully sent OTP'
];

const DEFAULT_VERIFIED_200 = [
    'response_code' => 'default_verified_200',
    'message' => 'Successfully verified'
];

const DEFAULT_EXPIRED_200 = [
    'response_code' => 'default_expired_200',
    'message' => 'Resource expired'
];

const COUPON_404 = [
    'response_code' => 'coupon_404',
    'message' => 'coupon not found or not applicable'
];

const DEFAULT_PASSWORD_RESET_200 = [
    'response_code' => 'default_password_reset_200',
    'message' => 'Password reset successful'
];

const DEFAULT_PASSWORD_CHANGE_200 = [
    'response_code' => 'default_password_change_200',
    'message' => 'Password changed successful'
];

const DEFAULT_PASSWORD_MISMATCH_403 = [
    'response_code' => 'default_password_mismatch_403',
    'message' => 'Given password does not match with previous password'
];

const NO_CHANGES_FOUND = [
    'response_code' => 'no_changes_found_200',
    'message' => 'No changes found'
];

const DEFAULT_204 = [
    'response_code' => 'default_204',
    'message' => 'Information not found'
];

const NO_DATA_200 = [
    'response_code' => 'no_data_found_200',
    'message' => 'No data found'
];
const DEFAULT_400 = [
    'response_code' => 'default_400',
    'message' => 'Invalid or missing information'
];

const DEFAULT_401 = [
    'response_code' => 'default_401',
    'message' => 'Credential does not match'
];

const DEFAULT_EXISTS_203 = [
    'response_code' => 'default_exists_203',
    'message' => 'Resource already exists'
];

const DEFAULT_USER_REMOVED_401 = [
    'response_code' => 'default_user_removed_401',
    'message' => 'User has been removed, please talk to the authority'
];

const USER_404 = [
    'response_code' => 'user_404',
    'message' => 'User not found'
];

const DEFAULT_USER_UNDER_REVIEW_DISABLED_401 = [
    'response_code' => 'default_user_under_review_or_disabled_401',
    'message' => 'Your account is under review'
];

const DEFAULT_USER_DISABLED_401 = [
    'response_code' => 'default_user_disabled_401',
    'message' => 'User has been disabled, please talk to the authority'
];

const DEFAULT_403 = [
    'response_code' => 'default_403',
    'message' => 'Your access has been denied'
];
const WITHDRAW_METHOD_INFO_EXIST_403 = [
    'response_code' => 'withdraw_method_info_exist_403',
    'message' => 'Your withdraw method info already exists.'
];

const DEFAULT_NOT_ACTIVE = [
    'response_code' => 'default_not_active_200',
    'message' => 'Retrieved data is not active'
];


const DEFAULT_404 = [
    'response_code' => 'default_404',
    'message' => 'Resource not found'
];

const TRIP_REQUEST_PAUSED_404 = [
    'response_code' => 'trip_request_paused_404',
    'message' => 'Trip is paused, status can not be updated'
];

const OFFLINE_403 = [
    'response_code' => 'offline_403',
    'message' => 'Can not go to offline during running trip',
];

const AMOUNT_400 = [
    'response_code' => 'amount_400',
    'message' => 'Requested amount is greater than available amount'
];

const DEFAULT_DELETE_200 = [
    'response_code' => 'default_delete_200',
    'message' => 'Successfully deleted information'
];

const DEFAULT_FAIL_200 = [
    'response_code' => 'default_fail_200',
    'message' => 'Action failed'
];

const DEFAULT_PAID_200 = [
    'response_code' => 'default_paid_200',
    'message' => 'Already paid'
];

const DEFAULT_LAT_LNG_400 = [
    'response_code' => 'default_lat_lng_400',
    'message' => 'Pick up or Destination points are wrong!'
];


const DEFAULT_STORE_200 = [
    'response_code' => 'default_store_200',
    'message' => 'Successfully added'
];

const DEFAULT_UPDATE_200 = [
    'response_code' => 'default_update_200',
    'message' => 'Successfully updated'
];

const DEFAULT_RESTORE_200 = [
    'response_code' => 'default_restore_200',
    'message' => 'Successfully restored'
];

const DEFAULT_STATUS_UPDATE_200 = [
    'response_code' => 'default_status_update_200',
    'message' => 'Successfully status updated'
];

const TOO_MANY_ATTEMPT_403 = [
    'response_code' => 'too_many_attempt_403',
    'message' => 'Your api hit limit exceeded, try after a minute.'
];


const REGISTRATION_200 = [
    'response_code' => 'registration_200',
    'message' => 'Successfully registered'
];

//auth module
const AUTH_LOGIN_200 = [
    'response_code' => 'auth_login_200',
    'message' => 'Successfully logged in'
];

const AUTH_LOGIN_406 = [
    'response_code' => 'auth_login_406',
    'message' => 'New user detected'
];

const AUTH_MERGE_ACCOUNT_407 = [
    'response_code' => 'auth_merge_account_407',
    'message' => 'Merge with existing account'
];

const AUTH_LOGOUT_200 = [
    'response_code' => 'auth_logout_200',
    'message' => 'Successfully logged out'
];

const ACCOUNT_DELETED_200 = [
    'response_code' => 'account_deleted_200',
    'message' => 'Your account is deleted successfully'
];

const AUTH_LOGIN_401 = [
    'response_code' => 'auth_login_401',
    'message' => 'User credential does not match'
];

const AUTH_UPDATE_PASSWORD_408 = [
    'response_code' => 'auth_update_password_408',
    'message' => 'Password needs to be updated'
];

const AUTH_LOGIN_404 = [
    'response_code' => 'auth_login_404',
    'message' => 'Incorrect phone number or password, Please try again'
];

const AUTH_OTP_LOGIN_404 = [
    'response_code' => 'auth_otp_login_404',
    'message' => 'Incorrect phone number, Please try again'
];

const USER_NOT_FOUND_404 = [
    'response_code' => 'user_not_found_404',
    'message' => 'No user found with that information'
];

const ACCOUNT_DISABLED = [
    'response_code' => 'account_disabled_401',
    'message' => 'User account has been disabled, please talk to the admin.'
];

const AUTH_LOGIN_403 = [
    'response_code' => 'auth_login_403',
    'message' => 'Wrong login credentials'
];


const ACCESS_DENIED = [
    'response_code' => 'access_denied_403',
    'message' => 'Access denied'
];


//user management module
const USER_ROLE_CREATE_400 = [
    'response_code' => 'user_role_create_400',
    'message' => 'Invalid or missing information'
];

const USER_ROLE_CREATE_200 = [
    'response_code' => 'user_role_create_200',
    'message' => 'Successfully added'
];

const USER_ROLE_UPDATE_200 = [
    'response_code' => 'user_role_update_200',
    'message' => 'Successfully updated'
];

const USER_ROLE_UPDATE_400 = [
    'response_code' => 'user_role_update_400',
    'message' => 'Invalid or missing data'
];

const DRIVER_STORE_200 = [
    'response_code' => 'driver_store_200',
    'message' => 'Successfully added'
];

const DRIVER_UPDATE_200 = [
    'response_code' => 'driver_store_200',
    'message' => 'Successfully updated'
];

const DRIVER_DELETE_200 = [
    'response_code' => 'driver_delete_200',
    'message' => 'Successfully deleted information'
];

const DRIVER_DELETE_403 = [
    'response_code' => 'driver_delete_403',
    'message' => 'Unable Delete Now'
];

const DRIVER_BID_NOT_FOUND_403 = [
    'response_code' => 'driver_bid_not_found_403',
    'message' => 'Driver cancel the bid or bid not available for this ride'
];

const DRIVER_403 = [
    'response_code' => 'driver_403',
    'message' => 'Driver is not available'
];
const CUSTOMER_STORE_200 = [
    'response_code' => 'customer_store_200',
    'message' => 'Successfully added'
];

const CUSTOMER_VERIFICATION_400 = [
    'response_code' => 'customer_verification_400',
    'message' => 'Please enable customer verification option'
];

const CUSTOMER_404 = [
    'response_code' => 'customer_404',
    'message' => 'Customer does not exists'
];
const DRIVER_404 = [
    'response_code' => 'driver_404',
    'message' => 'Driver does not exists'
];
const CUSTOMER_UPDATE_200 = [
    'response_code' => 'customer_store_200',
    'message' => 'Successfully updated'
];

const CUSTOMER_DELETE_200 = [
    'response_code' => 'customer_delete_200',
    'message' => 'Successfully deleted information'
];
const EMPLOYEE_STORE_200 = [
    'response_code' => 'employee_store_200',
    'message' => 'Successfully added'
];

const EMPLOYEE_UPDATE_200 = [
    'response_code' => 'employee_store_200',
    'message' => 'Successfully updated'
];

const EMPLOYEE_DELETE_200 = [
    'response_code' => 'employee_delete_200',
    'message' => 'Successfully deleted information'
];

const CUSTOMER_FUND_STORE_200 = [
    'response_code' => 'customer_fund_store_200',
    'message' => 'Successfully added'
];


// Vehicle Brand

const BRAND_CREATE_200 = [
    'response_code' => 'brand_create_200',
    'message' => 'Brand successfully added'
];

const BRAND_UPDATE_200 = [
    'response_code' => 'brand_update_200',
    'message' => 'Brand successfully updated'
];

const BRAND_DELETE_200 = [
    'response_code' => 'brand_update_200',
    'message' => 'Brand successfully deleted'
];

// Vehicle Model

const MODEL_CREATE_200 = [
    'response_code' => 'model_create_200',
    'message' => 'Model successfully added'
];

const MODEL_UPDATE_200 = [
    'response_code' => 'model_update_200',
    'message' => 'Model successfully updated'
];

const MODEL_EXISTS_400 = [
    'response_code' => 'model_exists_400',
    'message' => 'Model already exists!'
];

// Vehicle Category

const CATEGORY_CREATE_200 = [
    'response_code' => 'category_create_200',
    'message' => 'Category successfully added'
];

const NO_ACTIVE_CATEGORY_IN_ZONE_404 = [
    'response_code' => 'no_active_category_in_zone_404',
    'message' => 'There are no selected vehicle categories in your zone'
];

const CATEGORY_UPDATE_200 = [
    'response_code' => 'category_update_200',
    'message' => 'Category successfully updated'
];

const PARCEL_REFUND_ALREADY_EXIST_200 = [
    'response_code' => 'parcel_refund_already_exist_200',
    'message' => 'Parcel refund request already created for this parcel request'
];

const PARCEL_REFUND_CREATE_200 = [
    'response_code' => 'parcel_refund_create_200',
    'message' => 'Parcel refund request successfully added'
];

// Vehicle

const VEHICLE_CREATE_200 = [
    'response_code' => 'vehicle_create_200',
    'message' => 'Vehicle successfully added'
];

const VEHICLE_UPDATE_200 = [
    'response_code' => 'vehicle_update_200',
    'message' => 'Your vehicle information has been updated successfully.'
];


const VEHICLE_REQUEST_200 = [
    'response_code' => 'vehicle_request_200',
    'message' => 'Your request is submitted. Please wait for admin approval.'
];

const VEHICLE_UNCHANGED_200 = [
    'response_code' => 'vehicle_unchanged_200',
    'message' => 'You have not changed any information.'
];


const VEHICLE_DRIVER_EXISTS_403 = [
    'response_code' => 'vehicle_driver_exists_403',
    'message' => 'You have already created a vehicle.'
];

const LEVEL_CREATE_200 = [
    'response_code' => 'level_create_200',
    'message' => 'Level successfully added'
];

const LEVEL_UPDATE_200 = [
    'response_code' => 'level_update_200',
    'message' => 'Level successfully updated'
];

const LEVEL_DELETE_200 = [
    'response_code' => 'level_delete_200',
    'message' => 'Level successfully deleted'
];

const LEVEL_CREATE_403 = [
    'response_code' => 'level_create_403',
    'message' => 'First level sequence must be 1'
];

const LEVEL_403 = [
    'response_code' => 'level_403',
    'message' => 'Create a level first'
];

const LEVEL_DELETE_403 = [
    'response_code' => 'level_delete_403',
    'message' => 'Level delete restricted when users assigned in this level'
];


const BUSINESS_SETTING_UPDATE_200 = [
    'response_code' => 'business_setting_update_200',
    'message' => 'Settings successfully updated'
];

const SYSTEM_SETTING_UPDATE_200 = [
    'response_code' => 'system_setting_update_200',
    'message' => 'Settings successfully updated'
];


// Zone

const ZONE_STORE_200 = [
    'response_code' => 'zone_store_200',
    'message' => 'Zone successfully added'
];
const ZONE_STORE_INSTRUCTION_200 = [
    'response_code' => 'zone_store_200',
    'message' => 'Please setup the fares for this zone now'
];

const ZONE_UPDATE_200 = [
    'response_code' => 'zone_update_200',
    'message' => 'Zone successfully updated'
];

const ZONE_DESTROY_200 = [
    'response_code' => 'zone_destroy_200',
    'message' => 'Zone successfully deleted'
];

const ZONE_404 = [
    'response_code' => 'zone_404',
    'message' => 'Zone not found'
];

const ZONE_RESOURCE_404 = [
    'response_code' => 'zone_404',
    'message' => 'Operation service not available in this area'
];

const ROUTE_NOT_FOUND_404 = [
    'response_code' => 'route_404',
    'message' => 'Route not found your selected pickup & destination address'
];

// Area

const AREA_STORE_200 = [
    'response_code' => 'area_store_200',
    'message' => 'Area successfully added'
];

const AREA_UPDATE_200 = [
    'response_code' => 'area_update_200',
    'message' => 'Area successfully updated'
];

const AREA_DESTROY_200 = [
    'response_code' => 'area_destroy_200',
    'message' => 'Area successfully deleted'
];

const AREA_404 = [
    'response_code' => 'area_404',
    'message' => 'Area resource not found'
];

const AREA_RESOURCE_404 = [
    'response_code' => 'area_404',
    'message' => 'No provider or service is available within this area'
];


// Pick Hour

const PICK_HOUR_STORE_200 = [
    'response_code' => 'pick_hour_store_200',
    'message' => 'Pick Hour successfully added'
];

const PICK_HOUR_UPDATE_200 = [
    'response_code' => 'pick_hour_update_200',
    'message' => 'Pick Hour successfully updated'
];

const PICK_HOUR_DESTROY_200 = [
    'response_code' => 'pick_hour_destroy_200',
    'message' => 'Pick Hour successfully deleted'
];

const PICK_HOUR_404 = [
    'response_code' => 'pick_hour_404',
    'message' => 'Pick Hour resource not found'
];

const PICK_HOUR_RESOURCE_404 = [
    'response_code' => 'pick_hour_404',
    'message' => 'No provider or service is available within this pick hour'
];

const SOCIAL_MEDIA_LINK_STORE_200 = [
    'response_code' => 'social_media_link_store_200',
    'message' => 'Social media link successfully added'
];

const SOCIAL_MEDIA_LINK_UPDATE_200 = [
    'response_code' => 'social_media_link_update_200',
    'message' => 'Social media link successfully updated'
];

const SOCIAL_MEDIA_LINK_DELETE_200 = [
    'response_code' => 'social_media_link_delete_200',
    'message' => 'Social media link successfully deleted'
];

const TESTIMONIAL_DELETE_200 = [
    'response_code' => 'testimonial_delete_200',
    'message' => 'Testimonial successfully deleted'
];

const TESTIMONIAL_INTRO_UPDATE_200 = [
    'response_code' => 'testimonial_intro_update_200',
    'message' => 'Testimonial Intros successfully update'
];
const OUR_SOLUTION_DELETE_200 = [
    'response_code' => 'our_solution_delete_200',
    'message' => 'Our Solution successfully deleted'
];


// Banner

const BANNER_STORE_200 = [
    'response_code' => 'banner_store_200',
    'message' => 'Banner successfully added'
];

const BANNER_UPDATE_200 = [
    'response_code' => 'banner_update_200',
    'message' => 'Banner successfully updated'
];

const BANNER_DESTROY_200 = [
    'response_code' => 'banner_destroy_200',
    'message' => 'Banner successfully deleted'
];

const BANNER_404 = [
    'response_code' => 'banner_404',
    'message' => 'Banner resource not found'
];

const BANNER_RESOURCE_404 = [
    'response_code' => 'area_404',
    'message' => 'No provider or service is available within this area'
];

// Send Notification

const SEND_NOTIFICATION_STORE_200 = [
    'response_code' => 'send_notification_store_200',
    'message' => 'Send notification successfully added'
];
const SEND_NOTIFICATION_RESEND_200 = [
    'response_code' => 'send_notification_resend_200',
    'message' => 'Send notification successfully resent'
];

const SEND_NOTIFICATION_UPDATE_200 = [
    'response_code' => 'banner_update_200',
    'message' => 'Send notification successfully updated'
];

const SEND_NOTIFICATION_DESTROY_200 = [
    'response_code' => 'banner_destroy_200',
    'message' => 'Send notification successfully deleted'
];

const SEND_NOTIFICATION_404 = [
    'response_code' => 'banner_404',
    'message' => 'Send notification resource not found'
];

// Milestone

const MILESTONE_STORE_200 = [
    'response_code' => 'milestone_store_200',
    'message' => 'Milestone successfully added'
];

const MILESTONE_UPDATE_200 = [
    'response_code' => 'milestone_update_200',
    'message' => 'Milestone successfully updated'
];

const MILESTONE_DESTROY_200 = [
    'response_code' => 'milestone_destroy_200',
    'message' => 'Milestone successfully deleted'
];

const MILESTONE_404 = [
    'response_code' => 'milestone_404',
    'message' => 'Milestone resource not found'
];

const MILESTONE_RESOURCE_404 = [
    'response_code' => 'milestone_404',
    'message' => 'No'
];

// Discount

const DISCOUNT_STORE_200 = [
    'response_code' => 'discount_store_200',
    'message' => 'Discount successfully added'
];

const DISCOUNT_UPDATE_200 = [
    'response_code' => 'discount_update_200',
    'message' => 'Discount successfully updated'
];

const DISCOUNT_DESTROY_200 = [
    'response_code' => 'discount_destroy_200',
    'message' => 'Discount successfully deleted'
];

const DISCOUNT_404 = [
    'response_code' => 'discount_404',
    'message' => 'Discount resource not found'
];

const DISCOUNT_RESOURCE_404 = [
    'response_code' => 'discount_404',
    'message' => 'Discount is not found'
];

// BONUS

const BONUS_STORE_200 = [
    'response_code' => 'bonus_store_200',
    'message' => 'Bonus successfully added'
];

const BONUS_UPDATE_200 = [
    'response_code' => 'bonus_update_200',
    'message' => 'Bonus successfully updated'
];

const BONUS_DESTROY_200 = [
    'response_code' => 'bonus_destroy_200',
    'message' => 'Bonus successfully deleted'
];

const BONUS_404 = [
    'response_code' => 'BONUS_404',
    'message' => 'Bonus resource not found'
];

const BONUS_RESOURCE_404 = [
    'response_code' => 'area_404',
    'message' => 'No provider or service is available within this area'
];


// COUPON

const COUPON_STORE_200 = [
    'response_code' => 'coupon_store_200',
    'message' => 'Coupon successfully added'
];

const COUPON_UPDATE_200 = [
    'response_code' => 'coupon_update_200',
    'message' => 'Coupon successfully updated'
];

const COUPON_DESTROY_200 = [
    'response_code' => 'coupon_destroy_200',
    'message' => 'Coupon successfully deleted'
];


const COUPON_USAGE_LIMIT_406 = [
    'response_code' => 'coupon_usage_limit_406',
    'message' => 'Coupon usage limit over'
];


// Configuration

const CONFIGURATION_UPDATE_200 = [
    'response_code' => 'configuration_update_200',
    'message' => 'Configuration successfully updated'
];

const LANDING_PAGE_UPDATE_200 = [
    'response_code' => 'landing_page_update_200',
    'message' => 'Landing page successfully updated'
];

const LANDING_PAGE_INTRO_SECTION_UPDATE_200 = [
    'response_code' => 'landing_page_intro_section_update_200',
    'message' => 'Intro Section page content successfully updated'
];

const LANDING_PAGE_BUSINESS_STATISTICS_UPDATE_200 = [
    'response_code' => 'landing_page_business_statistics_update_200',
    'message' => 'Business Statistics page content successfully updated'
];


const LANDING_PAGE_OUR_SERVICES_UPDATE_200 = [
    'response_code' => 'landing_page_our_services_update_200',
    'message' => 'Our Services page content successfully updated'
];

const LANDING_PAGE_OUR_SOLUTIONS_UPDATE_200 = [
    'response_code' => 'landing_page_our_solutions_update_200',
    'message' => 'Our Solutions page content successfully updated'
];


const LANDING_PAGE_GALLERY_UPDATE_200 = [
    'response_code' => 'landing_page_gallery_update_200',
    'message' => 'Gallery page content successfully updated'
];

const LANDING_PAGE_CUSTOMER_APP_DOWNLOAD_UPDATE_200 = [
    'response_code' => 'landing_page_customer_app_download_update_200',
    'message' => 'Customer App Download page content successfully updated'
];

const LANDING_PAGE_APP_DOWNLOAD_PLAY_STORE_STATUS_UPDATE_DENY = [
    'response_code' => 'landing_page_customer_app_download_play_store_status_update_deny',
    'message' => 'Play Store Button status is not changed as url is not declared in the App Version page'
];

const LANDING_PAGE_APP_DOWNLOAD_APPLE_STORE_STATUS_UPDATE_DENY = [
    'response_code' => 'landing_page_customer_app_download_apple_store_status_update_deny',
    'message' => 'Apple Store Button status is not changed as url is not declared in the App Version page'
];

const LANDING_PAGE_EARN_MONEY_UPDATE_200 = [
    'response_code' => 'landing_page_earn_money_update_200',
    'message' => 'Earn Money page content successfully updated'
];

const LANDING_PAGE_NEWSLETTER_UPDATE_200 = [
    'response_code' => 'landing_page_newsletter_update_200',
    'message' => 'Newsletter page content successfully updated'
];

const LANDING_PAGE_FOOTER_UPDATE_200 = [
    'response_code' => 'landing_page_footer_update_200',
    'message' => 'Footer page content successfully updated'
];

const ROLE_STORE_200 = [
    'response_code' => 'role_store_200',
    'message' => 'Role successfully added'
];

const ROLE_UPDATE_200 = [
    'response_code' => 'role_update_200',
    'message' => 'Role successfully updated'
];

const ROLE_DESTROY_200 = [
    'response_code' => 'role_destroy_200',
    'message' => 'Role successfully deleted'
];

const ROLE_DESTROY_403 = [
    'response_code' => 'role_destroy_403',
    'message' => 'Role delete restricted when users assigned in this role'
];

//trip fare

const TRIP_FARE_STORE_200 = [
    'response_code' => 'trip_fare_store_200',
    'message' => 'Trip fare successfully added'
];

const TRIP_FARE_UPDATE_200 = [
    'response_code' => 'trip_fare_update_200',
    'message' => 'Trip fare successfully updated'
];

const TRIP_FARE_DESTROY_200 = [
    'response_code' => 'trip_fare_destroy_200',
    'message' => 'Trip fare successfully deleted'
];

//trip fare

const PARCEL_FARE_STORE_200 = [
    'response_code' => 'parcel_fare_store_200',
    'message' => 'Parcel fare successfully added'
];

const PARCEL_FARE_UPDATE_200 = [
    'response_code' => 'parcel_fare_update_200',
    'message' => 'Parcel fare successfully updated'
];

const PARCEL_FARE_DESTROY_200 = [
    'response_code' => 'parcel_fare_destroy_200',
    'message' => 'Parcel fare successfully deleted'
];


// Parcel Category

const PARCEL_CATEGORY_UPDATE_200 = [
    'response_code' => 'parcel_category_update_200',
    'message' => 'Parcel category successfully updated'
];


const PARCEL_CATEGORY_STORE_200 = [
    'response_code' => 'parcel_category_store_200',
    'message' => 'Parcel category successfully added'
];

const PARCEL_CATEGORY_DESTROY_200 = [
    'response_code' => 'parcel_category_destroy_200',
    'message' => 'Parcel category successfully deleted'
];


// Parcel Weight

const PARCEL_WEIGHT_UPDATE_200 = [
    'response_code' => 'parcel_weight_update_200',
    'message' => 'Parcel weight successfully updated'
];


const PARCEL_WEIGHT_STORE_200 = [
    'response_code' => 'parcel_weight_store_200',
    'message' => 'Parcel weight successfully added'
];

const PARCEL_WEIGHT_EXISTS_403 = [
    'response_code' => 'parcel_weight_exists_403',
    'message' => 'Parcel weight overlap'
];
const PARCEL_WEIGHT_DESTROY_200 = [
    'response_code' => 'parcel_weight_destroy_200',
    'message' => 'Parcel weight successfully deleted'
];

const PARCEL_WEIGHT_404 = [
    'response_code' => 'parcel_weight_404',
    'message' => 'Setup parcel weight first'
];


//TRIP

const TRIP_REQUEST_STORE_200 = [
    'response_code' => 'trip_request_store_200',
    'message' => 'Trip request successfully placed'
];

const TRIP_REQUEST_DELETE_200 = [
    'response_code' => 'trip_request_delete_200',
    'message' => 'Trip request deleted successfully'
];

const TRIP_REQUEST_DRIVER_403 = [
    'response_code' => 'trip_request_driver_403',
    'message' => 'Driver already assigned to this trip'
];

const TRIP_STATUS_ONGOING_403 = [
    'response_code' => 'trip_status_ongoing_403',
    'message' => 'Trip already ongoing'
];

const TRIP_REQUEST_404 = [
    'response_code' => 'trip_request_403',
    'message' => 'Trip request not found'
];
const PARCEL_REFUND_REQUEST_404 = [
    'response_code' => 'parcel_refund_request_403',
    'message' => 'Parcel refund request not found'
];

const PARCEL_REFUND_REQUEST_APPROVED_200 = [
    'response_code' => 'parcel_refund_request_approved_200',
    'message' => 'Parcel refund request approved successfully'
];

const PARCEL_REFUND_REQUEST_DENIED_200 = [
    'response_code' => 'parcel_refund_request_denied_200',
    'message' => 'Parcel refund request denied successfully'
];

const PARCEL_REFUND_REQUEST_REFUNDED_200 = [
    'response_code' => 'parcel_refund_request_refunded_200',
    'message' => 'Parcel refund request refunded successfully'
];

const TRIP_STATUS_NOT_COMPLETED_200 = [
    'response_code' => 'trip_status_200',
    'message' => 'Trip yet not completed'
];

const TRIP_STATUS_COMPLETED_403 = [
    'response_code' => 'trip_status_200',
    'message' => 'Trip already completed'
];
const TRIP_STATUS_RETURNING_403 = [
    'response_code' => 'trip_status_200',
    'message' => 'Trip already returning'
];
const TRIP_STATUS_RETURNED_403 = [
    'response_code' => 'trip_status_200',
    'message' => 'Trip already returned'
];

const TRIP_STATUS_CANCELLED_403 = [
    'response_code' => 'trip_status_200',
    'message' => 'Trip already cancelled'
];

const REVIEW_403 = [
    'response_code' => 'review_409',
    'message' => 'Review already submitted'
];

const REVIEW_SUBMIT_403 = [
    'response_code' => 'review_submit_409',
    'message' => 'Review submission is turned off'
];

const REVIEW_404 = [
    'response_code' => 'review_404',
    'message' => 'Review not found'
];
const LANGUAGE_UPDATE_FAIL_200 = [
    'response_code' => 'language_status_update_fail_200',
    'message' => 'Default language status can not be changed or deleted'
];

// otp

const OTP_MISMATCH_404 = [
    'response_code' => 'otp_mismatch_404',
    'message' => 'OTP is not matched'
];

const OTP_EXPIRE_404 = [
    'response_code' => 'otp_expire_404',
    'message' => 'OTP Expired!'
];

//BID

const BIDDING_LIMIT_429 = [
    'response_code' => 'bidding_limit_429',
    'message' => 'Bidding limit for this trip request exceeded'
];

const RAISING_BID_FARE_403 = [
    'response_code' => 'raising_bid_fare_403',
    'message' => 'Bid fare can not be same or less than initial bid fare'
];

const BIDDING_ACTION_200 = [
    'response_code' => 'bidding_action_200',
    'message' => 'Bidding action successfully updated'
];

const BIDDING_SUBMITTED_403 = [
    'response_code' => 'bidding_submitted_403',
    'message' => 'Bidding already submitted'
];

const MAXIMUM_INTERMEDIATE_POINTS_403 = [
    'response_code' => 'maximum_intermediate_points_403',
    'message' => 'More intermediate points can not be set'
];

const COUPON_AREA_NOT_VALID_403 = [
    'response_code' => 'coupon_area_not_valid_403',
    'message' => 'Coupon code not belongs to your current area'
];

const COUPON_VEHICLE_CATEGORY_NOT_VALID_403 = [
    'response_code' => 'coupon_vehicle_category_not_valid_403',
    'message' => 'Vehicle category not found for this coupon'
];

const USER_LAST_LOCATION_NOT_AVAILABLE_404 = [
    'response_code' => 'user_last_location_not_available_404',
    'message' => 'User Last Location Not Available'
];

const INCOMPLETE_RIDE_403 = [
    'response_code' => 'incomplete_ride_403',
    'message' => 'Please complete previous ride first'
];

const DRIVER_UNAVAILABLE_403 = [
    'response_code' => 'driver_unavailable_403',
    'message' => 'Please change your offline status'
];

const CHAT_UNAVAILABLE_403 = [
    'response_code' => 'chat_unavailable_403',
    'message' => 'Chat available only during active ride'
];
const PARCEL_WEIGHT_400 = [
    'response_code' => 'parcel_weight_400',
    'message' => 'Parcel weight is not acceptable'
];

//Wallet Errors
const INSUFFICIENT_FUND_403 = [
    'response_code' => 'insufficient_fund_403',
    'message' => 'You have insufficient balance on wallet'
];
const FUND_TRANSFER_200 = [
    'response_code' => 'fund_transfer_200',
    'message' => 'Fund transfer success'
];
const ERROR_INSUFFICIENT_POINTS = [
    'response_code' => 'invalid_convert_points_403',
    'message' => 'You must enter at least :min_points points to convert'
];

const ERROR_INVALID_POINTS_MULTIPLE = [
    'response_code' => 'invalid_convert_points_403',
    'message' => 'Points must be a multiple of :min_points'
];
const INSUFFICIENT_POINTS_403 = [
    'response_code' => 'insufficient_points_403',
    'message' => 'You have insufficient loyalty points'
];

const WITHDRAW_REQUEST_200 = [
    'response_code' => 'withdraw_request_200',
    'message' => 'Withdraw request sent for admin approval'
];

const WITHDRAW_REQUEST_AMOUNT_403 = [
    'response_code' => 'withdraw_request_amount_403',
    'message' => 'Please enter '
];

const WITHDRAW_METHOD_INFO_STORE_200 = [
    'response_code' => 'withdraw_method_info_store_200',
    'message' => 'Withdraw method info saved successfully'
];
const WITHDRAW_METHOD_INFO_UPDATE_200 = [
    'response_code' => 'withdraw_method_info_update_200',
    'message' => 'Withdraw method info updated successfully'
];
const WITHDRAW_METHOD_INFO_DELETE_200 = [
    'response_code' => 'withdraw_method_info_delete_200',
    'message' => 'Withdraw method info deleted successfully'
];

const WITHDRAW_METHOD_INFO_REQUEST_EXIST_403 = [
    'response_code' => 'withdraw_method_info_request_exist_403',
    'message' => 'Pending withdraw request exist, You can not delete it'
];


const DRIVER_REQUEST_ACCEPT_TIMEOUT_408 = [
    'response_code' => 'driver_request_accept_timeout_408',
    'message' => 'The trip request has already been expired'
];

const NEGATIVE_VALUE = [
    'message' => 'Negative value is not acceptable'
];
const MAX_VALUE = [
    'message' => 'Max value can be greater than 10'
];

const COUPON_APPLIED_403 = [
    'response_code' => 'coupon_applied_403',
    'message' => 'Coupon already applied on this ride'
];
const COUPON_APPLIED_200 = [
    'response_code' => 'coupon_applied_200',
    'message' => 'Coupon applied successfully'
];

const COUPON_REMOVED_200 = [
    'response_code' => 'coupon_removed_200',
    'message' => 'Coupon removed successfully'
];

const REFERRAL_CODE_NOT_MATCH_403 = [
    'response_code' => 'referral_code_not_match_403',
    'message' => 'Referral code not match'
];

const SELF_REGISTRATION_400 = [
    'response_code' => 'self_registration_400',
    'message' => 'Self registration is turned off. contact admin for registration'
];

const LAST_LOCATION_404 = [
    'response_code' => 'last_location_404',
    'message' => 'User last location not found'
];

const VEHICLE_CATEGORY_404 = [
    'response_code' => 'vehicle_category_404',
    'message' => 'No vehicle category found. Please activate or create new vehicle category'
];

const VEHICLE_NOT_APPROVED_OR_ACTIVE_404 = [
    'response_code' => 'vehicle_not_approved_or_active_404',
    'message' => 'Your registered vehicle is not approved or active. Please contact system admin, otherwise you do not found trip in this system.'
];
const VEHICLE_NOT_REGISTERED_404 = [
    'response_code' => 'vehicle_not_registered_404',
    'message' => 'Please registered your vehicle first, you do not found trip in this system.'
];


const GATEWAYS_DEFAULT_204 = [
    'response_code' => 'default_204',
    'message' => 'information not found'
];

const GATEWAYS_DEFAULT_400 = [
    'response_code' => 'default_400',
    'message' => 'invalid or missing information'
];


const CHANNEL_NOT_FOUND_404 = [
    'response_code' => 'channel_404',
    'message' => 'Channel not found'
];

//safety alert
const SAFETY_ALERT_STORE_200 = [
    'response_code' => 'safety_alert_store_200',
    'message' => 'Safety alert sent'
];

const SAFETY_ALERT_ALREADY_EXIST_400 = [
    'response_code' => 'safety_alert_already_exist_400',
    'message' => 'Safety alert already exist'
];

const SAFETY_ALERT_NOT_FOUND_404 = [
    'response_code' => 'safety_alert_404',
    'message' => 'Safety alert not found'
];

const SAFETY_ALERT_RESEND_200 = [
    'response_code' => 'safety_alert_resend_200',
    'message' => 'Safety alert resent'
];

const SAFETY_ALERT_MARK_AS_SOLVED = [
    'response_code' => 'safety_alert_mark_as_solved_200',
    'message' => 'Safety alert marked as solved'
];

const SAFETY_ALERT_UNDO_200 = [
    'response_code' => 'safety_alert_undo_200',
    'message' => 'You have successfully removed your safety alert.'
];

const SURGE_PRICING_NOT_FOUND_404 = [
    'response_code' => 'surge_pricing_not_found_404',
    'message' => 'Surge pricing not found'
];

const SURGE_PRICING_NOT_ACTIVE_404 = [
    'response_code' => 'surge_pricing_not_active_404',
    'message' => 'Surge pricing is not active'
];

const SURGE_PRICING_STORE_200 = [
    'response_code' => 'surge_pricing_store_200',
    'message' => 'Surge pricing successfully added'
];

const SURGE_PRICING_UPDATE_200 = [
    'response_code' => 'surge_pricing_update_200',
    'message' => 'Surge pricing successfully updated'
];

const SURGE_PRICING_DESTROY_200 = [
    'response_code' => 'surge_pricing_destroy_200',
    'message' => 'Surge pricing successfully deleted'
];

const WALLET_BONUS_STORE_200 = [
    'response_code' => 'wallet_bonus_store_200',
    'message' => 'Successfully added'
];

const WALLET_BONUS_DESTROY_200 = [
    'response_code' => 'wallet_bonus_destroy_200',
    'message' => 'Wallet Bonus successfully deleted'
];

const WALLET_BONUS_UPDATE_200 = [
    'response_code' => 'wallet_bonus_update_200',
    'message' => 'Wallet bonus successfully updated'
];

const WALLET_BONUS_ALREADY_EXISTS = [
    'response_code' => 'wallet_bonus_already_exists',
    'message' => 'Wallet Bonus already exists with this Bonus Amount, Minimum Add Amount, Maximum Bonus'
];

const MAXIMUM_AMOUNT_TO_HOLD_CASH_EXCEEDS = [
    'response_code' => 'maximum_amount_to_hold_cash_exceeds',
    'message' => 'Your account is on hold, so you cannot start a trip right now.'
];

const ACCOUNT_SUSPEND = [
    'response_code' => 'account_suspend',
    'message' => 'Your account is suspended, so you cannot start a trip right now.'
];

const GATEWAY_INACTIVE = [
    'response_code' => 'gateway_inactive',
    'message' => 'The selected gateway is inactive'
];

const BLOG_DISABLE = [
    'response_code' => 'blog_disable',
    'message' => 'The blog Feature is disabled'
];

const BLOG_NOT_FOUND = [
    'response_code' => 'blog_not_found',
    'message' => 'Blog is not found'
];

const BLOG_INTRO_UPDATE = [
    'response_code' => 'blog_intro_update',
    'message' => 'Blog Intro section content updated successfully'
];

const BLOG_PRIORITY_SETUP_UPDATE = [
    'response_code' => 'blog_priority_setup_update',
    'message' => 'Blog Priority Setup content updated successfully'
];

const BLOG_APP_DOWNLOAD_SETUP_UPDATE = [
    'response_code' => 'blog_app_download_setup_update',
    'message' => 'App Download Setup section content updated successfully'
];

const BLOG_STORE = [
    'response_code' => 'blog_store',
    'message' => 'Blog stored successfully'
];

const BLOG_UPDATE = [
    'response_code' => 'blog_update',
    'message' => 'Blog updated successfully'
];

const BLOG_DELETE = [
    'response_code' => 'blog_delete',
    'message' => 'Blog deleted successfully'
];

const INVALID_URL = [
    'response_code' => 'invalid_url',
    'message' => 'The provided url is invalid or broken'
];


const FACE_VERIFICATION_API_UPDATE = [
    'response_code' => 'face_verification_api_update',
    'message' => 'Face verification api credentials updated successfully'
];

const INVALID_FACE_VERIFICATION_API_CREDENTIALS = [
    'response_code' => 'invalid_face_verification_api_credentials',
    'message' => 'Invalid AWS credentials or Rekognition permission denied'
];

const FACE_VERIFICATION_SKIP = [
    'response_code' => 'face_verification_skip',
    'message' => 'Face verification skipped successfully'
];

const FACE_VERIFICATION_FEATURE_NOT_ACTIVE_403 = [
    'response_code' => 'face_verification_feature_not_active_403',
    'message' => 'Face verification feature is not active at the moment'
];


const DRIVER_MARK_AS_VERIFIED = [
    'response_code' => 'driver_mark_as_verified',
    'message' => 'The driver is marked as verified successfully'
];

const DRIVER_MARK_AS_SUSPENDED = [
    'response_code' => 'driver_mark_as_suspended',
    'message' => 'The driver is marked as suspended successfully'
];

const DRIVER_MARK_AS_UN_SUSPENDED = [
    'response_code' => 'driver_mark_as_un_suspended',
    'message' => 'The driver is marked as un-suspended successfully'
];

const DRIVER_ALREADY_SUSPENDED = [
    'response_code' => 'driver_already_suspended',
    'message' => 'The driver is already marked as suspended'
];

const DRIVER_SUSPEND_FOR_CASH_IN_HAND_LIMIT_EXCEEDS = [
    'response_code' => 'driver_suspend_for_cash_in_hand_limit_exceeds',
    'message' => 'You cannot un-suspend this driver as his cash in hand limit exceeds'
];

const DRIVER_SUSPEND_FOR_FACE_VERIFICATION = [
    'response_code' => 'driver_suspend_for_face_verification',
    'message' => 'You cannot un-suspend this driver as face verification was not successful'
];

const AI_SETUP_UPDATE = [
    'response_code' => 'ai_setup_update',
    'message' => 'AI Setup is successfully updated'
];
