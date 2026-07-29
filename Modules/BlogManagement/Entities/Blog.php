<?php

namespace Modules\BlogManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;


class Blog extends Model
{
    use HasFactory, SoftDeletes, HasUuid;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'blog_category_id', 'blog_author_id', 'title', 'description', 'thumbnail', 'status', 'click_count', 'meta_title', 'meta_description', 'meta_image', 'published_at', 'status', 'is_published', 'is_drafted'
    ];

    protected $casts = [
        'published_at' => 'date',
    ];

    public function draft()
    {
        return $this->hasOne(BlogDraft::class);
    }

    public function category()
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id', 'id');
    }

    public function author()
    {
        return $this->belongsTo(BlogAuthor::class, 'blog_author_id', 'id');
    }
}
