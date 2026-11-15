<?php

namespace Modules\MarketManagement\Lib;

/**
 * E-Ride Jobs switches and numbers (admin E-Ride Jobs > Settings), with defaults. Off until an admin turns it on.
 */
class JobSettings
{
    public const TYPE = 'job_settings';

    /**
     * Words that flag a job for the reviewer: asking applicants for money is the classic job scam.
     * Checked without accents or case, as whole words. A flag does not block: E-Ride checks every job anyway.
     */
    public const DEFAULT_FEE_WORDS = "registration fee\napplication fee\nfile fee\nprocessing fee\ntraining fee\nuniform fee\nmedical fee\ndeposit\npay to apply\npay before\nsend money\nwestern union\nfrais de dossier\nfrais d inscription\nfrais de formation\nfrais de visite medicale\ncaution\npayer avant\nenvoyer de l argent\ndepot de garantie";

    public static function all(): array
    {
        $get = fn(string $key, $default) => businessConfig($key, self::TYPE)?->value ?? $default;
        return [
            'enabled' => (bool)(int)$get('jobs_enabled', 0),
            // Days a job stays open before it closes by itself.
            'job_days' => min(90, max(3, (int)$get('jobs_job_days', 30))),
            // Jobs one employer can have in review or live at once.
            'max_open_jobs' => min(100, max(1, (int)$get('jobs_max_open_jobs', 10))),
            // An application nobody answered closes with a kind note after this many days.
            'stale_days' => min(90, max(3, (int)$get('jobs_stale_days', 21))),
            // Job alerts one person can get in a day.
            'alerts_per_day' => min(10, max(1, (int)$get('jobs_alerts_per_day', 2))),
            // Monthly pay under this is flagged for the reviewer (Cameroon minimum wage, FCFA).
            'min_month_pay' => min(10000000, max(0, (int)$get('jobs_min_month_pay', 41875))),
            // The reviewer's target, shown to employers ("usually within 2 hours").
            'review_hours' => min(72, max(1, (int)$get('jobs_review_hours', 2))),
            // Distinct reports before a live job is taken down until an admin decides.
            'hide_after_reports' => min(20, max(1, (int)$get('jobs_hide_after_reports', 3))),
            'fee_words' => (string)$get('jobs_fee_words', self::DEFAULT_FEE_WORDS),
        ];
    }

    public static function enabled(): bool
    {
        return self::all()['enabled'];
    }

    /** @return string[] folded, one per line */
    public static function feeWords(): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', self::all()['fee_words']) as $line) {
            $line = MarketSettings::fold($line);
            if ($line !== '') {
                $out[] = $line;
            }
        }
        return array_values(array_unique($out));
    }

    /** The first phrase of $list found in $text (whole words, accents and case ignored), or null. */
    public static function firstIn(?string $text, array $list): ?string
    {
        $haystack = ' ' . MarketSettings::fold((string)$text) . ' ';
        foreach ($list as $phrase) {
            if (str_contains($haystack, ' ' . $phrase . ' ')) {
                return $phrase;
            }
        }
        return null;
    }
}
