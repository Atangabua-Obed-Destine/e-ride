<?php

namespace Modules\BusManagement\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\BusManagement\Entities\BusAgency;
use Modules\BusManagement\Entities\BusCompanyStaff;
use Modules\BusManagement\Service\CompanyService;
use Symfony\Component\HttpFoundation\Response;

/**
 * The bus company (head office) portal: a signed-in partner user with an active head office role.
 * Parameters: "act" (director or regional manager), "director" (director only).
 * Sets busCompany, busCompanyStaff and busBranches (the branches this person may see).
 */
class BusHeadOffice
{
    public function handle(Request $request, Closure $next, string ...$need): Response
    {
        $user = auth()->user();
        $staff = $user && $user->user_type === PARTNER_USER_TYPE && $user->is_active
            ? BusCompanyStaff::with('company')->where('user_id', $user->id)->where('active', true)->first()
            : null;
        if (!$staff || !$staff->company) {
            if ($user) {
                Auth::logout();
            }
            return $request->expectsJson()
                ? response()->json(['message' => translate('Please sign in again.')], 401)
                : redirect()->route('bus-company.login');
        }
        $allowed = match (true) {
            in_array('director', $need, true) => $staff->isDirector(),
            in_array('act', $need, true) => $staff->canAct(),
            default => true,
        };
        if (!$allowed) {
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => translate('Your role cannot do this.')], 403)
                : redirect()->route('bus-company.overview')->with('toast', translate('Your role cannot do this.'));
        }
        $ids = CompanyService::branchIds($staff);
        $branches = BusAgency::whereIn('id', $ids)->orderBy('region')->orderBy('origin_city')->get();
        $request->attributes->set('busCompany', $staff->company);
        $request->attributes->set('busCompanyStaff', $staff);
        $request->attributes->set('busBranches', $branches);
        view()->share(['busCompany' => $staff->company, 'busCompanyStaff' => $staff, 'busBranches' => $branches]);
        return $next($request);
    }
}
