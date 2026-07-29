<?php

namespace Modules\FareManagement\Service;

use App\Service\BaseService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\FareManagement\Repository\SurgePricingRepositoryInterface;
use Modules\ParcelManagement\Entities\ParcelCategory;
use Modules\ParcelManagement\Service\Interfaces\ParcelCategoryServiceInterface;
use Modules\TripManagement\Repository\TripRequestRepositoryInterface;
use Modules\VehicleManagement\Entities\VehicleCategory;
use Modules\VehicleManagement\Service\Interfaces\VehicleCategoryServiceInterface;
use Modules\ZoneManagement\Service\Interfaces\ZoneServiceInterface;
use Illuminate\Validation\ValidationException;

class SurgePricingService extends BaseService implements Interfaces\SurgePricingServiceInterface
{
    protected SurgePricingRepositoryInterface $surgePricingRepository;
    protected ZoneServiceInterface $zoneService;
    protected VehicleCategoryServiceInterface $vehicleCategoryService;
    protected ParcelCategoryServiceInterface $parcelCategoryService;

    protected TripRequestRepositoryInterface $tripRequestRepository;

    public function __construct(SurgePricingRepositoryInterface $surgePricingRepository, ZoneServiceInterface $zoneService,
                                VehicleCategoryServiceInterface $vehicleCategoryService, ParcelCategoryServiceInterface $parcelCategoryService,
                                TripRequestRepositoryInterface  $tripRequestRepository)
    {
        parent::__construct($surgePricingRepository);
        $this->surgePricingRepository = $surgePricingRepository;
        $this->zoneService = $zoneService;
        $this->vehicleCategoryService = $vehicleCategoryService;
        $this->parcelCategoryService = $parcelCategoryService;
        $this->tripRequestRepository = $tripRequestRepository;
    }

    public function index(array $criteria = [], array $relations = [], array $whereHasRelations = [], array $orderBy = [], int $limit = null, int $offset = null, array $withCountQuery = [], array $appends = [], array $groupBy = []): Collection|LengthAwarePaginator
    {
        $searchData = [];
        if (array_key_exists('search', $criteria) && $criteria['search'] != '') {
            $searchData['fields'] = ['readable_id', 'name'];
            $searchData['relations'] = [
                'surgePricingZones' => ['name'],
            ];
            $searchData['value'] = $criteria['search'];
        }

        $surgePricingList = $this->surgePricingRepository->getBy(searchCriteria: $searchData, relations: $relations, orderBy: $orderBy);
        $presentTime = Carbon::now();
        $surgePricingList = $surgePricingList->map(function ($surge) use ($presentTime) {
            return $this->formatSurgePricing($surge, $presentTime);
        });

        if ($limit) {
            $page = $offset ?? 1;
            $items = $surgePricingList->forPage($page, $limit);
            $paginator = new LengthAwarePaginator(
                items: $items,
                total: $surgePricingList->count(),
                perPage: $limit,
                currentPage: $page,
                options: ['path' => request()->url(), 'query' => request()->query()]
            );

            return !empty($appends) ? $paginator->appends($appends) : $paginator;
        }

        return new Collection($surgePricingList);
    }

    public function create(array $data): ?Model
    {
        return $this->insertOrUpdateSurgePricingData($data);
    }

