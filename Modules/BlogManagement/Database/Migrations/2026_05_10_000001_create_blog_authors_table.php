<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('blog_authors')) {
            Schema::create('blog_authors', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name')->unique();
                $table->string('slug')->unique();
                $table->timestamps();
            });
        }

        $makeSlug = function (string $name, array &$usedSlugs): string {
            $baseSlug = Str::slug($name);
            $baseSlug = $baseSlug !== '' ? $baseSlug : 'author';
            $slug = $baseSlug;

            while (in_array($slug, $usedSlugs, true)) {
                $slug = $baseSlug . '-' . random_int(1000, 9999);
            }

            $usedSlugs[] = $slug;

            return $slug;
        };

        $writers = collect();

        if (Schema::hasColumn('blogs', 'writer')) {
            $writers = $writers->merge(
                DB::table('blogs')
                    ->whereNotNull('writer')
                    ->where('writer', '!=', '')
                    ->pluck('writer')
            );
        }

        if (Schema::hasColumn('blog_drafts', 'writer')) {
            $writers = $writers->merge(
                DB::table('blog_drafts')
                    ->whereNotNull('writer')
                    ->where('writer', '!=', '')
                    ->pluck('writer')
            );
        }

        $writers = $writers
            ->map(fn ($writer) => trim($writer))
            ->filter()
            ->unique(fn ($writer) => mb_strtolower($writer))
            ->values();

        $usedSlugs = DB::table('blog_authors')->pluck('slug')->all();
        $now = now();

        $authors = $writers->map(function ($writer) use ($makeSlug, &$usedSlugs, $now) {
            return [
                'id' => (string) Str::uuid(),
                'name' => $writer,
                'slug' => $makeSlug($writer, $usedSlugs),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->all();

        if (!empty($authors)) {
            DB::table('blog_authors')->insertOrIgnore($authors);
        }

        if (!Schema::hasColumn('blogs', 'blog_author_id')) {
            Schema::table('blogs', function (Blueprint $table) {
                $table->foreignUuid('blog_author_id')->nullable()->after('blog_category_id')->constrained('blog_authors')->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('blog_drafts', 'blog_author_id')) {
            Schema::table('blog_drafts', function (Blueprint $table) {
                $table->foreignUuid('blog_author_id')->nullable()->after('blog_category_id')->constrained('blog_authors')->nullOnDelete();
            });
        }

        if (Schema::hasColumn('blogs', 'writer')) {
            DB::table('blogs')
                ->join('blog_authors', 'blogs.writer', '=', 'blog_authors.name')
                ->whereNull('blogs.blog_author_id')
                ->update(['blogs.blog_author_id' => DB::raw('blog_authors.id')]);

            Schema::table('blogs', function (Blueprint $table) {
                $table->dropColumn('writer');
            });
        }

        if (Schema::hasColumn('blog_drafts', 'writer')) {
            DB::table('blog_drafts')
                ->join('blog_authors', 'blog_drafts.writer', '=', 'blog_authors.name')
                ->whereNull('blog_drafts.blog_author_id')
                ->update(['blog_drafts.blog_author_id' => DB::raw('blog_authors.id')]);

            Schema::table('blog_drafts', function (Blueprint $table) {
                $table->dropColumn('writer');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('blogs', 'blog_author_id') && !Schema::hasColumn('blogs', 'writer')) {
            Schema::table('blogs', function (Blueprint $table) {
                $table->string('writer')->nullable()->after('blog_author_id');
            });

            DB::table('blogs')
                ->join('blog_authors', 'blogs.blog_author_id', '=', 'blog_authors.id')
                ->whereNull('blogs.writer')
                ->update(['blogs.writer' => DB::raw('blog_authors.name')]);
        }

        if (Schema::hasColumn('blog_drafts', 'blog_author_id') && !Schema::hasColumn('blog_drafts', 'writer')) {
            Schema::table('blog_drafts', function (Blueprint $table) {
                $table->string('writer')->nullable()->after('blog_author_id');
            });

            DB::table('blog_drafts')
                ->join('blog_authors', 'blog_drafts.blog_author_id', '=', 'blog_authors.id')
                ->whereNull('blog_drafts.writer')
                ->update(['blog_drafts.writer' => DB::raw('blog_authors.name')]);
        }

        if (Schema::hasColumn('blogs', 'blog_author_id')) {
            Schema::table('blogs', function (Blueprint $table) {
                $table->dropConstrainedForeignId('blog_author_id');
            });
        }

        if (Schema::hasColumn('blog_drafts', 'blog_author_id')) {
            Schema::table('blog_drafts', function (Blueprint $table) {
                $table->dropConstrainedForeignId('blog_author_id');
            });
        }

        Schema::dropIfExists('blog_authors');
    }
};
