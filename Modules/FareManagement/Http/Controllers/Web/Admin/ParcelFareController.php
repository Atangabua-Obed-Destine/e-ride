<?php

namespace Modules\FareManagement\Http\Controllers\Web\Admin;

use App\Http\Controllers\BaseController;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Modules\FareManagement\Http\Requests\ParcelFareStoreOrUpdateRequest;
use Modules\FareManagement\Service\Interfaces\ParcelFareServiceInterface;
use Modules\ParcelManagement\Service\Interfaces\ParcelCategoryServiceInterface;
use Modules\ParcelManagement\Service\Interfaces\ParcelWeightServiceInterface;
use Modules\ZoneManagement\Service\Interfaces\ZoneServiceInterface;

class ParcelFareController extends BaseController
{
    use AuthorizesRequests;
    protected $parcelFareService;
    protected $parcelWeightService;
    protected $parcelCategoryService;
    protected $zoneService;

    public function __construct(ParcelFareServiceInterface     $parcelFareService, ParcelWeightServiceInterface $parcelWeightService,
                                ParcelCategoryServiceInterface $parcelCategoryService, ZoneServiceInterface $zoneService)
    {
        parent::__construct($parcelFareService);
        $this->parcelFareService = $parcelFareService;
        $this->parcelWeightService = $parcelWeightService;
        $this->parcelCategoryService = $parcelCategoryService;
        $this->zoneService = $zoneService;
    }

    public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        $this->authorize('fare_view');
        $parcelCategoryCriteria = [
            'is_active' => 1
        ];
        $parcelCategory = $this->parcelCategoryService->getBy(criteria: $parcelCategoryCriteria);
        $zoneCriteria = array_merge($request?->all(),[
            'is_active' => 1
        ]);
        $withCountCriteria = [
            'drivers'=>[]
        ];
        $zones = $this->zoneService->index(criteria: $zoneCriteria, withCountQuery: $withCountCriteria);
        $fares = $this->parcelFareService->getAll(relations: ['fares']);

        return view('faremanagement::admin.parcel.index', compact('parcelCategory', 'zones', 'fares'));
    }

    /**
     * Show the form for creating a new resource.
     * @param $zone_id
     * @return Renderable|RedirectResponse
     */
    public function create($zone_id): Renderable|RedirectResponse
    {
        $this->authorize('fare_add');
        $zone = $this->zoneService->findOne(id: $zone_id);
        if (!$zone) {
            Toastr::error(ZONE_404['message']);
            return redirect()->back();
        }
        $parcelCategory = $this->parcelCategoryService->getAll();
        $parcelWeight = $this->parcelWeightService->getAll();
        if ($parcelWeight->count() < 1) {

            Toastr::error(PARCEL_WEIGHT_404['message']);
            return back();
        }
        $fares = $this->parcelFareService->findOneBy(criteria: [ 'zone_id'=> $zone_id]);

        return view('faremanagement::admin.parcel.create',
            compact('zone', 'parcelCategory', 'parcelWeight', 'fares'));
    }


    public function store(ParcelFareStoreOrUpdateRequest $request): RedirectResponse|Renderable
    {
        $this->authorize('fare_add');
        $parcelWeight = $this->parcelWeightService->getAll();
        $request->merge(['parcel_weight' => $parcelWeight]);
        $this->parcelFareService->create(data: $request->all());

        Toastr::success(PARCEL_FARE_STORE_200['message']);
        return back();
    }

}
