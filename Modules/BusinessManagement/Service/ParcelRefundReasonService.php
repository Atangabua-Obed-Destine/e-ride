<?php

namespace Modules\BusinessManagement\Service;

use App\Service\BaseService;
use Modules\BusinessManagement\Repository\ParcelRefundReasonRepositoryInterface;
use Modules\BusinessManagement\Service\Interfaces\ParcelRefundReasonServiceInterface;

class ParcelRefundReasonService extends BaseService implements Interfaces\ParcelRefundReasonServiceInterface
{
    protected $parcelRefundReasonRepository;
    public function __construct(ParcelRefundReasonRepositoryInterface $parcelRefundReasonRepository)
    {
        parent::__construct($parcelRefundReasonRepository);
        $this->parcelRefundReasonRepository = $parcelRefundReasonRepository;
    }
}
