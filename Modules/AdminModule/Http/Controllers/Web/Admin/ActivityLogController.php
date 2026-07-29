<?php

namespace Modules\AdminModule\Http\Controllers\Web\Admin;

use App\Http\Controllers\BaseController;
use Modules\AdminModule\Service\Interfaces\ActivityLogServiceInterface;

class ActivityLogController extends BaseController
{
    protected $activityLogService;

    public function __construct(ActivityLogServiceInterface $activityLogService)
    {
        parent::__construct($activityLogService);
        $this->activityLogService = $activityLogService;
    }

    public function log($request)
    {
        return $this->activityLogService->log(data: $request->all());
    }
}
