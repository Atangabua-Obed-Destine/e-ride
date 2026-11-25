<?php

namespace App\Http\Middleware;

use App\Ledger\ERide\Books;
use Closure;
use Illuminate\Http\Request;

/**
 * Every accounting page (routes/ledger.php) works on one set of books: the one
 * the person opened from their portal (App\Ledger\ERide\Books), checked again on
 * each request. Not signed in, or the books are no longer theirs: back to the
 * portal sign-in.
 */
class LedgerBooks
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();
        if (! $user || $user->user_type !== PARTNER_USER_TYPE || ! $user->is_active) {
            return $this->away($request, 'bus-partner.login');
        }
        $books = Books::fromSession($user);
        if (! $books) {
            session()->forget(['ledger.owner', 'user.business_id', 'business']);

            return $this->away($request, 'bus-partner.login', translate('Open Accounting from your portal.'));
        }
        Books::use($books);
        $request->attributes->set('ledgerBooks', $books);
        view()->share('ledgerBooks', $books);

        // A screen whose bus version is not ready yet (App\Ledger\ERide\Screens): back, with a note.
        if (\App\Ledger\ERide\Screens::isPending($request->route()?->getName())) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => translate('This accounting screen is coming soon.')], 409);
            }

            return redirect()->route('ledger.home')->with('status', ['success' => 0, 'msg' => translate('This accounting screen is coming soon.')]);
        }

        return $next($request);
    }

    protected function away(Request $request, string $route, ?string $message = null)
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message ?? 'unauthenticated'], 401);
        }
        $to = redirect()->route($route);

        return $message ? $to->withErrors(['login' => $message]) : $to;
    }
}
