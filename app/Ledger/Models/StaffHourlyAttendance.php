<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\BelongsToBusiness;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * paxhitestbed StaffHourlyAttendance: one session of work by a staff member paid by the hour. E-Ride
 * has no timetable, so "task" says what the session was (paxhitestbed's subject / class). Codes:
 * 1 present, 2 absent, 3 leave, 4 holiday. The payroll pays the hours of present sessions that have
 * both times (PayrollCalculator::hoursWorked()).
 */
class StaffHourlyAttendance extends Model
{
    use BelongsToBusiness;

    protected $table = 'staff_hourly_attendances';

    public const PRESENT = 1;
    public const ABSENT = 2;
    public const LEAVE = 3;
    public const HOLIDAY = 4;

    public const CODES = [self::PRESENT => 'present', self::ABSENT => 'absent', self::LEAVE => 'leave', self::HOLIDAY => 'holiday'];

    protected $fillable = ['business_id', 'staff_id', 'date', 'start_time', 'end_time', 'task', 'attendance', 'note', 'created_by', 'updated_by'];

    protected $casts = ['date' => 'date', 'attendance' => 'integer'];

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    /** Hours of this session (null when a time is missing or the end is not after the start). */
    public function hours(): ?float
    {
        if (! $this->start_time || ! $this->end_time) {
            return null;
        }
        $start = Carbon::parse($this->start_time);
        $end = Carbon::parse($this->end_time);
        if ($end->lessThanOrEqualTo($start)) {
            return null;
        }

        return round($start->diffInMinutes($end) / 60, 2);
    }
}
