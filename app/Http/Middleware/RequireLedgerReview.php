<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Every accounting action that posts goes through the review dialog first
 * (paxhitestbed's RequireReview). The dialog runs the action for real and
 * rolls it back (Ledger\ReviewController::dryRun); confirming adds reviewed=1.
 * A route carrying this middleware ('ledger.review') refuses a submission
 * without it, so the review cannot be skipped by posting the form another way.
 */
class RequireLedgerReview
{
    public const ALIAS = 'ledger.review';

    public function handle(Request $request, Closure $next)
    {
        if (! $request->isMethod('GET') && ! $request->boolean('reviewed')) {
            $message = __('ledger.review_first');
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }

            return redirect()->back()->withInput()->with('status', ['success' => 0, 'msg' => $message]);
        }

        return $next($request);
    }
}
