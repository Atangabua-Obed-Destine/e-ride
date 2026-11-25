<?php

namespace Modules\BusManagement\Http\Controllers\Web\Partner;

use App\Http\Controllers\Controller;
use App\Ledger\ERide\Books;
use Illuminate\Http\Request;

/**
 * "Accounting" in a portal's menu: opens that portal's books in the accounting
 * module (App\Ledger, paxhitestbed's module as ported to posinnova, under
 * /partner/accounting). A branch opens its own books; a head office its own.
 */
class LedgerEntryController extends Controller
{
    /** From the branch portal (BusPartner middleware: manager or accountant). */
    public function branch(Request $request)
    {
        $agency = $request->attributes->get('busAgency');

        return $this->open($request, Books::AGENCY, (string) $agency->id, 'bus-partner.departures');
    }

    /** From the head office portal (BusHeadOffice middleware; director or accountant). */
    public function company(Request $request)
    {
        $company = $request->attributes->get('busCompany');

        return $this->open($request, Books::COMPANY, (string) $company->id, 'bus-company.overview');
    }

    protected function open(Request $request, string $type, string $ownerId, string $back)
    {
        if (! Books::open($request->user(), $type, $ownerId)) {
            return redirect()->route($back)->with('toast', translate('Your role cannot open that page.'));
        }

        return redirect()->route('ledger.home');
    }
}
