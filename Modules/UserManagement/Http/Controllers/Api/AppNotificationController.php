<?php

namespace Modules\UserManagement\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\UserManagement\Entities\AppNotification;
use Modules\UserManagement\Lib\NotificationGroups;
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
            'group' => 'nullable|in:' . implode(',', NotificationGroups::GROUPS),
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }
        if ($request->filled('group')) {
            // One part of E-Ride only (Trips, Market, Jobs...).
            $query = AppNotification::where('user_id', auth('api')->id());
            NotificationGroups::apply($query, $request->input('group'));
            $notifications = $query->orderByDesc('id')->paginate((int)$request->limit, ['*'], 'page', (int)$request->offset);
        } else {
            $notifications = $this->appNotificationService->getBy(criteria: ['user_id'=>auth('api')->id()], orderBy: ['id'=>'desc'], limit: $request->limit, offset: $request->offset);
        }
        $notifications = AppNotificationResource::collection($notifications);
        return response()->json(responseFormatter(constant: DEFAULT_200, content: $notifications, limit: $request->limit, offset: $request->offset));
    }

    /** Unread notifications in all and per group, and the groups that have any notification (for the filter chips). */
    public function counts(): JsonResponse
    {
        $userId = auth('api')->id();
        $unread = array_fill_keys(NotificationGroups::GROUPS, 0);
        $has = array_fill_keys(NotificationGroups::GROUPS, false);
        $rows = AppNotification::where('user_id', $userId)
            ->selectRaw('type, notification_type, action, is_read, count(*) as n')
            ->groupBy('type', 'notification_type', 'action', 'is_read')->get();
        foreach ($rows as $row) {
            $group = NotificationGroups::of($row->type, $row->notification_type, $row->action);
            $has[$group] = true;
            if (!$row->is_read) {
                $unread[$group] += (int)$row->n;
            }
        }
        return response()->json(responseFormatter(constant: DEFAULT_200, content: [
            'unread' => array_sum($unread),
            'groups' => array_map(fn($g) => ['group' => $g, 'unread' => $unread[$g], 'has' => $has[$g]], NotificationGroups::GROUPS),
        ]));
    }

    /** Mark everything read, or one group only. */
    public function readAll(Request $request): JsonResponse
    {
        $request->validate(['group' => 'nullable|in:' . implode(',', NotificationGroups::GROUPS)]);
        $query = AppNotification::where('user_id', auth('api')->id())->where('is_read', 0);
        if ($request->filled('group')) {
            NotificationGroups::apply($query, $request->input('group'));
        }
        $updated = $query->update(['is_read' => 1]);
        return response()->json(responseFormatter(constant: DEFAULT_UPDATE_200, content: ['updated' => $updated]));
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
