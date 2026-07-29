<?php

namespace Modules\AiModule\Service;

use App\Service\BaseService;
use Illuminate\Support\Facades\Cache;
use Modules\AiModule\Entities\AiSetting;
use Modules\AiModule\Repository\AiSettingRepositoryInterface;
use Modules\AiModule\Service\Interfaces\AiSettingServiceInterface;

class AiSettingService extends BaseService implements AiSettingServiceInterface
{
    protected $aiSettingRepository;

    public function __construct(AiSettingRepositoryInterface $aiSettingRepository)
    {
        parent::__construct($aiSettingRepository);
        $this->aiSettingRepository = $aiSettingRepository;
    }

    public function getActiveAiProvider()
    {
        return Cache::remember('active_ai_provider', 60, function () {
            return AiSetting::where('status', 1)
                ->whereNotNull('api_key')
                ->where('api_key', '!=', '')
                ->first();
        });
    }
}
