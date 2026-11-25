<?php

namespace Modules\BusManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

/** Someone the books pay each month (driver, loader, counter agent...). */
class BusAccEmployee extends Model
{
    use HasUuid;

    public const JOBS = ['driver', 'loader', 'counter', 'manager', 'other'];

    protected $table = 'bus_acc_employees';

    protected $fillable = ['agency_id', 'name', 'job', 'user_id', 'cnps_number', 'niu', 'phone', 'base_pay', 'hired_on', 'active'];

    protected $casts = ['base_pay' => 'float', 'hired_on' => 'date', 'active' => 'boolean'];

    public function jobLabel(): string
    {
        return translate(match ($this->job) {
            'driver' => 'Driver',
            'loader' => 'Loader',
            'counter' => 'Counter agent',
            'manager' => 'Manager',
            default => 'Other',
        });
    }
}
