<?php

namespace Modules\BusinessManagement\Service\Interfaces;

use App\Service\BaseServiceInterface;
use Illuminate\Database\Eloquent\Model;

interface BusinessSettingServiceInterface extends BaseServiceInterface
{

    public function storeBusinessInfo(array $data);

    public function updateSetting(array $data);

    public function maintenance(array $data): ?Model;

    public function advanceMaintenance(array $data);

    public function storeDriverSetting(array $data);

    public function storeVehicleUpdateDriverSetting(array $data);

    public function storeCustomerSetting(array $data);

    public function storeTripFareSetting(array $data);

    public function storeBusinessPage(array $data);

    public function storeLandingPageIntroSection(array $data);

    public function storeLandingPageOurSolutionsSection(array $data);

    public function storeLandingPageOurSolutionsData(array $data);

    public function storeLandingPageBusinessStatistics(array $data);

    public function storeLandingPageEarnMoney(array $data);

    public function storeLandingPageTestimonial(array $data);

    public function storeLandingPageCTA(array $data);

    public function storeEmailConfig(array $data);

    public function storeGoogleMapApi(array $data);

    public function storeRecaptha(array $data);

    public function storeAppVersion(array $data);

    public function storeAllZoneExtraFare(array $data);

    public function storeParcelSetting(array $data);

    public function storeParcelTrackingSetting(array $data);

    public function storeParcelRefundSetting(array $data);

    public function storeOrUpdateFirebaseOtpSetting(array $data);

    public function storeSafetyFeature(array $data);

    public function storeEmergencyNumberForCall(array $data);

    public function storeScheduleTrip(array $data): void;

    public function updateCashInHand(array $data): void;
    public function updateIdentityVerification(array $data): void;

    public function updateOtpLoginAttemptsData(array $data): void;
    public function updateLoginSettingsData(array $data): void;
    public function updateUserLoginToDefaultOptions(): void;
    public function storeDeepLinkingInfo(array $data): void;

    public function storeAdditionalDataSetup(array $data): void;
}
