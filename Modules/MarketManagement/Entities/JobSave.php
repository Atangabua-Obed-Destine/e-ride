<?php

namespace Modules\MarketManagement\Entities;

use Illuminate\Database\Eloquent\Model;

/** A job seeker keeps a job to look at later. */
class JobSave extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'job_id'];
}
