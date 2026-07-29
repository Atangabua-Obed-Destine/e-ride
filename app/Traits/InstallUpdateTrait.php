<?php

namespace App\Traits;


use Illuminate\Support\Facades\DB;
use Modules\BusinessManagement\Entities\LandingPageSection;
use Modules\BusinessManagement\Service\BusinessSettingService;

trait InstallUpdateTrait
{
    public function setupScheduleTripSettingsData(){
        insertBusinessSetting(keyName: 'schedule_trip_status', settingType: SCHEDULE_TRIP_SETTINGS, value: '0');
        insertBusinessSetting(keyName: 'minimum_schedule_book_time', settingType: SCHEDULE_TRIP_SETTINGS, value: '1');
        insertBusinessSetting(keyName: 'minimum_schedule_book_time_type', settingType: SCHEDULE_TRIP_SETTINGS, value: 'day');
        insertBusinessSetting(keyName: 'advance_schedule_book_time', settingType: SCHEDULE_TRIP_SETTINGS, value: '1');
        insertBusinessSetting(keyName: 'advance_schedule_book_time_type', settingType: SCHEDULE_TRIP_SETTINGS, value: 'day');
        insertBusinessSetting(keyName: 'driver_request_notify_time', settingType: SCHEDULE_TRIP_SETTINGS, value: '1');
        insertBusinessSetting(keyName: 'driver_request_notify_time_type', settingType: SCHEDULE_TRIP_SETTINGS, value: 'day');
        insertBusinessSetting(keyName: 'increase_fare', settingType: SCHEDULE_TRIP_SETTINGS, value: 0);
        insertBusinessSetting(keyName: 'increase_fare_amount', settingType: SCHEDULE_TRIP_SETTINGS, value: '1');
    }

    public function setupVersion2Point7Data()
    {
        insertBusinessSetting(keyName: 'cash_in_hand_setup_status', settingType: DRIVER_SETTINGS, value: '0');
        insertBusinessSetting(keyName: 'max_amount_to_hold_cash', settingType: DRIVER_SETTINGS, value: '100');
        insertBusinessSetting(keyName: 'min_amount_to_pay', settingType: DRIVER_SETTINGS, value: '20');
        insertBusinessSetting(keyName: 'customer_wallet', settingType: CUSTOMER_SETTINGS, value: ['add_fund_status' => 0, 'min_deposit_limit' => 10]);
        insertBusinessSetting(keyName: 'do_not_charge_customer_return_fee', settingType: PARCEL_SETTINGS, value: '1');
    }

    public function setupDriverFaceVerificationData()
    {
        insertBusinessSetting(keyName: 'initiate_face_verification', settingType: FACE_VERIFICATION_SETTINGS, value: ['during_sign_up']);
        insertBusinessSetting(keyName: 'choose_verification_when_to_trigger', settingType: FACE_VERIFICATION_SETTINGS, value: 'before_going_online');
        insertBusinessSetting(keyName: 'trigger_frequency_time_within_a_time_period', settingType: FACE_VERIFICATION_SETTINGS, value: '1');
        insertBusinessSetting(keyName: 'trigger_frequency_time_type_within_a_time_period', settingType: FACE_VERIFICATION_SETTINGS, value: 'day');
        insertBusinessSetting(keyName: 'trips_required_before_random_verification', settingType: FACE_VERIFICATION_SETTINGS, value: '1');
    }

