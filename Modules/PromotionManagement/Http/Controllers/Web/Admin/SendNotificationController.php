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
use Modules\PromotionManagement\Http\Requests\SendNotificationStoreUpdateRequest;
use Modules\PromotionManagement\Service\Interfaces\SendNotificationServiceInterface;
use App\Exports\StyledReport\ColumnFormat;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SendNotificationController extends BaseController
{
    use AuthorizesRequests;

    protected SendNotificationServiceInterface $sendNotificationService;

    public function __construct(SendNotificationServiceInterface $sendNotificationService)
    {
        parent::__construct($sendNotificationService);
        $this->sendNotificationService = $sendNotificationService;
    }

    public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        $this->authorize('promotion_view');
        $sendNotifications = $this->sendNotificationService->index(criteria: $request?->all(), orderBy: ['created_at' => 'desc'], limit: paginationLimit(), offset: $request['page']?? 1);

        return view('promotionmanagement::admin.send-notification.index', compact('sendNotifications'));
    }

    public function store(SendNotificationStoreUpdateRequest $request):RedirectResponse
    {
        $this->authorize('promotion_add');
        $this->sendNotificationService->create(data: $request->validated());
        Toastr::success(SEND_NOTIFICATION_STORE_200['message']);

        return back();
    }

    public function status(Request $request): JsonResponse
    {
        $this->authorize('promotion_edit');
        $request->validate([
            'status' => 'boolean'
        ]);
        $model = $this->sendNotificationService->statusChange(id: $request->id, data: $request->all());

        return response()->json($model);
    }

    public function destroy($id)
    {
        $this->authorize('promotion_delete');
        $this->sendNotificationService->delete(id: $id);
        Toastr::success(SEND_NOTIFICATION_DESTROY_200['message']);

        return back();
    }

    public function edit($id): View
    {
        $this->authorize('promotion_edit');
        $sendNotification = $this->sendNotificationService->findOne(id: $id);

        return view('promotionmanagement::admin.send-notification.partials.offcanvas-edit', compact('sendNotification'));
    }

    public function update(SendNotificationStoreUpdateRequest $request, $id)
    {
        $attributes = $request->validated();
        if (array_key_exists('update_and_resend', $request->all()))
        {
            $attributes = array_merge($attributes, ['update_and_resend' => true]);
        }

        if (isset($request->old_image) && !in_array('image', $request->all()))
        {
            $attributes = array_merge($attributes, ['old_image' => $request->old_image]);
        }

        $this->authorize('promotion_edit');
        $this->sendNotificationService->update(id: $id, data: $attributes);
        Toastr::success(SEND_NOTIFICATION_UPDATE_200['message']);

        return back();
    }

    public function view($id): View
    {
        $this->authorize('promotion_view');
        $sendNotification = $this->sendNotificationService->findOne(id: $id);

        return view('promotionmanagement::admin.send-notification.partials.modal-view', compact('sendNotification'));
    }

    public function resend($id): RedirectResponse
    {
        $this->authorize('promotion_edit');

        $notification = $this->sendNotificationService->findOne(id: $id);
        if (!$notification) {
            Toastr::error(SEND_NOTIFICATION_404['message']);
            return back();
        }
        $topics = $notification->targeted_users;
        if (in_array('customers', $topics)) {
            $topics = array_merge($topics, ['customers_send_notification']);
        }
        if (in_array('drivers', $topics)) {
            $topics = array_merge($topics, ['drivers_send_notification']);
        }
        foreach ($topics as $topic) {
            sendTopicNotification(topic: $topic, title: $notification->name, description: $notification->description, image: $notification->image ?? null, type: 'send_notification', status: $notification->is_active);
        }

        Toastr::success(SEND_NOTIFICATION_RESEND_200['message']);
        return back();
    }

    public function export(Request $request): View|Factory|Response|StreamedResponse|string|Application
    {
        $this->authorize('promotion_export');
        $notifications = $this->sendNotificationService->index(criteria: $request->all(), orderBy: ['created_at' => 'desc']);
        $data = $notifications->map(function ($item) {
            return [
                'Name' => $item['name'],
                'Description' => $item['description'] ?? '',
                'Targeted Users' => implode(', ', $item['targeted_users']),
                "Active Status" => $item['is_active'] == 1 ? "Active" : "Inactive",
            ];
        });
        $config = styledExportConfig(
            $data,
            title: 'Send Notification',
            summary: [
                'Total Notifications' => $data->count(),
                'Active'              => $data->where('Active Status', 'Active')->count(),
                'Inactive'            => $data->where('Active Status', 'Inactive')->count(),
            ],
            filters: [
                'Search' => $request->search ?? translate('N/A'),
            ],
            columnFormats: [
                'Active Status' => ColumnFormat::STATUS,
            ],
            fileName: 'send-notification-' . time() . '.xlsx',
            headings: ['Name', 'Description', 'Targeted Users', 'Active Status'],
        );
        return exportData($data, $request['file'], '', $config);
    }

}
