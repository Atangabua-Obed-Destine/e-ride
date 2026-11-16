<?php

namespace Modules\MarketManagement\Http\Controllers\Web;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\MarketManagement\Entities\JobPost;
use Modules\MarketManagement\Entities\JobWorkCard;
use Modules\MarketManagement\Lib\JobSettings;
use Modules\MarketManagement\Lib\MarketLinks;
use Modules\MarketManagement\Service\JobService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The job page people open from WhatsApp ([domain]/j/<code>), the "get the app" page,
 * and the CV behind a signed link (owner and employers they applied to only).
 */
class JobPageController extends MarketPageController
{
    public function job(Request $request, string $code): Response
    {
        $t = $this->jobWords($request);
        $job = JobPost::with('employer')->where('code', $code)->first();
        if (!$job || !$job->isPublic() || !JobSettings::enabled()) {
            return $this->unavailable($t, $t['job_gone'], null, 404);
        }
        $jobs = app(JobService::class);
        $jobs->countView($job, null);
        return response()->view('marketmanagement::web.job', [
            't' => $t,
            'job' => $job,
            'employer' => $job->employer,
            'pay' => $jobs->payText($job),
            'url' => $jobs->link($job),
            'openApp' => MarketLinks::openInApp('/j/' . $job->code, route('jobs.get-app', ['code' => $job->code])),
        ]);
    }

    public function getApp(Request $request, string $code): Response
    {
        $t = $this->jobWords($request);
        $job = JobPost::with('employer')->where('code', $code)->first();
        if (!$job || !$job->isPublic()) {
            return $this->unavailable($t, $t['job_gone'], null, 404);
        }
        $jobs = app(JobService::class);
        return response()->view('marketmanagement::web.job-app', [
            't' => $t,
            'job' => $job,
            'pay' => $jobs->payText($job),
            'jobUrl' => $jobs->link($job),
            'playUrl' => MarketLinks::playStore('j:' . $job->code),
            'openApp' => MarketLinks::openInApp('/j/' . $job->code, MarketLinks::playStore('j:' . $job->code)),
        ])->header('X-Robots-Tag', 'noindex');
    }

    /** The CV, only through a signed link that expires (the route checks the signature). */
    public function cv(Request $request, string $card): BinaryFileResponse|Response
    {
        $card = JobWorkCard::find($card);
        $path = $card?->cvPath();
        if (!$path || !is_file($path)) {
            abort(404);
        }
        return response()->file($path, [
            'Content-Disposition' => 'inline; filename="' . preg_replace('/[^A-Za-z0-9._ -]/', '', $card->cv_name ?: 'cv') . '"',
            'X-Robots-Tag' => 'noindex',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** The voice intro, only through a signed link that expires. */
    public function voice(Request $request, string $card): BinaryFileResponse|Response
    {
        $card = JobWorkCard::find($card);
        $path = $card?->voicePath();
        if (!$path || !is_file($path)) {
            abort(404);
        }
        return response()->file($path, ['X-Robots-Tag' => 'noindex', 'Cache-Control' => 'private, no-store']);
    }

    private function jobWords(Request $request): array
    {
        $t = $this->words($request);
        $fr = $t['lang'] === 'fr';
        $en = [
            'market' => 'E-Ride Jobs',
            'job_gone' => 'This job is no longer on E-Ride Jobs.',
            'verified_employer' => 'Verified by E-Ride',
            'private_employer' => 'Private employer (home)',
            'pay' => 'Pay',
            'hours' => 'Hours',
            'places' => ':n places',
            'one_place' => '1 place',
            'the_job' => 'The job',
            'you_need' => 'You need',
            'where' => 'Where',
            'closed' => 'This job is closed. More jobs are in the app.',
            'no_fee' => 'Applying is free. E-Ride checks every job. Never pay anyone to get a job: report it in the app.',
            'apply' => 'Apply in the E-Ride app',
            'apply_note' => 'Free. Make your Work Card once, apply to any job in one tap, and follow your application.',
            'type_full_time' => 'Full-time', 'type_part_time' => 'Part-time', 'type_day_job' => 'Day job', 'type_internship' => 'Internship',
            'get_title_job' => 'Apply to :job in the E-Ride app',
            'get_body_job' => 'Install E-Ride, sign in with your phone number, and this job opens by itself. Make your Work Card once and apply in one tap.',
            'get_also_rides' => 'Rides in Bamenda, to get to work on time',
            'get_also_alerts' => 'Alerts when a verified job fits you',
            'get_have_job' => 'I already have E-Ride: open the job',
            'back_job' => 'Back to the job',
        ];
        $frWords = [
            'market' => 'E-Ride Emplois',
            'job_gone' => 'Cette offre n’est plus sur E-Ride Emplois.',
            'verified_employer' => 'Vérifié par E-Ride',
            'private_employer' => 'Employeur particulier (maison)',
            'pay' => 'Salaire',
            'hours' => 'Horaires',
            'places' => ':n postes',
            'one_place' => '1 poste',
            'the_job' => 'Le travail',
            'you_need' => 'Il faut',
            'where' => 'Où',
            'closed' => 'Cette offre est fermée. D’autres offres sont dans l’app.',
            'no_fee' => 'Postuler est gratuit. E-Ride vérifie chaque offre. Ne payez jamais personne pour un emploi : signalez-le dans l’app.',
            'apply' => 'Postuler dans l’app E-Ride',
            'apply_note' => 'Gratuit. Créez votre Carte Travail une fois, postulez à toute offre en un geste, et suivez votre candidature.',
            'type_full_time' => 'Temps plein', 'type_part_time' => 'Temps partiel', 'type_day_job' => 'À la journée', 'type_internship' => 'Stage',
            'get_title_job' => 'Postulez à :job dans l’app E-Ride',
            'get_body_job' => 'Installez E-Ride, connectez-vous avec votre numéro, et cette offre s’ouvre toute seule. Créez votre Carte Travail une fois et postulez en un geste.',
            'get_also_rides' => 'Des trajets dans Bamenda, pour arriver au travail à l’heure',
            'get_also_alerts' => 'Des alertes quand une offre vérifiée vous correspond',
            'get_have_job' => 'J’ai déjà E-Ride : ouvrir l’offre',
            'back_job' => 'Retour à l’offre',
        ];
        return array_merge($t, $en, $fr ? $frWords : []);
    }
}