    public function setupThreePointZeroDataForUpdate(): void
    {
        $rows = [

            // ================= INTRO SECTION =================
            [
                'key_name' => 'intro_contents',
                'settings_type' => 'intro_section',
                'value' => [
                    'title' => 'Navigate Life with Ease: Welcome to DriveMond, Your Premier Ride-Sharing Experience!',
                    'sub_title' => 'Unlock a World of Convenience: Welcome to DriveMond, Your Ultimate Ride-Sharing Destination! Seamlessly Connect with Reliable Drivers, Enjoy Comfortable Journeys- One Ride at a Time.',
                    'background_image' => '',
                ],
            ],

            // ================= BUSINESS STATISTICS =================
            [
                'key_name' => 'total_download',
                'settings_type' => 'business_statistics',
                'value' => [
                    'image' => '',
                    'title' => '40K+',
                    'status' => 1,
                    'content' => '"Join the millions who\'ve chosen excelle"',
                ],
            ],
            [
                'key_name' => 'complete_ride',
                'settings_type' => 'business_statistics',
                'value' => [
                    'image' => '',
                    'title' => '20M+',
                    'status' => 1,
                    'content' => 'Complete Ride',
                ],
            ],
            [
                'key_name' => 'happy_customer',
                'settings_type' => 'business_statistics',
                'value' => [
                    'image' => '',
                    'title' => '1M+',
                    'status' => 1,
                    'content' => 'Happy Customer',
                ],
            ],
            [
                'key_name' => 'support',
                'settings_type' => 'business_statistics',
                'value' => [
                    'image' => '',
                    'title' => '24/7hr',
                    'status' => 1,
                    'content' => 'Support',
                ],
            ],
            [
                'key_name' => 'is_business_statistics_enabled',
                'settings_type' => 'business_statistics',
                'value' => '1',
            ],

            // ================= OUR SOLUTIONS =================
            [
                'key_name' => 'intro_contents',
                'settings_type' => 'our_solutions',
                'value' => [
                    'title' => 'Our **Solutions**',
                    'sub_title' => 'Explore our dynamic day-to-day solution for everyday life',
                ],
            ],
            [
                'key_name' => 'is_our_solutions_enabled',
                'settings_type' => 'our_solutions',
                'value' => '1',
            ],

            // ================= OUR SERVICES =================
            [
                'key_name' => 'intro_contents',
                'settings_type' => 'our_services',
                'value' => [
                    'title' => 'Our Services',
                    'subtitle' => 'Discover our innovative solutions designed to enhance daily operations.',
                ],
            ],
            [
                'key_name' => 'is_our_services_enabled',
                'settings_type' => 'our_services',
                'value' => '1',
            ],

            // ================= GALLERY =================
            [
                'key_name' => 'is_gallery_enabled',
                'settings_type' => 'gallery',
                'value' => '1',
            ],

            // ================= TESTIMONIAL =================
            [
                'key_name' => 'intro_contents',
                'settings_type' => 'testimonial',
                'value' => [
                    'title' => '**2000+** People Share Their Love',
                ],
            ],
            [
                'key_name' => 'is_testimonial_enabled',
                'settings_type' => 'testimonial',
                'value' => '1',
            ],

            // ================= FOOTER =================
            [
                'key_name' => 'footer_contents',
                'settings_type' => 'footer',
                'value' => [
                    'title' => 'Connect with our social media and other sites to keep up to date',
                ],
            ],

            // ================= NEWSLETTER =================
            [
                'key_name' => 'intro_contents',
                'settings_type' => 'newsletter',
                'value' => [
                    'title' => "GET ALL UPDATES & EXCITING NEWS",
                    'subtitle' => "Subscribe to our newsletters to receive all the latest activity we provide for you",
                    'background_image' => ""
                ],
            ],
            [
                'key_name' => 'is_newsletter_enabled',
                'settings_type' => 'newsletter',
                'value' => "1",
            ],

            // ================= EARN MONEY =================
            [
                'key_name' => 'is_earn_money_enabled',
                'settings_type' => 'earn_money',
                'value' => "1",
            ],
            [
                'key_name' => 'button_contents',
                'settings_type' => 'earn_money',
                'value' => [
                    'image' => '',
                    'title' => "Download the Delivery / Driver App",
                    'subtitle' => "Start your earning Journey here"
                ],
            ],

            // ================= CUSTOMER APP DOWNLOAD =================
            [
                'key_name' => 'is_customer_app_download_enabled',
                'settings_type' => 'customer_app_download',
                'value' => "1",
            ],
            [
                'key_name' => 'intro_contents',
                'settings_type' => 'customer_app_download',
                'value' => [
                    'image' => '',
                    'title' => "Your **Smooth Ride**, Just a Tap Away",
                    'subtitle' => "Experience hassle-free transportation with DriveMond. Reliable rides anytime, anywhere."
                ],
            ],
            [
                'key_name' => 'button_contents',
                'settings_type' => 'customer_app_download',
                'value' => [
                    'image' => '',
                    'title' => "Download the User App",
                    'subtitle' => "Start your Journey here."
                ],
            ],

        ];

        foreach ($rows as $row) {

            $exists = LandingPageSection::where('key_name', $row['key_name'])
                ->where('settings_type', $row['settings_type'])
                ->exists();

            if ($exists) {
                continue;
            }

            LandingPageSection::create([
                'key_name' => $row['key_name'],
                'settings_type' => $row['settings_type'],
                'value' => $row['value'], // auto json via cast
            ]);
        }

        $service1 = landingPageConfig(key: 'service_1', settingsType: OUR_SERVICES)?->value ?? [];
        if (empty($service1['image']) && empty($service1['title']) && empty($service1['tab_name']) && empty($service1['description']))
        {
            LandingPageSection::updateOrCreate(['key_name' => 'service_1', 'settings_type' => OUR_SERVICES], [
                'value' => [
                    'image' => '',
                    'title' => "Hit the road instantly and start **earning** on your own terms",
                    'status' => 1,
                    'tab_name' => "Regular Trip",
                    'description' => "<p>Join the DriveMond community of drivers and turn every mile into a milestone with our seamless, real-time trip booking system.</p><ul><li>Accept trip requests that fit your current location and availability with just a single tap.</li><li>Whether you prefer the comfort of a car or the agility of a motorbike, we support your choice of ride.</li><li>Track your income in real-time with instant payouts and performance-based rewards after every ride.</li></ul>"
                ],
            ]);
        }

        $service2 = landingPageConfig(key: 'service_2', settingsType: OUR_SERVICES)?->value ?? [];
        if (empty($service2['image']) && empty($service2['title']) && empty($service2['tab_name']) && empty($service2['description']))
        {
            LandingPageSection::updateOrCreate(['key_name' => 'service_2', 'settings_type' => OUR_SERVICES], [
                'value' => [
                    'image' => '',
                    'title' => "Plan your next adventure with DriveMond's trip **scheduling** features.",
                    'status' => 1,
                    'tab_name' => "Schedule Trip",
                    'description' => "<p>Discover endless opportunities to schedule trips that align with your skills and interests, transforming your time into a profitable venture.</p><ul><li>Discover endless opportunities to schedule trips that align with your skills and interests, transforming your time into a profitable venture.</li><li>Enjoy the freedom of scheduling trips that suit your personal timetable.</li><li>Enjoy the freedom of scheduling trips that suit your personal timetable.                                                \r\n                                            </li></ul>"
                ]
            ]);
        }

        $service3 = landingPageConfig(key: 'service_3', settingsType: OUR_SERVICES)?->value ?? [];
        if (empty($service3['image']) && empty($service3['title']) && empty($service3['tab_name']) && empty($service3['description']))
        {
            LandingPageSection::updateOrCreate(['key_name' => 'service_3', 'settings_type' => OUR_SERVICES], [
                'value' => [
                    'image' => '',
                    'title' => "Become a delivery **hero** and keep your city moving.",
                    'status' => 1,
                    'tab_name' => "Parcel Delivery",
                    'description' => "<p>Unlock a steady stream of income by delivering packages and essentials. It’s a flexible way to earn while exploring every corner of your neighborhood.</p><ul><li>Get optimized routes for multi-stop deliveries, ensuring you save time and fuel while maximizing earnings.</li><li>Use your bicycle, scooter, or car to handle everything from small envelopes to larger parcels.</li><li>Benefit from a consistent flow of delivery tasks, competitive base rates, and tips from satisfied customers.</li></ul><p></p><p>                                                \r\n                                            </p>"
                ]
            ]);
        }

        $gallery1 = landingPageConfig(key: 'card_1', settingsType: GALLERY)?->value ?? [];
        if (empty($gallery1['image']) && empty($gallery1['title']) && empty($gallery1['subtitle']))
        {
            LandingPageSection::updateOrCreate(['key_name' => 'card_1', 'settings_type' => GALLERY], [
                'value' => [
                    'image' => '',
                    'title' => "Ride Completed **Hassle-Free**",
                    'subtitle' => "Experience comfort, safety, and satisfaction with every trip. End your journey with a smile — every time with DriveMond.",
                ]
            ]);
        }

        $gallery2 = landingPageConfig(key: 'card_2', settingsType: GALLERY)?->value ?? [];
        if (empty($gallery2['image']) && empty($gallery2['title']) && empty($gallery2['subtitle']))
        {
            LandingPageSection::updateOrCreate(['key_name' => 'card_2', 'settings_type' => GALLERY],[
                'value' => [
                    'image' => '',
                    'title' =>  "Easily **Share** Your Ride",
                    'subtitle' => "With every turn of the wheel, discover something new — because each ride opens the door to infinite possibilities.",
                ]
            ]);
        }
    }

