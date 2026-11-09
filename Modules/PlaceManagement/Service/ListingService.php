<?php

namespace Modules\PlaceManagement\Service;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Modules\PlaceManagement\Entities\Listing;
use Modules\PlaceManagement\Entities\ListingAlert;
use Modules\PlaceManagement\Entities\ListingOwner;
use Modules\PlaceManagement\Entities\ListingPhoto;
use Modules\PlaceManagement\Entities\ListingReport;
use Modules\PlaceManagement\Entities\ListingVerification;
use Modules\PlaceManagement\Entities\ListingVisit;
use Modules\PlaceManagement\Lib\ListingSettings;
use Modules\UserManagement\Entities\User;
use Modules\ZoneManagement\Service\Interfaces\ZoneServiceInterface;
use Throwable;

/**
 * Homes & spaces: everything that changes a listing, a visit, a report or an
 * owner's standing, and the pushes that go with it. Controllers validate the
 * input; the rules of the policy live here.
 */
class ListingService
{
    public function __construct(protected ZoneServiceInterface $zoneService)
    {
    }

    // ------------------------------------------------------------- owners

    /** Null when the user may list, else the reason they cannot. */
    public function cannotListReason(User $user): ?string
    {
        $owner = ListingOwner::find($user->id);
        if ($owner?->blocked) {
            return translate('Your account can no longer list places on E-Ride.');
        }
        if ($owner?->banned_until?->isFuture()) {
            return translate(key: 'You can list again from {date}, after 3 upheld reports.', replace: ['date' => $owner->banned_until->format('d M Y')]);
        }
        return null;
    }

    /** @param UploadedFile[] $photos */
    public function create(User $owner, array $data, array $photos): Listing
    {
        return DB::transaction(function () use ($owner, $data, $photos) {
            $listing = new Listing($this->fields($data));
            $listing->owner_id = $owner->id;
            $listing->status = Listing::PENDING;
            $listing->zone_id = $this->zoneFor($listing->latitude, $listing->longitude);
            $listing->save();
            $this->addPhotos($listing, $photos);
            return $listing->fresh('photos');
        });
    }

    /**
     * An owner's edit. A listing waiting for changes goes back to review; a live
     * one stays live and is marked for admin to look over.
     *
     * @param UploadedFile[] $photos
     * @param int[] $removePhotoIds
     */
    public function update(Listing $listing, array $data, array $photos, array $removePhotoIds): Listing
    {
        return DB::transaction(function () use ($listing, $data, $photos, $removePhotoIds) {
            $listing->fill($this->fields($data));
            $listing->zone_id = $this->zoneFor($listing->latitude, $listing->longitude);
            if (in_array($listing->status, [Listing::CHANGES, Listing::REFUSED], true)) {
                $listing->status = Listing::PENDING;
            } elseif (in_array($listing->status, [Listing::LIVE, Listing::PAUSED], true)) {
                $listing->needs_review = true;
            }
            $listing->save();
            // Owners never remove E-Ride's photos.
            $listing->photos()->where('source', 'owner')->whereIn('id', $removePhotoIds)->get()
                ->each(function (ListingPhoto $photo) {
                    fileRemover(ListingPhoto::DIR, $photo->file);
                    $photo->delete();
                });
            $this->addPhotos($listing, $photos);
            $ownerPhotos = $listing->photos()->where('source', 'owner')->count();
            if ($ownerPhotos < Listing::MIN_PHOTOS && !$listing->photos()->where('source', 'eride')->exists()) {
                throw ValidationException::withMessages(['photos' => translate(key: 'Keep at least {count} photos.', replace: ['count' => Listing::MIN_PHOTOS])]);
            }
            return $listing->fresh('photos');
        });
    }

    private function fields(array $data): array
    {
        $keys = ['type', 'title', 'description', 'neighbourhood', 'address', 'latitude', 'longitude', 'units_available', 'furnished',
            'rent', 'rent_period', 'advance_months', 'caution', 'available_from', 'water', 'power', 'backup_power', 'toilet', 'kitchen',
            'fenced', 'security', 'owner_role'];
        return array_intersect_key($data, array_flip($keys));
    }

