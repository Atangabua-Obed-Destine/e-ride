<?php

namespace Modules\BusManagement\Http\Controllers\Web\Admin;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\BusManagement\Entities\BusAgency;
use Modules\BusManagement\Entities\BusCompany;
use Modules\BusManagement\Entities\BusCompanyStaff;
use Modules\BusManagement\Entities\BusCompanyStatement;
use Modules\BusManagement\Service\CompanyService;

/** E-Ride admin: bus companies (head offices), their branches and head office logins. */
class CompanyAdminController extends Controller
{
    public function __construct(protected CompanyService $companies)
    {
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            $company = $this->companies->create($request->only(['name', 'about']));
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }
        return redirect()->route('admin.bus.companies.show', $company->id);
    }

    public function show(BusCompany $company): View
    {
        $branches = BusAgency::where('company_id', $company->id)->orderBy('origin_city')->get();
        $free = BusAgency::whereNull('company_id')->where('status', '!=', BusAgency::CHECKING)->orderBy('name')->get();
        $staff = BusCompanyStaff::with('user')->where('company_id', $company->id)->orderBy('role')->get();
        $statements = BusCompanyStatement::where('company_id', $company->id)->orderByDesc('month')->limit(12)->get();
        return view('busmanagement::admin.company', compact('company', 'branches', 'free', 'staff', 'statements'));
    }

    public function update(Request $request, BusCompany $company): RedirectResponse
    {
        return $this->run(fn() => $this->companies->update($company, $request->only(['name', 'about', 'turnaround_minutes'])), translate('Saved.'));
    }

    public function link(Request $request, BusCompany $company): RedirectResponse
    {
        $agency = BusAgency::findOrFail($request->input('agency_id'));
        return $this->run(fn() => $this->companies->link($company, $agency, $request->input('branch_label'), $request->input('region')),
            translate('The agency is now a branch of this company. Its buses joined the company fleet.'));
    }

    public function unlink(BusCompany $company, BusAgency $agency): RedirectResponse
    {
        abort_unless((string)$agency->company_id === (string)$company->id, 404);
        return $this->run(fn() => $this->companies->unlink($agency), translate('The agency stands alone again.'));
    }

    public function addStaff(Request $request, BusCompany $company): RedirectResponse
    {
        try {
            [, $login] = $this->companies->addStaff($company, $request->only(['name', 'email', 'role', 'agency_ids', 'password']));
        } catch (ValidationException $e) {
            return back()->withInput($request->except('password'))->withErrors($e->errors());
        }
        return back()->with('company_login', ['login' => $login, 'url' => route('bus-company.login')]);
    }

    public function toggleStaff(Request $request, BusCompany $company, BusCompanyStaff $staff): RedirectResponse
    {
        return $this->run(fn() => $this->companies->setStaffActive($company, $staff, $request->boolean('active')), translate('Saved.'));
    }

    public function modes(Request $request, BusCompany $company): RedirectResponse
    {
        return $this->run(fn() => $this->companies->setModes($company, $request->only(['money_mode', 'bill_mode'])), translate('Saved.'));
    }

    protected function run(callable $fn, string $done): RedirectResponse
    {
        try {
            $fn();
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }
        Toastr::success($done);
        return back();
    }
}
