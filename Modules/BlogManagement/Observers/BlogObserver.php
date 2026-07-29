<?php

namespace Modules\BlogManagement\Observers;

use Illuminate\Support\Str;
use Modules\BlogManagement\Entities\Blog;

class BlogObserver
{
    /**
     * Handle the Blog "saving" event.
     */
    public function saving(Blog $blog): void
    {
        if (!$blog->isDirty('title') && !empty($blog->slug)) {
            return;
        }

        $this->syncSlug($blog);
    }

    private function syncSlug(Blog $blog): void
    {
        $baseSlug = Str::slug($blog->title);
        $baseSlug = $baseSlug !== '' ? $baseSlug : 'blog';
        $slug = $baseSlug;

        while ($this->slugExists($slug, $blog->id)) {
            $slug = $baseSlug . '-' . random_int(1000, 9999);
        }

        $blog->slug = $slug;
    }

    private function slugExists(string $slug, ?string $exceptId = null): bool
    {
        return Blog::withTrashed()
            ->where('slug', $slug)
            ->when($exceptId, function ($query) use ($exceptId) {
                $query->where('id', '!=', $exceptId);
            })
            ->exists();
    }
}