    private function zoneFor(float $lat, float $lng): ?string
    {
        return $this->zoneService->getByPoints(new Point($lat, $lng))->where('is_active', 1)->first()?->id;
    }

    /** @param UploadedFile[] $photos */
    private function addPhotos(Listing $listing, array $photos, string $source = 'owner', ?float $lat = null, ?float $lng = null): void
    {
        $count = $listing->photos()->count();
        $sort = (int)$listing->photos()->max('sort');
        foreach ($photos as $upload) {
            if ($count >= Listing::MAX_PHOTOS + ($source === 'eride' ? Listing::MAX_PHOTOS : 0)) {
                break;
            }
            $file = fileUploader(ListingPhoto::DIR, APPLICATION_IMAGE_FORMAT, $upload);
            if ($file) {
                $listing->photos()->create([
                    'file' => $file, 'source' => $source, 'sort' => ++$sort,
                    'latitude' => $lat, 'longitude' => $lng, 'taken_at' => $source === 'eride' ? now() : null,
                ]);
                $count++;
            }
        }
    }

    /** "Still available": the clock restarts, and a paused listing comes back. */
    public function confirm(Listing $listing): Listing
    {
        $listing->last_confirmed_at = now();
        $listing->reminder_stage = 0;
        $listing->taken_check_until = null;
        if ($listing->status === Listing::PAUSED) {
            $listing->status = Listing::LIVE;
            $listing->paused_at = null;
        }
        $listing->save();
        return $listing;
    }

    /**
     * "It's rented": closes the listing. Whoever said "I'm taking it" is told it
     * is theirs (with Move for the day they move in); other open visits are
     * closed and those seekers told.
     */
    public function markRented(Listing $listing): Listing
    {
        $listing->update(['status' => Listing::RENTED, 'rented_at' => now(), 'units_available' => 0]);
        $vars = ['title' => $listing->title, 'area' => $listing->neighbourhood];
        $listing->visits()->where(fn($q) => $q->whereIn('status', ListingVisit::OPEN)->orWhere('taking', 1))->with('seeker')->get()
            ->each(function (ListingVisit $visit) use ($listing, $vars) {
                if ($visit->taking) {
                    $this->push($visit->seeker, 'listing_yours', 'The place is yours', 'The owner confirmed {title} is yours. To move your things in, book an E-Ride Move truck from Home.', $vars, $listing);
                    return;
                }
                $visit->update(['status' => ListingVisit::DECLINED]);
                $this->push($visit->seeker, 'listing_rented', 'This place is rented', '{title} in {area} was rented. Others are waiting in Homes & spaces.', $vars, $listing);
            });
        return $listing;
    }

    public function requestVerification(Listing $listing): ListingVerification
    {
        $open = $listing->verifications()->whereIn('status', [ListingVerification::REQUESTED, ListingVerification::SCHEDULED])->first();
        return $open ?? $listing->verifications()->create(['status' => ListingVerification::REQUESTED]);
    }

    // ------------------------------------------------------------- search

    /**
     * Live listings for seekers: verified and recently confirmed first, then
     * listed, then "not confirmed recently". Small enough to sort in PHP.
     */
    public function search(array $filters, int $offset, int $limit): array
    {
        $query = Listing::with('photos')->where('status', Listing::LIVE)->where('units_available', '>', 0);
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (!empty($filters['neighbourhood'])) {
            $query->where('neighbourhood', $filters['neighbourhood']);
        }
        if (!empty($filters['verified'])) {
            $query->where('verified_until', '>', now());
        }
        if (!empty($filters['water'])) {
            $query->whereNotNull('water')->where('water', '!=', 'none');
        }
        if (!empty($filters['backup_power'])) {
            $query->where('backup_power', 1);
        }
        $items = $query->get();
        if (!empty($filters['max_rent'])) {
            $max = (float)$filters['max_rent'];
            $items = $items->filter(fn(Listing $l) => $l->monthlyRent() <= $max);
        }
        $now = now();
        $items = $items->sortBy([
            fn(Listing $a, Listing $b) => (int)$b->isVerified($now) <=> (int)$a->isVerified($now),
            fn(Listing $a, Listing $b) => (int)$a->isLate($now) <=> (int)$b->isLate($now),
            fn(Listing $a, Listing $b) => ($b->last_confirmed_at?->timestamp ?? 0) <=> ($a->last_confirmed_at?->timestamp ?? 0),
        ])->values();
        return ['total' => $items->count(), 'items' => $items->slice($offset, $limit)->values()];
    }

