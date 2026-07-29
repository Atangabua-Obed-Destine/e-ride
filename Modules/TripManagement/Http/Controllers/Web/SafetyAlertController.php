<?php

namespace Modules\TripManagement\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\TripManagement\Service\Interfaces\SafetyAlertServiceInterface;
use Modules\TripManagement\Service\Interfaces\TripRequestServiceInterface;
use Modules\UserManagement\Service\Interfaces\UserLastLocationServiceInterface;
use App\Exports\StyledReport\ColumnFormat;

class SafetyAlertController extends Controller
{
    protected $tripRequestService;
    protected $safetyAlertService;

    protected $userLastLocationService;


    public function __construct(TripRequestServiceInterface $tripRequestService, SafetyAlertServiceInterface $safetyAlertService, UserLastLocationServiceInterface $userLastLocationService)
    {
        $this->tripRequestService = $tripRequestService;
        $this->safetyAlertService = $safetyAlertService;
        $this->userLastLocationService = $userLastLocationService;
    }

    public function index($type, Request $request)
    {
        $this->authorize('trip_view');
        $whereHasRelation = [
            'sentBy' => [
                'user_type' => $type
            ]
        ];
        $relations = [
            'sentBy', 'solvedBy', 'trip.customer', 'trip.driver'
        ];
        $safetyAlerts = $this->safetyAlertService->index(criteria: $request->all(), relations: $relations, whereHasRelations: $whereHasRelation, orderBy: ['created_at' => 'desc'], limit: paginationLimit(), offset: $request['page'] ?? 1);
        return view('tripmanagement::admin.safety-alert.index', compact('safetyAlerts', 'type'));
    }

    public function export($type, Request $request)
    {
        $this->authorize('trip_export');
        $whereHasRelations = [
            'sentBy' => [
                'user_type' => $type
            ]
        ];
        $relations = [
            'sentBy', 'solvedBy', 'trip.customer', 'trip.driver'
        ];
        $data = $this->safetyAlertService->export(criteria: $request->all(), relations: $relations, whereHasRelations: $whereHasRelations);
        $config = styledExportConfig(
            $data,
            title: 'Safety Alerts',
            summary: ['Total Alerts' => $data->count()],
            filters: [
                'User Type' => ucfirst((string)$type),
                'Status'    => $request->status ?? translate('All'),
                'Search'    => $request->search ?? translate('N/A'),
            ],
            columnFormats: [
                'Status' => ColumnFormat::STATUS,
            ],
            fileName: 'safety-alerts-' . $type . '-' . time() . '.xlsx',
            headings: ['Trip Reference Id', 'Date', 'Sent By', 'Customer', 'Driver', 'Alert Location', 'Resolved Location', 'Number of Alert', 'Resolved By', 'Trip Status When Alert Sent'],
        );
        return exportData($data, $request['file'], '', $config);
    }

    public function markAsSolved($id)
    {
        $this->authorize('trip_view');
        $attributes = ['resolved_by' => Auth::user()?->id];
        $safetyAlert = $this->safetyAlertService->findOneBy(criteria: ['id' => $id]);
        if (!$safetyAlert) {
            Toastr::error('Safety Alert not found');
            return redirect()->back();
        }

        if ($safetyAlert->status == 'solved') {
            Toastr::error('This safety alert is already marked as solved');
            return redirect()->back();
        }

        $this->safetyAlertService->updatedBy(criteria: ['trip_request_id' => $safetyAlert?->trip_request_id, 'sent_by' => $safetyAlert->sent_by], data: $attributes);
        $push = getNotification('safety_problem_resolved');
        sendDeviceNotification(fcm_token: $safetyAlert?->sentBy?->fcm_token,
            title: translate(key: $push['title'], locale: $safetyAlert?->sentBy?->current_language_key),
            description: textVariableDataFormat(value: $push['description'], tripId: $safetyAlert?->trip?->ref_id, userName: $safetyAlert?->sentBy?->first_name . ' ' . $safetyAlert?->sentBy?->last_name, locale: $safetyAlert?->sentBy?->current_language_key),
            status: $push['status'],
            ride_request_id: $safetyAlert?->trip_request_id,
            type: 'safety_alert',
            notification_type: 'safety_alert',
            action: $push['action'],
            user_id: $safetyAlert?->sent_by
        );
        Toastr::success('Safety Alert marked as solved successfully');
        return redirect()->back()->with('success', 'Safety Alert marked as solved successfully');
    }

    public function ajaxMarkAsSolved($id)
    {
        $attributes = ['resolved_by' => Auth::user()?->id];
        $safetyAlert = $this->safetyAlertService->findOneBy(criteria: ['id' => $id]);
        if (!$safetyAlert) {
            return response()->json(['error' => 'Safety Alert not found'], 403);
        }
        if ($safetyAlert->status == 'solved') {
            return response()->json([
                'status' => 403,
                'code' => 'already_solved',
                'message' => translate('This safety alert is already marked as solved')
            ], 403);
        }
        $this->safetyAlertService->updatedBy(criteria: ['trip_request_id' => $safetyAlert?->trip_request_id, 'sent_by' => $safetyAlert?->sent_by], data: $attributes);
        $push = getNotification('safety_problem_resolved');
        sendDeviceNotification(fcm_token: $safetyAlert?->sentBy?->fcm_token,
            title: translate(key: $push['title'], locale: $safetyAlert?->sentBy?->current_language_key),
            description: textVariableDataFormat(value: $push['description'], tripId: $safetyAlert?->trip?->ref_id, userName: $safetyAlert?->sentBy?->first_name . ' ' . $safetyAlert?->sentBy?->last_name, locale: $safetyAlert?->sentBy?->current_language_key),
            status: $push['status'],
            ride_request_id: $safetyAlert?->trip_request_id,
            type: 'safety_alert',
            notification_type: 'safety_alert',
            action: $push['action'],
            user_id: $safetyAlert?->sent_by
        );

        return response()->json(['success' => translate('Safety Alert marked as solved successfully')], 200);
    }
}
