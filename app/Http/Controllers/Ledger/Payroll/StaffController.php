<?php

namespace App\Http\Controllers\Ledger\Payroll;

use App\Http\Controllers\Ledger\LedgerController;
use App\Ledger\ERide\Books;
use App\Ledger\Ledger;
use App\Ledger\Models\Department;
use App\Ledger\Models\Designation;
use App\Ledger\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\BusManagement\Entities\BusAgency;
use Modules\BusManagement\Entities\BusCompanyStaff;
use Modules\BusManagement\Entities\BusStaff;

/**
 * The staff register: paxhitestbed's staff list, create and edit screens (Admin\UserController), with
 * only the fields payroll and tax read. A bus branch's drivers, conductors and loaders are often not
 * E-Ride users, so a person is a row here, linked when they are to the E-Ride user who works at the
 * branch (or the head office) whose books are open. Someone already paid is not deleted (their
 * payrolls name them): they are switched to inactive instead.
 */
class StaffController extends LedgerController
{
    public function index(Request $request)
    {
        $this->allow('staff-view', 'staff-create', 'staff-edit', 'staff-delete');
        $q = Staff::with(['designation', 'department', 'user'])->orderBy('staff_no');
        if ($request->filled('department')) {
            $q->where('department_id', $request->department);
        }
        if ($request->filled('designation')) {
            $q->where('designation_id', $request->designation);
        }
        $status = $request->input('status', '1');
        if ($status !== 'all') {
            $q->where('status', (int) $status);
        }

        return view('ledger.payroll.staff.index', [
            'title' => __('ledger_payroll.staff_list'),
            'rows' => $q->get(),
            'departments' => Department::orderBy('title')->get(),
            'designations' => Designation::orderBy('title')->get(),
            'selected_department' => $request->department,
            'selected_designation' => $request->designation,
            'selected_status' => $status,
        ]);
    }

    public function create()
    {
        $this->allow('staff-create');

        return view('ledger.payroll.staff.form', $this->formData(null));
    }

    public function store(Request $request)
    {
        $this->allow('staff-create');
        $this->check($request);
        $staff = new Staff();
        $this->fill($staff, $request);
        $staff->created_by = auth()->id();
        $staff->save();

        return $this->ok(route('ledger.staff.index'), __('ledger_payroll.created'));
    }

    public function edit($id)
    {
        $this->allow('staff-edit');

        return view('ledger.payroll.staff.form', $this->formData(Staff::findOrFail($id)));
    }

    public function update(Request $request, $id)
    {
        $this->allow('staff-edit');
        $staff = Staff::findOrFail($id);
        $this->check($request, $staff);
        $this->fill($staff, $request);
        $staff->updated_by = auth()->id();
        $staff->save();

        return $this->ok(route('ledger.staff.index'), __('ledger_payroll.updated'));
    }

    public function destroy($id)
    {
        $this->allow('staff-delete');
        $staff = Staff::findOrFail($id);
        if ($staff->payrolls()->exists() || $staff->loans()->exists()) {
            $staff->forceFill(['status' => 0, 'updated_by' => auth()->id()])->save();

            return $this->ok(back(), __('ledger_payroll.staff_switched_off'));
        }
        $staff->exemptTaxes()->delete();
        $staff->delete();

        return $this->ok(back(), __('ledger_payroll.deleted'));
    }

    // ------------------------------------------------------------------

    private function formData(?Staff $row): array
    {
        return [
            'title' => __('ledger_payroll.staff'),
            'row' => $row,
            'next_no' => $row ? $row->staff_no : Staff::nextNumber(),
            'departments' => Department::where('status', 1)->orderBy('title')->get(),
            'designations' => Designation::where('status', 1)->orderBy('title')->get(),
            'people' => self::people(),
        ];
    }

