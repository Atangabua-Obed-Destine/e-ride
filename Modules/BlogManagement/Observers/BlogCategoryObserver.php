<?php

namespace Modules\BlogManagement\Observers;

use Illuminate\Support\Str;
use Modules\BlogManagement\Entities\BlogCategory;

class BlogCategoryObserver
{
    /**
     * Handle the BlogCategory "saving" event.
     */
    public function saving(BlogCategory $blogCategory): void
    {
        if (! $blogCategory->isDirty('name') && ! empty($blogCategory->slug)) {
            return;
        }

        $this->syncSlug($blogCategory);
    }

    private function syncSlug(BlogCategory $blogCategory): void
    {
        $baseSlug = Str::slug($blogCategory->name);
        $baseSlug = $baseSlug !== '' ? $baseSlug : 'category';
        $slug = $baseSlug;

        while ($this->slugExists($slug, $blogCategory->id)) {
            $slug = $baseSlug . '-' . random_int(1000, 9999);
        }

        $blogCategory->slug = $slug;
    }

    private function slugExists(string $slug, ?string $exceptId = null): bool
    {
        return BlogCategory::query()
            ->where('slug', $slug)
            ->when($exceptId, function ($query) use ($exceptId) {
                $query->where('id', '!=', $exceptId);
            })
            ->exists();
    }
}
