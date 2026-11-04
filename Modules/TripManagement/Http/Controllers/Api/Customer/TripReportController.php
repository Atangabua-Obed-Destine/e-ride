<?php

namespace Modules\TripManagement\Http\Controllers\Api\Customer;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Modules\AdminModule\Entities\AdminNotification;
use Modules\TripManagement\Entities\TripReport;
use Modules\TripManagement\Entities\TripRequest;
use Throwable;

/** The customer reports the driver of one of their trips; the admin follows up. */
class TripReportController extends Controller
{
    public function reasons(): JsonResponse
    {
        $reasons = collect(TripReport::REASONS)
            ->map(fn($label, $key) => ['key' => $key, 'label' => translate($label)])
            ->values();
        return response()->json(responseFormatter(DEFAULT_200, $reasons));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'trip_request_id' => 'required|string',
            'reason' => ['required', Rule::in(array_keys(TripReport::REASONS))],
            'comment' => 'nullable|string|max:1000',
        ]);
        $trip = TripRequest::with('deposit')
            ->whereKey($data['trip_request_id'])
            ->where('customer_id', auth('api')->id())
            ->first();
        // A customer cancel clears the trip's driver; the deposit remembers it.
        $driverId = $trip?->driver_id ?? $trip?->deposit?->driver_id;
        if (!$trip || !$driverId) {
            return $this->problem(translate('This trip has no driver to report.'));
        }
        if ($trip->created_at->lt(now()->subDays(TripReport::MAX_DAYS_TO_REPORT))) {
            return $this->problem(translate('This trip is too old to report.'));
        }
        if (TripReport::where('trip_request_id', $trip->id)->exists()) {
            return $this->problem(translate('You already reported this trip. Our team will look into it.'));
        }
        $report = TripReport::create([
            'trip_request_id' => $trip->id,
            'customer_id' => $trip->customer_id,
            'driver_id' => $driverId,
            'reason' => $data['reason'],
            'comment' => trim((string)($data['comment'] ?? '')) ?: null,
            'trip_status' => $trip->current_status,
            'deposit_status' => $trip->deposit?->status,
            'status' => TripReport::OPEN,
        ]);
        try {
            AdminNotification::create([
                'model' => 'trip_report',
                'model_id' => $report->id,
                'message' => 'New driver report on trip #' . $trip->ref_id,
            ]);
        } catch (Throwable $e) {
            Log::info('Driver report admin notification not saved', ['report' => $report->id, 'error' => $e->getMessage()]);
        }
        return response()->json(responseFormatter(DEFAULT_STORE_200, [
            'id' => $report->id,
            'message' => translate('Thank you. Our team will look into it.'),
        ]));
    }

    protected function problem(string $message): JsonResponse
    {
        return response()->json(responseFormatter(DEFAULT_400, errors: [[
            'error_code' => 'trip_report',
            'message' => $message,
        ]]), 403);
    }
}
