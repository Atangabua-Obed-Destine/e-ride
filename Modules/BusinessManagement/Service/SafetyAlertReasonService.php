<?php

namespace Modules\BusinessManagement\Service;

use App\Service\BaseService;
use Modules\BusinessManagement\Repository\SafetyAlertReasonRepositoryInterface;
use Modules\BusinessManagement\Service\Interfaces\SafetyAlertReasonServiceInterface;

class SafetyAlertReasonService extends BaseService implements Interfaces\SafetyAlertReasonServiceInterface
{
    protected $safetyAlertReasonRepository;
    public function __construct(SafetyAlertReasonRepositoryInterface $safetyAlertReasonRepository)
    {
        parent::__construct($safetyAlertReasonRepository);
        $this->safetyAlertReasonRepository = $safetyAlertReasonRepository;
    }
}
