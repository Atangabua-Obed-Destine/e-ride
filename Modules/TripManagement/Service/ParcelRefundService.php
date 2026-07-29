<?php

namespace Modules\TripManagement\Service;

use App\Service\BaseService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\PromotionManagement\Repository\CouponSetupRepositoryInterface;
use Modules\PromotionManagement\Service\Interfaces\CouponSetupServiceInterface;
use Modules\TransactionManagement\Traits\TransactionTrait;
use Modules\TripManagement\Repository\ParcelRefundRepositoryInterface;

class ParcelRefundService extends BaseService implements Interfaces\ParcelRefundServiceInterface
{
    use TransactionTrait;

    protected $parcelRefundRepository;
    protected $couponSetupService;

    public function __construct(ParcelRefundRepositoryInterface $parcelRefundRepository, CouponSetupServiceInterface $couponSetupService)
    {
        parent::__construct($parcelRefundRepository);
        $this->parcelRefundRepository = $parcelRefundRepository;
        $this->couponSetupService = $couponSetupService;
    }

    public function index(array $criteria = [], array $relations = [], array $whereHasRelations = [], array $orderBy = [], ?int $limit = null, ?int $offset = null, array $withCountQuery = [], array $appends = [], array $groupBy = []): Collection|LengthAwarePaginator
    {
        $data = [];
        if (array_key_exists('type', $criteria)) {
            $data = array_merge($data, [
                'type' => $criteria['type']
            ]);
        }
        if (array_key_exists('status', $criteria)) {
            $data = array_merge($data, [
                'status' => $criteria['status']
            ]);
        }

        $searchData = [];
        if (array_key_exists('search', $criteria) && $criteria['search'] != '') {
            $searchData['fields'] = ['readable_id'];
            $searchData['relations'] = [
                'tripRequest' => ['ref_id'],
                'tripRequest.customer' => ['full_name', 'first_name', 'last_name', 'email', 'phone'],
                'tripRequest.driver' => ['full_name', 'first_name', 'last_name', 'email', 'phone'],
            ];
            $searchData['value'] = $criteria['search'];
        }
        $whereInCriteria = [];
        $whereBetweenCriteria = [];
        return $this->parcelRefundRepository->getBy(criteria: $data, searchCriteria: $searchData, whereInCriteria: $whereInCriteria, whereBetweenCriteria: $whereBetweenCriteria, whereHasRelations: $whereHasRelations, relations: $relations, orderBy: $orderBy, limit: $limit, offset: $offset, withCountQuery: $withCountQuery, appends: $appends);
    }

    public function create(array $data): ?Model
    {
        $documents = [];
        if (array_key_exists('attachments', $data)) {
            foreach ($data['attachments'] as $doc) {
                $extension = $doc->getClientOriginalExtension();
                $attachment = fileUploader('parcel/proof/', $extension, $doc);
                $documents[] = ['attachment' => $attachment];
            }
        }
        $storeData = [
            'trip_request_id' => $data['trip_request_id'],
            'reason' => $data['reason'],
            'customer_note' => $data['customer_note'],
            'parcel_approximate_price' => $data['parcel_approximate_price'],
        ];
        DB::beginTransaction();
        $parcelRefund = $this->parcelRefundRepository->create($storeData);
        $parcelRefund?->refundProofs()->createMany($documents);
        DB::commit();
        return $parcelRefund;
    }

    public function update(int|string $id, array $data = []): ?Model
    {
        $parcelRefund = $this->parcelRefundRepository->findOne(id: $id);
        if (array_key_exists('status', $data)) {
            if ($data['status'] == DENIED) {
                $updateData = [
                    'deny_note' => $data['note'],
                    'status' => $data['status']
                ];
                $parcelRefund = $this->parcelRefundRepository->update(id: $id, data: $updateData);
            } elseif ($data['status'] == APPROVED) {
                $updateData = [
                    'approval_note' => $data['note'],
                    'status' => $data['status']
                ];
                $parcelRefund = $this->parcelRefundRepository->update(id: $id, data: $updateData);
            } else {
                $updateData = [
                    'refund_amount_by_admin' => $data['refund_amount'],
                    'refund_method' => $data['refund_method'],
                    'note' => $data['refund_note'],
                    'status' => $data['status']
                ];
                DB::beginTransaction();
                $parcelRefund = $this->parcelRefundRepository->update(id: $id, data: $updateData);
                $this->parcelRefundDriverTransaction($parcelRefund?->tripRequest, $data['refund_amount']);
                if ($data['refund_method'] == 'wallet') {
                    $this->parcelRefundWalletTransaction($parcelRefund?->tripRequest, $data['refund_amount']);
                }
                if ($data['refund_method'] == 'coupon') {
                    $this->parcelRefundCouponCreate($parcelRefund?->tripRequest, $data['refund_amount']);
                }

                DB::commit();
            }
        }
        return $parcelRefund;
    }

    private function parcelRefundCouponCreate($trip, $amount)
    {
        $randomString = Str::random(6) . rand(1000, 9999);
        $randomString = str_shuffle($randomString);
        $shuffledString = substr($randomString, 0, 10);
        $couponData = [
            "coupon_title" => "Parcel Refund Coupon For " . $trip->customer->first_name . " " . $trip->customer->last_name,
            "short_desc" => "Parcel refund Coupon Given By Admin",
            "coupon_code" => $shuffledString,
            "zone_coupon_type" => ["all"],
            "customer_level_coupon_type" => ["all"],
            "customer_coupon_type" => [
                $trip->customer_id
            ],
            "category_coupon_type" => ["all"],
            "limit_same_user" => "1",
            "coupon_type" => "default",
            "amount_type" => "amount",
            "coupon" => $amount,
            "minimum_trip_amount" => ($amount + 1),
            "max_coupon_amount" => 0,
            "start_date" => Carbon::today()->format('Y-m-d'),
            "end_date" => Carbon::today()->addYear()->format('Y-m-d'),
        ];
        DB::beginTransaction();
        $couponData = $this->couponSetupService->create(data: $couponData);
        $updateData = [
            'coupon_setup_id' => $couponData->id,
        ];
        $this->parcelRefundRepository->update(id: $trip->parcelRefund->id, data: $updateData);
        DB::commit();
    }

}