    private function insertOrUpdateSurgePricingData($data): Model
    {
        $vehicleCategoryData = $parcelCategoryData = [];
        $surgePricingData = [
            'name' => $data['name'],
            'surge_pricing_for' => $data['pricing_for'],
            'increase_for_all_vehicles' => $data['increase_rate'] == 'all_vehicle' && in_array($data['pricing_for'], ['ride', 'both']) ? 1 : 0,
            'increase_for_all_parcels' => in_array($data['pricing_for'], ['parcel', 'both']) ? 1 : 0,
            'schedule' => $data['price_schedule'],
            'is_active' => isset($data['is_active']) ? 1 : 0,
            'customer_note' => $data['customer_note'] ?? null,
        ];
        if (in_array('all', $data['zones']))
        {
            $surgePricingData['zone_setup_type'] = 'all';
            $data['zones'] = $this->zoneService->getAll()->pluck('id')->toArray();
        } else {
            $surgePricingData['zone_setup_type'] = 'custom';
        }

        if ($surgePricingData['increase_for_all_vehicles']) {
            $surgePricingData['all_vehicle_surge_percent'] = $data['ride_surge_multiplier'];
        }

        if ($surgePricingData['increase_for_all_parcels']) {
            $surgePricingData['all_parcel_surge_percent'] = $data['parcel_surge_multiplier'];
        }

        if (in_array($surgePricingData['surge_pricing_for'], ['ride', 'both'])) {
            $vehicleCategoryModel = VehicleCategory::class;
            $vehicleCategories = $this->vehicleCategoryService->getAll()->pluck('id');
            $vehicleCategoryInfo = $surgePricingData['increase_for_all_vehicles'] ? $vehicleCategories->flip()->toArray() : $data['surge_multipliers'];
            $vehicleCategoryData = collect($vehicleCategoryInfo)->map(function ($surgeMultiplier, $categoryId) use ($data, $vehicleCategoryModel) {
                return [
                    'service_category_id' => $categoryId,
                    'service_category_type' => $vehicleCategoryModel,
                    'surge_multiplier' => ($data['increase_rate'] == 'all_vehicle' ? (double)$data['ride_surge_multiplier'] : (double)$surgeMultiplier) ?? 0,
                ];
            })->values()->toArray();
        }

        if (in_array($surgePricingData['surge_pricing_for'], ['parcel', 'both'])) {
            $parcelCategoryModel = ParcelCategory::class;
            $parcelCategories = $this->parcelCategoryService->getAll()->pluck('id');
            $parcelCategoryData = $parcelCategories->map(function ($categoryId) use ($data, $parcelCategoryModel) {
                return [
                    'service_category_id' => $categoryId,
                    'service_category_type' => $parcelCategoryModel,
                    'surge_multiplier' => (double)$data['parcel_surge_multiplier'] ?? 0,
                ];
            })->values()->toArray();
        }

        if ($surgePricingData['schedule'] == 'custom') {
            $dateTime = collect($data['date_range_custom'])
                ->combine($data['time_range_custom'])
                ->map(function ($timeRange, $date) {
                    [$start, $end] = explode(' - ', $timeRange);
                    return [
                        'date' => Carbon::createFromFormat('D M d Y', $date)->format('Y-m-d'),
                        'start_time' => date('H:i:s', strtotime($start)),
                        'end_time' => date('H:i:s', strtotime($end)),
                    ];
                })
                ->sortBy('date')
                ->values()
                ->toArray();

            $surgePricingTimeSlotsData = [
                'start_date' => $dateTime[0]['date'] ?? null,
                'end_date' => end($dateTime)['date'] ?? null,
                'slots' => $dateTime,
            ];
        } else {
            $date = $surgePricingData['schedule'] == 'daily' ? explode(' - ', $data['date_range_daily']) : explode(' - ', $data['date_range_weekly']);
            $startDate = Carbon::createFromFormat(($surgePricingData['schedule'] == 'weekly' ? 'j M, Y' : 'm/d/Y'), $date[0])->format('Y-m-d');
            $endDate = $date[1] == 'unlimited' ? 'unlimited' : Carbon::createFromFormat(($surgePricingData['schedule'] == 'weekly' ? 'j M, Y' : 'm/d/Y'), $date[1])->format('Y-m-d');
            $time = $surgePricingData['schedule'] == 'daily' ? explode(' - ', $data['time_range_daily']) : explode(' - ', $data['time_range_weekly']);
            $startTime = date('H:i:s', strtotime($time[0]));
            $endTime = date('H:i:s', strtotime($time[1]));
            $surgePricingTimeSlotsData = [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'slots' => [['date' => '', 'start_time' => $startTime, 'end_time' => $endTime]],
            ];
            if ($surgePricingData['schedule'] == 'weekly') {
                $selectedDays = explode(',', $data['select_days']);
                $surgePricingTimeSlotsData['selected_days'] = $selectedDays;
            }
        }
        $this->checkSurgePricingOverlap(
            data: $data,
            surgePricingSchedule: $surgePricingData['schedule'],
            surgePricingTimeSlot: $surgePricingTimeSlotsData,
            id: $data['row_to_be_updated'] ?? null
        );

        DB::transaction(function () use ($data, $surgePricingData, $vehicleCategoryData, $parcelCategoryData, $surgePricingTimeSlotsData, &$surgePricing) {
            if (!empty($data['row_to_be_updated'])) {
                $surgePricing = $this->surgePricingRepository->update(
                    id: $data['row_to_be_updated'],
                    data: $surgePricingData
                );
                $surgePricing->surgePricingServiceCategories()->delete();
                $surgePricing->surgePricingTimeSlot()->delete();
            } else {
                $surgePricing = $this->surgePricingRepository->create(data: $surgePricingData);
            }
            $surgePricing->surgePricingZones()->sync($data['zones']);
            $categories = array_merge($vehicleCategoryData ?? [], $parcelCategoryData ?? []);
            if (!empty($categories)) {
                $surgePricing->surgePricingServiceCategories()->createMany($categories);
            }
            if (!empty($surgePricingTimeSlotsData)) {
                $surgePricing->surgePricingTimeSlot()->create($surgePricingTimeSlotsData);
            }
        });

        return $surgePricing;
    }

    public function delete(int|string $id): bool
    {
        DB::beginTransaction();
        try {
            $surgePricing = $this->surgePricingRepository->findOneBy(criteria: ['id' => $id]);
            $surgePricing->surgePricingZones()->detach();
            $surgePricing->surgePricingServiceCategories()->delete();
            $surgePricing->surgePricingTimeSlot()->delete();
            $surgePricing->delete();
            DB::commit();
            return true;
        } catch (Exception $exception) {
            DB::rollBack();
            return false;
        }
    }

