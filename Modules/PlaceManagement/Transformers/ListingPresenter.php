<?php

namespace Modules\PlaceManagement\Transformers;

use Modules\PlaceManagement\Entities\Listing;
use Modules\PlaceManagement\Entities\ListingPayout;
use Modules\PlaceManagement\Entities\ListingPhoto;
use Modules\PlaceManagement\Entities\ListingReservation;
use Modules\PlaceManagement\Entities\ListingVerification;
use Modules\PlaceManagement\Entities\ListingVisit;
use Modules\PlaceManagement\Lib\ListingSettings;

/**
 * Homes & spaces as the customer app reads it. No phone number here: the
 * owner's number is only in an accepted visit (visit()).
 */
class ListingPresenter
{
    public static function listing(Listing $listing, ?string $viewerId = null): array
    {
        $settings = ListingSettings::all();
        $now = now();
        $mine = $viewerId && $viewerId === $listing->owner_id;
        $landmark = null;
        if ($settings['landmark_name'] !== '' && $settings['landmark_latitude'] !== null && $settings['landmark_longitude'] !== null) {
            $landmark = [
                'name' => $settings['landmark_name'],
                'distance_m' => (int)round(haversineDistance((float)$listing->latitude, (float)$listing->longitude, $settings['landmark_latitude'], $settings['landmark_longitude'])),
            ];
        }
        $verification = $listing->isVerified($now)
            ? $listing->verifications()->where('status', ListingVerification::PASSED)->latest('completed_at')->first()
            : null;

        $data = [
            'id' => $listing->id,
            'type' => $listing->type,
            'title' => $listing->title,
            'description' => $listing->description,
            'neighbourhood' => $listing->neighbourhood,
            'address' => $listing->address,
            'latitude' => (float)$listing->latitude,
            'longitude' => (float)$listing->longitude,
            'units_available' => $listing->units_available,
            'furnished' => $listing->furnished,
            'rent' => (float)$listing->rent,
            'rent_period' => $listing->rent_period,
            'monthly_rent' => $listing->monthlyRent(),
            'advance_months' => $listing->advance_months,
            'caution' => $listing->caution !== null ? (float)$listing->caution : null,
            'available_from' => $listing->available_from?->toDateString(),
            'water' => $listing->water,
            'power' => $listing->power,
            'backup_power' => $listing->backup_power,
            'toilet' => $listing->toilet,
            'kitchen' => $listing->kitchen,
            'fenced' => $listing->fenced,
            'security' => $listing->security,
            'amenities' => array_values($listing->amenities ?? []),
            'owner_role' => $listing->owner_role,
            'owner_first_name' => $listing->owner?->first_name,
            'status' => $listing->status,
            'verified' => $listing->isVerified($now),
            'verified_until' => $listing->verified_until?->toIso8601String(),
            'verification' => $verification ? [
                'date' => $verification->completed_at?->toDateString(),
                'checks' => array_values(array_filter(ListingVerification::CHECKS, fn($c) => !empty(($verification->checks ?? [])[$c]))),
            ] : null,
            'confirmed_at' => $listing->last_confirmed_at?->toIso8601String(),
            'not_confirmed_recently' => $listing->isLate($now),
            'photos' => $listing->photos->map(fn(ListingPhoto $p) => [
                'id' => $p->id,
                'url' => $p->url(),
                'by_eride' => $p->byEride(),
                'taken_at' => $p->taken_at?->toIso8601String(),
            ])->values()->all(),
            'landmark' => $landmark,
            'zone_served' => (bool)$listing->zone_id,
            'is_mine' => $mine,
            // Reservation money: shown only while seekers can reserve this place now.
            'reservation' => $listing->takesReservations() ? [
                'amount' => (float)$listing->reservation_amount,
                'fee_percent' => ListingSettings::reservations()['fee_percent'],
                'move_in_days' => ListingSettings::reservations()['move_in_days'],
            ] : null,
        ];

        if ($mine) {
            $open = $listing->verifications()->whereIn('status', [ListingVerification::REQUESTED, ListingVerification::SCHEDULED])->first();
            $data += [
                'review_note' => $listing->review_note,
                'confirm_due_at' => $listing->confirmDueAt()?->toIso8601String(),
                'confirm_every_days' => $listing->confirmEveryDays(),
                'taken_check_until' => $listing->taken_check_until?->toIso8601String(),
                'verification_status' => $open?->status,
                'verification_scheduled_for' => $open?->scheduled_for?->toIso8601String(),
                'views' => $listing->views,
                'visits_count' => $listing->visits()->count(),
                'open_visits' => $listing->visits()->whereIn('status', [ListingVisit::REQUESTED])->count(),
                'taking_requests' => $listing->visits()->where('taking', 1)->whereIn('status', [ListingVisit::ACCEPTED, ListingVisit::DONE])->count(),
                'reservations_on' => $listing->reservations_on,
                'reservation_amount' => $listing->reservation_amount !== null ? (float)$listing->reservation_amount : null,
                'payout_phone' => $listing->payout_phone,
                'payout_name' => $listing->payout_name,
                'reservations_waiting' => $listing->reservations()->where('status', ListingReservation::HELD)->count(),
            ];
        }
        return $data;
    }