    /** The E-Ride users who work where these books belong: [user id => "name · role"]. */
    public static function people(): array
    {
        $owner = Books::current()['owner'] ?? null;
        if (! $owner) {
            return [];
        }
        $rows = $owner instanceof BusAgency
            ? BusStaff::with('user')->where('agency_id', $owner->id)->where('active', true)->get()
            : BusCompanyStaff::with('user')->where('company_id', $owner->id)->where('active', true)->get();
        $out = [];
        foreach ($rows as $r) {
            if ($r->user) {
                $out[(string) $r->user_id] = trim(trim($r->user->first_name.' '.$r->user->last_name).' · '.translate(ucfirst($r->role)));
            }
        }

        return $out;
    }

    private function check(Request $request, ?Staff $row = null): void
    {
        $bid = Ledger::businessId();
        $request->validate([
            'staff_no' => ['required', 'string', 'max:50', Rule::unique('ledger_staff', 'staff_no')->where('business_id', $bid)->ignore($row?->id)],
            'first_name' => 'required|string|max:191',
            'last_name' => 'nullable|string|max:191',
            'gender' => 'nullable|in:1,2,3',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:191',
            'designation_id' => ['nullable', Rule::exists('designations', 'id')->where('business_id', $bid)],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('business_id', $bid)],
            'contract_type' => 'nullable|in:1,2',
            'salary_type' => 'nullable|in:1,2',
            'basic_salary' => 'required|numeric|min:0',
            'joining_date' => 'nullable|date',
            'ending_date' => 'nullable|date|after_or_equal:joining_date',
            'pay_channel' => ['nullable', Rule::in(Staff::PAY_CHANNELS)],
            'bank_name' => 'nullable|string|max:191',
            'bank_branch' => 'nullable|string|max:191',
            'account_name' => 'nullable|string|max:191',
            'account_number' => 'nullable|required_if:pay_channel,bank|string|max:60',
            'bank_code' => 'nullable|string|max:30',
            'momo_provider' => ['nullable', 'required_if:pay_channel,mobile_money', Rule::in(Staff::MOMO_PROVIDERS)],
            'momo_number' => 'nullable|required_if:pay_channel,mobile_money|string|max:30',
            'cnps_no' => 'nullable|string|max:50',
            'niu' => 'nullable|string|max:50',
            'user_id' => ['nullable', Rule::in(array_keys(self::people()))],
            'note' => 'nullable|string|max:2000',
            'status' => 'nullable|boolean',
        ], [], [
            'staff_no' => __('ledger_payroll.field_staff_id'), 'first_name' => __('ledger_payroll.field_first_name'),
            'basic_salary' => __('ledger_payroll.field_basic_salary'), 'account_number' => __('ledger_payroll.field_account_number'),
            'momo_number' => __('ledger_payroll.field_momo_number'), 'momo_provider' => __('ledger_payroll.field_momo_provider'),
        ]);
    }

    private function fill(Staff $staff, Request $request): void
    {
        $staff->fill($request->only(['staff_no', 'first_name', 'last_name', 'gender', 'phone', 'email', 'designation_id', 'department_id',
            'contract_type', 'joining_date', 'ending_date', 'pay_channel', 'bank_name', 'bank_branch', 'account_name', 'account_number', 'bank_code',
            'momo_provider', 'momo_number', 'cnps_no', 'niu', 'user_id', 'note']));
        $staff->basic_salary = round((float) $request->basic_salary, 2);
        $staff->salary_type = (int) $request->input('salary_type', $staff->salary_type ?: Staff::SALARY_FIXED) === Staff::SALARY_HOURLY ? Staff::SALARY_HOURLY : Staff::SALARY_FIXED;
        $staff->status = $request->has('status') ? $request->boolean('status') : ($staff->exists ? $staff->status : 1);
        foreach (['designation_id', 'department_id', 'contract_type', 'gender', 'user_id', 'pay_channel'] as $k) {
            $staff->{$k} = $staff->{$k} ?: null;
        }
    }
}
