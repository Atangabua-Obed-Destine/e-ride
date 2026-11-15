<?php

namespace Modules\MarketManagement\Transformers;

use Modules\MarketManagement\Entities\JobApplication;
use Modules\MarketManagement\Entities\JobEmployer;
use Modules\MarketManagement\Entities\JobPost;
use Modules\MarketManagement\Entities\JobSave;
use Modules\MarketManagement\Entities\JobWorkCard;
use Modules\MarketManagement\Service\JobService;

/** E-Ride Jobs as the app sees it. Phones and CVs only reach the people allowed to see them. */
class JobPresenter
{
    public static function employer(?JobEmployer $e, bool $mine = false): ?array
    {
        if (!$e) {
            return null;
        }
        $out = [
            'id' => $e->id,
            'name' => $e->name,
            'kind' => $e->kind,
            'logo' => $e->logoUrl(),
            'verified' => $e->isVerified(),
            'area' => $e->area_label,
            'about' => $e->about,
            'since' => $e->verified_at?->toIso8601String(),
        ];
        if ($mine) {
            $out += [
                'status' => $e->status,
                'review_note' => $e->review_note,
                'doc_type' => $e->doc_type,
                'has_doc' => (bool)$e->doc_file,
                'lat' => $e->lat,
                'lng' => $e->lng,
                'address' => $e->address,
            ];
        }
        return $out;
    }

    /**
     * @param string|null $viewer the user looking
     * @param JobApplication|null $application the viewer's own application, if any
     */
    public static function job(JobPost $job, ?string $viewer, bool $full = false, ?JobApplication $application = null): array
    {
        $jobs = app(JobService::class);
        $mine = $viewer !== null && $job->employer?->user_id === $viewer;
        $out = [
            'code' => $job->code,
            'title' => $job->title,
            'category' => $job->category,
            'type' => $job->type,
            'pay_min' => $job->pay_min,
            'pay_max' => $job->pay_max,
            'pay_period' => $job->pay_period,
            'schedule' => $job->schedule,
            'places' => $job->places,
            'area' => $job->area_label,
            'status' => $job->status,
            'live' => $job->isLive(),
            'live_at' => $job->live_at?->toIso8601String(),
            'closes_at' => $job->closes_at?->toIso8601String(),
            'applications' => $job->applications_count,
            'employer' => self::employer($job->employer),
            'mine' => $mine,
            'link' => $jobs->link($job),
        ];
        if ($full) {
            $out += [
                'description' => $job->description,
                'requirements' => $job->requirements,
                'skills' => array_values((array)($job->skills ?? [])),
                'questions' => $job->questionList(),
                'lat' => $job->lat,
                'lng' => $job->lng,
                'address' => $job->address,
                'saved' => $viewer !== null && JobSave::where('user_id', $viewer)->where('job_id', $job->id)->exists(),
                'my_application' => $application && $application->status !== JobApplication::WITHDRAWN
                    ? ['id' => $application->id, 'status' => $application->status] : null,
            ];
        }
        if ($mine) {
            $out += [
                'views' => $job->views,
                'alerted' => $job->alerted,
                'review_note' => $job->review_note,
                'submitted_at' => $job->submitted_at?->toIso8601String(),
                'new_applications' => $job->relationLoaded('applications')
                    ? $job->applications->where('status', JobApplication::SENT)->count()
                    : JobApplication::where('job_id', $job->id)->where('status', JobApplication::SENT)->count(),
            ];
        }
        return $out;
    }

    /** The Work Card. $cv: include the CV link (the owner, or an employer the person applied to). */
    public static function card(?JobWorkCard $card, bool $cv = true): ?array
    {
        if (!$card) {
            return null;
        }
        return [
            'name' => $card->name,
            'area' => $card->area_label,
            'languages' => $card->languages,
            'headline' => $card->headline,
            'skills' => array_values((array)($card->skills ?? [])),
            'experience' => array_values((array)($card->experience ?? [])),
            'education' => $card->education,
            'looking_for' => array_values((array)($card->looking_for ?? [])),
            'cv_url' => $cv ? $card->cvUrl() : null,
            'cv_name' => $card->cv_name,
            'has_cv' => (bool)$card->cv_file,
            'percent' => $card->percent(),
            'can_apply' => $card->canApply(),
        ];
    }

    /** One application for the person who sent it. */
    public static function forApplicant(JobApplication $a): array
    {
        return [
            'id' => $a->id,
            'status' => $a->status,
            'job' => $a->job ? self::job($a->job, $a->user_id) : null,
            'answers' => array_values((array)($a->answers ?? [])),
            'questions' => $a->job?->questionList() ?? [],
            'message' => $a->message,
            'share_phone' => $a->share_phone,
            'employer_note' => $a->employer_note,
            'next_step' => $a->next_step,
            'next_step_at' => $a->next_step_at?->toIso8601String(),
            'next_step_place' => $a->next_step_place,
            'next_step_sent_at' => $a->next_step_sent_at?->toIso8601String(),
            'next_step_unread' => $a->status === JobApplication::NEXT_STEP && !$a->next_step_read_at,
            'closed_reason' => $a->closed_reason,
            'sent_at' => $a->created_at?->toIso8601String(),
            'seen_at' => $a->seen_at?->toIso8601String(),
            'shortlisted_at' => $a->shortlisted_at?->toIso8601String(),
            'updated_at' => $a->updated_at?->toIso8601String(),
        ];
    }

    /** One applicant for the employer: Work Card, answers, fit; the phone only if they chose to share it. */
    public static function forEmployer(JobApplication $a, bool $full = false): array
    {
        $card = $a->relationLoaded('card') ? $a->card : JobWorkCard::where('user_id', $a->user_id)->first();
        $out = [
            'id' => $a->id,
            'status' => $a->status,
            'name' => $card?->name ?? trim(($a->user?->first_name ?? '') . ' ' . ($a->user?->last_name ?? '')),
            'area' => $card?->area_label,
            'headline' => $card?->headline ?: (($card?->experience[0]['title'] ?? null)),
            'skills' => array_values((array)($card?->skills ?? [])),
            'answers' => array_values((array)($a->answers ?? [])),
            'fit' => $a->fit ?? null,
            'has_cv' => (bool)$card?->cv_file,
            'has_message' => (bool)$a->message,
            'next_step_read' => $a->status === JobApplication::NEXT_STEP ? (bool)$a->next_step_read_at : null,
            'sent_at' => $a->created_at?->toIso8601String(),
        ];
        if ($full) {
            $out += [
                'card' => self::card($card, true),
                'questions' => $a->job?->questionList() ?? [],
                'message' => $a->message,
                'phone' => $a->share_phone ? $a->user?->phone : null,
                'employer_note' => $a->employer_note,
                'next_step' => $a->next_step,
                'next_step_at' => $a->next_step_at?->toIso8601String(),
                'next_step_place' => $a->next_step_place,
                'next_step_sent_at' => $a->next_step_sent_at?->toIso8601String(),
                'job' => $a->job ? ['code' => $a->job->code, 'title' => $a->job->title] : null,
            ];
        }
        return $out;
    }
}