    // ------------------------------------------------------------- visits

    public function book(Listing $listing, User $seeker, string $date, string $window): ListingVisit
    {
        if ($listing->owner_id === $seeker->id) {
            throw ValidationException::withMessages(['listing' => translate('This is your own listing.')]);
        }
        if ($listing->status !== Listing::LIVE) {
            throw ValidationException::withMessages(['listing' => translate('This place is no longer available.')]);
        }
        if ($listing->visits()->where('seeker_id', $seeker->id)->whereIn('status', ListingVisit::OPEN)->exists()) {
            throw ValidationException::withMessages(['listing' => translate('You already have a visit planned for this place.')]);
        }
        $visit = $listing->visits()->create([
            'seeker_id' => $seeker->id, 'owner_id' => $listing->owner_id,
            'visit_date' => $date, 'time_window' => $window, 'status' => ListingVisit::REQUESTED,
        ]);
        $this->push($listing->owner, 'listing_visit_requested', 'Someone wants to visit', '{name} would like to visit {title} on {date}, {window}. Accept or propose another time.', [
            'name' => $seeker->first_name, 'title' => $listing->title, 'date' => $visit->visit_date->format('d M'), 'window' => translate($window),
        ], $listing);
        return $visit;
    }

    /** The owner's answer: accept, propose another time, decline, or "It's rented". */
    public function ownerRespond(ListingVisit $visit, string $action, ?string $date = null, ?string $window = null): ListingVisit
    {
        $listing = $visit->listing;
        // "It's rented" is always possible on an open listing, e.g. to confirm "I'm taking it".
        if ($action === 'rented') {
            if (!in_array($listing->status, [Listing::LIVE, Listing::PAUSED], true)) {
                throw ValidationException::withMessages(['visit' => translate('This listing is not open.')]);
            }
            $this->markRented($listing);
            return $visit->fresh();
        }
        if (!in_array($visit->status, [ListingVisit::REQUESTED, ListingVisit::PROPOSED], true)) {
            throw ValidationException::withMessages(['visit' => translate('This visit was already answered.')]);
        }
        $vars = ['title' => $listing->title, 'date' => $visit->visit_date->format('d M'), 'window' => translate($visit->time_window)];
        switch ($action) {
            case 'accept':
                $visit->update(['status' => ListingVisit::ACCEPTED]);
                $this->push($visit->seeker, 'listing_visit_accepted', 'Visit confirmed', 'Your visit to {title} on {date}, {window} is confirmed. You can now call the owner from My visits.', $vars, $listing);
                break;
            case 'propose':
                $visit->update(['status' => ListingVisit::PROPOSED, 'proposed_date' => $date, 'proposed_window' => $window]);
                $this->push($visit->seeker, 'listing_visit_proposed', 'Another time proposed', 'The owner of {title} proposes {date}, {window}. Accept it in My visits.', [
                    'title' => $listing->title, 'date' => Carbon::parse($date)->format('d M'), 'window' => translate($window),
                ], $listing);
                break;
            case 'decline':
                $visit->update(['status' => ListingVisit::DECLINED]);
                $this->push($visit->seeker, 'listing_visit_declined', 'Visit not possible', 'The owner of {title} cannot receive you then. You can ask for another day.', $vars, $listing);
                break;
        }
        return $visit->fresh();
    }

