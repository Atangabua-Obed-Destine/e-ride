<?php

namespace App\Http\Controllers\Ledger;

use App\Ledger\Ledger;
use App\Ledger\Models\AccountingSetting;
use App\Ledger\Services\LedgerSetup;
use App\Ledger\Services\LedgerSync;
use App\Ledger\Services\Preview;
use App\Ledger\Services\SyscohadaChart;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * First run: open the business's books. Creates the SYSCOHADA chart, this
 * year with its twelve months and the default mappings, then posts what
 * customers and suppliers owe at the start date as one opening entry.
 * Everything from the start date on posts itself.
 */
class SetupController extends LedgerController
{
    public function home()
    {
        return redirect()->route('ledger.chart-of-accounts.index');
    }

    public function index(Request $request)
    {
        $this->allow('chart-of-accounts-create');
        $bid = (int) session('user.business_id');
        if (Ledger::isSetUp($bid)) {
            return redirect()->route('ledger.chart-of-accounts.index');
        }
        if ($request->ajax() || $request->get('preview')) {
            return $this->preview($request);
        }

        return view('ledger.setup.index', [
            'accounts' => count(SyscohadaChart::accounts()),
            'year' => (int) Carbon::today()->year,
            'today' => Carbon::today()->toDateString(),
            'yearStart' => Carbon::today()->startOfYear()->toDateString(),
        ]);
    }

    private function preview(Request $request)
    {
        $from = $this->startDate($request);
        $p = Preview::blank();
        $p['summary'] = [
            [__('ledger.setup_chart'), __('ledger.setup_chart_n', ['n' => count(SyscohadaChart::accounts())])],
            [__('ledger.fiscal_year'), Carbon::parse($from)->startOfYear()->format('d/m/Y').' – '.Carbon::parse($from)->endOfYear()->format('d/m/Y')],
            [__('ledger.setup_start'), Carbon::parse($from)->format('d/m/Y')],
        ];
        $p['notes'][] = __('ledger.setup_note_opening', ['date' => Carbon::parse($from)->format('d/m/Y')]);
        $p['notes'][] = __('ledger.setup_note_after');

        return view('ledger.partials.preview', ['p' => $p]);
    }

    public function store(Request $request)
    {
        $this->allow('chart-of-accounts-create');
        if (! $this->reviewed($request)) {
            return $this->needReview();
        }
        $bid = (int) session('user.business_id');
        $from = $this->startDate($request);
        @set_time_limit(600);
        try {
            LedgerSetup::install($bid, auth()->id(), (int) substr($from, 0, 4));
            Ledger::forBusiness($bid, function () use ($from) {
                AccountingSetting::setValue('post_from_date', $from);
                // the documents from the start date on are posted by the scheduled ledger:sync
                (new LedgerSync())->openingFromHistory(auth()->id());
            });
        } catch (\Throwable $e) {
            \Log::error('Ledger setup: '.$e->getMessage());

            return $this->err(route('ledger.setup'), $e->getMessage());
        }

        // E-Ride: Ledger health is adapted in stage 2; until then the chart of accounts opens.
        return $this->ok(route(\App\Ledger\ERide\Screens::isReady('ledger.ledger-health') ? 'ledger.ledger-health' : 'ledger.chart-of-accounts.index'), __('ledger.setup_done'));
    }

    private function startDate(Request $request): string
    {
        $d = $request->input('start') === 'year' ? Carbon::today()->startOfYear() : Carbon::today();

        return $d->toDateString();
    }
}
