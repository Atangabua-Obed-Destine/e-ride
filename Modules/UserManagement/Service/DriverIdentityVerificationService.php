<?php

namespace Modules\UserManagement\Service;


use App\Service\BaseService;
use Aws\Exception\AwsException;
use Aws\Rekognition\RekognitionClient;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\UserManagement\Enums\SuspendReasonEnum;
use Modules\UserManagement\Repository\DriverIdentityVerificationRepositoryInterface;
use Modules\UserManagement\Service\Interfaces\DriverIdentityVerificationServiceInterface;

class DriverIdentityVerificationService extends BaseService implements DriverIdentityVerificationServiceInterface
{
    protected $driverIdentityVerificationService;

    public function __construct(DriverIdentityVerificationRepositoryInterface $driverIdentityVerificationService)
    {
        parent::__construct($driverIdentityVerificationService);
        $this->driverIdentityVerificationService = $driverIdentityVerificationService;
    }

    public function index(array $criteria = [], array $relations = [], array $whereHasRelations = [], array $orderBy = [], ?int $limit = null, ?int $offset = null, array $withCountQuery = [], array $appends = [], array $groupBy = []): Collection|LengthAwarePaginator
    {
        $data = [];
        $searchData = [];
        if (array_key_exists('verification_status', $criteria)) {
            $data = array_merge($data, [
                'current_status' => $criteria['verification_status']
            ]);
        }

        if (array_key_exists('search', $criteria) && $criteria['search'] != '') {
            $searchData['fields'] = ['driver_id'];
            $searchData['relations'] = [
                'driver' => ['full_name', 'first_name', 'last_name', 'email', 'phone'],
            ];
            $searchData['value'] = $criteria['search'];
        }

        $whereBetweenCriteria = [];
        if (array_key_exists('filter_date', $criteria)) {
            $whereBetweenCriteria = ['created_at' => $criteria['filter_date']];
        }

        if (array_key_exists('order_by', $criteria)) {
            $orderBy = ['created_at' => $criteria['order_by']];
        }

        return $this->driverIdentityVerificationService->getBy(criteria: $data, searchCriteria: $searchData, whereInCriteria: $whereInCriteria ?? [], whereBetweenCriteria: $whereBetweenCriteria, whereHasRelations: $whereHasRelations, relations: $relations, orderBy: $orderBy, limit: $limit, offset: $offset, withCountQuery: $withCountQuery, appends: $appends, groupBy: $groupBy);

    }


    public function skip(?Model $user): void
    {
        $driverVerifyIdentity = (((int) (businessConfig(key: 'face_verification_api')?->value['status'] ?? 0))
            && ((int) (businessConfig(key: 'driver_identity_verification_status')?->value ?? 0))
        );
        $chooseVerificationWhenToTrigger = $driverVerifyIdentity
        && in_array('at_intervals', businessConfig(key: 'initiate_face_verification')?->value ?? [])
            ? businessConfig(key: 'choose_verification_when_to_trigger')?->value : null;

        $triggeringPeriod = $chooseVerificationWhenToTrigger == 'within_a_time_period' ? (int) convertTimeToSecond(
            businessConfig(key: 'trigger_frequency_time_within_a_time_period')?->value,
            businessConfig(key: 'trigger_frequency_time_type_within_a_time_period')?->value
        )
            : null;

        DB::transaction(function() use($user, $triggeringPeriod) {
            if ($user->driverIdentityVerification)
            {
                $user->driverIdentityVerification->update(['current_status' => 'skipped']);
            } else {
                $user->driverIdentityVerification()->create(['current_status' => 'skipped']);
            }

            if (!is_null($triggeringPeriod))
            {
                $user->driverDetails->update(['trigger_verification_at' => now()->addSeconds($triggeringPeriod)]);
            }

        });

    }

