<?php

namespace Modules\StoreManagement\Http\Controllers\Web\Partner;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\StoreManagement\Entities\Store;
use Modules\StoreManagement\Entities\StorePosItem;
use Modules\StoreManagement\Entities\StorePosLink;
use Modules\StoreManagement\Service\PosChannelService;

/**
 * POS Innova in the partner portal (owner only): a key per branch for its POS, and linking the
 * products POS publishes to the E-Ride menu, so stock in POS decides what customers can order.
 */
class PartnerPosController extends Controller
{
    public function __construct(protected PosChannelService $channel)
    {
    }

    /** The kitchens this login manages: a branch its own, the owner every branch. */
    protected function kitchens(Request $request)
    {
        $store = $request->attributes->get('partnerStore');
        return $store->isBranch() ? collect([$store]) : $store->kitchens();
    }

    protected function ownLink(Request $request, string $id): StorePosLink
    {
        return StorePosLink::whereIn('store_id', $this->kitchens($request)->pluck('id'))->findOrFail($id);
    }

    public function index(Request $request): View
    {
        $kitchens = $this->kitchens($request);
        $kitchen = $kitchens->firstWhere('id', $request->query('kitchen')) ?? $kitchens->first();
        $link = StorePosLink::where('store_id', $kitchen->id)->whereNull('revoked_at')->latest()->first();
        $filter = in_array($request->query('show'), ['unlinked', 'linked', 'all'], true) ? $request->query('show') : 'unlinked';
        $q = trim((string)$request->query('q', ''));
        $items = null;
        $suggest = collect();
        if ($link) {
            $items = $link->items()->with('menuItem')->whereNull('removed_at')
                ->when($filter === 'unlinked', fn($w) => $w->whereNull('store_item_id'))
                ->when($filter === 'linked', fn($w) => $w->whereNotNull('store_item_id'))
                ->when($q !== '', fn($w) => $w->where(fn($x) => $x->where('name', 'like', "%{$q}%")->orWhere('sku', 'like', "%{$q}%")))
                ->orderBy('name')->paginate(50)->withQueryString();
            $suggest = $this->channel->suggestions($link);
        }
        $brand = $kitchen->brand();
        return view('storemanagement::partner.pos', [
            'kitchens' => $kitchens, 'kitchen' => $kitchen, 'link' => $link, 'items' => $items, 'filter' => $filter, 'q' => $q,
            'suggest' => $suggest, 'menu' => $brand->items()->orderBy('name')->get(['id', 'name', 'price']),
            'categories' => $brand->categories()->orderBy('sort')->get(['id', 'name']),
            'counts' => $link ? [
                'all' => $link->items()->whereNull('removed_at')->count(),
                'linked' => $link->items()->whereNull('removed_at')->whereNotNull('store_item_id')->count(),
            ] : null,
            'images' => $items ? \Illuminate\Support\Facades\DB::table('store_pos_images')
                ->whereIn('sha1', $items->pluck('image_sha1')->filter()->unique())->pluck('file', 'sha1')
                ->map(fn($f) => PosChannelService::imageBase() . $f) : collect(),
            'apiUrl' => url('api/channel/posinnova/v1'),
            'newKey' => session('pos_key'),
        ]);
    }

    /** A new key for a branch (the old one stops working). Shown once. */
    public function createLink(Request $request): RedirectResponse
    {
        $kitchen = $this->kitchens($request)->firstWhere('id', $request->input('kitchen'));
        abort_unless($kitchen, 404);
        StorePosLink::where('store_id', $kitchen->id)->whereNull('revoked_at')->get()->each(fn($l) => $this->channel->revoke($l));
        [, $key] = $this->channel->createLink($kitchen, $request->user()?->id);
        return redirect()->route('partner.pos', ['kitchen' => $kitchen->id])->with('pos_key', $key);
    }

    public function revoke(Request $request, string $link): RedirectResponse
    {
        $row = $this->ownLink($request, $link);
        $this->channel->revoke($row);
        return redirect()->route('partner.pos', ['kitchen' => $row->store_id])->with('success', translate('The POS link was removed. That POS can no longer reach E-Ride.'));
    }

    public function linkItem(Request $request, string $item): RedirectResponse
    {
        $row = StorePosItem::findOrFail($item);
        $link = $this->ownLink($request, $row->link_id);
        $this->channel->linkItem($link, $row, $request->input('menu_item_id') ?: null);
        return back()->with('success', $request->input('menu_item_id') ? translate('Linked. E-Ride now follows the stock of this product in POS.') : translate('Unlinked.'));
    }

    public function createItem(Request $request, string $item): RedirectResponse
    {
        $row = StorePosItem::findOrFail($item);
        $link = $this->ownLink($request, $row->link_id);
        $this->channel->createMenuItem($link, $row, $request->input('category_id') ?: null);
        return back()->with('success', translate('Added to your menu and linked.'));
    }

    /** Links every suggested match (same name) in one go. */
    public function linkSuggested(Request $request, string $link): RedirectResponse
    {
        $row = $this->ownLink($request, $link);
        $n = 0;
        foreach ($this->channel->suggestions($row) as $posId => $menuId) {
            if ($menuId) {
                $this->channel->linkItem($row, $row->items()->findOrFail($posId), $menuId);
                $n++;
            }
        }
        return back()->with('success', translate(key: '{n} products linked.', replace: ['n' => $n]));
    }
}
