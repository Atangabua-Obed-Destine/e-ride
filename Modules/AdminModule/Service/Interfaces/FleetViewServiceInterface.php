<?php

namespace Modules\AdminModule\Service\Interfaces;

use Illuminate\Database\Eloquent\Model;

interface FleetViewServiceInterface
{
    public function getFleetData(?string $type, Model $zone, array $filters, bool $markersOnly = false): array;

    public function getDriverFleetDetails(int|string $id): array;

    public function getCustomerFleetDetails(int|string $id): array;

    public function getSingleDriverMarker(int|string $id): array;

    public function getSingleCustomerMarker(int|string $id): array;
}