    public function setupThreePointOneData(): void
    {
        insertBusinessSetting(keyName: 'customer_login_options', settingType: LOGIN_SETTINGS, value: ['manual_login' => 1, 'otp_login' => 0]);
        insertBusinessSetting(keyName: 'driver_login_options', settingType: LOGIN_SETTINGS, value: ['manual_login' => 1, 'otp_login' => 0]);

        if (!isOtpEnabled())
        {
            updateBusinessSetting(keyName: 'customer_verification', settingType: BUSINESS_INFORMATION, value: 0);
            updateBusinessSetting(keyName: 'driver_verification', settingType: BUSINESS_INFORMATION, value: 0);
        }

        insertBusinessSetting(keyName: 'temporary_block_time', settingType: BUSINESS_SETTINGS, value: "30");
        insertBusinessSetting(keyName: 'otp_resend_time', settingType: BUSINESS_SETTINGS, value: "30");
        insertBusinessSetting(keyName: 'maximum_otp_hit', settingType: BUSINESS_SETTINGS, value: "3");
        insertBusinessSetting(keyName: 'temporary_login_block_time', settingType: BUSINESS_SETTINGS, value: "30");
        insertBusinessSetting(keyName: 'maximum_login_hit', settingType: BUSINESS_SETTINGS, value: "3");
    }

}
