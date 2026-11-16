<?php

namespace Modules\BusManagement\Http\Controllers\Web\Partner;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\BusManagement\Service\AgencyService;

/** The agency's own Fapshi account (and, later, refunds to send and E-Ride's monthly statements). */
class PaymentController extends Controller
{
    public function __construct(protected AgencyService $agencies)
    {
    }

    public function index(Request $request): View
    {
        $agency = $request->attributes->get('busAgency');
        $fapshi = is_array($agency->fapshi) ? $agency->fapshi : [];
        $webhookUrl = url('bus/fapshi/' . $agency->webhook_code);
        return view('busmanagement::partner.payments', compact('fapshi', 'webhookUrl'));
    }

    public function saveFapshi(Request $request): RedirectResponse
    {
        $agency = $request->attributes->get('busAgency');
        try {
            $this->agencies->saveFapshi($agency, $request->only(['api_user', 'api_key', 'webhook_secret', 'mode']));
        } catch (ValidationException $e) {
            return back()->withInput($request->only(['api_user', 'mode']))->withErrors($e->errors());
        }
        [$ok, $message] = $this->agencies->testFapshi($agency->fresh());
        return redirect()->route('bus-partner.payments')->with($ok ? 'success' : 'fapshi_error', $message);
    }

    public function testFapshi(Request $request): JsonResponse
    {
        [$ok, $message] = $this->agencies->testFapshi($request->attributes->get('busAgency'));
        return response()->json(['ok' => $ok, 'message' => $message]);
    }
}
