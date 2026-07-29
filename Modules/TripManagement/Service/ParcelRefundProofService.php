<?php

namespace Modules\TripManagement\Service;

use App\Service\BaseService;
use Modules\TripManagement\Repository\ParcelRefundProofRepositoryInterface;

class ParcelRefundProofService extends BaseService implements Interfaces\ParcelRefundProofServiceInterface
{
    protected $parcelRefundProofRepository;
    public function __construct(ParcelRefundProofRepositoryInterface $parcelRefundProofRepository)
    {
        parent::__construct($parcelRefundProofRepository);
        $this->parcelRefundProofRepository = $parcelRefundProofRepository;
    }
}
