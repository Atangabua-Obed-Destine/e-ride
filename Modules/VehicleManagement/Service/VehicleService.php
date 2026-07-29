<?php

namespace Modules\VehicleManagement\Service;

use App\Service\BaseService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\VehicleManagement\Repository\VehicleBrandRepositoryInterface;
use Modules\VehicleManagement\Repository\VehicleCategoryRepositoryInterface;
use Modules\VehicleManagement\Repository\VehicleModelRepositoryInterface;
use Modules\VehicleManagement\Repository\VehicleRepositoryInterface;
use Modules\VehicleManagement\Service\Interfaces\VehicleServiceInterface;

class VehicleService extends BaseService implements VehicleServiceInterface
{
    protected $vehicleRepository;
    protected $vehicleModelRepository;
    protected $vehicleCategoryRepository;

    public function __construct(VehicleRepositoryInterface $vehicleRepository,
                                VehicleModelRepositoryInterface $vehicleModelRepository,
                                VehicleCategoryRepositoryInterface $vehicleCategoryRepository)
    {
        parent::__construct($vehicleRepository);
        $this->vehicleRepository = $vehicleRepository;
        $this->vehicleModelRepository = $vehicleModelRepository;
        $this->vehicleCategoryRepository = $vehicleCategoryRepository;
    }


    public function index(array $criteria = [], array $relations = [], array $whereHasRelations = [], array $orderBy = [], ?int $limit = null, ?int $offset = null, array $withCountQuery = [], array $appends = [], array $groupBy = []): Collection|LengthAwarePaginator
    {
        $data = [];
        if (array_key_exists('status', $criteria) && $criteria['status'] !== 'all') {
            $data['is_active'] = $criteria['status'] == 'active' ? 1 : 0;
        }
        if (array_key_exists('vehicle_request_status', $criteria)) {
            $data['vehicle_request_status'] = $criteria['vehicle_request_status'];
        }
        if (array_key_exists('draft', $criteria) && $criteria['draft']) {
            $data[] = ['draft', '!=', null];
        }
        $searchData = [];
        if (array_key_exists('search', $criteria) && $criteria['search'] != '') {
            $searchData['fields'] = ['ref_id', 'licence_plate_number', 'vin_number'];
            $searchData['relations'] = [
                'driver' => ['full_name', 'first_name', 'last_name', 'email', 'phone'],
            ];
            $searchData['value'] = ltrim($criteria['search'], '#');
        }

        $whereInCriteria = [];
        $whereBetweenCriteria = [];
        $whereHasRelations = [];
        return $this->baseRepository->getBy(criteria: $data, searchCriteria: $searchData, whereInCriteria: $whereInCriteria, whereBetweenCriteria: $whereBetweenCriteria, whereHasRelations: $whereHasRelations, relations: $relations, orderBy: $orderBy, limit: $limit, offset: $offset, withCountQuery: $withCountQuery);
    }

    public function create(array $data): ?Model
    {
        $documents = [];
        if (array_key_exists('other_documents', $data)) {
            foreach ($data['other_documents'] as $doc) {
                $extension = $doc->getClientOriginalExtension();
                $documents[] = fileUploader('vehicle/document/', $extension, $doc);
            }
        }
        $storeData = [
            'brand_id' => $data['brand_id'],
            'model_id' => $data['model_id'],
            'category_id' => $data['category_id'],
            'licence_plate_number' => $data['licence_plate_number'],
            'licence_expire_date' => $data['licence_expire_date'],
            'vin_number' => $data['vin_number'],
            'transmission' => $data['transmission'],
            'parcel_weight_capacity' => $data['parcel_weight_capacity'],
            'fuel_type' => $data['fuel_type'],
            'ownership' => $data['ownership'],
            'driver_id' => $data['driver_id'],
            'vehicle_request_status' => $data['vehicle_request_status'],
            'documents' => $documents,
        ];
        return $this->vehicleRepository->create($storeData);
    }

