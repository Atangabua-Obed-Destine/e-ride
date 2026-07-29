<?php

namespace Modules\TripManagement\Service\Interfaces;

use App\Service\BaseServiceInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Modules\UserManagement\Entities\User;

interface TripRequestServiceInterface extends BaseServiceInterface
{
    public function getAnalytics($dateRange): mixed;

    public function couponRuleValidate($coupon, $pickupCoordinates, $vehicleCategoryId): ?array;

    public function statusWiseTotalTripRecords(array $attributes);

    public function export(array $criteria = [], array $relations = [], array $orderBy = [], ?int $limit = null, ?int $offset = null, array $withCountQuery = []);

    public function getAdminZoneWiseStatistics(array $data);

    public function getAdminZoneWiseEarning(array $data);

    public function getDateZoneWiseEarningStatistics(array $data);

    public function getDateRideTypeWiseEarningStatistics(array $data);

    public function getDateZoneWiseExpenseStatistics(array $data);

    public function getDateRideTypeWiseExpenseStatistics(array $data);

    public function getLeaderBoard(array $data, $limit = null, $offset = null);

    public function storeTrip(array $attributes): Model;

    public function pendingParcelList(array $attributes);


    public function updateRelationalTable($attributes);

    public function findOneWithAvg(array $criteria = [], array $relations = [], array $withCountQuery = [], bool $withTrashed = false, bool $onlyTrashed = false, array $withAvgRelation = []): ?Model;

    public function getWithAvg(array $criteria = [], array $searchCriteria = [], array $whereInCriteria = [], array $relations = [], array $orderBy = [], ?int $limit = null, ?int $offset = null, bool $onlyTrashed = false, bool $withTrashed = false, array $withCountQuery = [], array $withAvgRelation = [], array $whereBetweenCriteria = [], array $whereNotNullCriteria = []): Collection|LengthAwarePaginator;

    public function getPendingRides($attributes): mixed;

    public function makeRideRequest($request, $pickupCoordinates): mixed;

    public function allNearestDrivers($latitude, $longitude, $zoneId, $radius = 5, $vehicleCategoryId = null): mixed;

    public function validateDiscount($trip, $response, $tripId, $cuponId);

//    public function handleCancelledTrip($trip, $attributes, $tripId);

//    public function handleCompletedTrip($trip, $request, $attributes);

    public function handleCustomerRideStatusUpdate($trip, $request, $attributes);

    public function removeCouponData($trip);

    public function getCustomerIncompleteRide(): mixed;

//    public function handleDriverStatusUpdate($request, $trip);

    public function getDriverIncompleteRide(): mixed;

    public function handleRequestActionPushNotification($trip, $user);

    public function getTripOverview($data);

    public function getPopularTips();

    public function sendParcelTrackingLinkToReceiver($user, $message): bool;

    public function getTripHeatMapCompareDataBy(array $data);

    public function getTripHeatMapCompareZoneDateWiseEarningStatistics(array $data);

    public function allRideList(): mixed;

    public function rideWaiting(Model $trip, Model $time): void;

    public function getPendingParcel(array $data = []): mixed;

    public function getPendingRide(array $data = []): mixed;

    public function tripOverview(array $data = []): mixed;

    public function updateRideStatus(array $data = []): mixed;

    public function getLockedTrip(array $data = []): mixed;

    public function findNearestDrivers(string $latitude, string $longitude, string $zoneId, int|string $radius, ?string $vehicleCategoryId = null, ?string $requestType = null, ?string $rideRequestType = null, int|string|null $parcelWeight = null, bool $femaleDriverOnly = false): mixed;

    public function getIncompleteRide(array $criteria = []): mixed;

    public function createRideRequest(array $attributes = []): mixed;

    public function storeScreenShot(array $attributes): mixed;

    public function getRidesBy(array $criteria, array $whereBetweenCriteria = []): mixed;

    public function getCustomerPendingRideList(array $criteria, array $relations, ?int $limit = null, ?int $offset = null): mixed;

    public function canDriverReachInTime(array $segments, float $avgKmPerMinute,  int|string $scheduledAt, Carbon $timeAddedWith, float $addTripEstimatedDistance = 0, int $minimumScheduleBookTime = 0): bool;

    public function updateTripRequestAction(array $attributes, Model $trip): Model;

    public function canDriverAcceptRegularTrip(User $user, Model $trip, float $estimatedDistance, float $avgKmPerMinute,): bool;
    public function canDriverAcceptScheduledTrip(User $user, Model $trip, float $estimatedDistance, float $avgKmPerMinute,): bool;

    public function pendingRideResponse(array $data, $request): JsonResponse;

    public function hasIncompleteRegularRide(array $data): bool;
}
