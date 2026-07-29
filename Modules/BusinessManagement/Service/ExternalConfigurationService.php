<?php

namespace Modules\BusinessManagement\Service;

use App\Service\BaseService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Modules\BusinessManagement\Repository\ExternalConfigurationRepositoryInterface;
use Modules\BusinessManagement\Service\Interfaces\ExternalConfigurationServiceInterface;

class ExternalConfigurationService extends BaseService implements Interfaces\ExternalConfigurationServiceInterface
{
    protected $externalConfigurationRepository;

    public function __construct(ExternalConfigurationRepositoryInterface $externalConfigurationRepository)
    {
        parent::__construct($externalConfigurationRepository);
        $this->externalConfigurationRepository = $externalConfigurationRepository;
    }

    public function storeExternalInfo(array $data): bool
    {
        if (array_key_exists('activation_mode', $data)) {
            $externalConfiguration = $this->externalConfigurationRepository
                ->findOneBy(criteria: ['key' => 'activation_mode']);
            $data0 = [
                'key' => 'activation_mode',
                'value' => 1,
            ];
            if ($externalConfiguration) {
                $this->externalConfigurationRepository->update(id: $externalConfiguration->id, data: $data0);
            } else {
                $this->externalConfigurationRepository->create(data: $data0);
            }
        } else {
            $externalConfiguration = $this->externalConfigurationRepository
                ->findOneBy(criteria: ['key' => 'activation_mode']);
            $data0 = [
                'key' => 'activation_mode',
                'value' => 0,
            ];
            if ($externalConfiguration) {
                $this->externalConfigurationRepository->update(id: $externalConfiguration->id, data: $data0);
            } else {
                $this->externalConfigurationRepository->create(data: $data0);
            }
        }
        $externalConfiguration1 = $this->externalConfigurationRepository
            ->findOneBy(criteria: ['key' => 'mart_base_url']);
        $data1 = [
            'key' => 'mart_base_url',
            'value' => $data['mart_base_url'],
        ];
        if ($externalConfiguration1) {
            $this->externalConfigurationRepository->update(id: $externalConfiguration1->id, data: $data1);
        } else {
            $this->externalConfigurationRepository->create(data: $data1);
        }
        $externalConfiguration2 = $this->externalConfigurationRepository
            ->findOneBy(criteria: ['key' => 'mart_token']);
        $data2 = [
            'key' => 'mart_token',
            'value' => $data['mart_token'],
        ];
        if ($externalConfiguration2) {
            $this->externalConfigurationRepository->update(id: $externalConfiguration2->id, data: $data2);
        } else {
            $this->externalConfigurationRepository->create(data: $data2);
        }

        $externalConfiguration3 = $this->externalConfigurationRepository
            ->findOneBy(criteria: ['key' => 'system_self_token']);
        $data3 = [
            'key' => 'system_self_token',
            'value' => $data['system_self_token'],
        ];
        if ($externalConfiguration3) {
            $this->externalConfigurationRepository->update(id: $externalConfiguration3->id, data: $data3);
        } else {
            $this->externalConfigurationRepository->create(data: $data3);
        }
        $activationMode = $this->externalConfigurationRepository
            ->findOneBy(criteria: ['key' => 'activation_mode']);
        if ($activationMode && $activationMode->value == 1) {
            try {
                $response = Http::get($data['mart_base_url'] . '/api/v1/configurations');
                if ($response->status() == 200) {
                    $martConfig = $response->json();
                    $externalConfigurationBusinessName = $this->externalConfigurationRepository
                        ->findOneBy(criteria: ['key' => 'mart_business_name']);
                    $externalConfigurationBusinessNameData = [
                        'key' => 'mart_business_name',
                        'value' => $martConfig['business_name'],
                    ];
                    if ($externalConfigurationBusinessName) {
                        $this->externalConfigurationRepository->update(id: $externalConfigurationBusinessName->id, data: $externalConfigurationBusinessNameData);
                    } else {
                        $this->externalConfigurationRepository->create(data: $externalConfigurationBusinessNameData);
                    }
                    $externalConfigurationBusinessLogo = $this->externalConfigurationRepository
                        ->findOneBy(criteria: ['key' => 'mart_business_logo']);
                    $externalConfigurationBusinessLogoData = [
                        'key' => 'mart_business_logo',
                        'value' => $martConfig['logo'],
                    ];
                    if ($externalConfigurationBusinessLogo) {
                        $this->externalConfigurationRepository->update(id: $externalConfigurationBusinessLogo->id, data: $externalConfigurationBusinessLogoData);
                    } else {
                        $this->externalConfigurationRepository->create(data: $externalConfigurationBusinessLogoData);
                    }
                    $externalConfigurationAppUrlAndroid = $this->externalConfigurationRepository
                        ->findOneBy(criteria: ['key' => 'mart_app_url_android']);
                    $externalConfigurationAppUrlAndroidData = [
                        'key' => 'mart_app_url_android',
                        'value' => $martConfig['app_url_android'] ?? "",
                    ];
                    if ($externalConfigurationAppUrlAndroid) {
                        $this->externalConfigurationRepository->update(id: $externalConfigurationAppUrlAndroid->id, data: $externalConfigurationAppUrlAndroidData);
                    } else {
                        $this->externalConfigurationRepository->create(data: $externalConfigurationAppUrlAndroidData);
                    }
                    $externalConfigurationAppUrlIos = $this->externalConfigurationRepository
                        ->findOneBy(criteria: ['key' => 'mart_app_url_ios']);
                    $externalConfigurationAppUrlIosData = [
                        'key' => 'mart_app_url_ios',
                        'value' => $martConfig['app_url_android'] ?? "",
                    ];
                    if ($externalConfigurationAppUrlIos) {
                        $this->externalConfigurationRepository->update(id: $externalConfigurationAppUrlIos->id, data: $externalConfigurationAppUrlIosData);
                    } else {
                        $this->externalConfigurationRepository->create(data: $externalConfigurationAppUrlIosData);
                    }

                    $externalConfigurationAppMinimumVersionAndroid = $this->externalConfigurationRepository
                        ->findOneBy(criteria: ['key' => 'mart_app_minimum_version_android']);
                    $externalConfigurationAppMinimumVersionAndroidData = [
                        'key' => 'mart_app_minimum_version_android',
                        'value' => $martConfig['app_minimum_version_android'] ?? 0,
                    ];
                    if ($externalConfigurationAppMinimumVersionAndroid) {
                        $this->externalConfigurationRepository->update(id: $externalConfigurationAppMinimumVersionAndroid->id, data: $externalConfigurationAppMinimumVersionAndroidData);
                    } else {
                        $this->externalConfigurationRepository->create(data: $externalConfigurationAppMinimumVersionAndroidData);
                    }

                    $externalConfigurationAppMinimumVersionIos = $this->externalConfigurationRepository
                        ->findOneBy(criteria: ['key' => 'mart_app_minimum_version_ios']);
                    $externalConfigurationAppMinimumVersionIosData = [
                        'key' => 'mart_app_minimum_version_ios',
                        'value' => $martConfig['app_minimum_version_ios'] ?? 0,
                    ];
                    if ($externalConfigurationAppMinimumVersionIos) {
                        $this->externalConfigurationRepository->update(id: $externalConfigurationAppMinimumVersionIos->id, data: $externalConfigurationAppMinimumVersionIosData);
                    } else {
                        $this->externalConfigurationRepository->create(data: $externalConfigurationAppMinimumVersionIosData);
                    }
                    return true;
                }
            } catch (\Exception $exception) {

            }

            $externalConfiguration = $this->externalConfigurationRepository
                ->findOneBy(criteria: ['key' => 'activation_mode']);
            $data0 = [
                'key' => 'activation_mode',
                'value' => 0,
            ];
            if ($externalConfiguration) {
                $this->externalConfigurationRepository->update(id: $externalConfiguration->id, data: $data0);
            } else {
                $this->externalConfigurationRepository->create(data: $data0);
            }
            return false;
        }
        return true;

    }