    public function export(array $criteria = [], array $relations = [], array $orderBy = [], int $limit = null, int $offset = null, array $withCountQuery = []): Collection|LengthAwarePaginator|\Illuminate\Support\Collection
    {
        return $this->index(criteria: $criteria, relations: $relations, orderBy: $orderBy)->map(function ($item) {
            $parts = [];
            $appendMultipliers = function ($typeClass, $allLabel, $increaseFlag) use ($item, &$parts) {
                if ($item->$increaseFlag) {
                    $value = optional($item->surgePricingServiceCategories->firstWhere('service_category_type', $typeClass))->surge_multiplier;
                    if ($value !== null) {
                        $parts[] = $value . '% for ' . $allLabel;
                    }
                } else {
                    foreach ($item->surgePricingServiceCategories->where('service_category_type', $typeClass) as $cat) {
                        $parts[] = $cat->serviceCategory->name . ' ' . $cat->surge_multiplier . '%';
                    }
                }
            };

            if (in_array($item->surge_pricing_for, ['ride', 'both'])) {
                $appendMultipliers(VehicleCategory::class, 'All vehicles', 'increase_for_all_vehicles');
            }

            if (in_array($item->surge_pricing_for, ['parcel', 'both'])) {
                $appendMultipliers(ParcelCategory::class, 'All parcels', 'increase_for_all_parcels');
            }
            $item['surge_multipliers'] = implode(', ', $parts);
            return [
                'Id' => $item['id'],
                'Name' => $item['name'],
                'Zones' => implode(', ', $item->zone_setup_type == 'all' ? $this->zoneService->getAll()->pluck('name', 'id')->toArray() : $item['zones']),
                'Extra Price' => $item['surge_multipliers'],
                'Schedule' => $item['schedule'],
                'Date Range' => $item['date_range'],
                'Selected Days' => $item['selected_days'],
                'Time Slots' => $item['schedule'] == 'custom' ? collect($item['date_time_slots'])->map(function ($slot) {
                    return $slot['date'] . ' ' . $slot['time_slot'];
                })->implode(', ') : $item['date_time_slots']['time_slot'],
                'Statistics' => $item['statistic']['name'],
                'Status' => $item['is_active'] == 1 ? "Active" : "Inactive",
                'Note' => $item['note'] ?? '',
            ];
        });
    }

    public function updatesSurgePricing(int|string $id, array $data = []): ?Model
    {
        $data['row_to_be_updated'] = $id;
        return $this->insertOrUpdateSurgePricingData(data: $data);
    }

    public function findOne(int|string $id, array $withAvgRelations = [], array $relations = [], array $whereHasRelations = [], array $withCountQuery = [], bool $withTrashed = false, bool $onlyTrashed = false): ?Model
    {
        $surge = $this->surgePricingRepository->findOne(id: $id, relations: $relations);

        return $this->formatSurgePricing($surge, Carbon::now());
    }


    private function formatSurgePricing($surge, Carbon $presentTime)
    {
        $timeSlot = $surge->surgePricingTimeSlot;

        $surge['zone_text'] = $this->getSurgeZoneText($surge);
        $surge['zones'] = $surge->zone_setup_type == 'all' ? $this->zoneService->getAll()->pluck('name', 'id')->toArray() : $surge->surgePricingZones->pluck('name', 'id')->toArray();
        $surge['date_range'] = $this->getSurgeDateRange($timeSlot);
        $surge['selected_days'] = $this->getSelectedDaysText($timeSlot->selected_days);
        $surge['date_time_slots'] = $this->getDateTimeSlots($surge, $timeSlot);
        $surge['surge_multipliers'] = implode(', ', $this->getSurgeMultipliers($surge));
        $surge['statistic'] = $this->getSurgeStatistic(surge: $surge, presentTime: $presentTime);

        return $surge;
    }

    private function getSurgeDateRange($timeSlot): string
    {
        $startDate = Carbon::parse($timeSlot->start_date)->format('d M y');
        $endDate = $timeSlot->end_date === 'unlimited'
            ? 'unlimited'
            : Carbon::parse($timeSlot->end_date)->format('d M y');

        return "$startDate - $endDate";
    }

    private function getSelectedDaysText($selectedDays): string
    {
        return collect($selectedDays)
            ->map(fn($day) => translate($day))
            ->implode(', ') ?? '';
    }

    private function getDateTimeSlots($surge, $timeSlot): array
    {
        if ($surge->schedule === 'custom') {
            return collect($timeSlot->slots)->map(fn($slot) => [
                'date' => Carbon::parse($slot['date'])->format('D, M d Y'),
                'time_slot' => Carbon::createFromFormat('H:i:s', $slot['start_time'])->format('h:i A')
                    . ' - ' .
                    Carbon::createFromFormat('H:i:s', $slot['end_time'])->format('h:i A'),
            ])->toArray();
        }

        $slot = $timeSlot->slots[0];
        return [
            'date' => '',
            'time_slot' => Carbon::createFromFormat('H:i:s', $slot['start_time'])->format('h:i A')
                . ' - ' .
                Carbon::createFromFormat('H:i:s', $slot['end_time'])->format('h:i A'),
        ];
    }


