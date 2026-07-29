<?php

namespace Modules\UserManagement\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\UserManagement\Service\Interfaces\AppNotificationServiceInterface;
use Modules\UserManagement\Transformers\AppNotificationResource;

class AppNotificationController extends Controller
{
    protected $appNotificationService;
    public function __construct(AppNotificationServiceInterface $appNotificationService)
    {
        $this->appNotificationService = $appNotificationService;
    }

    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required|numeric',
            'offset' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }
        $notifications = $this->appNotificationService->getBy(criteria: ['user_id'=>auth('api')->id()], orderBy: ['id'=>'desc'], limit: $request->limit, offset: $request->offset);
        $notifications = AppNotificationResource::collection($notifications);
        return response()->json(responseFormatter(constant: DEFAULT_200, content: $notifications, limit: $request->limit, offset: $request->offset));
    }

    public function readNotification(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'notification_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }

        $notification = $this->appNotificationService->findOneBy(criteria: ['id' => $request->notification_id, 'user_id' => auth('api')->id(), 'is_read' => 0]);

        if ($notification)
        {
            $this->appNotificationService->update(id: $notification->id, data: ['is_read' => 1]);
        }

        return response()->json(responseFormatter(constant: DEFAULT_UPDATE_200));
    }
}