    public function updatedByAdmin(int|string $id, array $data = []): ?Model
    {
        $updateData = [
            'brand_id' => $data['brand_id'],
            'model_id' => $data['model_id'],
            'category_id' => $data['category_id'],
            'licence_plate_number' => $data['licence_plate_number'],
            'licence_expire_date' => $data['licence_expire_date'],
            'vin_number' => $data['vin_number'],
            'transmission' => $data['transmission'],
            'parcel_weight_capacity' => $data['parcel_weight_capacity'],
            'fuel_type' => $data['fuel_type'],
            'ownership' => $data['ownership'],
            'driver_id' => $data['driver_id'],
        ];

        if (array_key_exists('type', $data) && $data['type'] == 'update_and_approve') {
            $updateData['vehicle_request_status'] = APPROVED;
            $updateData['is_active'] = 1;
        }

        if (array_key_exists('type', $data) && $data['type'] == 'draft') {
            $updateData['draft'] = NULL;
        }

        $existingDocuments = array_key_exists('existing_documents', $data) ? $data['existing_documents'] : [];
        $deletedDocuments = array_key_exists('deleted_documents', $data) ? explode(',', $data['deleted_documents']) : [];

        // Remove deleted documents from the existing list
        $documents = array_diff($existingDocuments, $deletedDocuments);

        // Handle new uploads
        if ($data['other_documents'] ?? null) {
            foreach ($data['other_documents'] as $doc) {
                $extension = $doc->getClientOriginalExtension();
                $documents[] = fileUploader('vehicle/document/', $extension, $doc);
            }
        }
        $updateData['documents'] = $documents;
        return $this->vehicleRepository->update($id, $updateData);
    }

    public function export(array $criteria = [], array $relations = [], array $orderBy = [], ?int $limit = null, ?int $offset = null, array $withCountQuery = []): Collection|LengthAwarePaginator|\Illuminate\Support\Collection
    {
        return $this->index(criteria: $criteria, relations: $relations, orderBy: $orderBy)->map(function ($item) {
            return [
                'Id' => $item['id'],
                'Driver Name' => $item?->driver?->full_name ?? $item?->driver?->first_name . ' ' . $item?->driver?->last_name,
                'Type' => ucwords(str_replace('_', ' ', $item?->category?->type ?? 'N/A')),
                'Brand' => $item?->brand?->name ?? 'N/A',
                'Model' => $item?->model?->name ?? 'N/A',
                'License' => $item['licence_plate_number'],
                'Owner' => ucwords($item['ownership']),
                'Seat Capacity' => $item?->model?->seat_capacity ?? 'N/A',
                "Hatch Bag Capacity" => $item?->model?->hatch_bag_capacity ?? 'N/A',
                "Fuel" => ucwords($item['fuel_type']),
                "Mileage" => $item?->model?->engine ?? 'N/A',
                'Status' => $item['is_active'] == 1 ? "Active" : "Inactive",
            ];
        });
    }