    private function getSurgeStatistic($surge, Carbon $presentTime): array
    {
        $statistic = ['name' => 'expired', 'badge' => 'badge-danger'];

        if (!$surge->is_active) {
            return ['name' => 'inactive', 'badge' => 'badge-danger'];
        }

        $timeSlot = $surge->surgePricingTimeSlot;
        $slotStartDate = Carbon::parse($timeSlot->start_date)->seconds(0)->micro(0);
        $slotEndDate = Carbon::parse($timeSlot->end_date == 'unlimited' ? '9999-12-31' : $timeSlot->end_date)->seconds(0)->micro(0);
        $slotStartTime = $timeSlot->slots[0]['start_time'];
        $slotEndTime = $timeSlot->slots[0]['end_time'];
        $presentStartTime = Carbon::parse($presentTime->toDateString() . ' ' . $slotStartTime)->seconds(0)->micro(0);
        $presentEndTime = Carbon::parse($presentTime->toDateString() . ' ' . $slotEndTime)->seconds(0)->micro(0);
        $presentTime = $presentTime->seconds(0)->micro(0);
        if ($timeSlot->end_date !== 'unlimited') {
            if ($presentTime->between($slotStartDate->startOfDay(), $slotEndDate->endOfDay())) {

                if ($presentTime->lessThan($presentStartTime) && $presentTime->isSameDay($slotStartDate)) {
                    return ['name' => 'upcoming', 'badge' => 'badge-info'];
                }

                if ($presentTime->greaterThan($presentEndTime) && $presentTime->isSameDay($slotEndDate)) {
                    return ['name' => 'expired', 'badge' => 'badge-danger'];
                }

                return ['name' => 'ongoing', 'badge' => 'badge-primary'];
            }

            if ($presentTime->lessThan($slotStartDate)) {
                return ['name' => 'upcoming', 'badge' => 'badge-info'];
            }

            if ($presentTime->greaterThan($slotEndDate)) {
                return ['name' => 'expired', 'badge' => 'badge-danger'];
            }
        } else {
            return ['name' => 'ongoing', 'badge' => 'badge-primary'];
        }


        return $statistic;
    }

    private function getSurgeMultipliers($surge): array
    {
        $parts = [];

        $mapping = [
            'ride' => [VehicleCategory::class, 'All vehicles', 'increase_for_all_vehicles', 'all_vehicle_surge_percent'],
            'parcel' => [ParcelCategory::class, 'All parcels', 'increase_for_all_parcels', 'all_parcel_surge_percent'],
        ];

        foreach ($mapping as $key => [$typeClass, $allLabel, $increaseFlag, $surgePercent]) {
            if (in_array($surge->surge_pricing_for, [$key, 'both'])) {
                if ($surge->$increaseFlag) {
                    $parts[] = '<span class="fw-semibold">' . $surge->$surgePercent . '%</span> for ' . $allLabel;
                } else {
                    foreach ($surge->surgePricingServiceCategories->where('service_category_type', $typeClass) as $cat) {
                        $parts[] = $cat->serviceCategory->name . ' <span class="fw-semibold">' . $cat->surge_multiplier . '%</span>';
                    }
                }
            }
        }

        return $parts;
    }

    private function getSurgeZoneText($surge): string
    {
        $countSurgePricingZones = $surge->surgePricingZones->count();


        if ($surge->zone_setup_type == 'all') {
            return translate('All zones');
        }

        if ($countSurgePricingZones == 1) {
            return $surge->surgePricingZones->first()->name;
        }

        return translate($countSurgePricingZones . ' ' . Str::plural('zone'));
    }

