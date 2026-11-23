<?php

namespace Modules\AdminModule\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Lib\ShareLooks;
use App\Models\ShareLook;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin > Share pictures: what partners (Food & Shops, Market shops, bus agencies) may
 * change on the pictures they share, and the pictures they designed themselves (take down).
 */
class ShareLookController extends Controller
{
    public const REASONS = [
        'Copies another brand or business',
        'Not about what you sell',
        'Shocking or adult content',
        'False or misleading',
        'Hard to read or poor quality',
    ];

    public function index(Request $request): View
    {
        $kind = in_array($request->query('kind'), ShareLooks::KINDS, true) ? $request->query('kind') : null;
        $designs = ShareLook::whereNotNull('design')
            ->when($kind, fn($q) => $q->where('kind', $kind))
            ->orderByDesc('design_at')->paginate(24)->withQueryString();
        $owners = $designs->getCollection()->mapWithKeys(fn(ShareLook $r) => [$r->id => ShareLooks::owner($r)]);
        $removed = ShareLook::whereNotNull('removed_at')->whereNull('design')->orderByDesc('removed_at')->limit(10)->get();
        $removedOwners = $removed->mapWithKeys(fn(ShareLook $r) => [$r->id => ShareLooks::owner($r)]);
        $counts = [
            'designs' => ShareLook::whereNotNull('design')->count(),
            'colors' => ShareLook::whereNotNull('color')->count(),
        ];
        return view('adminmodule::share-looks', [
            'settings' => ShareLooks::settings(),
            'designs' => $designs, 'owners' => $owners, 'kind' => $kind,
            'removed' => $removed, 'removedOwners' => $removedOwners,
            'counts' => $counts, 'reasons' => self::REASONS,
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $request->validate([
            'palette' => 'nullable|array|max:16',
            'palette.*' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'layouts' => 'nullable|array',
            'strip' => ['required', Rule::in(['required', 'optional'])],
            'max_mb' => 'required|integer|min:1|max:10',
        ]);
        $values = [];
        foreach (['store_on', 'market_on', 'bus_on', 'any_color', 'designs', 'page_color'] as $switch) {
            $values[$switch] = $request->boolean($switch) ? 1 : 0;
        }
        $palette = ShareLooks::colors(implode(',', array_filter((array)$request->input('palette'))));
        $values['palette'] = implode(',', $palette ?: ShareLooks::DEFAULT_PALETTE);
        foreach (ShareLooks::LAYOUTS as $kind => $all) {
            $on = array_values(array_intersect($all, (array)($request->input('layouts')[$kind] ?? [])));
            $values['layouts_' . $kind] = implode(',', $on ?: [$all[0]]);
        }
        $values['strip'] = $request->input('strip');
        $values['max_mb'] = (int)$request->input('max_mb');
        foreach ($values as $key => $value) {
            updateBusinessSetting(keyName: 'share_' . $key, settingType: ShareLooks::TYPE, value: (string)$value);
        }
        Cache::forget(CACHE_BUSINESS_SETTINGS);
        Toastr::success(translate('Share picture settings saved'));
        return back();
    }

    public function takeDown(Request $request, ShareLook $look): RedirectResponse
    {
        $data = $request->validate(['reason' => 'required|string|max:190']);
        ShareLooks::takeDown($look, $data['reason'], auth()->id());
        Toastr::success(translate('Picture taken down. The partner sees the reason.'));
        return back();
    }
}
