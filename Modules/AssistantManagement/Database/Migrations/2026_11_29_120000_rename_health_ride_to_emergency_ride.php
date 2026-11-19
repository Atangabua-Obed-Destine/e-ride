<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Health ride" is now called "Emergency ride" ("course d'urgence" in French):
 * Ask E-Ride's answers and the "Did you know?" cards are updated to match. The
 * search words keep the old name too, so people who type it still find it.
 */
return new class extends Migration {
    private const EN = [
        '/\bHEALTH RIDES\b/' => 'EMERGENCY RIDES', '/\bHEALTH RIDE\b/' => 'EMERGENCY RIDE',
        '/\bHealth [Rr]ides\b/' => 'Emergency rides', '/\bHealth [Rr]ide\b/' => 'Emergency ride',
        '/\bhealth rides\b/' => 'emergency rides', '/\bhealth ride\b/' => 'emergency ride',
        '/\b([Aa]) emergency\b/' => '$1n emergency',
    ];
    private const FR = [
        '/\bCourses santé\b/u' => "Courses d'urgence", '/\bcourses santé\b/u' => "courses d'urgence",
        '/\bCourse santé\b/u' => "Course d'urgence", '/\bcourse santé\b/u' => "course d'urgence",
        '/\bcourse\(s\) santé\b/u' => "course(s) d'urgence",
    ];

    public function up(): void
    {
        if (!Schema::hasTable('assistant_entries')) {
            return;
        }
        $columns = array_values(array_filter(
            ['title_en', 'body_en', 'tip_label_en', 'title_fr', 'body_fr', 'tip_label_fr', 'search_words'],
            fn($c) => Schema::hasColumn('assistant_entries', $c)
        ));
        DB::table('assistant_entries')->orderBy('id')->get(array_merge(['id'], $columns))->each(function ($row) use ($columns) {
            $changes = [];
            foreach ($columns as $c) {
                $value = $row->$c;
                if (!is_string($value) || $value === '') {
                    continue;
                }
                if ($c === 'search_words') {
                    if (preg_match('/health ride|course sant/i', $value) && !str_contains(strtolower($value), 'emergency ride')) {
                        $changes[$c] = rtrim($value, " ,\n") . ", emergency ride, course d'urgence";
                    }
                    continue;
                }
                $new = preg_replace(array_keys(self::EN), array_values(self::EN), $value);
                if (str_ends_with($c, '_fr')) {
                    $new = preg_replace(array_keys(self::FR), array_values(self::FR), $new);
                }
                if ($new !== $value) {
                    $changes[$c] = $new;
                }
            }
            if ($changes) {
                DB::table('assistant_entries')->where('id', $row->id)->update($changes + ['updated_at' => now()]);
            }
        });
    }

    public function down(): void
    {
        // Wording only: nothing to undo.
    }
};