    /** The seeker's answer to a proposed time, or a cancellation. */
    public function seekerRespond(ListingVisit $visit, string $action): ListingVisit
    {
        $listing = $visit->listing;
        if ($action === 'accept_proposal') {
            if ($visit->status !== ListingVisit::PROPOSED) {
                throw ValidationException::withMessages(['visit' => translate('There is no new time to accept.')]);
            }
            $visit->update([
                'status' => ListingVisit::ACCEPTED, 'visit_date' => $visit->proposed_date, 'time_window' => $visit->proposed_window,
                'proposed_date' => null, 'proposed_window' => null,
            ]);
            $this->push($visit->owner, 'listing_visit_accepted', 'Visit confirmed', '{name} will visit {title} on {date}, {window}. Their number is in My listings.', [
                'name' => $visit->seeker?->first_name, 'title' => $listing->title, 'date' => $visit->visit_date->format('d M'), 'window' => translate($visit->time_window),
            ], $listing);
        } elseif ($action === 'cancel') {
            if (!in_array($visit->status, ListingVisit::OPEN, true)) {
                throw ValidationException::withMessages(['visit' => translate('This visit is already closed.')]);
            }
            $visit->update(['status' => ListingVisit::CANCELLED]);
            $this->push($visit->owner, 'listing_visit_cancelled', 'Visit cancelled', '{name} cancelled the visit to {title}.', [
                'name' => $visit->seeker?->first_name, 'title' => $listing->title,
            ], $listing);
        }
        return $visit->fresh();
    }

    /**
     * How the visit went. Anything but "as described" is a report for admin,
     * and "already taken" counts towards pausing the listing.
     */
    public function outcome(ListingVisit $visit, string $outcome, ?string $note): ListingVisit
    {
        if (!in_array($visit->status, [ListingVisit::ACCEPTED, ListingVisit::DONE], true) || $visit->outcome) {
            throw ValidationException::withMessages(['visit' => translate('This visit cannot be rated.')]);
        }
        $visit->update(['status' => ListingVisit::DONE, 'outcome' => $outcome, 'outcome_note' => $note]);
        if ($outcome !== 'as_described') {
            $this->report($visit->listing, $visit->seeker, $outcome, $note, $visit);
        }
        return $visit->fresh();
    }

    /** "I'm taking it": the owner is asked to confirm, which marks it rented. */
    public function taking(ListingVisit $visit): ListingVisit
    {
        if (!in_array($visit->status, [ListingVisit::ACCEPTED, ListingVisit::DONE], true)) {
            throw ValidationException::withMessages(['visit' => translate('Visit the place first.')]);
        }
        $visit->update(['taking' => true]);
        $this->push($visit->owner, 'listing_taking', 'Someone is taking your place', '{name} wants to take {title}. Confirm in My listings to mark it rented.', [
            'name' => $visit->seeker?->first_name, 'title' => $visit->listing->title,
        ], $visit->listing);
        return $visit->fresh();
    }

    // ------------------------------------------------------------- reports

    public function report(Listing $listing, User $reporter, string $reason, ?string $note, ?ListingVisit $visit = null): ListingReport
    {
        $report = $listing->reports()->create([
            'reporter_id' => $reporter->id, 'visit_id' => $visit?->id, 'reason' => $reason, 'note' => $note, 'status' => ListingReport::OPEN,
        ]);
        // Enough people say it is gone: the owner must confirm soon, or it is paused.
        if ($reason === 'taken' && $listing->status === Listing::LIVE && !$listing->taken_check_until) {
            $settings = ListingSettings::all();
            $reporters = $listing->reports()->where('reason', 'taken')->where('status', ListingReport::OPEN)
                ->where('created_at', '>=', now()->subDays(30))->distinct()->count('reporter_id');
            if ($reporters >= $settings['taken_reports']) {
                $listing->update(['taken_check_until' => now()->addHours($settings['taken_hours'])]);
                $this->push($listing->owner, 'listing_check', 'Please confirm it is still available', 'Seekers say {title} is already taken. Confirm within {hours} hours, or it will be hidden.', [
                    'title' => $listing->title, 'hours' => $settings['taken_hours'],
                ], $listing);
            }
        }
        return $report;
    }

