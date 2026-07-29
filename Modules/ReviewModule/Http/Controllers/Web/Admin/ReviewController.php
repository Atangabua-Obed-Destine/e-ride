<?php

namespace Modules\ReviewModule\Http\Controllers\Web\Admin;

use App\Http\Controllers\BaseController;
use App\Exports\StyledReport\ColumnFormat;
use Illuminate\Http\Request;
use Modules\ReviewModule\Service\Interfaces\ReviewServiceInterface;

class ReviewController extends BaseController
{
    protected $reviewService;

    public function __construct(ReviewServiceInterface $reviewService)
    {
        parent::__construct($reviewService);
        $this->reviewService = $reviewService;
    }

    public function driverReviewExport($id, $reviewed, Request $request)
    {
        $exportData = $this->reviewService->export($id, $reviewed, $request, "driver");
        $config = styledExportConfig(
            $exportData,
            title: 'Driver Reviews',
            summary: [
                'Total Reviews' => $exportData->count(),
                'Avg Rating'    => $exportData->avg('Rating') !== null ? round((float)$exportData->avg('Rating'), 2) : 0,
            ],
            filters: [
                'Reviewed' => $reviewed === 'reviewed' ? translate('Yes') : translate('No'),
                'Search'   => $request->search ?? translate('N/A'),
            ],
            columnFormats: [
                'Rating' => ColumnFormat::DECIMAL,
            ],
            fileName: 'driver-reviews-' . time() . '.xlsx',
            headings: ['Trip Id', 'Reviewer', 'Rating', 'Review'],
        );
        return exportData($exportData, $request['file'], 'usermanagement::admin.driver.transaction.print', $config);
    }

    public function customerReviewExport($id, $reviewed, Request $request)
    {
        $exportData = $this->reviewService->export($id, $reviewed, $request, "customer");
        $config = styledExportConfig(
            $exportData,
            title: 'Customer Reviews',
            summary: [
                'Total Reviews' => $exportData->count(),
                'Avg Rating'    => $exportData->avg('Rating') !== null ? round((float)$exportData->avg('Rating'), 2) : 0,
            ],
            filters: [
                'Reviewed' => $reviewed === 'reviewed' ? translate('Yes') : translate('No'),
                'Search'   => $request->search ?? translate('N/A'),
            ],
            columnFormats: [
                'Rating' => ColumnFormat::DECIMAL,
            ],
            fileName: 'customer-reviews-' . time() . '.xlsx',
            headings: ['Trip Id', 'Reviewer', 'Rating', 'Review'],
        );
        return exportData($exportData, $request['file'], 'usermanagement::admin.driver.transaction.print', $config);
    }

}
