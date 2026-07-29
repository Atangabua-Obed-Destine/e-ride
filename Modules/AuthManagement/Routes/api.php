<?php

use Illuminate\Support\Facades\Route;

Route::controller(\Modules\AuthManagement\Http\Controllers\Api\AuthController::class)->group(function () {
    Route::group(['prefix' => 'customer'], function () {
        Route::group(['prefix' => 'auth'], function () {
            Route::post('registration', 'register')->name('customer-registration');
            Route::post('registration-from-otp', 'registrationFromOtp');
            Route::post('login', 'login')->name('customer-login');
            Route::post('social-login', 'customerSocialLogin');
            Route::post('update-data', 'updateData');
            //login
            Route::post('otp-login', 'otpLogin');
            Route::post('check', 'userExistOrNotChecking');
            // reset or forget password
            Route::post('forget-password', 'forgetPassword');
            Route::post('reset-password', 'resetPassword');
            Route::post('otp-verification', 'otpVerification');
            Route::post('firebase-otp-verification', 'firebaseOtpVerification');
            //send otp for otp login or reset
            Route::post('send-otp', 'sendOtp');
            Route::post('external-registration', 'customerRegistrationFromMart');
            Route::post('external-login', 'customerLoginFromMart');

        });
        Route::group(['middleware' => ['auth:api', 'maintenance_mode']], function () {
            Route::group(['prefix' => 'update'], function () {
                Route::put('fcm-token',  'updateFcmToken');
            });
        });
    });

    //driver routes
    Route::group(['prefix' => 'driver'], function () {
        Route::group(['prefix' => 'auth'], function () {
            Route::post('registration', 'register')->name('driver-registration');
            Route::post('registration-from-otp', 'registrationFromOtp');
            Route::post('update-data', 'updateData');
            Route::post('login', 'login')->name('driver-login');
            Route::post('send-otp', 'sendOtp');
            Route::post('check', 'userExistOrNotChecking');
            Route::post('forget-password', 'forgetPassword');
            Route::post('reset-password', 'resetPassword');
            Route::post('otp-verification', 'otpVerification');
            Route::post('firebase-otp-verification', 'firebaseOtpVerification');
        });

        Route::group(['middleware' => ['auth:api', 'maintenance_mode']], function () {
            Route::group(['prefix' => 'update'], function () {
                Route::put('fcm-token',  'updateFcmToken');
            });

            Route::post('verify-or-set-password-for-biometric', 'verifyOrSetPasswordForBiometric');
        });

    });

    Route::group(['prefix' => 'user', 'middleware' => ['auth:api', 'maintenance_mode']], function () {
        Route::post('logout', 'logout')->name('logout');
        Route::post('delete', 'delete')->name('delete');
        Route::post('change-password', 'changePassword');
    });

});
