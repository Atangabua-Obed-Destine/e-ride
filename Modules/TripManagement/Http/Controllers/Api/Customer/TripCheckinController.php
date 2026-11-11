<?php

namespace Modules\TripManagement\Http\Controllers\Api\Customer;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\TripManagement\Entities\TripCheckin;
use Modules\TripManagement\Lib\CheckinSettings;
use Modules\TripManagement\Service\TripCheckinService;

/** "Are you OK?" during a ride: the open question, and the rider's answer. */
class TripCheckinController extends Controller
{
    public function __construct(protected TripCheckinService $checkins)
    {
    }

    /** Whether check-ins are on, the night hours, and the open question for this trip (if any). */
    public function show(Request $request): JsonResponse
    {
        $s = CheckinSettings::all();
        $tripId = (string)$request->query('trip_request_id', '');
        $checkin = $tripId !== '' ? $this->checkins->active($tripId, auth('api')->id()) : null;
        return response()->json(responseFormatter(DEFAULT_200, [
            'enabled' => $s['enabled'],
            'night_from' => $s['night_from'],
            'night_to' => $s['night_to'],
            'is_night' => CheckinSettings::isNight(now()),
            'checkin' => $checkin ? $this->present($checkin) : null,
        ]));
    }

    public function answer(Request $request, string $checkinId): JsonResponse
    {
        $answer = $request->input('answer');
        if (!in_array($answer, ['ok', 'help'], true)) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: [['error_code' => 'answer', 'message' => translate('Choose I am OK or I need help')]]), 403);
        }
        $checkin = TripCheckin::where('id', $checkinId)->where('customer_id', auth('api')->id())->first();
        if (!$checkin) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }
        $checkin = $this->checkins->answer($checkin, $answer);
        return response()->json(responseFormatter(DEFAULT_200, $this->present($checkin)));
    }

    private function present(TripCheckin $checkin): array
    {
        return [
            'id' => $checkin->id,
            'trip_request_id' => $checkin->trip_request_id,
            'reason' => $checkin->reason,
            'status' => $checkin->status,
            'asked_at' => $checkin->asked_at?->toIso8601String(),
            // Seconds left before E-Ride and the trusted contacts are alerted.
            'seconds_left' => $checkin->status === TripCheckin::ASKED && $checkin->asked_at
                ? max(0, CheckinSettings::all()['answer_minutes'] * 60 - (int)abs($checkin->asked_at->diffInSeconds(now())))
                : 0,
        ];
    }
}
