<?php

namespace Modules\PlaceManagement\Service\Interfaces;

use App\Service\BaseServiceInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\PlaceManagement\Entities\Place;

interface PlaceServiceInterface extends BaseServiceInterface
{
    public function list(array $filters, int $perPage = 20): LengthAwarePaginator;

    /** Create or edit a listing from the admin form: photos added and removed, zone from the pin. */
    public function save(Place $place, array $data): Place;

    /**
     * The Stay screen for a customer at a point: verified listings, then
     * Google's top rated and nearest lodging, minus hidden and linked places.
     */
    public function stay(?float $latitude, ?float $longitude, string $language = 'en'): array;

    /** Admin: what customers around a point see from Google, with each place's status. */
    public function googlePreview(float $latitude, float $longitude): ?array;

    public function hideGooglePlace(string $googlePlaceId, ?string $label, ?string $reason, ?string $adminId): void;

    public function unhideGooglePlace(string $googlePlaceId): void;
}
