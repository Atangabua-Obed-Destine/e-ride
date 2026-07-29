<?php

use Illuminate\Support\Facades\Route;
use Modules\BusinessManagement\Http\Controllers\Web\Admin\AdditionalDataSetup\AdditionalDataSetupController;
use Modules\BusinessManagement\Http\Controllers\Web\Admin\BusinessSetup\BusinessInfoController;
use Modules\BusinessManagement\Http\Controllers\Web\Admin\BusinessSetup\ChattingSetupController;
use Modules\BusinessManagement\Http\Controllers\Web\Admin\BusinessSetup\CustomerSettingController;
use Modules\BusinessManagement\Http\Controllers\Web\Admin\BusinessSetup\DriverSettingController;
use Modules\BusinessManagement\Http\Controllers\Web\Admin\BusinessSetup\ExternalConfigurationController;
use Modules\BusinessManagement\Http\Controllers\Web\Admin\BusinessSetup\ParcelSettingController;
use Modules\BusinessManagement\Http\Controllers\Web\Admin\BusinessSetup\ReferralEarningSettingController;
use Modules\BusinessManagement\Http\Controllers\Web\Admin\BusinessSetup\RefundSettingController;
use Modules\BusinessManagement\Http\Controllers\Web\Admin\BusinessSetup\SafetyAndPrecautionController;
use Modules\BusinessManagement\Http\Controllers\Web\Admin\BusinessSetup\ScheduleTripController;
use Modules\BusinessManagement\Http\Controllers\Web\Admin\BusinessSetup\TripFareSettingController;
use Modules\BusinessManagement\Http\Controllers\Web\Admin\Configuration\AiSetupController;
use Modules\BusinessManagement\Http\Controllers\Web\Admin\Configuration\FaceVerificationApiSetupController;
use Modules\BusinessManagement\Http\Controllers\Web\Admin\Configuration\FirebaseOtpController;
use Modules\BusinessManagement\Http\Controllers\Web\Admin\Configuration\LoginSettingsController;
use Modules\BusinessManagement\Http\Controllers\Web\Admin\Configuration\NotificationController;
use Modules\BusinessManagement\Http\Controllers\Web\Admin\Configuration\PaymentConfigController;
use Modules\BusinessManagement\Http\Controllers\Web\Admin\Configuration\SMSConfigController;
use Modules\BusinessManagement\Http\Controllers\Web\Admin\Configuration\ThirdPartyController;
use Modules\BusinessManagement\Http\Controllers\Web\Admin\PagesMedia\LandingPageController;
use Modules\BusinessManagement\Http\Controllers\Web\Admin\PagesMedia\PagesMediaController;
use Modules\BusinessManagement\Http\Controllers\Web\Admin\SystemSetting\LanguageController;
use Modules\BusinessManagement\Http\Controllers\Web\Admin\SystemSetting\SystemSettingController;