    /** Admin's decision: a strike, a block for a scam, or nothing. */
    public function decideReport(ListingReport $report, string $decision, User $admin): ListingReport
    {
        $report->update(['status' => $decision, 'handled_by' => $admin->id, 'handled_at' => now()]);
        $listing = $report->listing;
        $ownerId = $listing?->owner_id;
        if (!$ownerId) {
            return $report;
        }
        if ($decision === ListingReport::BLOCKED) {
            $this->remove($listing, translate('Removed after a report was confirmed.'), true);
        } elseif ($decision === ListingReport::UPHELD) {
            $settings = ListingSettings::all();
            $strikes = ListingReport::whereIn('status', [ListingReport::UPHELD])
                ->whereHas('listing', fn($q) => $q->withTrashed()->where('owner_id', $ownerId))
                ->where('handled_at', '>=', now()->subMonths($settings['strike_months']))->count();
            $owner = User::find($ownerId);
            if ($strikes >= $settings['strike_limit']) {
                ListingOwner::updateOrCreate(['owner_id' => $ownerId], ['banned_until' => now()->addDays($settings['ban_days'])]);
                Listing::where('owner_id', $ownerId)->where('status', Listing::LIVE)->update(['status' => Listing::PAUSED, 'paused_at' => now()]);
                $this->push($owner, 'listing_banned', 'Listing paused', 'After {count} confirmed reports, your places are hidden and you cannot list for {days} days.', [
                    'count' => $strikes, 'days' => $settings['ban_days'],
                ], $listing);
            } else {
                $this->push($owner, 'listing_strike', 'A report was confirmed', 'A report on {title} was confirmed by E-Ride. {count} of {limit} reports leads to a pause from listing.', [
                    'title' => $listing->title, 'count' => $strikes, 'limit' => $settings['strike_limit'],
                ], $listing);
            }
        }
        return $report;
    }

    // ------------------------------------------------------------- review (admin)

    public function approve(Listing $listing): Listing
    {
        $first = !$listing->approved_at;
        $listing->update([
            'status' => Listing::LIVE, 'review_note' => null, 'needs_review' => false,
            'approved_at' => $listing->approved_at ?? now(), 'last_confirmed_at' => now(), 'reminder_stage' => 0,
        ]);
        $this->push($listing->owner, 'listing_live', 'Your place is live', '{title} is now visible in Homes & spaces. Get it E-Ride Verified to appear first.', [
            'title' => $listing->title,
        ], $listing);
        if ($first) {
            $this->sendAlerts($listing);
        }
        return $listing;
    }

    public function requestChanges(Listing $listing, string $note): Listing
    {
        $listing->update(['status' => Listing::CHANGES, 'review_note' => $note]);
        $this->push($listing->owner, 'listing_changes', 'Changes needed', 'E-Ride needs a change to {title}: {note}', ['title' => $listing->title, 'note' => $note], $listing);
        return $listing;
    }

    public function refuse(Listing $listing, string $note): Listing
    {
        $listing->update(['status' => Listing::REFUSED, 'review_note' => $note]);
        $this->push($listing->owner, 'listing_refused', 'Listing not accepted', '{title} cannot be listed: {note}', ['title' => $listing->title, 'note' => $note], $listing);
        return $listing;
    }

    public function remove(Listing $listing, string $note, bool $block): Listing
    {
        $listing->update(['status' => Listing::REMOVED, 'review_note' => $note]);
        $listing->visits()->whereIn('status', ListingVisit::OPEN)->update(['status' => ListingVisit::CANCELLED]);
        if ($block) {
            ListingOwner::updateOrCreate(['owner_id' => $listing->owner_id], ['blocked' => true]);
            Listing::where('owner_id', $listing->owner_id)->whereIn('status', [Listing::LIVE, Listing::PAUSED, Listing::PENDING])
                ->update(['status' => Listing::REMOVED]);
        }
        $this->push($listing->owner, 'listing_removed', 'Listing removed', '{title} was removed: {note}', ['title' => $listing->title, 'note' => $note], $listing);
        return $listing;
    }

    // ------------------------------------------------------------- verification (staff)

    public function schedule(ListingVerification $verification, string $staffId, string $at): ListingVerification
    {
        $verification->update(['staff_id' => $staffId, 'scheduled_for' => $at, 'status' => ListingVerification::SCHEDULED]);
        $this->push($verification->listing->owner, 'listing_visit_scheduled', 'E-Ride visit booked', 'E-Ride staff will visit {title} on {date}. Please be there with your ID.', [
            'title' => $verification->listing->title, 'date' => Carbon::parse($at)->format('d M, H:i'),
        ], $verification->listing);
        return $verification;
    }

