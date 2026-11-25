<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\Auditable;
use App\Ledger\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

/** paxhitestbed Department (Human Resource), per set of books: a simple list the staff register reads. */
class Department extends Model
{
    use Auditable, BelongsToBusiness;

    protected $table = 'departments';

    protected $fillable = ['business_id', 'title', 'slug', 'description', 'status'];

    protected $casts = ['status' => 'boolean'];

    public function staff()
    {
        return $this->hasMany(Staff::class, 'department_id');
    }

    public function getAuditDescription($event)
    {
        return "Department {$event}: {$this->title}, Status: ".($this->status ? 'Active' : 'Inactive');
    }
}