    public function getRidesByDateAndTimeRange($data, $id): array
    {
        $criteria = $whereInCriteria = [];

        $surgePrice = $this->findOne(
            id: $id,
            relations: ['surgePricingTimeSlot', 'surgePricingZones', 'surgePricingServiceCategories']
        );

        if (!empty($data['zone']) && $data['zone'] !== 'all') {
            $criteria['zone_id'] = $data['zone'];
        } else {
            if ($surgePrice->zone_setup_type != 'all')
            {
                $whereInCriteria = ['zone_id' => $surgePrice->surgePricingZones()->pluck('zone_id')->toArray()];
            }
        }

        $timeSlots = $surgePrice->surgePricingTimeSlot;
        $schedule = $surgePrice->schedule;
        $weekDays = array_map('strtolower', $timeSlots->selected_days ?? []);

        $labels = [];
        $totalTrips = [];
        $totalRides = [];
        $totalAmount = [];
        $totalParcels = [];

        $start = Carbon::parse($timeSlots->start_date);
        $end = $timeSlots->end_date === 'unlimited' ? now() : Carbon::parse($timeSlots->end_date);
        $points = (int)getSession('currency_decimal_point') ?? 0;

        $period = CarbonPeriod::create($start->startOfDay(), $end->endOfDay());

        // Slot processor
        $processSlot = function ($slotStart, $slotEnd, $label) use (
            &$labels, &$totalTrips, &$totalRides,
            &$totalAmount, &$totalParcels, $criteria, $points, $whereInCriteria
        ) {

            $rides = $this->tripRequestRepository->getBy(
                criteria: $criteria,
                whereInCriteria: $whereInCriteria,
                whereBetweenCriteria: ['created_at' => [$slotStart, $slotEnd]]
            );

            $labels[] = (string)$label;
            $totalTrips[] = $rides->count();
            $totalRides[] = $rides->where('type', 'ride_request')->count();
            $totalParcels[] = $rides->where('type', 'parcel')->count();
            $totalAmount[] = number_format($rides->where('payment_status', PAID)->sum('paid_fare'), $points, '.', '');
        };

        // Generate slots based on rules
        $generateDailySlots = function ($start, $end) use ($processSlot, $timeSlots) {

            // SAME DAY → only selected day
            if ($start->isSameDay($end)) {
                foreach ($timeSlots->slots as $slot) {
                    $slotStart = $start->copy()->setTimeFromTimeString($slot['start_time']);
                    $slotEnd = $start->copy()->setTimeFromTimeString($slot['end_time']);
                    $processSlot(
                        $slotStart,
                        $slotEnd,
                        $slotStart->format('d M Y')
                    );
                }
            } // SAME MONTH → only selected dates in that month
            elseif ($start->isSameMonth($end) && $start->isSameYear($end)) {
                $period = CarbonPeriod::create($start->copy()->startOfDay(), $end->copy()->endOfDay());
                foreach ($period as $date) {
                    foreach ($timeSlots->slots as $slot) {
                        $slotStart = $date->copy()->setTimeFromTimeString($slot['start_time']);
                        $slotEnd = $date->copy()->setTimeFromTimeString($slot['end_time']);
                        $processSlot(
                            $slotStart,
                            $slotEnd,
                            $slotStart->format('d M Y')
                        );
                    }
                }
            } // DIFFERENT MONTHS SAME YEAR → month-wise
            elseif ($start->year === $end->year) {
                $months = CarbonPeriod::create(
                    $start->copy()->startOfMonth(),
                    '1 month',
                    $end->copy()->endOfMonth()
                );

                foreach ($months as $month) {
                    // Get only the portion of the month inside selected range
                    $monthStart = max($start->copy(), $month->copy()->startOfMonth());
                    $monthEnd = min($end->copy(), $month->copy()->endOfMonth());

                    if ($monthStart <= $monthEnd) {
                        // return/process month block once
                        $processSlot(
                            $monthStart,
                            $monthEnd,
                            $monthStart->format('M Y') // e.g. Jan 2024
                        );
                    }
                }
            } // DIFFERENT YEARS → year-wise
            else {
                for ($year = $start->year; $year <= $end->year; $year++) {
                    // start of the selected range in this year
                    $yearStart = max($start->copy(), Carbon::create($year, 1, 1)->startOfYear());
                    // end of the selected range in this year
                    $yearEnd = min($end->copy(), Carbon::create($year, 12, 31)->endOfYear());

                    // only process if the range is valid
                    if ($yearStart <= $yearEnd) {
                        $processSlot(
                            $yearStart,
                            $yearEnd,
                            $yearStart->format('Y') // label is year
                        );
                    }
                }
            }

        };

        // DAILY schedule
        if ($schedule === 'daily') {
            $generateDailySlots($start, $end);
        }

        $generateWeeklySlots = function ($weeklyDates) use ($processSlot, $timeSlots) {
            // Ensure slots array exists
            $slotsArray = is_array($timeSlots->slots)
                ? $timeSlots->slots
                : (method_exists($timeSlots->slots, 'toArray') ? $timeSlots->slots->toArray() : []);

            if (empty($slotsArray)) {
                $slotsArray = [['start_time' => '00:00', 'end_time' => '23:59']];
            }

            // Group dates by year
            $groupedByYear = [];
            foreach ($weeklyDates as $date) {
                $groupedByYear[$date->year][] = $date->copy();
            }

            // If multiple years → show year only
            if (count($groupedByYear) > 1) {
                foreach ($groupedByYear as $year => $dates) {
                    $yearStart = $dates[0]->copy()->setTimeFromTimeString($slotsArray[0]['start_time']);
                    $yearEnd = end($dates)->copy()->setTimeFromTimeString($slotsArray[0]['end_time']);
                    $label = $yearStart->format('Y');
                    $processSlot($yearStart, $yearEnd, $label);
                }
                return;
            }

            // Same year → check if same month or multiple months
            $yearDates = reset($groupedByYear);
            $groupedByMonth = [];
            foreach ($yearDates as $date) {
                $groupedByMonth[$date->format('m')][] = $date;
            }

            // If multiple months → show month + year with time
            if (count($groupedByMonth) > 1) {
                foreach ($groupedByMonth as $monthDates) {
                    $monthStart = $monthDates[0]->copy()->setTimeFromTimeString($slotsArray[0]['start_time']);
                    $monthEnd = end($monthDates)->copy()->setTimeFromTimeString($slotsArray[0]['end_time']);
                    $label = $monthStart->format('M Y');
                    $processSlot($monthStart, $monthEnd, $label);
                }
            } else {
                // Single month → show each selected date individually
                foreach ($yearDates as $date) {
                    foreach ($slotsArray as $slot) {
                        $slotStart = $date->copy()->setTimeFromTimeString($slot['start_time']);
                        $slotEnd = $date->copy()->setTimeFromTimeString($slot['end_time']);
                        $label = $slotStart->format('d M Y');
                        $processSlot($slotStart, $slotEnd, $label);
                    }
                }
            }
        };

// WEEKLY schedule
        if ($schedule === 'weekly') {
            $weeklyDates = [];
            foreach ($period as $date) {
                $dayName = strtolower($date->format('l'));
                if (in_array($dayName, $weekDays)) {
                    $weeklyDates[] = $date->copy();
                }
            }

            if (!empty($weeklyDates)) {
                $generateWeeklySlots($weeklyDates);
            } else {
                $generateDailySlots(now()->seconds(0)->micro(0), now()->seconds(0)->micro(0));
            }
        }

        if ($schedule === 'custom') {
            $slots = is_array($timeSlots->slots)
                ? $timeSlots->slots
                : (method_exists($timeSlots->slots, 'toArray') ? $timeSlots->slots->toArray() : []);

            if (!empty($slots)) {

                // Step 1: Convert all slots to Carbon objects
                $slotObjects = [];
                foreach ($slots as $slot) {
                    $slotStart = Carbon::parse(($slot['start_date'] ?? $slot['date']) . ' ' . ($slot['start_time'] ?? '00:00'));
                    $slotEnd = Carbon::parse(($slot['end_date'] ?? $slot['date']) . ' ' . ($slot['end_time'] ?? '23:59'));
                    $slotObjects[] = ['start' => $slotStart, 'end' => $slotEnd];
                }

                // Step 2: Collect unique years, months, days
                $years = [];
                $months = [];
                $days = [];

                foreach ($slotObjects as $slot) {
                    $start = $slot['start'];
                    $end = $slot['end'];

                    $years[$start->year] = true;

                    // Months
                    $periodMonths = CarbonPeriod::create($start->copy()->startOfMonth(), $end->copy()->endOfMonth(), '1 month');
                    foreach ($periodMonths as $month) {
                        $months[$month->format('Y-m')] = $month->copy();
                    }

                    // Days with time
                    $days[] = $slot; // store each slot as-is to preserve times
                }

                // Step 3: Determine aggregation level
                if (count($years) > 1) {
                    // DIFFERENT YEARS → show only years
                    ksort($years);
                    foreach ($years as $year => $_) {
                        $processSlot(Carbon::createFromFormat('Y', $year)->startOfYear(), Carbon::createFromFormat('Y', $year)->endOfYear(), $year);
                    }
                } elseif (count($months) > 1) {
                    // DIFFERENT MONTHS SAME YEAR → show month-year
                    ksort($months);
                    foreach ($months as $month) {
                        $monthStart = $month->copy()->startOfMonth()->setTime(0, 0);
                        $monthEnd = $month->copy()->endOfMonth()->setTime(23, 59);
                        $processSlot($monthStart, $monthEnd, $monthStart->format('M Y'));
                    }
                } else {
                    // SAME MONTH → show day-level slots with time
                    usort($days, function ($a, $b) {
                        return $a['start']->timestamp <=> $b['start']->timestamp;
                    });

                    foreach ($days as $slot) {
                        $slotStart = $slot['start'];
                        $slotEnd = $slot['end'];
                        $label = $slotStart->format('d M');
                        $processSlot($slotStart, $slotEnd, $label);
                    }
                }
            }
        }

        return [
            'labels' => $labels,
            'totalTrips' => $totalTrips,
            'totalRides' => $totalRides,
            'totalAmount' => $totalAmount,
            'totalParcels' => $totalParcels,
        ];
    }

