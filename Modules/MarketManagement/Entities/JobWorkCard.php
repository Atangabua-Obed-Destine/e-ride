<?php

namespace Modules\MarketManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Modules\UserManagement\Entities\User;

/** A job seeker's Work Card: made once, sent with every application. No phone number on it. */
class JobWorkCard extends Model
{
    use HasUuid;

    public const MAX_SKILLS = 10;
    public const MAX_EXPERIENCE = 6;
    public const MAX_LINKS = 5;
    /** Professional links people add, and employers can require. */
    public const LINK_TYPES = ['linkedin', 'github', 'portfolio', 'website', 'other'];
    /** The voice intro: up to 30 seconds (a little slack for the recorder). */
    public const MAX_VOICE_SECONDS = 35;
    /** On the private disk, like the CV: opened through a signed link. */
    public const VOICE_DIR = 'jobs/voice/';
    /** On the private disk (storage/app): opened through a signed link that lasts 30 minutes. */
    public const CV_DIR = 'jobs/cv/';

    protected $fillable = [
        'user_id', 'name', 'area_label', 'languages', 'headline', 'skills', 'experience', 'education', 'looking_for', 'cv_file', 'cv_name',
        'voice_file', 'voice_seconds', 'links',
    ];

    protected $casts = [
        'skills' => 'array',
        'experience' => 'array',
        'looking_for' => 'array',
        'links' => 'array',
        'voice_seconds' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** A link to the CV that works for 30 minutes. Give it only to the owner and to employers they applied to. */
    public function cvUrl(): ?string
    {
        return $this->cv_file ? URL::temporarySignedRoute('jobs.cv', now()->addMinutes(30), ['card' => $this->id]) : null;
    }

    /** A link to the voice intro that works for 30 minutes (owner and employers they applied to). */
    public function voiceUrl(): ?string
    {
        return $this->voice_file ? URL::temporarySignedRoute('jobs.voice', now()->addMinutes(30), ['card' => $this->id]) : null;
    }

    public function voicePath(): ?string
    {
        return $this->voice_file ? Storage::disk('local')->path(self::VOICE_DIR . $this->voice_file) : null;
    }

    /** @return string[] the link types on the card */
    public function linkTypes(): array
    {
        return array_values(array_unique(array_map(fn($l) => (string)($l['type'] ?? ''), (array)($this->links ?? []))));
    }

    public function cvPath(): ?string
    {
        return $this->cv_file ? Storage::disk('local')->path(self::CV_DIR . $this->cv_file) : null;
    }

    /** 0 to 100: name, area, skills, experience, education, CV, voice intro, links, what they look for. */
    public function percent(): int
    {
        $parts = [
            trim((string)$this->name) !== '' ? 15 : 0,
            trim((string)$this->area_label) !== '' ? 10 : 0,
            count((array)$this->skills) > 0 ? 20 : 0,
            count((array)$this->experience) > 0 ? 20 : 0,
            trim((string)$this->education) !== '' ? 5 : 0,
            $this->cv_file ? 10 : 0,
            $this->voice_file ? 5 : 0,
            count((array)$this->links) > 0 ? 5 : 0,
            count((array)$this->looking_for) > 0 ? 10 : 0,
        ];
        return array_sum($parts);
    }

    /** Enough to apply: a name, and skills or experience. */
    public function canApply(): bool
    {
        return trim((string)$this->name) !== '' && (count((array)$this->skills) > 0 || count((array)$this->experience) > 0);
    }
}
