<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\Auditable;
use App\Ledger\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

/** paxhitestbed Designation (Human Resource), per set of books: a simple list the staff register reads. */
class Designation extends Model
{
    use Auditable, BelongsToBusiness;

    protected $table = 'designations';

    protected $fillable = ['business_id', 'title', 'slug', 'description', 'status'];

    protected $casts = ['status' => 'boolean'];

    public function staff()
    {
        return $this->hasMany(Staff::class, 'designation_id');
    }

    public function getAuditDescription($event)
    {
        return "Designation {$event}: {$this->title}, Status: ".($this->status ? 'Active' : 'Inactive');
    }
}