    public function verify(array $data): array
    {
        $driverVerifyIdentity = (((int) (businessConfig(key: 'face_verification_api')?->value['status'] ?? 0))
            && ((int) (businessConfig(key: 'driver_identity_verification_status')?->value ?? 0))
        );
        $chooseVerificationWhenToTrigger = $driverVerifyIdentity
        && in_array('at_intervals', businessConfig(key: 'initiate_face_verification')?->value ?? [])
            ? businessConfig(key: 'choose_verification_when_to_trigger')?->value : null;

        $triggeringPeriod = $chooseVerificationWhenToTrigger == 'within_a_time_period' ? (int) convertTimeToSecond(
            businessConfig(key: 'trigger_frequency_time_within_a_time_period')?->value,
            businessConfig(key: 'trigger_frequency_time_type_within_a_time_period')?->value
        )
            : null;

        $user = $data['user'];
        if (!$user->driverDetails?->base_image) {
            $baseImage = fileUploader(dir: 'driver/face-verification/', format: 'jpeg', image: $data['image']);

            DB::transaction(function() use($user, $triggeringPeriod, $baseImage) {
                $user->driverDetails->update(['base_image' => $baseImage, 'is_verified' => 1]);

                if (!is_null($triggeringPeriod))
                {
                    $user->driverDetails->update(['trigger_verification_at' => now()->addSeconds($triggeringPeriod)]);
                }

                if ($user?->driverIdentityVerification)
                {
                    $user->driverIdentityVerification->delete();
                }
            });

            return [
                'response_code' => 'ok',
                'message' => 'Face verification completed successfully'
            ];
        }

        $baseImagePath = storage_path('app/public/driver/face-verification/' . $user->driverDetails?->base_image);
        $baseImageInByte = file_get_contents($baseImagePath);
        $toBeVerifiedImageInByte = file_get_contents($data['image']->getRealPath());
        $rekognition = new RekognitionClient([
            'version' => 'latest',
            'region' => $data['faceVerificationApiCred']['region'],
            'credentials' => [
                'key' => $data['faceVerificationApiCred']['access_key'],
                'secret' => $data['faceVerificationApiCred']['secret_access_key'],
            ],
        ]);

        $attemptDetail = null;

        try {
            $result = $rekognition->compareFaces([
                'SourceImage' => [
                    'Bytes' => $baseImageInByte,
                ],
                'TargetImage' => [
                    'Bytes' => $toBeVerifiedImageInByte,
                ],
                'SimilarityThreshold' => 80,
            ])->toArray();

            if (empty($result['FaceMatches'])) {
                $attemptDetail = [
                    'current_status' => 'failed',
                    'time' => now()->format('Y-m-d h:i A'),
                    'reason' => 'Face did not match'
                ];
            } else {
                $similarity = $result['FaceMatches'][0]['Similarity'];

                if ($similarity < 85) {
                    $attemptDetail = [
                        'current_status' => 'failed',
                        'time' => now()->format('Y-m-d h:i A'),
                        'reason' => 'Face match confidence below required threshold'
                    ];
                }
            }
        } catch (AwsException $e) {
            $mapped = mapRekognitionError($e);
            $attemptDetail = [
                'current_status' => 'failed',
                'time' => now()->format('Y-m-d h:i A'),
                'reason' => $mapped['user']
            ];
        }

        $verification = $user->driverIdentityVerification()->first();

        if (!$verification) {
            $verification = $user->driverIdentityVerification()->create([
                'attempt_details' => [],
            ]);
        }

        if ($attemptDetail) {
            $attempts = $verification->attempt_details ?? [];
            $attempts[] = $attemptDetail;

            $verification->update([
                'attempt_details' => $attempts,
                'current_status' => 'failed',
            ]);

            return [
                'response_code' => 'failed',
                'message' => $attemptDetail['reason']
            ];

        }

        DB::transaction(function() use($user, $triggeringPeriod) {

            $user->driverIdentityVerification()->delete();

            $data = [
                'is_verified' => 1
            ];

            if (!is_null($triggeringPeriod))
            {
                $data = array_merge($data, ['trigger_verification_at' => now()->addSeconds($triggeringPeriod)]);
            }

            if ($user?->driverDetails?->is_suspended && $user?->driverDetails?->suspend_reason == SuspendReasonEnum::FACE_VERIFICATION->value)
            {
                $data = array_merge($data, [
                    'is_suspended' => 0,
                    'suspend_reason' => null
                ]);
            }

            $user->driverDetails->update($data);
        });

        return [
            'response_code' => 'ok',
            'message' => 'Face verification completed successfully'
        ];
    }

    public function MarkIdentityAsVerified(?Model $unverifiedDriverInfo, array $data): void
    {
        $driverDetails = $unverifiedDriverInfo->driver->driverDetails;

        if (array_key_exists('image', $data))
        {
            $fileName = fileUploader('driver/face-verification/verified/', image: $data['image'], oldImage: $driverDetails->verified_image ?? '');
        }

        $driverDetails->update([
            'is_verified' => 1,
            'verified_image' => $fileName ?? $driverDetails->verified_image ?? null,
            'suspend_reason' => null
        ]);
        $unverifiedDriverInfo->delete();
    }

    public function MarkIdentityAsSuspended(?Model $unverifiedDriverInfo): void
    {
        $driverDetails = $unverifiedDriverInfo->driver->driverDetails;
        $data = [
            'suspend_reason' => SuspendReasonEnum::FACE_VERIFICATION->value,
            'is_suspended' => 1,
            'is_verified' => 0,
            'verified_image' => null
        ];
        $driverDetails->update($data);
        $unverifiedDriverInfo->delete();
    }
}
