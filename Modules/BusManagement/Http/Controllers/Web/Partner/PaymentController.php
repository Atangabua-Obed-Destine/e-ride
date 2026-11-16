<?php

namespace Modules\BusManagement\Http\Controllers\Web\Partner;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\BusManagement\Entities\BusBooking;
use Modules\BusManagement\Entities\BusStatement;
use Modules\BusManagement\Service\AgencyService;
use Modules\BusManagement\Service\StatementService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The agency's own Fapshi account, refunds it owes, and E-Ride's monthly statements. */
class PaymentController extends Controller
{
    public function __construct(protected AgencyService $agencies, protected StatementService $statements)
    {
    }

    public function index(Request $request): View
    {
        $agency = $request->attributes->get('busAgency');
        $fapshi = is_array($agency->fapshi) ? $agency->fapshi : [];
        $webhookUrl = url('bus/fapshi/' . $agency->webhook_code);
        $statements = BusStatement::where('agency_id', $agency->id)->orderByDesc('month')->limit(24)->get();
        $thisMonth = $this->statements->totals($agency, now()->startOfMonth());
        $refundsDue = BusBooking::where('agency_id', $agency->id)->where('refund_status', 'due')->count();
        $paymentStatus = $request->query('payment_status');
        return view('busmanagement::partner.payments', compact('fapshi', 'webhookUrl', 'statements', 'thisMonth', 'refundsDue', 'paymentStatus'));
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

    /** One statement, ticket by ticket. */
    public function statement(Request $request, string $statement): View
    {
        $s = $this->find($request, $statement);
        $lines = $this->statements->lines($s);
        return view('busmanagement::partner.statement', ['statement' => $s, 'lines' => $lines]);
    }

    public function csv(Request $request, string $statement): StreamedResponse
    {
        $s = $this->find($request, $statement);
        $lines = $this->statements->lines($s);
        $name = 'e-ride-statement-' . $s->month->format('Y-m') . '.csv';
        return response()->streamDownload(function () use ($lines) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Departure', 'Destination', 'Booking', 'Sold', 'Seats', 'Fare', 'Refunded', 'Kept', 'Rate %', 'Commission']);
            foreach ($lines as $b) {
                fputcsv($out, [
                    $b->departure?->leavesAt()->format('Y-m-d H:i'), $b->departure?->destination?->city, $b->code, $b->source,
                    $b->tickets->whereIn('status', ['valid', 'boarded', 'no_show'])->sortBy(fn($t) => (int)$t->seat)->pluck('seat')->implode(' '),
                    $b->amount, $b->refund_amount, round($b->amount - $b->refund_amount, 2), $b->commission_pct, $b->commission,
                ]);
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    /** Pay E-Ride through E-Ride's own Fapshi checkout. */
    public function pay(Request $request, string $statement): RedirectResponse
    {
        $s = $this->find($request, $statement);
        try {
            return redirect()->away($this->statements->payLink($s, auth()->user()));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    protected function find(Request $request, string $id): BusStatement
    {
        return BusStatement::where('agency_id', $request->attributes->get('busAgency')->id)->findOrFail($id);
    }
}
