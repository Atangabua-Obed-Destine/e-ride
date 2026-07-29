<?php

namespace Modules\UserManagement\Http\Controllers\Api\Driver;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\UserManagement\Service\Interfaces\DriverDetailServiceInterface;
use Modules\UserManagement\Service\Interfaces\TimeTrackServiceInterface;
use Modules\UserManagement\Transformers\TimeTrackResource;

class TimeTrackController extends Controller
{
    protected $timeTrackService;
    protected $driverDetailService;

    public function __construct(TimeTrackServiceInterface $timeTrackService, DriverDetailServiceInterface $driverDetailService)
    {
        $this->timeTrackService = $timeTrackService;
        $this->driverDetailService = $driverDetailService;
    }


    /**
     * Store a newly created resource in storage.
     * @return JsonResponse
     */
    public function store(): JsonResponse
    {
        $id = auth('api')->id();
        $trackCriteria = [
            'user_id' => $id,
            'date' => date('Y-m-d')
        ];
        $track = $this->timeTrackService->findOneBy(criteria: $trackCriteria,
            relations: ['latestLog'], orderBy: ['created_at' => 'desc']);

        if (!$track) {
            $trackData = [
                'user_id' => $id,
                'date' => now()
            ];
            $track = $this->timeTrackService->create($trackData);

            //need to set driver to online if he is offline
            $track->logs()->create([
                'online_at' => now(),
            ]);
        }

        $previousTrackCriteria = [
            'user_id' => $id,
            'date' => date('Y-m-d', strtotime('yesterday'))
        ];
        $previousTrack = $this->timeTrackService->findOneBy(criteria: $previousTrackCriteria,
            relations: ['latestLog'], orderBy: ['created_at' => 'desc']);

        if ($previousTrack) {
            $previousDayEnd = now()->subDay()->endOfDay();
            if (!$previousTrack->latestLog->offline_at) {
                $previousTrack->latestLog()->update([
                    'offline_at' => $previousDayEnd
                ]);
                $previousTrack->total_online += Carbon::parse($previousTrack->latestLog->online_at)->diffInMinutes($previousDayEnd);
            }
            if ($previousTrack->last_ride_started_at && !$previousTrack->last_ride_completed_at) {
                $previousTrack->last_ride_completed_at = $previousDayEnd;
                $previousTrack->total_driving += Carbon::parse($previousTrack->last_ride_started_at)->diffInMinutes($previousDayEnd);

                if ($track->isClean('date')) {
                    $track->last_ride_started_at = now();
                    $track->save();
                }
            }
            $previousTrack->save();
        }

        $track->load('latestLog');
        $totalOnline = $track->total_online;
        if ($track->latestLog?->online_at && !$track->latestLog?->offline_at) {
            $totalOnline += Carbon::parse($track->latestLog->online_at)->diffInMinutes(now());
        }
        $track->setAttribute('total_online', $totalOnline);
        $data = TimeTrackResource::make($track);
        return response()->json(responseFormatter(constant: DEFAULT_UPDATE_200, content: $data));
    }


    public function onlineStatus(): JsonResponse
    {
        $id = auth('api')->id();
        $details = $this->driverDetailService->findOneBy(criteria: ['user_id' => $id]);
        if ($details['availability_status'] == 'on_trip') {

            return response()->json(responseFormatter(OFFLINE_403), 403);
        }

        $trackCriteria = [
            'user_id' => $id,
            'date' => date('Y-m-d')
        ];
        $track = $this->timeTrackService->findOneBy(criteria: $trackCriteria,
            relations: ['latestLog'], orderBy: ['created_at' => 'desc']);
        if (!$track) {
            $trackData = [
                'user_id' => $id,
                'date' => now()
            ];
            $track = $this->timeTrackService->create($trackData);

            //need to set driver to online if he is offline
            $track->logs()->create([
                'online_at' => now(),
            ]);
        }
        if ($details['is_online']) {
            //means he is going to be offline

            $track->latestLog()->update([
                'offline_at' => now()
            ]);
            $track->total_online += Carbon::parse($track?->latestLog?->online_at)->diffInMinutes(now());
            $track->save();

        }

        if (!$details['is_online']) {
            //means he is going to be online
            $track->total_offline += Carbon::parse($track->latestLog?->offline_at)->diffInMinutes(now());
            $track->save();
            $track->latestLog()->create([
                'online_at' => now()
            ]);

        }
        $attributes = [
            'is_online' => $details['is_online'] == 1 ? 0 : 1,
            'availability_status' => $details['is_online'] == 1 ? 'unavailable' : 'available',
        ];
        $this->driverDetailService->updatedBy(criteria: ['user_id' => $id], data: $attributes);

        return response()->json(responseFormatter(DEFAULT_STATUS_UPDATE_200));
    }
}