    /**
     * The staff member's result, sent from the place itself. Refused when the
     * phone is not at the listing's pin.
     *
     * @param UploadedFile[] $photos
     */
    public function completeVerification(ListingVerification $verification, User $staff, float $lat, float $lng, array $checks,
                                         string $result, ?string $note, array $photos): ListingVerification
    {
        $listing = $verification->listing;
        $distance = (int)round(haversineDistance($lat, $lng, (float)$listing->latitude, (float)$listing->longitude));
        $radius = ListingSettings::all()['visit_radius_m'];
        if ($distance > $radius) {
            throw ValidationException::withMessages(['location' => translate(key: 'You are {distance} m from the pin of this listing. Go to the place to verify it (within {radius} m).', replace: [
                'distance' => $distance, 'radius' => $radius,
            ])]);
        }
        $status = match ($result) {
            'pass' => ListingVerification::PASSED,
            'fixable' => ListingVerification::FIXABLE,
            default => ListingVerification::FAILED,
        };
        DB::transaction(function () use ($verification, $staff, $lat, $lng, $distance, $checks, $note, $status, $photos, $listing) {
            $verification->update([
                'staff_id' => $verification->staff_id ?? $staff->id, 'status' => $status, 'latitude' => $lat, 'longitude' => $lng,
                'distance_m' => $distance, 'checks' => $checks, 'note' => $note, 'completed_at' => now(),
            ]);
            if ($status === ListingVerification::PASSED) {
                $this->addPhotos($listing, $photos, 'eride', $lat, $lng);
                $listing->update([
                    'verified_until' => now()->addMonths(ListingSettings::all()['verified_months']),
                    'verified_expiry_notified_at' => null,
                ]);
            }
        });
        if ($status === ListingVerification::PASSED) {
            $this->push($listing->owner, 'listing_verified', 'Your place is E-Ride Verified', '{title} passed the E-Ride visit. It now shows first, with the Verified badge.', [
                'title' => $listing->title,
            ], $listing);
            $this->sendAlerts($listing->fresh(), true);
        } elseif ($status === ListingVerification::FIXABLE) {
            $this->push($listing->owner, 'listing_fix', 'A few things to correct', 'The E-Ride visit to {title} found things to correct: {note}', [
                'title' => $listing->title, 'note' => $note ?: translate('see My listings'),
            ], $listing);
        } else {
            $this->remove($listing, $note ?: translate('The place did not match the listing.'), true);
        }
        return $verification->fresh();
    }

    // ------------------------------------------------------------- alerts

    /** Pushes to seekers whose saved search matches. Only verified-only alerts on a verification. */
    private function sendAlerts(Listing $listing, bool $verifiedNow = false): void
    {
        ListingAlert::where('user_id', '!=', $listing->owner_id)
            ->when($verifiedNow, fn($q) => $q->where('verified_only', 1))
            ->get()
            ->filter(fn(ListingAlert $alert) => $alert->matches($listing))
            ->unique('user_id')
            ->take(500)
            ->each(function (ListingAlert $alert) use ($listing) {
                $this->push(User::find($alert->user_id), 'listing_alert', 'A place matches your alert', '{type} in {area}: {rent}. Open Homes & spaces to see it.', [
                    'type' => translate($listing->type), 'area' => $listing->neighbourhood,
                    'rent' => getCurrencyFormat($listing->rent) . ' / ' . translate($listing->rent_period),
                ], $listing);
            });
    }

    // ------------------------------------------------------------- the daily job

