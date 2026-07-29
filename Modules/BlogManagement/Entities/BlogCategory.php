<?php

namespace Modules\BlogManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BlogCategory extends Model
{
    use HasFactory, HasUuid;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'slug', 'name', 'status', 'click_count'
    ];

}
