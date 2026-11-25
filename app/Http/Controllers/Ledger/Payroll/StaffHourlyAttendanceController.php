<?php

namespace App\Http\Controllers\Ledger\Payroll;

use App\Http\Controllers\Ledger\LedgerController;
use App\Ledger\Ledger;
use App\Ledger\Models\Department;
use App\Ledger\Models\Designation;
use App\Ledger\Models\Payroll;
use App\Ledger\Models\Staff;
use App\Ledger\Models\StaffHourlyAttendance;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * paxhitestbed Admin\StaffHourlyAttendanceController (permissions staff-hourly-attendance-action and
 * -report): the day's sessions of a staff member paid by the hour, with Present / Absent / Leave /
 * Holiday and the times, the month's report, and one person's month in detail.
 *
 * paxhitestbed lists the classes of the timetable for that day; a bus branch has none, so the day's
 * sessions are typed here (a trip, a shift, loading), each with its start and end time. A month
 * whose payroll is already paid is locked: changing its hours would make the paid pay disagree
 * with them (unpay it first).
 */
class StaffHourlyAttendanceController extends LedgerController
{
    private function base(): array
    {
        return ['title' => __('ledger_payroll.hourly_attendance'), 'route' => 'ledger.staff-hourly-attendance', 'access' => 'staff-hourly-attendance'];
    }

    public function index(Request $request)
    {
        $this->allow('staff-hourly-attendance-action');
        $data = $this->base();
        $data['selected_staff'] = $request->staff ?: '';
        $data['selected_date'] = $request->date ? Carbon::parse($request->date)->toDateString() : Carbon::today()->toDateString();
        $data['users'] = Staff::where('salary_type', Staff::SALARY_HOURLY)->where('status', 1)->orderBy('staff_no')->get();
        $data['staff'] = $request->staff ? Staff::where('salary_type', Staff::SALARY_HOURLY)->findOrFail($request->staff) : null;
        $data['attendances'] = $data['staff'] ? StaffHourlyAttendance::where('staff_id', $data['staff']->id)->whereDate('date', $data['selected_date'])->orderBy('start_time')->get() : collect();
        $data['locked'] = $data['staff'] ? $this->locked($data['staff']->id, $data['selected_date']) : false;

        return view('ledger.payroll.staff-hourly-attendance.index', $data);
    }

    public function store(Request $request)
    {
        $this->allow('staff-hourly-attendance-action');
        $bid = Ledger::businessId();
        $request->validate([
            'staff_id' => ['required', Rule::exists('ledger_staff', 'id')->where('business_id', $bid)->where('salary_type', Staff::SALARY_HOURLY)],
            'date' => 'required|date',
            'tasks' => 'nullable|array',
            'tasks.*' => 'nullable|string|max:191',
            'start_time' => 'nullable|array',
            'start_time.*' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|array',
            'end_time.*' => 'nullable|date_format:H:i',
            'attendance' => 'nullable|array',
            'attendance.*' => 'required|in:1,2,3,4',
            'notes' => 'nullable|array',
            'notes.*' => 'nullable|string|max:500',
        ]);
        $date = Carbon::parse($request->date)->toDateString();
        if ($this->locked((int) $request->staff_id, $date)) {
            return $this->err(back(), __('ledger_payroll.err_hours_locked'));
        }
        DB::transaction(function () use ($request, $date) {
            // the day's sessions are what the form shows: written again as a whole
            StaffHourlyAttendance::where('staff_id', $request->staff_id)->whereDate('date', $date)->delete();
            foreach ((array) $request->input('attendance', []) as $key => $code) {
                $start = $request->input("start_time.$key");
                $end = $request->input("end_time.$key");
                $task = trim((string) $request->input("tasks.$key"));
                if ($task === '' && ! $start && ! $end) {
                    continue;
                }
                StaffHourlyAttendance::create([
                    'staff_id' => $request->staff_id, 'date' => $date, 'start_time' => $start ?: null, 'end_time' => $end ?: null,
                    'task' => $task ?: null, 'attendance' => (int) $code, 'note' => $request->input("notes.$key"), 'created_by' => auth()->id(),
                ]);
            }
        });

        return $this->ok(route('ledger.staff-hourly-attendance.index', ['staff' => $request->staff_id, 'date' => $date]), __('ledger_payroll.updated'));
    }

    /** The month's attendance of every hourly staff member (paxhitestbed's P / A / L / H grid). */
    public function report(Request $request)
    {
        $this->allow('staff-hourly-attendance-report');
        $data = $this->base();
        $data['title'] = __('ledger_payroll.hourly_report');
        $data['selected_department'] = $request->department ?: '0';
        $data['selected_designation'] = $request->designation ?: '0';
        $data['selected_month'] = (int) ($request->month ?: date('m'));
        $data['selected_year'] = (int) ($request->year ?: date('Y'));
        $data['departments'] = Department::where('status', 1)->orderBy('title')->get();
        $data['designations'] = Designation::where('status', 1)->orderBy('title')->get();
        $q = Staff::where('salary_type', Staff::SALARY_HOURLY)->where('status', 1);
        if ($request->filled('department')) {
            $q->where('department_id', $request->department);
        }
        if ($request->filled('designation')) {
            $q->where('designation_id', $request->designation);
        }
        $data['rows'] = $q->orderBy('staff_no')->get();
        $data['attendances'] = StaffHourlyAttendance::whereIn('staff_id', $data['rows']->pluck('id'))
            ->whereYear('date', $data['selected_year'])->whereMonth('date', $data['selected_month'])->get()->groupBy('staff_id');
        $data['days'] = Carbon::createFromDate($data['selected_year'], $data['selected_month'], 1)->daysInMonth;

        return view('ledger.payroll.staff-hourly-attendance.report', $data);
    }

    /** One staff member's month, session by session, with the hours the payroll pays. */
    public function reportDetails(Request $request, $id)
    {
        $this->allow('staff-hourly-attendance-report');
        $data = $this->base();
        $data['title'] = __('ledger_payroll.hourly_report');
        $data['selected_month'] = (int) ($request->month ?: date('m'));
        $data['selected_year'] = (int) ($request->year ?: date('Y'));
        $data['user'] = Staff::where('salary_type', Staff::SALARY_HOURLY)->findOrFail($id);
        $data['attendances'] = StaffHourlyAttendance::where('staff_id', $id)->whereYear('date', $data['selected_year'])
            ->whereMonth('date', $data['selected_month'])->orderBy('date')->orderBy('start_time')->get();
        [$data['hours'], $data['without_times']] = app(\App\Ledger\Payroll\PayrollCalculator::class)
            ->hoursWorked((int) $id, Carbon::createFromDate($data['selected_year'], $data['selected_month'], 1));

        return view('ledger.payroll.staff-hourly-attendance.report-details', $data);
    }

    /** Is the month of $date already paid for this staff member? */
    private function locked(int $staffId, string $date): bool
    {
        $d = Carbon::parse($date);

        return Payroll::where('staff_id', $staffId)->whereYear('salary_month', $d->year)->whereMonth('salary_month', $d->month)->where('status', 1)->exists();
    }
}
