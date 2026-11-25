<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Ledger\Ledger;
use Illuminate\Http\Request;

/**
 * Base of every accounting screen. Accounting must be switched on for the
 * business (Settings › Modules › Accounting) and its books set up; the
 * permission names are paxhitestbed's (App\Ledger\Permissions).
 *
 * Messages: paxhitestbed shows Toastr::success / Toastr::error; PosInnova pages
 * show the session 'status' flash, so ok() / err() put the message there.
 */
abstract class LedgerController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $bid = (int) $request->session()->get('user.business_id');
            if (! $bid || ! Ledger::enabledFor($bid)) {
                abort(403, __('ledger.err_module_off'));
            }
            if (! Ledger::isSetUp($bid) && ! $this instanceof SetupController) {
                return redirect()->route('ledger.setup');
            }

            return $next($request);
        });
    }

    /** Abort unless the user holds one of these permissions. */
    protected function allow(string ...$perms): void
    {
        foreach ($perms as $p) {
            if (auth()->user()->can($p)) {
                return;
            }
        }
        abort(403, __('messages.unauthorized_action'));
    }

    /**
     * Was the review dialog gone through? (paxhitestbed: confirming adds
     * reviewed=1; the server refuses a posting without it.)
     */
    protected function reviewed(Request $request): bool
    {
        return $request->boolean('reviewed');
    }

    protected function needReview()
    {
        return back()->withInput()->with('status', ['success' => 0, 'msg' => __('ledger.review_first')]);
    }

    protected function ok($to, string $msg)
    {
        return ($to instanceof \Illuminate\Http\RedirectResponse ? $to : redirect($to))->with('status', ['success' => 1, 'msg' => $msg]);
    }

    protected function err($to, string $msg)
    {
        return ($to instanceof \Illuminate\Http\RedirectResponse ? $to : redirect($to))->withInput()->with('status', ['success' => 0, 'msg' => $msg]);
    }

    /**
     * The PDF writer of the statements (posinnova's host app gives its controllers this; E-Ride
     * gives it here). mPDF, with its temporary files kept out of the public folder.
     */
    protected function getMpdf($orientation = 'P')
    {
        $tmp = storage_path('app/mpdf-temp');
        if (! is_dir($tmp)) {
            @mkdir($tmp, 0775, true);
        }

        return new \Mpdf\Mpdf(['tempDir' => $tmp, 'mode' => 'utf-8', 'autoScriptToLang' => true, 'autoLangToFont' => true,
            'useSubstitutions' => true, 'orientation' => $orientation]);
    }
}
