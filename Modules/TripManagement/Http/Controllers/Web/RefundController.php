<?php

namespace Modules\TripManagement\Http\Controllers\Web;

use App\Http\Controllers\BaseController;
use Brian2694\Toastr\Facades\Toastr;
use Carbon\Factory;
use Illuminate\Console\Application;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Modules\TripManagement\Http\Requests\ParcelRefundRequestApprovedOrDeniedStoreRequest;
use Modules\TripManagement\Http\Requests\ParcelRefundRequestRefundedStoreRequest;
use Modules\TripManagement\Service\Interfaces\ParcelRefundServiceInterface;
use Modules\TripManagement\Service\Interfaces\TripRequestServiceInterface;
use App\Exports\StyledReport\ColumnFormat;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RefundController extends BaseController
{
    use AuthorizesRequests;

    protected $parcelRefundService;
    protected $tripRequestService;

    public function __construct(ParcelRefundServiceInterface $parcelRefundService, TripRequestServiceInterface $tripRequestService)
    {
        parent::__construct($parcelRefundService);
        $this->parcelRefundService = $parcelRefundService;
        $this->tripRequestService = $tripRequestService;
    }

    public function parcelRefundList(?Request $request, string $type = null)
    {
        $this->authorize('trip_view');

        $attributes = [];
        $search = null;

        if ($type != 'all') {
            $attributes['status'] = $type;
        }
        $request->has('search') ? ($search = $attributes['search'] = $request->search) : null;


        $parcelRefunds = $this->parcelRefundService->index(criteria: $attributes, relations: ['tripRequest.customer', 'tripRequest.parcel.parcelCategory', 'tripRequest.parcelUserInfo', 'refundProofs'], orderBy: ['created_at' => 'desc'], limit: paginationLimit(), offset: $request['page'] ?? 1, appends: $request->all());
        return view('tripmanagement::admin.refund.index', compact('parcelRefunds', 'search', 'type'));
    }

    public function show($id, Request $request): \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|RedirectResponse
    {
        $this->authorize('trip_view');

        $parcelRefund = $this->parcelRefundService->findOne(id: $id, withTrashed: true);
        if (!$parcelRefund) {
            Toastr::error(PARCEL_REFUND_REQUEST_404['message']);
            return back();
        }
        $trip = $this->tripRequestService->findOne(id: $parcelRefund->trip_request_id, relations: ['customer', 'driver', 'tripStatus', 'parcelRefund.refundProofs', 'parcel.parcelCategory'], withTrashed: true);
        if ($request['page'] == 'log') {

            return view('tripmanagement::admin.refund.log', compact('trip'));
        }

        return view('tripmanagement::admin.refund.details', compact('trip'));
    }

    public function storeApproved($id, ParcelRefundRequestApprovedOrDeniedStoreRequest $request): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|RedirectResponse
    {
        $this->authorize('trip_edit');
        $parcelRefund = $this->parcelRefundService->findOne(id: $id);
        if (!$parcelRefund) {
            Toastr::error(PARCEL_REFUND_REQUEST_404['message']);
            return back();
        }
        $data = array_merge($request->validated(), ['status' => APPROVED]);
        $this->parcelRefundService->update(id: $id, data: $data);
        if ($parcelRefund?->tripRequest?->driver?->fcm_token) {
            try {
                $push = getNotification('refund_accepted');
                sendDeviceNotification(fcm_token: $parcelRefund?->tripRequest?->driver?->fcm_token,
                    title: translate(key: $push['title'], locale: $parcelRefund?->tripRequest?->driver?->current_language_key),
                    description: textVariableDataFormat(value: $push['description'], parcelId: $parcelRefund?->tripRequest?->ref_id, locale: $parcelRefund?->tripRequest?->driver?->current_language_key),
                    status: $push['status'],
                    ride_request_id: $parcelRefund?->trip_request_id,
                    type: $parcelRefund?->trip_request_id,
                    notification_type: 'parcel',
                    action: $push['action'],
                    user_id: $parcelRefund?->tripRequest?->driver?->id
                );
            } catch (\Exception $exception) {

            }
        }
        if ($parcelRefund?->tripRequest?->customer?->fcm_token) {
            try {
                $push = getNotification('refund_accepted');
                sendDeviceNotification(fcm_token: $parcelRefund?->tripRequest?->customer?->fcm_token,
                    title: translate(key: $push['title'], locale: $parcelRefund?->tripRequest?->customer?->current_language_key),
                    description: textVariableDataFormat(value: $push['description'], parcelId: $parcelRefund?->tripRequest?->ref_id, locale: $parcelRefund?->tripRequest?->customer?->current_language_key),
                    status: $push['status'],
                    ride_request_id: $parcelRefund?->trip_request_id,
                    type: $parcelRefund?->trip_request_id,
                    notification_type: 'parcel',
                    action: $push['action'],
                    user_id: $parcelRefund?->tripRequest?->customer?->id
                );
            } catch (\Exception $exception) {

            }
        }
        Toastr::success(PARCEL_REFUND_REQUEST_APPROVED_200['message']);
        return redirect()->route('admin.trip.refund.index', APPROVED);
    }

    public function storeDenied($id, ParcelRefundRequestApprovedOrDeniedStoreRequest $request): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|RedirectResponse
    {
        $this->authorize('trip_edit');
        $parcelRefund = $this->parcelRefundService->findOne(id: $id);
        if (!$parcelRefund) {
            Toastr::error(PARCEL_REFUND_REQUEST_404['message']);
            return back();
        }
        $data = array_merge($request->validated(), ['status' => DENIED]);
        $this->parcelRefundService->update(id: $id, data: $data);
        if ($parcelRefund?->tripRequest?->driver?->fcm_token) {
            try {
                $push = getNotification('refund_denied');
                sendDeviceNotification(fcm_token: $parcelRefund?->tripRequest?->driver?->fcm_token,
                    title: translate(key: $push['action'], locale: $parcelRefund?->tripRequest?->driver?->current_language_key),
                    description: textVariableDataFormat(value: $push['description'], parcelId: $parcelRefund?->tripRequest?->ref_id, locale: $parcelRefund?->tripRequest?->driver?->current_language_key),
                    status: $push['status'],
                    ride_request_id: $parcelRefund?->trip_request_id,
                    type: $parcelRefund?->trip_request_id,
                    notification_type: 'parcel',
                    action: $push['action'],
                    user_id: $parcelRefund?->tripRequest?->driver?->id
                );
            } catch (\Exception $exception) {

            }
        }
        if ($parcelRefund?->tripRequest?->customer?->fcm_token) {
            try {
                $push = getNotification('refund_denied');
                sendDeviceNotification(fcm_token: $parcelRefund?->tripRequest?->customer?->fcm_token,
                    title: translate(key: $push['title'], locale: $parcelRefund?->tripRequest?->customer?->current_language_key),
                    description: textVariableDataFormat(value: $push['description'], parcelId: $parcelRefund?->tripRequest?->ref_id, locale: $parcelRefund?->tripRequest?->customer?->current_language_key),
                    status: $push['status'],
                    ride_request_id: $parcelRefund?->trip_request_id,
                    type: $parcelRefund?->trip_request_id,
                    notification_type: 'parcel',
                    action: $push['action'],
                    user_id: $parcelRefund?->tripRequest?->customer?->id
                );
            } catch (\Exception $exception) {

            }
        }
        Toastr::success(PARCEL_REFUND_REQUEST_DENIED_200['message']);
        return redirect()->route('admin.trip.refund.index', DENIED);
    }

    public function store($id, ParcelRefundRequestRefundedStoreRequest $request): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|RedirectResponse
    {
        $this->authorize('trip_edit');
        $parcelRefund = $this->parcelRefundService->findOne(id: $id);
        if (!$parcelRefund) {
            Toastr::error(PARCEL_REFUND_REQUEST_404['message']);
            return back();
        }
        $data = array_merge($request->validated(), ['status' => REFUNDED]);
        $this->parcelRefundService->update(id: $id, data: $data);
        $parcelRefund = $this->parcelRefundService->findOne(id: $id);
        if ($parcelRefund?->tripRequest?->driver?->fcm_token) {
            try {
                $push = getNotification('parcel_amount_debited');
                sendDeviceNotification(fcm_token: $parcelRefund?->tripRequest?->driver?->fcm_token,
                    title: translate(key: $push['title'], locale: $parcelRefund?->tripRequest?->driver?->current_language_key),
                    description: textVariableDataFormat(value: $push['description'], parcelId: $parcelRefund?->tripRequest->ref_id, approximateAmount: set_currency_symbol($parcelRefund->refund_amount_by_admin), locale: $parcelRefund?->tripRequest?->driver?->current_language_key),
                    status: $push['status'],
                    ride_request_id: $parcelRefund?->trip_request_id,
                    type: $parcelRefund?->trip_request_id,
                    notification_type: 'parcel',
                    action: $push['action'],
                    user_id: $parcelRefund?->tripRequest?->driver?->id
                );
            } catch (\Exception $exception) {

            }
        }
        if ($parcelRefund?->tripRequest?->customer?->fcm_token) {
            if ($request->refund_method == 'coupon') {
                try {
                    $push = getNotification('refunded_as_coupon');
                    sendDeviceNotification(fcm_token: $parcelRefund?->tripRequest?->customer?->fcm_token,
                        title: translate(key: $push['title'], locale: $parcelRefund?->tripRequest?->customer?->current_language_key),
                        description: textVariableDataFormat(value: $push['description'], parcelId: $parcelRefund?->tripRequest?->ref_id, approximateAmount: set_currency_symbol($parcelRefund->refund_amount_by_admin), locale: $parcelRefund?->tripRequest?->customer?->current_language_key),
                        status: $push['status'],
                        ride_request_id: $parcelRefund?->coupon_setup_id,
                        type: 'coupon',
                        notification_type: 'parcel',
                        action: $push['action'],
                        user_id: $parcelRefund?->tripRequest?->customer?->id
                    );
                } catch (\Exception $exception) {

                }
            }
            if ($request->refund_method == 'wallet') {
                try {
                    $push = getNotification('refunded_to_wallet');
                    sendDeviceNotification(fcm_token: $parcelRefund?->tripRequest?->customer?->fcm_token,
                        title: translate(key: $push['title'], locale: $parcelRefund?->tripRequest?->customer?->current_language_key),
                        description: textVariableDataFormat(value: $push['description'], parcelId: $parcelRefund?->tripRequest?->ref_id, approximateAmount: set_currency_symbol($parcelRefund->refund_amount_by_admin), locale: $parcelRefund?->tripRequest?->customer?->current_language_key),
                        status: $push['status'],
                        ride_request_id: $parcelRefund?->trip_request_id,
                        type: 'wallet',
                        notification_type: 'parcel',
                        action: $push['action'],
                        user_id: $parcelRefund?->tripRequest?->customer?->id
                    );
                } catch (\Exception $exception) {

                }
            }

        }
        Toastr::success(PARCEL_REFUND_REQUEST_REFUNDED_200['message']);
        return redirect()->route('admin.trip.refund.index', REFUNDED);
    }

    public function export(Request $request): View|Factory|Response|StreamedResponse|string|Application
    {
        $this->authorize('trip_export');

        $this->authorize('trip_view');

        $attributes = [];

        if ($request->type != 'all') {
            $attributes['status'] = $request->type;
        }
        $request->has('search') ? ($attributes['search'] = $request->search) : null;


        $parcelRefunds = $this->parcelRefundService->index(criteria: $attributes, relations: ['tripRequest.customer', 'tripRequest.parcel.parcelCategory', 'tripRequest.parcelUserInfo', 'refundProofs'], orderBy: ['created_at' => 'desc'], limit: paginationLimit(), offset: $request['page'] ?? 1, appends: $request->all());
        $data = $parcelRefunds->map(fn($item) => [
            'Refund ID' => $item['readable_id'],
            'Trip ID' => $item['tripRequest']->ref_id,
            'Date' => date('d F Y', strtotime($item['created_at'])) . ' ' . date('h:i a', strtotime($item['created_at'])),
            'Category' => $item['tripRequest']['parcel']['parcelCategory']->name,
            'Approximate Price' => $item['parcel_approximate_price'],
            'Customer Name' => $item['tripRequest']['customer']->full_name,
            'Customer Phone' => $item['tripRequest']['customer']->phone,
            'Refund Reason' => $item['reason'],
            'Refund Status' => ucwords($item['status'])
        ]);
        $config = styledExportConfig(
            $data,
            title: 'Parcel Refunds',
            summary: ['Total Refunds' => $data->count()],
            filters: [
                'Type'   => $request->type   ?? translate('All'),
                'Search' => $request->search ?? translate('N/A'),
            ],
            columnFormats: [
                'Approximate Price' => ColumnFormat::CURRENCY,
                'Date'              => ColumnFormat::DATETIME,
                'Refund Status'     => ColumnFormat::STATUS,
            ],
            fileName: 'parcel-refunds-' . time() . '.xlsx',
            headings: ['Refund ID', 'Trip ID', 'Date', 'Category', 'Approximate Price', 'Customer Name', 'Customer Phone', 'Refund Reason', 'Refund Status'],
        );
        return exportData($data, $request['file'], 'tripmanagement::admin.refund.print', $config);
    }
}