#new route
Route::group(['prefix' => 'admin', 'as' => 'admin.', 'middleware' => 'admin'], function () {
    Route::group(['prefix' => 'business', 'as' => 'business.'], function () {
        Route::group(['prefix' => 'setup', 'as' => 'setup.'], function () {
            Route::controller(BusinessInfoController::class)->group(function () {
                Route::get('update-business-setting', 'updateBusinessSetting')->name('update-business-setting');
                Route::group(['prefix' => 'info', 'as' => 'info.'], function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('store', 'store')->name('store');
                    Route::any('maintenance', 'maintenance')->name('maintenance');
                    Route::get('settings', 'settings')->name('settings');
                    Route::post('update-settings', 'updateSettings')->name('update-settings');
                });
            });

            Route::group(['prefix' => 'driver', 'as' => 'driver.'], function () {
                Route::controller(DriverSettingController::class)->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('store', 'store')->name('store');
                    Route::post('vehicle-update', 'vehicleUpdate')->name('vehicle-update');
                    Route::post('update-cash-in-hand-setup', 'updateCashInHandSetup')->name('update-cash-in-hand-setup');
                    Route::post('update-identity-verification', 'updateIdentityVerification')->name('update-identity-verification');
                });
            });
            Route::group(['prefix' => 'parcel', 'as' => 'parcel.'], function () {
                Route::controller(ParcelSettingController::class)->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('store', 'store')->name('store');
                    Route::post('tracking-store', 'storeParcelTracking')->name('tracking-store');
                    Route::group(['prefix' => 'cancellation-reason', 'as' => 'cancellation_reason.'], function () {
                        Route::post('store', 'storeCancellationReason')->name('store');
                        Route::get('edit/{id}', 'editCancellationReason')->name('edit');
                        Route::post('update/{id}', 'updateCancellationReason')->name('update');
                        Route::delete('delete/{id}', 'destroyCancellationReason')->name('delete');
                        Route::get('status', 'statusCancellationReason')->name('status');
                    });
                    Route::post('store-parcel-weight-unit', 'storeParcelWeightUnit')->name('store-parcel-weight-unit');
                    Route::post('store-maximum-parcel-weight', 'storeMaxParcelWeight')->name('store-maximum-parcel-weight');
                });
            });
            Route::group(['prefix' => 'parcel-refund', 'as' => 'parcel-refund.'], function () {
                Route::controller(RefundSettingController::class)->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('store', 'store')->name('store');
                    Route::group(['prefix' => 'reason', 'as' => 'reason.'], function () {
                        Route::post('store', 'storeRefundReason')->name('store');
                        Route::get('edit/{id}', 'editRefundReason')->name('edit');
                        Route::post('update/{id}', 'updateRefundReason')->name('update');
                        Route::delete('delete/{id}', 'destroyRefundReason')->name('delete');
                        Route::get('status', 'statusRefundReason')->name('status');
                    });
                });
            });
            Route::group(['prefix' => 'safety-precaution', 'as' => 'safety-precaution.'], function () {
                Route::controller(SafetyAndPrecautionController::class)->group(function () {
                    Route::get('/{type}', 'index')->name('index');
                    Route::post('store', 'store')->name('store');
                    Route::group(['prefix' => 'safety-alert-reason', 'as' => 'safety-alert-reason.'], function () {
                        Route::post('store', 'storeSafetyAlertReason')->name('store');
                        Route::get('edit/{id}', 'editSafetyAlertReason')->name('edit');
                        Route::post('update/{id}', 'updateSafetyAlertReason')->name('update');
                        Route::delete('delete/{id}', 'destroySafetyAlertReason')->name('delete');
                        Route::get('status', 'statusSafetyAlertReason')->name('status');
                    });
                    Route::group(['prefix' => 'precaution', 'as' => 'precaution.'], function () {
                        Route::post('store', 'storeSafetyPrecaution')->name('store');
                        Route::get('edit/{id}', 'editSafetyPrecaution')->name('edit');
                        Route::post('update/{id}', 'updateSafetyPrecaution')->name('update');
                        Route::delete('delete/{id}', 'destroySafetyPrecaution')->name('delete');
                        Route::get('status', 'statusSafetyPrecaution')->name('status');
                    });
                    Route::group(['prefix' => 'emergency-number-for-call', 'as' => 'emergency-number-for-call.'], function () {
                        Route::post('store', 'storeEmergencyNumberForCall')->name('store');
                    });
                });
            });


            Route::group(['prefix' => 'chatting-setup', 'as' => 'chatting-setup.'], function () {
                Route::controller(ChattingSetupController::class)->group(function () {
                    Route::get('/{type}', 'index')->name('index');
                    Route::post('store', 'store')->name('store');
                    Route::group(['prefix' => 'question-answer', 'as' => 'question-answer.'], function () {
                        Route::post('store', 'storeQuestionAnswer')->name('store');
                        Route::get('edit/{id}', 'editQuestionAnswer')->name('edit');
                        Route::post('update/{id}', 'updateQuestionAnswer')->name('update');
                        Route::delete('delete/{id}', 'destroyQuestionAnswer')->name('delete');
                        Route::get('status', 'statusQuestionAnswer')->name('status');
                    });
                    Route::group(['prefix' => 'support-saved-reply', 'as' => 'support-saved-reply.'], function () {
                        Route::post('store', 'storeSupportSavedReply')->name('store');
                        Route::get('edit/{id}', 'editSupportSavedReply')->name('edit');
                        Route::post('update/{id}', 'updateSupportSavedReply')->name('update');
                        Route::delete('delete/{id}', 'destroySupportSavedReply')->name('delete');
                        Route::get('status', 'statusSupportSavedReply')->name('status');
                    });
                });
            });
            Route::group(['prefix' => 'referral-earning', 'as' => 'referral-earning.'], function () {
                Route::controller(ReferralEarningSettingController::class)->group(function () {
                    Route::get('update-referral-setting', 'updateReferralSetting')->name('update-referral-setting');
                    Route::get('/', 'index')->name('index');
                    Route::post('store', 'store')->name('store');
                });
            });

            Route::group(['prefix' => 'customer', 'as' => 'customer.'], function () {
                Route::controller(CustomerSettingController::class)->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('store', 'store')->name('store');
                });
            });

            Route::group(['prefix' => 'trip-fare', 'as' => 'trip-fare.'], function () {
                Route::controller(TripFareSettingController::class)->group(function () {
                    Route::get('penalty', 'index')->name('penalty');
                    Route::get('trips', 'tripIndex')->name('trips');
                    Route::post('store', 'store')->name('store');
                    Route::group(['prefix' => 'cancellation-reason', 'as' => 'cancellation_reason.'], function () {
                        Route::post('store', 'storeCancellationReason')->name('store');
                        Route::get('edit/{id}', 'editCancellationReason')->name('edit');
                        Route::post('update/{id}', 'updateCancellationReason')->name('update');
                        Route::delete('delete/{id}', 'destroyCancellationReason')->name('delete');
                        Route::get('status', 'statusCancellationReason')->name('status');
                    });
                });
            });

            Route::group(['prefix' => 'schedule-trip', 'as' => 'schedule-trip.'], function () {
                Route::controller(ScheduleTripController::class)->group(function () {
                    Route::post('store', 'store')->name('store');
                });
            });
        });
        Route::group(['prefix' => 'external', 'as' => 'external.'], function () {
            Route::controller(ExternalConfigurationController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('store', 'store')->name('store');
            });
        });


        Route::group(['prefix' => 'configuration', 'as' => 'configuration.'], function () {
            Route::group(['prefix' => 'notification', 'as' => 'notification.'], function () {
                Route::controller(NotificationController::class)->group(function () {
                    Route::get('/firebase-configuration', 'firebaseConfiguration')->name('firebase-configuration');
                    Route::get('/{type}', 'index')->name('index');
                    Route::get('/firebase-push-notifications-fields/{type}', 'firebasePushNotificationFields')->name('firebase-push-notifications-fields');
                    Route::post('store', 'store')->name('store');
                    Route::post('push/store/{type}', 'pushStore')->name('push-store');
                    Route::get('notification-settings', 'updateNotificationSettings')->name('notification-settings');
                });

                });

            Route::group(['prefix' => 'third-party', 'as' => 'third-party.'], function () {
                Route::group(['prefix' => 'payment-method', 'as' => 'payment-method.'], function () {
                    Route::controller(PaymentConfigController::class)->group(function () {
                        Route::get('/', 'paymentConfigGet')->name('index');
                        Route::put('update', 'paymentConfigSet')->name('update');
                    });
                });
                Route::group(['prefix' => 'sms-gateway', 'as' => 'sms-gateway.'], function () {
                    Route::controller(SMSConfigController::class)->group(function () {
                        Route::get('/', 'smsConfigGet')->name('index');
                        Route::put('update', 'smsConfigSet')->name('update');
                    });
                });
                Route::group(['prefix' => 'firebase-otp', 'as' => 'firebase-otp.'], function () {
                    Route::controller(FirebaseOtpController::class)->group(function () {
                        Route::get('/', 'firebaseOtpConfigGet')->name('index');
                        Route::put('update', 'firebaseOtpConfigSet')->name('update');
                    });
                });
                Route::controller(ThirdPartyController::class)->group(function () {
                    Route::group(['prefix' => 'email-config', 'as' => 'email-config.'], function () {
                        Route::get('/', 'emailConfig')->name('index');
                        Route::post('update', 'updateEmailConfig')->name('update');
                    });
                    Route::group(['prefix' => 'recaptcha', 'as' => 'recaptcha.'], function () {
                        Route::get('/', 'recaptcha')->name('index');
                        Route::post('update', 'updateRecaptcha')->name('update');
                    });
                    Route::group(['prefix' => 'google-map', 'as' => 'google-map.'], function () {
                        Route::get('/', 'map')->name('index');
                        Route::post('update', 'updateMap')->name('update');
                    });
                });
            });

            Route::group(['prefix' => 'login-settings', 'as' => 'login-settings.'], function () {
                Route::controller(LoginSettingsController::class)->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('/store', 'store')->name('store');
                    Route::get('otp-login-attempts-index', 'otpLoginAttemptsIndex')->name('otp-login-attempts-index');
                    Route::post('otp-login-attempts-store', 'otpLoginAttemptsStore')->name('otp-login-attempts-store');
                });
            });

            Route::controller(FaceVerificationApiSetupController::class)->group(function (){
                Route::group(['prefix' => 'face-verification-api', 'as' => 'face-verification-api.'], function (){
                    Route::get('/', 'faceVerificationApi')->name('index');
                    Route::post('update', 'updateFaceVerificationApi')->name('update');
                });
            });

            Route::controller(AiSetupController::class)->group(function (){
                Route::group(['prefix' => 'ai-setup', 'as' => 'ai-setup.'], function (){
                    Route::get('/', 'index')->name('index');
                    Route::post('update', 'update')->name('update');
                });
            });
        });

        Route::group(['prefix' => 'pages-media', 'as' => 'pages-media.'], function () {
            Route::controller(PagesMediaController::class)->group(function () {
                Route::get('social-media', 'socialMedia')->name('social-media');
                Route::post('store-social-link', 'storeSocialLink')->name('store-social-link');
                Route::post('update-social-link/{id}', 'updateSocialLink')->name('update-social-link');
                Route::get('update-social-link-status', 'updateSocialStatus')->name('update-social-link-status');
                Route::delete('delete-social-link', 'deleteSocialLink')->name('delete-social-link');
                Route::get('log', 'log')->name('log');
                Route::group(['prefix' => 'business-page', 'as' => 'business-page.'], function () {
                    Route::get('/', 'businessPages')->name('index');
                    Route::post('update', 'businessPagesUpdate')->name('update');
                });
            });

            Route::group(['prefix' => 'landing-page', 'as' => 'landing-page.'], function () {
                Route::controller(LandingPageController::class)->group(function () {
                    Route::get('update-landing-page-setting', 'updateLandingPageSetting')->name('update-landing-page-setting');
                    Route::group(['prefix' => 'intro-section', 'as' => 'intro-section.'], function () {
                        Route::get('/', 'introSection')->name('index');
                        Route::post('update', 'updateIntroSection')->name('update');
                    });
                    Route::group(['prefix' => 'our-solutions', 'as' => 'our-solutions.'], function () {
                        Route::get('/', 'getOurSolutionsSectionView')->name('index');
                       Route::post('update-intro', 'updateOurSolutions')->name('update-intro');
                        Route::post('update', 'getAddOrUpdateOurSolutions')->name('update');
                        Route::get('status', 'statusOurSolutions')->name('status');
                        Route::get('edit/{id}', 'editOurSolutions')->name('edit');
                        Route::delete('delete/{id}', 'deleteOurSolutions')->name('delete');
                    });
                    Route::group(['prefix' => 'our-services', 'as' => 'our-services.'], function () {
                        Route::get('/', 'getOurServicesView')->name('index');
                        Route::post('update-section', 'updateOurServicesSection')->name('update-section');
                    });
                    Route::group(['prefix' => 'gallery', 'as' => 'gallery.'], function () {
                        Route::get('/', 'gallery')->name('index');
                        Route::post('update-section', 'updateGallerySection')->name('update-section');
                    });
                    Route::group(['prefix' => 'customer-app-download', 'as' => 'customer-app-download.'], function () {
                        Route::get('/', 'customerAppDownload')->name('index');
                        Route::post('update-section', 'updateCustomerAppDownloadSection')->name('update-section');
                    });

                    Route::group(['prefix' => 'business-statistics', 'as' => 'business-statistics.'], function () {
                        Route::get('/', 'businessStatistics')->name('index');
                        Route::post('update', 'updateBusinessStatistics')->name('update');
                    });
                    Route::group(['prefix' => 'our-platform', 'as' => 'our-platform.'], function () {
                        Route::get('/', 'ourPlatform')->name('index');
                        Route::post('update', 'updateOurPlatform')->name('update');
                    });
                    Route::group(['prefix' => 'earn-money', 'as' => 'earn-money.'], function () {
                        Route::get('/', 'earnMoney')->name('index');
                        Route::post('update-section', 'updateEarnMoneySection')->name('update-section');
                    });
                    Route::group(['prefix' => 'testimonial', 'as' => 'testimonial.'], function () {
                        Route::get('/', 'testimonial')->name('index');
                        Route::post('update-intro', 'updateTestimonialIntro')->name('update-intro');
                        Route::post('update', 'updateTestimonial')->name('update');
                        Route::get('status', 'statusTestimonial')->name('status');
                        Route::get('edit/{id}', 'editTestimonial')->name('edit');
                        Route::delete('delete/{id}', 'deleteTestimonial')->name('delete');
                    });
                    Route::group(['prefix' => 'newsletter', 'as' => 'newsletter.'], function () {
                        Route::get('/', 'newsletter')->name('index');
                        Route::post('update-section', 'updateNewsletterSection')->name('update-section');
                    });
                    Route::group(['prefix' => 'footer', 'as' => 'footer.'], function () {
                        Route::get('/', 'footer')->name('index');
                        Route::post('update-section', 'updateFooterSection')->name('update-section');
                    });
                });
            });

            Route::group(['prefix' => 'additional-data-setup', 'as' => 'additional-data-setup.'], function () {
                Route::controller(AdditionalDataSetupController::class)->group(function () {
                    Route::get('/{userType}', 'getAdditionalData')->name('index');
                    Route::post('/update', 'update')->name('update');
                });
            });
        });

        Route::controller(SystemSettingController::class)->group(function () {
            Route::group(['prefix' => 'environment-setup', 'as' => 'environment-setup.'], function () {
                Route::get('/', 'envSetup')->name('index');
                Route::post('update', 'envUpdate')->name('update');
            });
            Route::group(['prefix' => 'app-version-setup', 'as' => 'app-version-setup.'], function () {
                Route::get('/', 'appVersionSetup')->name('index');
                Route::post('update', 'updateAppVersionSetup')->name('update');
            });

            Route::group(['prefix' => 'clean-database', 'as' => 'clean-database.'], function () {
                Route::get('/', 'dbIndex')->name('index');
                Route::post('update', 'cleanDb')->name('clean');
            });

            Route::group(['prefix' => 'deep-linking-setup', 'as' => 'deep-linking.'], function () {
                Route::get('/', 'deepLinkingIndex')->name('index');
                Route::post('update', 'updateDeepLinking')->name('update');
            });
        });

        Route::group(['prefix' => 'languages', 'as' => 'languages.'], function () {
            Route::controller(LanguageController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('add-new', 'store')->name('add-new');
                Route::get('update-status', 'updateStatus')->name('update-status');
                Route::post('update-default-status', 'updateDefaultStatus')->name('update-default-status');
                Route::delete('delete/{lang}', 'delete')->name('delete');
                Route::get('translate/{lang}', 'translate')->name('translate');
                Route::post('update', 'update')->name('update');

                Route::post('translate-submit/{lang}', 'translateSubmit')->name('translate-submit');
                Route::any('auto-translate/{lang}', 'autoTranslate')->name('auto-translate');
                Route::any('auto-translate-all/{lang}', 'autoTranslateAll')->name('auto-translate-all');
            });
        });
    });
});
