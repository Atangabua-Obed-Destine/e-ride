<?php

namespace Modules\BusinessManagement\Service;

use App\Service\BaseService;
use Modules\BusinessManagement\Repository\ParcelCancellationReasonRepositoryInterface;

class ParcelCancellationReasonService extends BaseService implements Interfaces\ParcelCancellationReasonServiceInterface
{
    protected $parcelCancellationReasonRepository;
    public function __construct(ParcelCancellationReasonRepositoryInterface $parcelCancellationReasonRepository)
    {
        parent::__construct($parcelCancellationReasonRepository);
        $this->parcelCancellationReasonRepository = $parcelCancellationReasonRepository;
    }
}
