<?php

namespace Modules\PromotionManagement\Http\Controllers\Web\Admin;

use App\Http\Controllers\BaseController;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Modules\AdminModule\Service\Interfaces\ActivityLogServiceInterface;
use Modules\PromotionManagement\Entities\BannerSetup;
use Modules\PromotionManagement\Http\Requests\BannerSetupStoreUpdateRequest;
use Modules\PromotionManagement\Service\Interfaces\BannerSetupServiceInterface;
use App\Exports\StyledReport\ColumnFormat;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BannerSetupController extends BaseController
{
    use AuthorizesRequests;
    protected $bannerSetupService;
    protected $activityLogService;

    public function __construct(BannerSetupServiceInterface $bannerSetupService, ActivityLogServiceInterface $activityLogService)
    {
        parent::__construct($bannerSetupService);
        $this->bannerSetupService = $bannerSetupService;
        $this->activityLogService = $activityLogService;
    }

    public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        $this->authorize('promotion_view');
        $banners = $this->bannerSetupService->index(criteria: $request?->all(), orderBy: ['created_at' => 'desc'], limit: paginationLimit(), offset: $request['page']??1);
        return view('promotionmanagement::admin.banner-setup.index', compact('banners'));
    }

    public function store(BannerSetupStoreUpdateRequest $request)
    {
        $this->authorize('promotion_add');
        $this->bannerSetupService->create(data: $request->validated());
        Toastr::success(BANNER_STORE_200['message']);
        return back();
    }

    public function edit($id)
    {
        $this->authorize('promotion_edit');
        $banner = $this->bannerSetupService->findOne(id: $id);
        return view('promotionmanagement::admin.banner-setup.edit', compact('banner'));
    }

    public function update(BannerSetupStoreUpdateRequest $request, $id)
    {
        $this->authorize('promotion_edit');
        $this->bannerSetupService->update(id: $id, data: $request->validated());
        Toastr::success(BANNER_UPDATE_200['message']);
        return back();

    }

    public function destroy($id)
    {
        $this->authorize('promotion_delete');
        $this->bannerSetupService->delete(id: $id);
        Toastr::success(BANNER_DESTROY_200['message']);
        return back();
    }

    public function status(Request $request): JsonResponse
    {
        $this->authorize('promotion_edit');
        $request->validate([
            'status' => 'boolean'
        ]);
        $model = $this->bannerSetupService->statusChange(id: $request->id, data: $request->all());
        return response()->json($model);
    }


    public function trashed(Request $request): View
    {
        $this->authorize('super-admin');
        $banners = $this->bannerSetupService->trashedData(criteria: $request->all(), limit: paginationLimit(), offset: $request['page']??1);
        return view('promotionmanagement::admin.banner-setup.trashed', compact('banners'));
    }

    public function restore($id): RedirectResponse
    {
        $this->authorize('super-admin');

        $this->bannerSetupService->restoreData(id: $id);

        Toastr::success(DEFAULT_RESTORE_200['message']);
        return redirect()->route('admin.promotion.banner-setup.index');

    }

    public function permanentDelete($id)
    {
        $this->authorize('super-admin');
        $this->bannerSetupService->permanentDelete(id: $id);
        Toastr::success(BANNER_DESTROY_200['message']);
        return back();
    }

    public function export(Request $request): View|Factory|Response|StreamedResponse|string|Application
    {
        $this->authorize('promotion_export');
        $banner = $this->bannerSetupService->getBy(criteria: $request->all());
        $data = $banner->map(function ($item) {
            return [
                'id' => $item['id'],
                'banner_title' => $item['name'],
                "image" => $item['image'],
                'position' => $item['display_position'],
                'redirect_link' => $item['redirect_link'],
                "total_redirection" => $item['total_redirection'],
                "group" => $item['banner_group'],
                'time_period' => $item['time_period'] == ALL_TIME ? ALL_TIME : $item['start_date'] . ' To ' . $item['end_date'],
                "is_active" => $item['is_active'],
                "created_at" => $item['created_at'],
            ];
        });

        $config = styledExportConfig(
            $data,
            title: 'Banner Setup',
            summary: [
                'Total Banners'    => $data->count(),
                'Active Banners'   => $data->where('is_active', 1)->count(),
                'Inactive Banners' => $data->where('is_active', 0)->count(),
            ],
            filters: [
                'Search' => $request->search ?? translate('N/A'),
            ],
            columnFormats: [
                'total_redirection' => ColumnFormat::INTEGER,
                'created_at'        => ColumnFormat::DATETIME,
            ],
            fileName: 'banner-setup-' . time() . '.xlsx',
            headings: ['banner_title', 'image', 'position', 'redirect_link', 'total_redirection', 'group', 'time_period', 'is_active', 'created_at'],
        );
        return exportData($data, $request['file'], 'promotionmanagement::admin.banner-setup.print', $config);
    }


    public function log(Request $request): View|Factory|Response|StreamedResponse|string|Application
    {
        $this->authorize('promotion_log');

        $request->merge([
            'logable_type' => BannerSetup::class,
        ]);
        $logs = $this->activityLogService->log($request->all());
        $file = array_key_exists('file', $request->all()) ? $request['file'] : '';
        return logViewerNew($logs,$file);
    }
}
