<?php

namespace Modules\BlogManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BlogDraft extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'blog_id', 'blog_category_id', 'blog_author_id', 'title', 'description', 'thumbnail', 'published_at',
    ];

    protected $casts = [
        'published_at' => 'date',
    ];

    public function blog()
    {
        return $this->belongsTo(Blog::class);
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
