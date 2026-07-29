<?php

namespace Modules\ParcelManagement\Http\Controllers\Web\Admin;

use App\Http\Controllers\BaseController;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Modules\AdminModule\Service\Interfaces\ActivityLogServiceInterface;
use Modules\ParcelManagement\Entities\ParcelCategory;
use Modules\ParcelManagement\Http\Requests\ParcelCategoryStoreOrUpdateRequest;
use Modules\ParcelManagement\Service\Interfaces\ParcelCategoryServiceInterface;
use App\Exports\StyledReport\ColumnFormat;

class ParcelCategoryController extends BaseController
{
    use AuthorizesRequests;

    protected $parcelCategoryService;
    protected $activityLogService;

    public function __construct(ParcelCategoryServiceInterface $parcelCategoryService, ActivityLogServiceInterface $activityLogService)
    {
        parent::__construct($parcelCategoryService);
        $this->parcelCategoryService = $parcelCategoryService;
        $this->activityLogService = $activityLogService;
    }

    public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        $this->authorize('parcel_view');
        $request?->validate([
            'status' => 'in:all,active,inactive',
            'query' => 'sometimes',
            'search' => 'sometimes'
        ]);
        $categories = $this->parcelCategoryService->index(criteria: $request?->all(), relations: ['parcels'], orderBy: ['created_at' => 'desc'], limit: paginationLimit(), offset: $request['page']??1);
        return view('parcelmanagement::admin.attributes.category.index', compact('categories'));
    }


    public function store(ParcelCategoryStoreOrUpdateRequest $request): RedirectResponse
    {
        $this->authorize('parcel_add');
        $this->parcelCategoryService->create(data: $request->validated());

        Toastr::success(ucfirst(PARCEL_CATEGORY_STORE_200['message']));
        return back();
    }


    public function edit(int|string $id)
    {
        $this->authorize('parcel_edit');

        $category = $this->parcelCategoryService->findOne(id: $id);
        return view('parcelmanagement::admin.attributes.category.edit', compact('category'));
    }

    public function update(ParcelCategoryStoreOrUpdateRequest $request, $id): RedirectResponse
    {
        $this->authorize('parcel_edit');
        $this->parcelCategoryService->update(id: $id, data: $request->validated());

        Toastr::success(PARCEL_CATEGORY_UPDATE_200['message']);
        return redirect()->route('admin.parcel.attribute.category.index');
    }

    public function destroy($id): RedirectResponse
    {
        $this->authorize('parcel_delete');

        $this->parcelCategoryService->delete(id: $id);

        Toastr::success(PARCEL_CATEGORY_DESTROY_200['message']);
        return redirect()->route('admin.parcel.attribute.category.index');
    }

    public function status(Request $request): JsonResponse
    {
        $this->authorize('parcel_edit');

        $validated = $request->validate([
            'status' => 'boolean'
        ]);
        $model = $this->parcelCategoryService->statusChange(id: $request->id, data: $request->all());

        return response()->json($model);
    }

    public function download(Request $request): mixed
    {
        $this->authorize('parcel_export');
        $data = $this->parcelCategoryService->export(criteria: $request->all(), relations: ['parcels']);
        $config = styledExportConfig(
            $data,
            title: 'Parcel Categories',
            summary: ['Total Categories' => $data->count()],
            filters: [
                'Status' => ucfirst($request->status ?? 'all'),
                'Search' => $request->search ?? translate('N/A'),
            ],
            columnFormats: [
                'Status' => ColumnFormat::STATUS,
            ],
            fileName: 'parcel-categories-' . time() . '.xlsx',
            headings: ['Parcel Category Name', 'Total Delivered', 'Status'],
        );
        return exportData($data, $request['file'], 'parcelmanagement::admin.attributes.category.print', $config);
    }

    public function log(Request $request)
    {
        $this->authorize('parcel_log');

        $request->merge([
            'logable_type' => ParcelCategory::class,
        ]);

        $logs = $this->activityLogService->log($request->all());
        $file = array_key_exists('file', $request->all()) ? $request['file'] : '';
        return logViewerNew($logs,$file);
    }

    public function trashed(Request $request): View
    {
        $this->authorize('super-admin');
        $categories = $this->parcelCategoryService->trashedData(criteria: $request->all(), relations: ['parcels'],limit: paginationLimit());
        return view('parcelmanagement::admin.attributes.category.trashed', compact('categories'));

    }

    public function restore($id): RedirectResponse
    {
        $this->authorize('super-admin');

        $this->parcelCategoryService->restoreData(id: $id);

        Toastr::success(DEFAULT_RESTORE_200['message']);
        return redirect()->route('admin.parcel.attribute.category.index');

    }

    public function permanentDelete($id)
    {
        $this->authorize('super-admin');
        $this->parcelCategoryService->permanentDelete(id: $id);
        Toastr::success(PARCEL_CATEGORY_DESTROY_200['message']);
        return back();
    }
}