    /** A visit from one side's point of view; the other side's number only once accepted. */
    public static function visit(ListingVisit $visit, string $viewerId): array
    {
        $asOwner = $viewerId === $visit->owner_id;
        $other = $asOwner ? $visit->seeker : $visit->owner;
        $listing = $visit->listing;
        $cover = $listing?->photos->first();
        return [
            'id' => $visit->id,
            'role' => $asOwner ? 'owner' : 'seeker',
            'status' => $visit->status,
            'date' => $visit->visit_date?->toDateString(),
            'window' => $visit->time_window,
            'proposed_date' => $visit->proposed_date?->toDateString(),
            'proposed_window' => $visit->proposed_window,
            'outcome' => $visit->outcome,
            'taking' => $visit->taking,
            'other_name' => trim(($other?->first_name ?? '') . ' ' . ($other?->last_name ?? '')),
            'other_phone' => $visit->contactsShared() ? $other?->phone : null,
            'listing' => $listing ? [
                'id' => $listing->id,
                'title' => $listing->title,
                'type' => $listing->type,
                'neighbourhood' => $listing->neighbourhood,
                'status' => $listing->status,
                'latitude' => (float)$listing->latitude,
                'longitude' => (float)$listing->longitude,
                'address' => $listing->address,
                'cover' => $cover?->url(),
                // The amount while seekers can reserve it through E-Ride, else null.
                'reservation_amount' => $listing->takesReservations() ? (float)$listing->reservation_amount : null,
            ] : null,
            // The latest one still meaning something (a lapsed attempt shows nothing, so Reserve comes back).
            'reservation' => self::reservation(
                ListingReservation::where('visit_id', $visit->id)->whereNot('status', ListingReservation::LAPSED)->latest()->first(), $viewerId),
            'created_at' => $visit->created_at?->toIso8601String(),
        ];
    }

    /** A reservation from one side's point of view: deadlines, the problem, and the payout or refund. */
    public static function reservation(?ListingReservation $r, string $viewerId): ?array
    {
        if (!$r) {
            return null;
        }
        $asOwner = $viewerId === $r->owner_id;
        $payout = $r->payouts()->where('user_id', $viewerId)->latest()->first();
        return [
            'id' => $r->id,
            'status' => $r->status,
            'amount' => $r->amount,
            'owner_amount' => $asOwner ? $r->owner_amount : null,
            'fee' => $asOwner ? $r->fee : null,
            'refunded_amount' => $r->refunded_amount,
            'payment_method' => $r->payment_method,
            'move_in_date' => $r->move_in_date?->toDateString(),
            'pay_due_at' => $r->pay_due_at?->toIso8601String(),
            'accept_due_at' => $r->accept_due_at?->toIso8601String(),
            'release_due_at' => $r->release_due_at?->toIso8601String(),
            'problem_by' => $r->problem_by,
            'problem_reason' => $r->problem_reason,
            'decision_note' => $r->decision_note,
            'closed_reason' => $r->closed_reason,
            'payout' => $payout ? [
                'kind' => $payout->kind,
                'amount' => $payout->amount,
                'phone' => $payout->phone,
                'status' => $payout->status,
                'reference' => $payout->reference,
                'paid_at' => $payout->paid_at?->toIso8601String(),
            ] : null,
            // A refund still in the wallet can be sent on mobile money once.
            'can_refund_to_momo' => !$asOwner && $r->refunded_amount > 0 && !$r->payouts()->where('kind', ListingPayout::REFUND)->exists(),
        ];
    }
}
