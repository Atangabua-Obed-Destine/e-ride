<?php

namespace Modules\BusManagement\Http\Controllers\Web\Company;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\BusManagement\Entities\BusAgency;
use Modules\BusManagement\Entities\BusCompanyStaff;
use Modules\BusManagement\Entities\BusCompanyStatement;
use Modules\BusManagement\Entities\BusDeparture;
use Modules\BusManagement\Entities\BusDestination;
use Modules\BusManagement\Entities\BusDropPoint;
use Modules\BusManagement\Entities\BusStaff;
use Modules\BusManagement\Entities\BusStatement;
use Modules\BusManagement\Entities\BusVehicle;
use Modules\BusManagement\Lib\BusSettings;
use Modules\BusManagement\Lib\CameroonRegions;
use Modules\BusManagement\Service\AgencyService;
use Modules\BusManagement\Service\CompanyReportService;
use Modules\BusManagement\Service\CompanyService;
use Modules\BusManagement\Service\ScheduleService;
use Modules\BusManagement\Service\StatementService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A bus company's head office: every branch live today, reports by branch, route, agent and day, and a few
 * controls (pause a branch's app sales, staff in any branch, fares, the company booking rules, money).
 */
class CompanyPortalController extends Controller
{
    public function __construct(
        protected CompanyService $companies,
        protected CompanyReportService $reports,
        protected AgencyService $agencies,
    ) {
    }

    /** The branches this person sees, narrowed by the region / city / branch filter. */
    protected function scope(Request $request): Collection
    {
        $branches = $request->attributes->get('busBranches');
        if ($r = $request->query('region')) {
            $branches = $branches->where('region', $r);
        }
        if ($c = $request->query('city')) {
            $branches = $branches->filter(fn($b) => strcasecmp($b->origin_city, $c) === 0);
        }
        if ($b = $request->query('branch')) {
            $branches = $branches->where('id', $b);
        }
        return $branches->values();
    }

    protected function branch(Request $request, string $id): BusAgency
    {
        $b = $request->attributes->get('busBranches')->firstWhere('id', $id);
        abort_unless($b, 404);
        return $b;
    }

    protected function filters(Request $request): array
    {
        $all = $request->attributes->get('busBranches');
        return [
            'regions' => $all->pluck('region')->filter()->unique()->mapWithKeys(fn($r) => [$r => CameroonRegions::label($r)])->sort()->all(),
            'cities' => $all->pluck('origin_city')->unique()->sort()->values()->all(),
            'branches' => $all,
            'picked' => $request->only(['region', 'city', 'branch', 'from', 'to']),
        ];
    }

    // ------------------------------------------------------------- overview

    public function overview(Request $request): View
    {
        return view('busmanagement::company.overview', $this->filters($request));
    }

    public function feed(Request $request): JsonResponse
    {
        return response()->json(['now' => now()->format('H:i')] + $this->reports->today($this->scope($request)));
    }

    // ------------------------------------------------------------- branches

    public function branches(Request $request): View
    {
        $branches = $this->scope($request);
        $staffCount = BusStaff::whereIn('agency_id', $branches->pluck('id'))->where('active', true)->selectRaw('agency_id, count(*) as n')->groupBy('agency_id')->pluck('n', 'agency_id');
        return view('busmanagement::company.branches', compact('branches', 'staffCount') + $this->filters($request));
    }

    public function branchShow(Request $request, string $branch): View
    {
        $agency = $this->branch($request, $branch);
        $departures = BusDeparture::with(['destination', 'vehicle'])->where('agency_id', $agency->id)
            ->whereBetween('date', [today()->toDateString(), today()->addDays(6)->toDateString()])
            ->where('status', '!=', BusDeparture::CANCELLED)->orderBy('date')->orderBy('time')->get();
        $sold = \Modules\BusManagement\Entities\BusSeatClaim::whereIn('departure_id', $departures->pluck('id'))
            ->selectRaw('departure_id, count(*) as n')->groupBy('departure_id')->pluck('n', 'departure_id');
        $destinations = BusDestination::with(['dropPoints' => fn($q) => $q->orderBy('sort')])->where('agency_id', $agency->id)->orderBy('city')->get();
        return view('busmanagement::company.branch', compact('agency', 'departures', 'sold', 'destinations'));
    }

    public function pause(Request $request, string $branch): JsonResponse
    {
        $agency = $this->branch($request, $branch);
        $pause = $request->boolean('pause');
        $n = $this->companies->pauseBranch($agency, auth()->id(), $pause);
        return response()->json(['ok' => true, 'message' => $pause
            ? ($n > 0 ? translate(key: '{branch}: app sales paused. {n} waiting request(s) were declined.', replace: ['branch' => $agency->branchLabel(), 'n' => $n])
                : translate(key: '{branch}: app sales paused. Its counter keeps selling.', replace: ['branch' => $agency->branchLabel()]))
            : translate(key: '{branch}: app sales are back on.', replace: ['branch' => $agency->branchLabel()])]);
    }

    /** Fares of a branch's stops, changed from the head office. */
    public function fares(Request $request, string $branch): RedirectResponse
    {
        $agency = $this->branch($request, $branch);
        $data = $request->validate([
            'fares' => 'required|array',
            'fares.*.classic' => 'nullable|numeric|min:0|max:1000000',
            'fares.*.vip' => 'nullable|numeric|min:0|max:1000000',
            'fares.*.executive' => 'nullable|numeric|min:0|max:1000000',
        ]);
        $points = BusDropPoint::whereIn('destination_id', BusDestination::where('agency_id', $agency->id)->select('id'))
            ->whereIn('id', array_keys($data['fares']))->get();
        foreach ($points as $p) {
            $f = $data['fares'][$p->id];
            if (!collect(['classic', 'vip', 'executive'])->contains(fn($k) => (float)($f[$k] ?? 0) > 0)) {
                return back()->withErrors(['fares' => translate(key: '{stop}: give it at least one fare.', replace: ['stop' => $p->name])]);
            }
        }
        foreach ($points as $p) {
            $f = $data['fares'][$p->id];
            $p->update(['fare_classic' => ($f['classic'] ?? null) ?: null, 'fare_vip' => ($f['vip'] ?? null) ?: null, 'fare_executive' => ($f['executive'] ?? null) ?: null]);
        }
        return back()->with('toast', translate(key: '{branch}: fares saved. Passengers see them at once.', replace: ['branch' => $agency->branchLabel()]));
    }

    // ------------------------------------------------------------- reports

    protected function period(Request $request): array
    {
        $from = $this->day($request->query('from')) ?? today()->startOfMonth();
        $to = $this->day($request->query('to')) ?? today();
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }
        if ($from->diffInDays($to) > 366) {
            $from = $to->copy()->subDays(366);
        }
        return [$from, $to];
    }

    protected function day(?string $value): ?Carbon
    {
        if (!$value || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    public function reports(Request $request): View
    {
        [$from, $to] = $this->period($request);
        $report = $this->reports->report($this->scope($request), $from, $to);
        return view('busmanagement::company.reports', compact('report', 'from', 'to') + $this->filters($request));
    }

    public function csv(Request $request, string $section): StreamedResponse
    {
        abort_unless(in_array($section, ['branches', 'routes', 'agents', 'days'], true), 404);
        [$from, $to] = $this->period($request);
        $rows = $this->reports->report($this->scope($request), $from, $to)[$section];
        $company = $request->attributes->get('busCompany');
        $name = \Illuminate\Support\Str::slug($company->name) . '-' . $section . '-' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.csv';
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            if ($rows) {
                fputcsv($out, array_keys($rows[0]));
                foreach ($rows as $r) {
                    fputcsv($out, array_map(fn($v) => is_bool($v) ? (int)$v : $v, array_values($r)));
                }
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ------------------------------------------------------------- staff

    public function staff(Request $request): View
    {
        $branches = $this->scope($request);
        $branchStaff = BusStaff::with(['user', 'agency'])->whereIn('agency_id', $branches->pluck('id'))->orderBy('agency_id')->orderBy('role')->get();
        $headOffice = $request->attributes->get('busCompanyStaff')->isDirector()
            ? BusCompanyStaff::with('user')->where('company_id', $request->attributes->get('busCompany')->id)->orderBy('role')->get() : collect();
        return view('busmanagement::company.staff', compact('branchStaff', 'headOffice') + $this->filters($request));
    }

    public function addBranchStaff(Request $request): RedirectResponse
    {
        $agency = $this->branch($request, (string)$request->input('agency_id'));
        try {
            [, $login] = $this->agencies->addStaff($agency, $request->only(['name', 'email', 'role', 'password']));
        } catch (ValidationException $e) {
            return back()->withInput($request->except('password'))->withErrors($e->errors());
        }
        return back()->with('new_login', ['login' => $login, 'url' => route('bus-partner.login'), 'branch' => $agency->branchLabel()]);
    }

    public function branchStaffToggle(Request $request, int $staff): JsonResponse
    {
        $s = BusStaff::findOrFail($staff);
        $agency = $this->branch($request, (string)$s->agency_id);
        try {
            $this->agencies->setStaffActive($agency, $s, $request->boolean('active'), null);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        }
        return response()->json(['ok' => true, 'message' => $request->boolean('active') ? translate('Switched on.') : translate('Switched off. They can no longer sign in.')]);
    }

    public function branchStaffPassword(Request $request, int $staff): JsonResponse
    {
        $s = BusStaff::findOrFail($staff);
        $agency = $this->branch($request, (string)$s->agency_id);
        try {
            $this->agencies->resetPassword($agency, $s, (string)$request->input('password'));
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        }
        return response()->json(['ok' => true, 'message' => translate('New password saved. Give it to them yourself.')]);
    }

    public function addCompanyStaff(Request $request): RedirectResponse
    {
        try {
            [, $login] = $this->companies->addStaff($request->attributes->get('busCompany'), $request->only(['name', 'email', 'role', 'agency_ids', 'password']));
        } catch (ValidationException $e) {
            return back()->withInput($request->except('password'))->withErrors($e->errors());
        }
        return back()->with('new_login', ['login' => $login, 'url' => route('bus-company.login'), 'branch' => translate('Head office')]);
    }

    public function companyStaffToggle(Request $request, int $staff): JsonResponse
    {
        $company = $request->attributes->get('busCompany');
        $s = BusCompanyStaff::findOrFail($staff);
        try {
            $this->companies->setStaffActive($company, $s, $request->boolean('active'), $request->attributes->get('busCompanyStaff'));
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        }
        return response()->json(['ok' => true, 'message' => translate('Saved.')]);
    }

    public function companyStaffPassword(Request $request, int $staff): JsonResponse
    {
        try {
            $this->companies->resetStaffPassword($request->attributes->get('busCompany'), BusCompanyStaff::findOrFail($staff), (string)$request->input('password'));
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        }
        return response()->json(['ok' => true, 'message' => translate('New password saved. Give it to them yourself.')]);
    }

    // ------------------------------------------------------------- buses

    public function buses(Request $request): View
    {
        $company = $request->attributes->get('busCompany');
        $branchIds = BusAgency::where('company_id', $company->id)->pluck('id');
        $buses = BusVehicle::with('agency')->where(fn($q) => $q->where('company_id', $company->id)->orWhereIn('agency_id', $branchIds))
            ->orderByRaw("FIELD(status, 'ready', 'garage', 'retired')")->orderBy('registration')->get();
        $trips = BusDeparture::with(['destination', 'agency.company'])->whereIn('vehicle_id', $buses->pluck('id'))
            ->whereIn('status', [BusDeparture::SCHEDULED, BusDeparture::BOARDING, BusDeparture::DEPARTED])
            ->whereBetween('date', [today()->subDay()->toDateString(), today()->addDays(6)->toDateString()])
            ->orderBy('date')->orderBy('time')->get()->groupBy('vehicle_id');
        // Each bus's coming trips, with clashes (two trips at once) and where it will be (it ends a trip in another city).
        $plan = $buses->map(function (BusVehicle $bus) use ($trips) {
            $list = ($trips[$bus->id] ?? collect())->sortBy(fn($d) => $d->leavesAt()->timestamp)->values();
            $rows = [];
            $prev = null;
            foreach ($list as $d) {
                [$start, $end] = ScheduleService::busyWindow($d);
                $clash = $prev && $start->lt($prev['end']);
                $elsewhere = $prev && $prev['to'] && strcasecmp($prev['to'], (string)$d->agency?->origin_city) !== 0;
                $row = ['d' => $d, 'start' => $start, 'end' => $end, 'from' => $d->agency?->origin_city, 'to' => $d->destination?->city,
                    'branch' => $d->agency?->branchLabel(), 'clash' => $clash, 'elsewhere' => $elsewhere ? $prev['to'] : null];
                $rows[] = $row;
                if ($end->gte(now()->subHours(12))) {
                    $prev = ['end' => $end, 'to' => $row['to']];
                }
            }
            return ['bus' => $bus, 'trips' => collect($rows)->filter(fn($r) => $r['end']->gte(now()))->values()];
        });
        return view('busmanagement::company.buses', compact('plan'));
    }

    // ------------------------------------------------------------- rules

    public function rules(Request $request): View
    {
        $company = $request->attributes->get('busCompany');
        $rules = BusSettings::rules($company->rules_template ?? $request->attributes->get('busBranches')->first()?->rules);
        return view('busmanagement::company.rules', compact('rules'));
    }

    public function saveRules(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'free_bags' => 'required|integer|min:0|max:5',
            'bag_kg' => 'required|integer|min:5|max:50',
            'extra_bag_fee' => 'required|integer|min:0|max:100000',
            'online_cutoff_minutes' => 'required|integer|min:0|max:720',
            'id_check' => 'required|in:eride,agents,number',
            'tickets' => 'required|in:app,collect',
            'tier_full_hours' => 'required|integer|min:0|max:168',
            'tier_half_hours' => 'required|integer|min:0|max:168',
            'tier_half_pct' => 'required|integer|min:0|max:100',
            'apply_to' => 'nullable|array',
            'apply_to.*' => 'uuid',
        ]);
        if ($data['tier_half_hours'] > $data['tier_full_hours']) {
            return back()->withInput()->withErrors(['tier_half_hours' => translate('The second deadline must come after the first (fewer hours before departure).')]);
        }
        $company = $request->attributes->get('busCompany');
        $this->companies->saveRulesTemplate($company, [
            'free_bags' => $data['free_bags'], 'bag_kg' => $data['bag_kg'], 'extra_bag_fee' => $data['extra_bag_fee'],
            'online_cutoff_minutes' => $data['online_cutoff_minutes'], 'require_id' => true,
            'id_check' => $data['id_check'], 'tickets' => $data['tickets'], 'paper_copy' => $request->boolean('paper_copy'),
            'cancel_tiers' => [[$data['tier_full_hours'], 100], [$data['tier_half_hours'], $data['tier_half_pct']], [0, 0]],
        ]);
        $visible = $request->attributes->get('busBranches')->pluck('id')->map(fn($id) => (string)$id)->all();
        $apply = array_values(array_intersect($visible, (array)($data['apply_to'] ?? [])));
        $n = $apply ? $this->companies->applyRules($company->fresh(), $apply) : 0;
        return back()->with('toast', $n > 0
            ? translate(key: 'Company booking rules saved and given to {n} branch(es).', replace: ['n' => $n])
            : translate('Company booking rules saved. Give them to branches when you are ready.'));
    }

    // ------------------------------------------------------------- money and E-Ride bill (director)

    /**
     * Every branch's books side by side (director and accountant): ticket sales of trips that left,
     * other income, spending, result, money held, agents' cash, what is owed. One branch's reports
     * can be opened read-only.
     */
    public function accounts(Request $request): View
    {
        abort_if($request->attributes->get('busCompanyStaff')->role === BusCompanyStaff::REGIONAL, 403);
        [$from, $to] = $this->period($request);
        $accReports = app(\Modules\BusManagement\Service\Accounting\AccReports::class);
        $sync = app(\Modules\BusManagement\Service\Accounting\LedgerSync::class);
        $rows = [];
        foreach ($this->scope($request) as $b) {
            $sync->sync($b);
            $rows[] = ['branch' => $b, 'f' => $accReports->branchFigures($b, $from->toDateString(), $to->toDateString())];
        }
        $open = collect($rows)->filter(fn($r) => $r['f']);
        $totals = [];
        foreach (['tickets', 'other_income', 'fuel', 'spending', 'result', 'money', 'agents', 'held', 'owes', 'cash_short'] as $k) {
            $totals[$k] = round($open->sum(fn($r) => $r['f'][$k]), 2);
        }
        $tab = in_array($request->query('tab'), ['income', 'profit', 'cash', 'balance'], true) ? $request->query('tab') : null;
        $by = in_array($request->query('by'), ['bus', 'route', 'trip'], true) ? $request->query('by') : 'bus';
        $detail = null;
        $one = $request->query('branch') ? $open->first(fn($r) => (string)$r['branch']->id === (string)$request->query('branch')) : null;
        if ($tab && $one) {
            $f = $from->toDateString();
            $t = $to->toDateString();
            $detail = match ($tab) {
                'income' => $accReports->incomeStatement($one['branch'], $f, $t),
                'profit' => $accReports->profit($one['branch'], $f, $t, $by),
                'cash' => $accReports->cashFlow($one['branch'], $f, $t),
                'balance' => $accReports->balanceSheet($one['branch'], $t),
            };
        }
        return view('busmanagement::company.accounts', compact('rows', 'totals', 'from', 'to', 'tab', 'by', 'detail', 'one') + $this->filters($request));
    }

    public function money(Request $request): View
    {
        // Money is for the director and the accountant, not regional managers.
        abort_if($request->attributes->get('busCompanyStaff')->role === BusCompanyStaff::REGIONAL, 403);
        $company = $request->attributes->get('busCompany');
        $branchIds = BusAgency::where('company_id', $company->id)->pluck('id');
        $companyStatements = BusCompanyStatement::where('company_id', $company->id)->orderByDesc('month')->limit(12)->get();
        $branchStatements = BusStatement::with('agency')->whereIn('agency_id', $branchIds)->orderByDesc('month')->limit(60)->get();
        $fapshi = $company->fapshiValues() ?? [];
        $webhookUrl = url('bus/fapshi/' . $company->webhook_code);
        return view('busmanagement::company.money', compact('companyStatements', 'branchStatements', 'fapshi', 'webhookUrl'));
    }

    public function saveModes(Request $request): RedirectResponse
    {
        try {
            $this->companies->setModes($request->attributes->get('busCompany'), $request->only(['money_mode', 'bill_mode']));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
        return back()->with('toast', translate('Saved.'));
    }

    public function saveFapshi(Request $request): RedirectResponse
    {
        $company = $request->attributes->get('busCompany');
        try {
            $this->agencies->saveFapshi($company, $request->only(['api_user', 'api_key', 'webhook_secret', 'mode']));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
        [$ok, $message] = $this->agencies->testFapshi($company->fresh());
        return back()->with($ok ? 'toast' : 'fapshi_error', $message);
    }

    public function testFapshi(Request $request): RedirectResponse
    {
        [$ok, $message] = $this->agencies->testFapshi($request->attributes->get('busCompany'));
        return back()->with($ok ? 'toast' : 'fapshi_error', $message);
    }

    public function payStatement(Request $request, string $statement): RedirectResponse
    {
        $s = BusCompanyStatement::where('company_id', $request->attributes->get('busCompany')->id)->findOrFail($statement);
        try {
            return redirect()->away(app(StatementService::class)->payCompanyLink($s, auth()->user()));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }
}