    public function checkSurgePricing($zoneId, $tripType, $vehicleCategoryId = null, $scheduledAt = null): array
    {
        $surges = $this->fetchSurgesForZone($zoneId, $scheduledAt);
        return $this->evaluateSurgeForCategory($surges, $tripType,
            $vehicleCategoryId, $scheduledAt);
    }

    private function isWithinSlot(Carbon $time, $timeSlot): bool
    {
        $startDateTime = $time->copy()->setTimeFromTimeString($timeSlot->slots[0]['start_time']);
        $endDateTime = $time->copy()->setTimeFromTimeString($timeSlot->slots[0]['end_time']);
        return $time->betweenIncluded($startDateTime, $endDateTime);
    }

    private function hasMatchingCustomSlot(Carbon $time, $timeSlot): bool
    {
        return collect($timeSlot->slots)->contains(function ($slot) use ($time) {
            return $time->betweenIncluded(
                Carbon::parse($slot['date'])->setTimeFromTimeString($slot['start_time']),
                Carbon::parse($slot['date'])->setTimeFromTimeString($slot['end_time'])
            );
        });
    }

    public function updateZone(string|int $id, array $data): ?Model
    {
        if (empty($data['zones']) || !is_array($data['zones'])) {
            throw ValidationException::withMessages(['message' => 'Zones must be a non-empty array.']);
        }
        $criteria = [
            'zone_setup_type' => 'custom'
        ];

        $surgePricing = $this->surgePricingRepository->findOne(
            id: $id,
            relations: ['surgePricingZones', 'surgePricingTimeSlot']
        );
        if (in_array('all', $data['zones'])) {
            $data['zones'] = $this->zoneService->getAll()->pluck('id')->toArray();
            $criteria = [
                'zone_setup_type' => 'all'
            ];
        }
        $data['pricing_for'] = $surgePricing->surge_pricing_for;
        $surgePricingTimeSlot = $surgePricing->surgePricingTimeSlot->toArray();
        $this->checkSurgePricingOverlap($data, $surgePricing->schedule, $surgePricingTimeSlot, $id);

        DB::transaction(function () use ($surgePricing, $data, $id, $criteria) {
            $this->surgePricingRepository->update(id: $id, data: $criteria);
            $surgePricing->surgePricingZones()->sync($data['zones']);
        });

        return $surgePricing;
    }

