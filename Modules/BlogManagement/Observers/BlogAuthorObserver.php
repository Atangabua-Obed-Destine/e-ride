<?php

namespace Modules\BlogManagement\Observers;

use Illuminate\Support\Str;
use Modules\BlogManagement\Entities\BlogAuthor;

class BlogAuthorObserver
{
    /**
     * Handle the BlogAuthor "saving" event.
     */
    public function saving(BlogAuthor $blogAuthor): void
    {
        if (!$blogAuthor->isDirty('name') && !empty($blogAuthor->slug)) {
            return;
        }

        $this->syncSlug($blogAuthor);
    }

    private function syncSlug(BlogAuthor $blogAuthor): void
    {
        $baseSlug = Str::slug($blogAuthor->name);
        $baseSlug = $baseSlug !== '' ? $baseSlug : 'author';
        $slug = $baseSlug;

        while ($this->slugExists($slug, $blogAuthor->id)) {
            $slug = $baseSlug . '-' . random_int(1000, 9999);
        }

        $blogAuthor->slug = $slug;
    }

    private function slugExists(string $slug, ?string $exceptId = null): bool
    {
        return BlogAuthor::query()
            ->where('slug', $slug)
            ->when($exceptId, function ($query) use ($exceptId) {
                $query->where('id', '!=', $exceptId);
            })
            ->exists();
    }
}