    /**
     * The "still available" policy, once a day: reminders, the late badge
     * (computed, nothing to store), pausing, the 48 h check after reports,
     * nudges for listings nobody asks to visit, and verified badges expiring.
     * $now lets a test run the policy on any date.
     */
    public function sweep(?Carbon $now = null): array
    {
        $now ??= now();
        $settings = ListingSettings::all();
        $counts = ['reminded' => 0, 'paused' => 0, 'nudged' => 0, 'expired' => 0];

        Listing::with('owner')->where('status', Listing::LIVE)->whereNotNull('last_confirmed_at')->chunkById(200, function ($listings) use ($now, $settings, &$counts) {
            foreach ($listings as $listing) {
                $due = $listing->confirmDueAt();
                $vars = ['title' => $listing->title, 'area' => $listing->neighbourhood];

                if ($now->gte($listing->last_confirmed_at->copy()->addDays(2 * $listing->confirmEveryDays()))) {
                    $listing->update(['status' => Listing::PAUSED, 'paused_at' => $now]);
                    $this->push($listing->owner, 'listing_paused', 'Your place is hidden', '{title} was not confirmed for a long time, so seekers no longer see it. One tap brings it back.', $vars, $listing);
                    $counts['paused']++;
                    continue;
                }
                if ($listing->taken_check_until && $now->gte($listing->taken_check_until)) {
                    $listing->update(['status' => Listing::PAUSED, 'paused_at' => $now, 'taken_check_until' => null]);
                    $this->push($listing->owner, 'listing_paused', 'Your place is hidden', 'Seekers said {title} is taken and it was not confirmed, so it is hidden. One tap brings it back.', $vars, $listing);
                    $counts['paused']++;
                    continue;
                }
                if ($listing->reminder_stage < 2 && $now->gte($due)) {
                    $listing->update(['reminder_stage' => 2]);
                    $this->push($listing->owner, 'listing_confirm', 'Confirm your place is still free', 'Confirm {title} in {area} is still available today, or mark it rented.', $vars, $listing);
                    $counts['reminded']++;
                } elseif ($listing->reminder_stage < 1 && $now->gte($due->copy()->subDays(2))) {
                    $listing->update(['reminder_stage' => 1]);
                    $this->push($listing->owner, 'listing_confirm', 'Confirm your place is still free', 'In 2 days, confirm {title} in {area} is still available.', $vars, $listing);
                    $counts['reminded']++;
                }
                if (!$listing->stale_nudged_at && $listing->approved_at && $now->gte($listing->approved_at->copy()->addDays($settings['stale_days']))
                    && !$listing->visits()->exists()) {
                    $listing->update(['stale_nudged_at' => $now]);
                    $this->push($listing->owner, 'listing_stale', 'No visits yet', 'No one asked to visit {title} yet. A better price or new photos can help.', $vars, $listing);
                    $counts['nudged']++;
                }
                if ($listing->verified_until && $now->gte($listing->verified_until) && !$listing->verified_expiry_notified_at) {
                    $listing->update(['verified_expiry_notified_at' => $now]);
                    $this->push($listing->owner, 'listing_verified_expired', 'Verification expired', '{title} is no longer E-Ride Verified (6 months passed). Book a new E-Ride visit to get the badge back.', $vars, $listing);
                    $counts['expired']++;
                }
            }
        });

        // Visit reminders: the evening before, for visits that are on.
        ListingVisit::with(['listing', 'seeker', 'owner'])->where('status', ListingVisit::ACCEPTED)->whereNull('reminded_at')
            ->whereDate('visit_date', $now->copy()->addDay()->toDateString())->get()
            ->each(function (ListingVisit $visit) use ($now) {
                $vars = ['title' => $visit->listing->title, 'window' => translate($visit->time_window)];
                $this->push($visit->seeker, 'listing_visit_reminder', 'Visit tomorrow', 'Your visit to {title} is tomorrow, {window}. Ride there from My visits.', $vars, $visit->listing);
                $this->push($visit->owner, 'listing_visit_reminder', 'Visit tomorrow', 'A seeker visits {title} tomorrow, {window}.', $vars, $visit->listing);
                $visit->update(['reminded_at' => $now]);
            });

        return $counts;
    }

    // ------------------------------------------------------------- pushes

    private function push(?User $user, string $action, string $title, string $body, array $replace, Listing $listing): void
    {
        if (!$user) {
            return;
        }
        try {
            $locale = $user->current_language_key;
            sendDeviceNotification(
                fcm_token: $user->fcm_token,
                title: translate(key: $title, replace: $replace, locale: $locale),
                description: translate(key: $body, replace: $replace, locale: $locale),
                status: 1,
                type: 'listing',
                notification_type: 'listing',
                action: $action,
                user_id: $user->id,
                notificationData: ['listing_id' => $listing->id],
            );
        } catch (Throwable $e) {
            Log::warning('Listing push failed', ['listing' => $listing->id, 'action' => $action, 'error' => $e->getMessage()]);
        }
    }
}
