<?php

namespace Modules\PlaceManagement\Http\Controllers\Api\Customer;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\PlaceManagement\Entities\Listing;
use Modules\PlaceManagement\Entities\ListingVisit;
use Modules\PlaceManagement\Service\ListingService;
use Modules\PlaceManagement\Transformers\ListingPresenter;

/** Customer app, Homes & spaces: booking and answering visits, and what came of them. */
class ListingVisitController extends Controller
{
    public function __construct(protected ListingService $listings)
    {
    }

    /** The user's visits, as seeker and as owner, newest first. */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $visits = ListingVisit::with(['listing.photos', 'seeker', 'owner'])
            ->where(fn($q) => $q->where('seeker_id', $userId)->orWhere('owner_id', $userId))
            ->latest()->limit(100)->get()
            ->map(fn(ListingVisit $v) => ListingPresenter::visit($v, $userId))->all();
        return response()->json(responseFormatter(DEFAULT_200, ['items' => $visits]));
    }

    public function store(Request $request, Listing $listing): JsonResponse
    {
        $data = $request->validate([
            'date' => 'required|date|after_or_equal:today|before_or_equal:' . now()->addDays(30)->toDateString(),
            'window' => ['required', Rule::in(ListingVisit::WINDOWS)],
        ]);
        return $this->run(fn() => $this->listings->book($listing, $request->user(), $data['date'], $data['window']), $request);
    }

    /** The owner: accept, propose another time, decline, or "It's rented". */
    public function respond(Request $request, ListingVisit $visit): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['accept', 'propose', 'decline', 'rented'])],
            'date' => 'required_if:action,propose|nullable|date|after_or_equal:today|before_or_equal:' . now()->addDays(30)->toDateString(),
            'window' => ['required_if:action,propose', 'nullable', Rule::in(ListingVisit::WINDOWS)],
        ]);
        if ($visit->owner_id !== $request->user()->id) {
            return $this->fail(translate('This is not your listing.'));
        }
        return $this->run(fn() => $this->listings->ownerRespond($visit, $data['action'], $data['date'] ?? null, $data['window'] ?? null), $request);
    }

    /** The seeker: accept the proposed time, or cancel. */
    public function seeker(Request $request, ListingVisit $visit): JsonResponse
    {
        $data = $request->validate(['action' => ['required', Rule::in(['accept_proposal', 'cancel'])]]);
        if ($visit->seeker_id !== $request->user()->id) {
            return $this->fail(translate('This is not your visit.'));
        }
        return $this->run(fn() => $this->listings->seekerRespond($visit, $data['action']), $request);
    }

    public function outcome(Request $request, ListingVisit $visit): JsonResponse
    {
        $data = $request->validate([
            'outcome' => ['required', Rule::in(ListingVisit::OUTCOMES)],
            'note' => 'nullable|string|max:500',
        ]);
        if ($visit->seeker_id !== $request->user()->id) {
            return $this->fail(translate('This is not your visit.'));
        }
        return $this->run(fn() => $this->listings->outcome($visit, $data['outcome'], $data['note'] ?? null), $request);
    }

    public function taking(Request $request, ListingVisit $visit): JsonResponse
    {
        if ($visit->seeker_id !== $request->user()->id) {
            return $this->fail(translate('This is not your visit.'));
        }
        return $this->run(fn() => $this->listings->taking($visit), $request);
    }

    private function run(callable $action, Request $request): JsonResponse
    {
        try {
            $visit = $action();
        } catch (ValidationException $e) {
            return $this->fail(collect($e->errors())->flatten()->first() ?? translate('Something went wrong'));
        }
        $visit->load(['listing.photos', 'seeker', 'owner']);
        return response()->json(responseFormatter(DEFAULT_UPDATE_200, ListingPresenter::visit($visit, $request->user()->id)));
    }

    private function fail(string $message): JsonResponse
    {
        return response()->json(responseFormatter(DEFAULT_400, errors: [['error_code' => 'visit', 'message' => $message]]), 403);
    }
}