    public function updateExternalConfiguration(array $data)
    {
        if (array_key_exists('mart_business_name', $data)) {
            $externalConfiguration1 = $this->externalConfigurationRepository
                ->findOneBy(criteria: ['key' => 'mart_business_name']);
            $data1 = [
                'key' => 'mart_business_name',
                'value' => $data['mart_business_name'],
            ];
            if ($externalConfiguration1) {
                $this->externalConfigurationRepository->update(id: $externalConfiguration1->id, data: $data1);
            } else {
                $this->externalConfigurationRepository->create(data: $data1);
            }
        }
        if (array_key_exists('mart_business_logo', $data)) {
            $externalConfiguration1 = $this->externalConfigurationRepository
                ->findOneBy(criteria: ['key' => 'mart_business_logo']);
            $data1 = [
                'key' => 'mart_business_logo',
                'value' => $data['mart_business_logo'],
            ];
            if ($externalConfiguration1) {
                $this->externalConfigurationRepository->update(id: $externalConfiguration1->id, data: $data1);
            } else {
                $this->externalConfigurationRepository->create(data: $data1);
            }
        }
        if (array_key_exists('mart_app_url_android', $data)) {
            $externalConfiguration1 = $this->externalConfigurationRepository
                ->findOneBy(criteria: ['key' => 'mart_app_url_android']);
            $data1 = [
                'key' => 'mart_app_url_android',
                'value' => $data['mart_app_url_android'],
            ];
            if ($externalConfiguration1) {
                $this->externalConfigurationRepository->update(id: $externalConfiguration1->id, data: $data1);
            } else {
                $this->externalConfigurationRepository->create(data: $data1);
            }
        }
        if (array_key_exists('mart_app_url_ios', $data)) {
            $externalConfiguration1 = $this->externalConfigurationRepository
                ->findOneBy(criteria: ['key' => 'mart_app_url_ios']);
            $data1 = [
                'key' => 'mart_app_url_ios',
                'value' => $data['mart_app_url_ios'],
            ];
            if ($externalConfiguration1) {
                $this->externalConfigurationRepository->update(id: $externalConfiguration1->id, data: $data1);
            } else {
                $this->externalConfigurationRepository->create(data: $data1);
            }
        }
        if (array_key_exists('mart_app_minimum_version_ios', $data)) {
            $externalConfiguration1 = $this->externalConfigurationRepository
                ->findOneBy(criteria: ['key' => 'mart_app_minimum_version_ios']);
            $data1 = [
                'key' => 'mart_app_minimum_version_ios',
                'value' => $data['mart_app_minimum_version_ios'],
            ];
            if ($externalConfiguration1) {
                $this->externalConfigurationRepository->update(id: $externalConfiguration1->id, data: $data1);
            } else {
                $this->externalConfigurationRepository->create(data: $data1);
            }
        }
        if (array_key_exists('mart_app_minimum_version_android', $data)) {
            $externalConfiguration1 = $this->externalConfigurationRepository
                ->findOneBy(criteria: ['key' => 'mart_app_minimum_version_android']);
            $data1 = [
                'key' => 'mart_app_minimum_version_android',
                'value' => $data['mart_app_minimum_version_android'],
            ];
            if ($externalConfiguration1) {
                $this->externalConfigurationRepository->update(id: $externalConfiguration1->id, data: $data1);
            } else {
                $this->externalConfigurationRepository->create(data: $data1);
            }
        }
    }
}