    public function updatedByDriver(int|string $id, array $data): ?Model
    {
        $vehicle = $this->vehicleRepository->findOneBy(criteria: ['driver_id' => $id]);
        $vehicleLicenseExpireDate = $vehicle->licence_expire_date->toDateString();
        $updateVehicleValue = businessConfig(key: 'update_vehicle', settingsType: DRIVER_SETTINGS)?->value ?? [];
        $draftData = null;
        if ($vehicle->vehicle_request_status == APPROVED) {
            $updateVehicleStatus = businessConfig(key: 'update_vehicle_status', settingsType: DRIVER_SETTINGS)?->value ?? 0;
            if ($updateVehicleStatus == 1) {
                foreach (UPDATE_VEHICLE as $updateVehicle) {
                    if (in_array($updateVehicle, $updateVehicleValue, true)) {
                        if ($updateVehicle == 'vehicle_brand' && $vehicle?->brand_id != $data['brand_id']) {
                            $vehicleModel = $this->vehicleModelRepository->findOne(id: $data['model_id'], relations: ['brand']);
                            $draftData['brand_id'] = $vehicleModel?->brand->id;
                            $draftData['brand'] = $vehicleModel?->brand->name;
                            $draftData['model_id'] = $vehicleModel->id;
                            $draftData['model'] = $vehicleModel->name;
                        }
                        if ($updateVehicle == 'vehicle_category' && $vehicle?->category_id != $data['category_id']) {
                            $vehicleCategory = $this->vehicleCategoryRepository->findOne(id: $data['category_id']);
                            $draftData['category_id'] = $vehicleCategory->id;
                            $draftData['category'] = $vehicleCategory->name;
                        }
                        if ($updateVehicle == 'license_plate_number' && $vehicle?->licence_plate_number != $data['licence_plate_number']) {
                            $draftData['licence_plate_number'] = $data['licence_plate_number'];
                        }
                        if ($updateVehicle == 'license_expiry_date' && $vehicleLicenseExpireDate != $data['licence_expire_date']) {
                            $draftData['licence_expire_date'] = $data['licence_expire_date'];
                        }
                    }
                }
            }
        }

        $updateData = [
            'brand_id' => $vehicle->brand_id,
            'model_id' => $vehicle->model_id,
            'category_id' => $vehicle->category_id,
            'licence_plate_number' => $vehicle->licence_plate_number,
            'licence_expire_date' => $vehicle->licence_expire_date,
            'vin_number' => $data['vin_number'],
            'transmission' => $data['transmission'],
            'parcel_weight_capacity' => $data['parcel_weight_capacity'],
            'fuel_type' => $data['fuel_type'],
            'ownership' => $data['ownership'],
            'draft' => $draftData,
        ];

        if ($vehicle->vehicle_request_status == DENIED) {
            $updateData['vehicle_request_status'] = PENDING;
        }
        return $this->vehicleRepository->update($vehicle->id, $updateData);
    }

    public function deniedVehicleUpdateByAdmin(int|string $id, array $data = []): ?Model
    {
        $draftData = [];
        foreach ($data as $key => $value) {
            $draftData = $value;
        }

        $updateData = [
            'draft' => NULL,
        ];

        $data = array_merge($updateData, $draftData);
        return $this->vehicleRepository->update($id, $data);
    }

    public function exportUpdateVehicle(array $criteria = [], array $relations = [], array $orderBy = [], ?int $limit = null, ?int $offset = null, array $withCountQuery = []): Collection|LengthAwarePaginator|\Illuminate\Support\Collection
    {
        return $this->index(criteria: $criteria, relations: $relations, orderBy: $orderBy)->map(function ($item) {
            $draft = is_array($item?->draft) ? $item->draft : [];
            $beforeEdit = [];
            $afterEdit = [];

            if (array_key_exists('category_id', $draft)) {
                $beforeEdit[] = 'Category: ' . ($item?->category?->name ?? 'N/A');
                $afterEdit[]  = 'Category: ' . ($draft['category'] ?? 'N/A');
            }
            if (array_key_exists('brand_id', $draft)) {
                $beforeEdit[] = 'Brand: ' . ($item?->brand?->name ?? 'N/A');
                $afterEdit[]  = 'Brand: ' . ($draft['brand'] ?? 'N/A');
            }
            if (array_key_exists('model_id', $draft)) {
                $beforeEdit[] = 'Model: ' . ($item?->model?->name ?? 'N/A');
                $afterEdit[]  = 'Model: ' . ($draft['model'] ?? 'N/A');
            }
            if (array_key_exists('licence_plate_number', $draft)) {
                $beforeEdit[] = 'License Plate Number: ' . ($item?->licence_plate_number ?? 'N/A');
                $afterEdit[]  = 'License Plate Number: ' . ($draft['licence_plate_number'] ?? 'N/A');
            }
            if (array_key_exists('licence_expire_date', $draft)) {
                $beforeEdit[] = 'License Expiry Date: ' . ($item?->licence_expire_date ? date('Y-m-d', strtotime($item->licence_expire_date)) : 'N/A');
                $afterEdit[]  = 'License Expiry Date: ' . ($draft['licence_expire_date'] ? date('Y-m-d', strtotime($draft['licence_expire_date'])) : 'N/A');
            }

            return [
                'Vehicle ID'  => '#' . ($item?->ref_id ?? $item['id']),
                'Date & Time' => date('d/m/Y h:i A', strtotime($item->updated_at)),
                'Before Edit' => implode("\n", $beforeEdit),
                'After Edit'  => implode("\n", $afterEdit),
            ];
        });
    }
    }
