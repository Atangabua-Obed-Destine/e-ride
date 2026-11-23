<?php

namespace Modules\BusManagement\Http\Controllers\Web\Partner;

use App\Lib\ShareLooks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\View\View;
use Modules\BusManagement\Entities\BusAgency;
use Modules\BusManagement\Entities\BusDestination;
use Modules\BusManagement\Entities\BusSchedule;
use Modules\BusManagement\Entities\BusStaff;

/**
 * "Share": the agency's WhatsApp Status picture (destinations and fares, or departure times),
 * its own designed picture, a poster for the office, and its look (manager only).
 */
class ShareController extends Controller
{
    public function index(Request $request): View
    {
        /** @var BusAgency $agency */
        $agency = $request->attributes->get('busAgency');
        $staff = $request->attributes->get('busStaff');
        $url = route('bus.web.agency', ['slug' => $agency->slug]);
        $look = ShareLooks::present('bus', $agency->id, true);
        $classes = ['classic' => translate('Classic'), 'vip' => translate('VIP'), 'executive' => translate('Executive')];

        // Destinations, each with its lowest fare.
        $destinations = BusDestination::with(['dropPoints' => fn($q) => $q->where('active', true)])
            ->where('agency_id', $agency->id)->where('active', true)->orderBy('sort')->get();
        $routes = $destinations->map(function (BusDestination $d) {
            $fares = $d->dropPoints->flatMap(fn($p) => [$p->fare_classic, $p->fare_vip, $p->fare_executive])->filter(fn($f) => $f > 0);
            return ['l' => $d->city, 'r' => $fares->isNotEmpty() ? translate(key: 'from {fare}', replace: ['fare' => getCurrencyFormat((float)$fares->min())]) : ''];
        })->values()->all();

        // The daily timetable: time, destination, class.
        $times = BusSchedule::with('destination')->where('agency_id', $agency->id)->where('active', true)
            ->where('kind', 'fixed')->orderBy('time')->get()
            ->filter(fn($s) => $s->destination)
            ->map(fn($s) => ['l' => substr((string)$s->time, 0, 5) . '  ' . $s->destination->city, 'r' => $classes[$s->class] ?? ucfirst((string)$s->class)])
            ->values()->all();

        $cities = $destinations->pluck('city')->take(4)->implode(', ');
        $shareText = translate(key: 'Book your bus seat with {agency} on E-Ride: {link}', replace: ['agency' => $agency->name, 'link' => $url]);
        $card = [
            'kind' => 'BUS',
            'name' => $agency->name,
            'line' => trim($agency->origin_city . ($cities ? '  →  ' . $cities : '')),
            'cover' => null,
            'logo' => $agency->logo ? dynamicStorage('storage/app/public/bus/logo/' . $agency->logo) : null,
            'items' => [],
            'rows' => ['routes' => $routes, 'times' => $times],
            'about' => (string)$agency->about,
            'cta' => translate('Book your seat in the E-Ride app'),
            'link' => preg_replace('#^https?://#', '', $url),
            'page' => $url,
            'share_text' => $shareText,
            'file' => 'eride-bus-' . $agency->slug . '.png',
            'qr' => ShareLooks::qrRows($url),
            'scan' => translate('Scan to see the departures and book'),
            'scan_short' => translate('Scan or tap the link'),
            'no_app' => translate('See the seats in the browser, book in the app.'),
            'poster_line' => translate(key: 'Buses from {city}', replace: ['city' => $agency->origin_city]),
            'poster_hint' => translate('Print it and put it at your office and in your buses: travellers scan it to book their next trip.'),
            'look' => Arr::except($look, ['options', 'removed']),
        ];
        $canEdit = $staff?->role === BusStaff::MANAGER;
        $pickItems = [];
        return view('busmanagement::partner.share', compact('agency', 'url', 'card', 'shareText', 'look', 'canEdit', 'pickItems'));
    }

    /** The manager sets or removes the agency's logo. */
    public function saveLogo(Request $request, \Modules\BusManagement\Service\AgencyService $agencies): \Illuminate\Http\RedirectResponse
    {
        $agency = $request->attributes->get('busAgency');
        if ($request->boolean('remove')) {
            $agencies->removeLogo($agency);
            return back()->with('logo_saved', translate('Logo removed'));
        }
        if (!$request->hasFile('logo')) {
            return back()->withErrors(['logo' => translate('Choose a picture.')]);
        }
        try {
            $agencies->saveLogo($agency, $request->file('logo'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }
        return back()->with('logo_saved', translate('Logo saved'));
    }

    public function saveLook(Request $request): JsonResponse
    {
        $data = $request->validate([
            'color' => 'nullable|string|max:7',
            'layout' => 'nullable|string|max:20',
            'strip' => 'nullable|boolean',
        ]);
        $agency = $request->attributes->get('busAgency');
        ShareLooks::save('bus', $agency->id, $data + ['item' => null]);
        return response()->json(['ok' => true, 'look' => ShareLooks::present('bus', $agency->id, true)]);
    }

    public function saveDesign(Request $request): JsonResponse
    {
        $request->validate(['design' => 'required|file']);
        $agency = $request->attributes->get('busAgency');
        ShareLooks::saveDesign('bus', $agency->id, $request->file('design'));
        return response()->json(['ok' => true, 'look' => ShareLooks::present('bus', $agency->id, true)]);
    }

    public function removeDesign(Request $request): JsonResponse
    {
        $agency = $request->attributes->get('busAgency');
        ShareLooks::removeDesign('bus', $agency->id);
        return response()->json(['ok' => true, 'look' => ShareLooks::present('bus', $agency->id, true)]);
    }
}