    private function checkSurgePricingOverlap(array $data, string $surgePricingSchedule, array $surgePricingTimeSlot, int|string $id = null): void
    {
        $criteria = !empty($id) ? [['id', '!=', $id]] : [];
        $existing = $this->surgePricingRepository->getBy(criteria: array_merge($criteria, ['is_active' => 1]), relations: ['surgePricingZones', 'surgePricingServiceCategories', 'surgePricingTimeSlot']);
        if ($existing->isEmpty()) {
            return;
        }
        $zoneIds = $data['zones'] ?? [];
        $newSlots = $this->buildNewSlots($surgePricingSchedule, $surgePricingTimeSlot);

        foreach ($existing as $surgePricing) {
            $existingZones = $surgePricing->zone_setup_type == 'all' ? $this->zoneService->getAll()->pluck('id')->toArray() : $surgePricing->surgePricingZones->pluck('id')->toArray();
            $timeSlot = $surgePricing->surgePricingTimeSlot;
            foreach ($newSlots as $slot) {
                $newStart = $slot['start_date'];
                $newEnd = $slot['end_date'] === 'unlimited' ? '9999-12-31' : $slot['end_date'];
                $existingStart = $timeSlot->start_date;
                $existingEnd = $timeSlot->end_date === 'unlimited' ? '9999-12-31' : $timeSlot->end_date;
                $dateOverlap = ($newStart <= $existingEnd) && ($newEnd >= $existingStart);
                $timeOverlap = ($slot['start_time'] <= $timeSlot->slots[0]['end_time'])
                    && ($slot['end_time'] >= $timeSlot->slots[0]['start_time']);
                $dayOverlap = $this->hasDayOverlap($slot['selected_days'] ?? [], $timeSlot->selected_days ?? []);
                $zoneOverlap = count(array_intersect($zoneIds, $existingZones)) > 0;
                $categoryOverlap =  $surgePricing->surge_pricing_for === 'both'
                    || $data['pricing_for'] === 'both'
                    || $surgePricing->surge_pricing_for === $data['pricing_for'];

                if ($dateOverlap && $timeOverlap && $dayOverlap && $zoneOverlap && $categoryOverlap) {
                    throw ValidationException::withMessages([
                        'surge_pricing_overlap_message' => translate('This surge price overlaps with another. Please change the module or reschedule to fix it.') . ' ' . translate('Overlapping with - #') . $surgePricing->readable_id
                    ]);
                }
            }
        }
    }

    private function buildNewSlots(string $schedule, array $timeSlot): array
    {
        $slots = [];
        if ($schedule == 'custom') {
            foreach ($timeSlot['slots'] as $slot) {
                $slots[] = [
                    'start_date' => $slot['date'],
                    'end_date' => $slot['date'],
                    'start_time' => $slot['start_time'],
                    'end_time' => $slot['end_time'],
                ];
            }
        } else {
            $slots[] = [
                'start_date' => $timeSlot['start_date'],
                'end_date' => $timeSlot['end_date'],
                'start_time' => $timeSlot['slots'][0]['start_time'],
                'end_time' => $timeSlot['slots'][0]['end_time'],
                'selected_days' => $timeSlot['selected_days'] ?? null,
            ];
        }

        return $slots;
    }

    private function hasDayOverlap(array $newDays, array $existingDays): bool
    {
        if (empty($newDays) || empty($existingDays)) {
            return true;
        }

        return count(array_intersect($newDays, $existingDays)) > 0;
    }

