<?php

namespace Modules\TripManagement\Service;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Modules\TripManagement\Entities\TripRequest;
use Modules\VehicleManagement\Entities\VehicleCategory;

/**
 * E-Ride Move: trucks booked like rides, with the price agreed in person
 * between the customer and the driver. A move is a ride_request trip with
 * is_move set. E-Ride never prices it: the only money in the app is the
 * booking deposit, which E-Ride keeps as its booking fee. Moves carry no
 * commission and no fees.
 */
class MoveTripService
{
    public const PHOTO_FOLDER = 'trip/move/';
    public const MAX_PHOTOS = 3;

    public function isMoveCategory(?string $vehicleCategoryId): bool
    {
        return $vehicleCategoryId
            && VehicleCategory::whereKey($vehicleCategoryId)->whereIn('type', MOVE_VEHICLE_TYPES)->exists();
    }

    /** Stores photos of the load before the request, so drivers see them while it rings. */
    public function storePhotos(array $files): array
    {
        $names = [];
        foreach (array_slice($files, 0, self::MAX_PHOTOS) as $file) {
            if ($file instanceof UploadedFile) {
                $name = fileUploader(self::PHOTO_FOLDER, $file->getClientOriginalExtension(), $file);
                $name && $names[] = $name;
            }
        }
        return $names;
    }

    /** Only names of photos that were uploaded through storePhotos. */
    public function validPhotoNames(mixed $names): array
    {
        if (is_string($names)) {
            $names = json_decode($names, true) ?: [];
        }
        $valid = [];
        foreach (array_slice((array)$names, 0, self::MAX_PHOTOS) as $name) {
            if (is_string($name) && preg_match('/^[A-Za-z0-9._-]+$/', $name)
                && is_file(storage_path('app/public/' . self::PHOTO_FOLDER . $name))) {
                $valid[] = $name;
            }
        }
        return $valid;
    }

    public function photoUrls(?array $names): array
    {
        $base = dynamicStorage('storage/app/public/' . rtrim(self::PHOTO_FOLDER, '/'));
        return array_values(array_map(fn($name) => $base . '/' . $name, $names ?? []));
    }

    /**
     * The move ended (completed, or cancelled once it had started). Nothing
     * is owed in the app: fare and fees are 0, the trip is paid, and the
     * deposit becomes E-Ride's booking fee. Marking it paid releases the
     * driver and lets the customer book again. Safe to call more than once.
     */
    public function settle(TripRequest $trip): void
    {
        if (!$trip->is_move) {
            return;
        }
        DB::transaction(function () use ($trip) {
            $trip = TripRequest::whereKey($trip->id)->lockForUpdate()->first();
            if (!$trip) {
                return;
            }
            $trip->actual_fare = 0;
            $trip->paid_fare = 0;
            $trip->due_amount = 0;
            $trip->payment_method = $trip->payment_method ?: 'cash';
            $trip->payment_status = PAID;
            $trip->save();
            $trip->fee()->update([
                'admin_commission' => 0,
                'vat_tax' => 0,
                'idle_fee' => 0,
                'delay_fee' => 0,
                'cancellation_fee' => 0,
                'waiting_fee' => 0,
            ]);
            $trip->time()->update(['idle_time' => 0, 'delay_time' => 0]);
            app(TripDepositService::class)->keepAsBookingFee($trip);
        });
    }

    /** What the final-fare endpoints return for a move: nothing to pay. */
    public function finalFare(TripRequest $trip): array
    {
        $deposit = app(TripDepositService::class)->forTrip($trip->id);
        return [
            'is_move' => true,
            'actual_fare' => 0,
            'paid_fare' => 0,
            'amount_due' => 0,
            'deposit' => app(TripDepositService::class)->present($deposit, $trip),
        ];
    }
}