    public function statusChange(string|int $id, array $data): ?Model
    {
        $data = [
            'is_active' => $data['status'] == 0 ? $data['status'] : 1
        ];

        $surgePrice = $this->surgePricingRepository->findOne(id: $id, relations: ['surgePricingZones', 'surgePricingServiceCategories', 'surgePricingTimeSlot']);
        $surgePriceData = $surgePrice->toArray();
        $surgePriceData['zones'] = $surgePrice->zone_setup_type == 'all' ? $this->zoneService->getAll()->pluck('id')->toArray() : $surgePrice->surgePricingZones->pluck('id')->toArray();
        $surgePriceData['pricing_for'] = $surgePrice->surge_pricing_for;
        $surgePriceTimeSlot = $surgePrice->surgePricingTimeSlot->toArray();
        $this->checkSurgePricingOverlap(
            data: $surgePriceData,
            surgePricingSchedule: $surgePriceData['schedule'],
            surgePricingTimeSlot: $surgePriceTimeSlot,
            id: $id
        );

        return $this->surgePricingRepository->update(id: $id, data: $data);
    }

    public function fetchSurgesForZone(int|string $zoneId, $scheduledAt = null): Collection
    {
        $time = Carbon::parse($scheduledAt ??
            Carbon::now())->seconds(0)->micro(0);
        $criteria = ['is_active' => 1];
        $relations = ['surgePricingTimeSlot',
            'surgePricingServiceCategories'];
        $whereHasRelations = [
            'surgePricingTimeSlot' => [
                ['start_date', '<=', $time->toDateString()],
                ['end_date', '>=', $time->toDateString()],
            ],
        ];

        return $this->surgePricingRepository->getSurgePricingListForChecking(
            zoneId: $zoneId,
            criteria: $criteria,
            whereHasRelations: $whereHasRelations,
            relations: $relations
        );
    }

    public function evaluateSurgeForCategory(Collection $surges, int|string $tripType, int|string $vehicleCategoryId = null, $scheduledAt = null): array
    {
        if ($tripType == 'ride_request') {
            $tripType = 'ride';
        }
        if ($surges->isEmpty()) {
            return [];
        }

        $time = Carbon::parse($scheduledAt ??
            Carbon::now())->seconds(0)->micro(0);
        $day = $time->format('l');
        $returnArray = [];

        foreach ($surges as $singleSurge) {
            $schedule = $singleSurge->schedule;
            $singleSurgeTimeSlot = $singleSurge->surgePricingTimeSlot;
            $singleSurgeSelectDays = $singleSurgeTimeSlot->selected_days ??
                [];
            $singleSurgeServiceCategories =
                $singleSurge->surgePricingServiceCategories;

            if (empty($vehicleCategoryId)) {
                $vehicleCategorySurgeObj =
                    $singleSurgeServiceCategories->firstWhere('service_category_type',
                        ParcelCategory::class);
                $vehicleCategorySurge =
                    $vehicleCategorySurgeObj?->surge_multiplier ?? 0;
            } else {
                if (!empty($singleSurge->increase_for_all_vehicles)) {
                    $vehicleCategorySurge =
                        $singleSurge->all_vehicle_surge_percent;
                } else {
                    $vehicleCategorySurgeObj =
                        $singleSurgeServiceCategories->firstWhere('service_category_id',
                            $vehicleCategoryId);
                    $vehicleCategorySurge =
                        $vehicleCategorySurgeObj?->surge_multiplier ?? 0;
                }
            }

            $startDate = Carbon::parse($singleSurgeTimeSlot->start_date)->setTimeFromTimeString($singleSurgeTimeSlot->slots[0]['start_time']);
          if ($singleSurgeTimeSlot->end_date === 'unlimited') {
              $endDate = Carbon::parse('9999-12-31')->setTimeFromTimeString
              ($singleSurgeTimeSlot->slots[0]['end_time']);
          } else {
              $endDate = Carbon::parse($singleSurgeTimeSlot->end_date)->setTimeFromTimeString($singleSurgeTimeSlot->slots[0]['end_time']);
          }
          if (!$time->betweenIncluded($startDate, $endDate)) {
              continue;
          }

          $matched = match ($schedule) {
              'daily'  => $this->isWithinSlot($time, $singleSurgeTimeSlot),
              'weekly' => $this->isWithinSlot($time, $singleSurgeTimeSlot)
                  && in_array($day, $singleSurgeSelectDays),
              'custom' => $this->hasMatchingCustomSlot($time,
                  $singleSurgeTimeSlot),
              default  => false,
          };

          if ($matched && ($singleSurge->surge_pricing_for == $tripType ||
                  $singleSurge->surge_pricing_for == 'both')) {
              $returnArray = [
                  'surge_multiplier' => $vehicleCategorySurge,
                  'surge_pricing_customer_note' =>
                      $singleSurge->customer_note,
              ];
              break;
          }
      }

        return $returnArray;
    }

}
