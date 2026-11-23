<?php

namespace Modules\StoreManagement\Service;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\StoreManagement\Entities\Store;
use Modules\StoreManagement\Entities\StoreItem;
use Modules\StoreManagement\Entities\StoreItemState;
use Modules\StoreManagement\Entities\StoreOrder;
use Modules\StoreManagement\Entities\StorePosItem;
use Modules\StoreManagement\Entities\StorePosLink;

/**
 * E-Ride's side of the POS Innova channel (docs/posinnova-channel-contract.md, v1).
 *
 * POS always calls E-Ride: it pushes its published products and stock, uploads their images, polls
 * the paid E-Ride orders for its branch, and says when the salesperson rang one up. E-Ride never
 * writes into POS. A linked menu item is sold out on E-Ride while POS says it is not sellable.
 */
class PosChannelService
{
    public const IMAGE_DIR = 'store/pos/';
    public const SNAPSHOT_HOURS = 2;

    // ------------------------------------------------------------ linking

    /** A new key for a kitchen, shown once. Returns [link, key]. */
    public function createLink(Store $kitchen, ?string $byUserId): array
    {
        $key = 'erk_' . Str::random(40);
        $link = StorePosLink::create([
            'store_id' => $kitchen->id,
            'key_hash' => hash('sha256', $key),
            'key_hint' => substr($key, 0, 8),
            'created_by' => $byUserId,
        ]);
        return [$link, $key];
    }

    public function revoke(StorePosLink $link): void
    {
        $link->forceFill(['revoked_at' => now()])->save();
    }

    /** The live link of a bearer key, or null. Notes the call on E-Ride's clock. */
    public function authenticate(?string $bearer): ?StorePosLink
    {
        if (!$bearer || !str_starts_with($bearer, 'erk_')) {
            return null;
        }
        $link = StorePosLink::where('key_hash', hash('sha256', $bearer))->first();
        if (!$link) {
            return null;
        }
        if (!$link->revoked_at) {
            $link->forceFill(['last_seen_at' => now(), 'linked_at' => $link->linked_at ?? now()])->saveQuietly();
        }
        return $link;
    }

    public function hello(StorePosLink $link): array
    {
        $kitchen = $link->store;
        $brand = $kitchen->brand();
        return [
            'branch' => ['id' => $kitchen->id, 'store' => $brand->name, 'branch' => $kitchen->branch_name ?: $kitchen->name, 'type' => $brand->type],
            'linked_at' => $link->linked_at?->toIso8601String(),
            'last_catalog_at' => $link->last_catalog_at?->toIso8601String(),
            'server_time' => now()->toIso8601String(),
        ];
    }

    public function heartbeat(StorePosLink $link, array $input): array
    {
        $link->forceFill(['last_heartbeat_at' => now()])->save();
        return ['ok' => true, 'server_time' => now()->toIso8601String()];
    }

    // ------------------------------------------------------------ catalogue

    /** A page of a full snapshot. Removals apply once the last page arrives. */
    public function catalogFull(StorePosLink $link, array $input): array
    {
        $snapshot = (string)($input['snapshot_id'] ?? '');
        $page = max(1, (int)($input['page'] ?? 1));
        $pages = max(1, (int)($input['pages'] ?? 1));
        if (!Str::isUuid($snapshot)) {
            throw ValidationException::withMessages(['snapshot_id' => 'snapshot_id must be a uuid']);
        }
        $this->location($link, $input['location'] ?? []);
        $items = (array)($input['items'] ?? []);
        $need = $this->upsert($link, $items, $snapshot);
        $cacheKey = 'pos-snapshot:' . $link->id . ':' . $snapshot;
        $seenPages = Cache::get($cacheKey, []);
        $seenPages[$page] = true;
        Cache::put($cacheKey, $seenPages, now()->addHours(self::SNAPSHOT_HOURS));
        if (count($seenPages) >= $pages) {
            // Every page arrived: what this location published before but not in this snapshot is gone.
            $gone = $link->items()->whereNull('removed_at')->where(fn($q) => $q->whereNull('snapshot_id')->orWhere('snapshot_id', '!=', $snapshot))->get();
            foreach ($gone as $item) {
                $item->forceFill(['removed_at' => now()])->save();
                $this->applyAvailability($link, $item);
            }
            Cache::forget($cacheKey);
        }
        $link->forceFill(['last_catalog_at' => now()])->save();
        return ['received' => count($items), 'need_images' => $need];
    }

    public function catalogChanges(StorePosLink $link, array $input): array
    {
        $this->location($link, $input['location'] ?? []);
        $items = (array)($input['items'] ?? []);
        $need = $this->upsert($link, $items, null);
        foreach ((array)($input['removed'] ?? []) as $variationId) {
            $item = $link->items()->where('variation_id', (int)$variationId)->first();
            if ($item && !$item->removed_at) {
                $item->forceFill(['removed_at' => now()])->save();
                $this->applyAvailability($link, $item);
            }
        }
        $link->forceFill(['last_catalog_at' => now()])->save();
        return ['received' => count($items), 'need_images' => $need];
    }

    protected function location(StorePosLink $link, array $location): void
    {
        $fill = [];
        if (isset($location['id'])) {
            $fill['pos_location_id'] = (int)$location['id'];
        }
        if (filled($location['name'] ?? null)) {
            $fill['pos_location_name'] = Str::limit((string)$location['name'], 120, '');
        }
        if (in_array($location['qty_display'] ?? null, ['available', 'exact'], true)) {
            $fill['qty_display'] = $location['qty_display'];
        }
        if ($fill) {
            $link->forceFill($fill)->save();
        }
    }

    /** Upserts items by variation_id. Returns the image hashes E-Ride does not have yet. */
    protected function upsert(StorePosLink $link, array $items, ?string $snapshot): array
    {
        $need = [];
        foreach ($items as $row) {
            if (!is_array($row) || !isset($row['variation_id'], $row['name'])) {
                continue;
            }
            $category = $row['category'] ?? null;
            $data = [
                'product_id' => isset($row['product_id']) ? (int)$row['product_id'] : null,
                'sku' => Str::limit((string)($row['sku'] ?? ''), 120, '') ?: null,
                'name' => Str::limit((string)$row['name'], 191, ''),
                'variation_name' => filled($row['variation_name'] ?? null) ? Str::limit((string)$row['variation_name'], 120, '') : null,
                'description' => filled($row['description'] ?? null) ? Str::limit(strip_tags((string)$row['description']), 2000, '') : null,
                'category' => is_array($category) ? Str::limit((string)($category['name'] ?? ''), 120, '') : null,
                'sub_category' => is_array($row['sub_category'] ?? null) ? Str::limit((string)($row['sub_category']['name'] ?? ''), 120, '') : null,
                'image_sha1' => preg_match('/^[a-f0-9]{40}$/', (string)($row['image_sha1'] ?? '')) ? $row['image_sha1'] : null,
                'unit_id' => isset($row['unit_id']) ? (int)$row['unit_id'] : null,
                'unit' => filled($row['unit'] ?? null) ? Str::limit((string)$row['unit'], 40, '') : null,
                'price' => max(0, (int)round((float)($row['price'] ?? 0))),
                'track_stock' => (bool)($row['track_stock'] ?? true),
                'qty_available' => isset($row['qty_available']) ? max(0, (float)$row['qty_available']) : null,
                'low_stock_threshold' => max(0, (float)($row['low_stock_threshold'] ?? 0)),
                'next_expiry' => filled($row['next_expiry'] ?? null) ? Carbon::parse($row['next_expiry'])->toDateString() : null,
                'pos_updated_at' => filled($row['updated_at'] ?? null) ? Carbon::parse($row['updated_at']) : null,
                'removed_at' => null,
            ];
            if ($snapshot) {
                $data['snapshot_id'] = $snapshot;
            }
            $item = StorePosItem::updateOrCreate(['link_id' => $link->id, 'variation_id' => (int)$row['variation_id']], $data);
            $this->applyAvailability($link, $item);
            if ($item->image_sha1 && !in_array($item->image_sha1, $need, true) && !DB::table('store_pos_images')->where('sha1', $item->image_sha1)->exists()) {
                $need[] = $item->image_sha1;
            }
        }
        return $need;
    }

    /**
     * A linked menu item follows POS at this kitchen: sold out while POS says it cannot be sold, back on
     * when it can. A single-kitchen business also takes the POS price (POS owns the E-Ride price).
     */
    public function applyAvailability(StorePosLink $link, StorePosItem $item): void
    {
        if (!$item->store_item_id) {
            return;
        }
        $kitchen = $link->store;
        $state = StoreItemState::firstOrNew(['store_id' => $kitchen->id, 'store_item_id' => $item->store_item_id]);
        if ($item->sellable()) {
            if ($state->exists && !$state->not_here) {
                $state->delete();
            }
        } elseif (!$state->not_here) {
            // Refreshed by every push; lapses by itself if POS goes silent for two days.
            $state->sold_out_until = now()->addDays(2);
            $state->save();
        }
        $brand = $kitchen->brand();
        if ($item->price > 0 && $brand->kitchens()->count() === 1) {
            StoreItem::whereKey($item->store_item_id)->where('price', '!=', $item->price)->update(['price' => $item->price]);
        }
    }

    /** A product's main image, sent once per sha1 (POS scales it to 1200 px). */
    public function storeImage(StorePosLink $link, string $sha1, UploadedFile $file): void
    {
        if (!preg_match('/^[a-f0-9]{40}$/', $sha1)) {
            throw ValidationException::withMessages(['sha1' => 'sha1 must be 40 hex characters']);
        }
        if (sha1_file($file->getRealPath()) !== $sha1) {
            throw ValidationException::withMessages(['sha1' => 'The file does not match its sha1.']);
        }
        if (DB::table('store_pos_images')->where('sha1', $sha1)->exists()) {
            return;
        }
        $ext = match ($file->getMimeType()) { 'image/png' => 'png', 'image/webp' => 'webp', default => 'jpg' };
        $name = $sha1 . '.' . $ext;
        Storage::disk('public')->putFileAs(rtrim(self::IMAGE_DIR, '/'), $file, $name);
        DB::table('store_pos_images')->insertOrIgnore(['sha1' => $sha1, 'file' => $name, 'created_at' => now(), 'updated_at' => now()]);
    }

    public static function imageUrl(?string $sha1): ?string
    {
        if (!$sha1) {
            return null;
        }
        $file = DB::table('store_pos_images')->where('sha1', $sha1)->value('file');
        return $file ? self::imageBase() . $file : null;
    }

    /** Public URL folder of POS images, ending in "/" (dynamicStorage drops a trailing slash). */
    public static function imageBase(): string
    {
        return rtrim(dynamicStorage('storage/app/public/' . rtrim(self::IMAGE_DIR, '/')), '/') . '/';
    }

    // ------------------------------------------------------------ orders

    /** Paid orders of this kitchen not rung up yet (since the link), and rung-up orders cancelled since. */
    public function pending(StorePosLink $link): array
    {
        $since = $link->created_at;
        $orders = StoreOrder::with(['items', 'customer', 'store'])->where('store_id', $link->store_id)
            ->where('payment_status', StoreOrder::PAID)->whereNull('pos_rung_up_at')
            ->whereNotIn('status', [StoreOrder::CANCELLED, StoreOrder::REJECTED, StoreOrder::EXPIRED])
            ->where('paid_at', '>=', $since)->orderBy('paid_at')->limit(50)->get();
        $cancelled = StoreOrder::where('store_id', $link->store_id)->whereNotNull('pos_sale_id')->whereNull('pos_cancel_seen_at')
            ->whereIn('status', [StoreOrder::CANCELLED, StoreOrder::REJECTED])->limit(50)->get();
        return [
            'orders' => $orders->map(fn(StoreOrder $o) => $this->orderPayload($link, $o))->values()->all(),
            'cancelled' => $cancelled->map(fn(StoreOrder $o) => [
                'order_id' => $o->id, 'number' => (string)$o->ref_id, 'pos_sale_id' => $o->pos_sale_id,
                'reason' => (string)$o->cancel_reason, 'cancelled_at' => $o->cancelled_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    public function find(StorePosLink $link, string $orderId): StoreOrder
    {
        $order = StoreOrder::with(['items', 'customer', 'store'])->where('store_id', $link->store_id)->find($orderId);
        abort_if(!$order, 404);
        return $order;
    }

    public function orderPayload(StorePosLink $link, StoreOrder $o): array
    {
        $map = $link->items()->whereIn('store_item_id', $o->items->pluck('store_item_id')->filter())->get()->keyBy('store_item_id');
        $lines = $o->items->map(function ($line) use ($map) {
            $pos = $line->store_item_id ? $map->get($line->store_item_id) : null;
            return [
                'variation_id' => $pos?->variation_id,
                'unit_id' => $pos?->unit_id,
                'name' => $line->name,
                'unit' => $pos?->unit,
                'qty' => (int)$line->quantity,
                'unit_price' => (int)round((float)$line->unit_price),
                'total' => (int)round((float)$line->line_total),
            ];
        })->values()->all();
        $status = in_array($o->status, [StoreOrder::CANCELLED, StoreOrder::REJECTED, StoreOrder::EXPIRED], true) ? 'cancelled'
            : ($o->pos_rung_up_at ? 'rung_up' : 'paid');
        $shop = $o->paid_to === StoreOrder::PAID_TO_SHOP;
        $roomBill = $o->onRoomBill();
        $stay = $o->hotel_stay_id ? \Modules\HotelManagement\Entities\HotelStay::find($o->hotel_stay_id) : null;
        return [
            'order_id' => $o->id,
            'number' => (string)$o->ref_id,
            'status' => $status,
            'placed_at' => ($o->placed_at ?? $o->created_at)?->toIso8601String(),
            'paid_at' => $o->paid_at?->toIso8601String(),
            'customer' => [
                'name' => $o->receiver_name ?: trim(($o->customer?->first_name ?? '') . ' ' . ($o->customer?->last_name ?? '')),
                'phone' => $o->receiver_phone ?: $o->customer?->phone,
                'address' => $o->isDelivery() ? $o->drop_address : null,
            ],
            'way' => match ($o->fulfilment) { StoreOrder::DELIVERY => 'delivery', 'dine_in' => 'dine_in', StoreOrder::ROOM => 'room', default => 'pickup' },
            // Room service: the staff carry it to this room; hotel_pos_ref is the guest's reservation (room bill).
            'room' => $o->isRoom() ? [
                'hotel' => $o->hotel_id ? \Modules\HotelManagement\Entities\Hotel::whereKey($o->hotel_id)->value('name') : null,
                'number' => (string)$o->room_label,
                'hotel_pos_ref' => $stay?->pos_ref,
            ] : null,
            'lines' => $lines,
            'items_total' => (int)round((float)$o->items_subtotal),
            'discount' => 0,
            'delivery_fee' => (int)round((float)$o->delivery_fee),
            'delivery_fee_collected_by_shop' => $shop && (float)$o->delivery_fee > 0,
            'total' => (int)round((float)$o->total),
            'payment' => $roomBill
                ? ['method' => 'room_bill', 'provider' => null, 'reference' => '', 'paid_to' => 'room_bill']
                : ['method' => 'mobile_money', 'provider' => 'fapshi', 'reference' => (string)($o->payment_request_id ?? ''), 'paid_to' => $shop ? 'shop' : 'eride'],
            'note' => (string)$o->note,
            'eride_url' => route('partner.orders'),
        ];
    }

    /** The salesperson completed the sale in POS (or edited it later: same pos_sale_id). */
    public function rungUp(StorePosLink $link, StoreOrder $order, array $input): array
    {
        $saleId = (int)($input['pos_sale_id'] ?? 0);
        if ($saleId <= 0) {
            throw ValidationException::withMessages(['pos_sale_id' => 'pos_sale_id is required']);
        }
        if ($order->pos_sale_id && (int)$order->pos_sale_id !== $saleId) {
            return ['conflict' => 'already_rung_up', 'pos_sale_id' => (int)$order->pos_sale_id];
        }
        if (in_array($order->status, [StoreOrder::CANCELLED, StoreOrder::REJECTED, StoreOrder::EXPIRED], true) && !$order->pos_sale_id) {
            return ['conflict' => 'order_cancelled'];
        }
        $lines = collect((array)($input['lines'] ?? []))->map(fn($l) => [
            'variation_id' => isset($l['variation_id']) ? (int)$l['variation_id'] : null,
            'qty' => (float)($l['qty'] ?? 0),
            'total' => (int)round((float)($l['total'] ?? 0)),
        ])->values()->all();
        $saleTotal = (int)round((float)($input['sale_total'] ?? 0));
        // Rung up for less than the customer paid (a line dropped, a quantity lowered): flagged to the store.
        // POS adds the delivery fee (shipping) only when the shop collected it; otherwise the sale is the items.
        $expected = (int)round((float)$order->items_subtotal)
            + ($order->paid_to === StoreOrder::PAID_TO_SHOP ? (int)round((float)$order->delivery_fee) : 0);
        $matches = $saleTotal >= $expected;
        $order->forceFill([
            'pos_sale_id' => $saleId,
            'pos_invoice_no' => Str::limit((string)($input['invoice_no'] ?? ''), 60, '') ?: $order->pos_invoice_no,
            'pos_rung_up_at' => filled($input['rung_up_at'] ?? null) ? Carbon::parse($input['rung_up_at']) : now(),
            'pos_rung_by' => Str::limit((string)($input['by'] ?? ''), 60, '') ?: $order->pos_rung_by,
            'pos_sale_total' => $saleTotal,
            'pos_lines' => $lines,
            'pos_matches' => $matches,
            // Room service on the room bill: false when POS could not charge the room (another payment was taken).
            'pos_charged_to_room' => $order->onRoomBill() && array_key_exists('charged_to_room', $input) ? (bool)$input['charged_to_room'] : $order->pos_charged_to_room,
        ])->save();
        return ['ok' => true, 'matches' => $matches];
    }

    public function unrung(StorePosLink $link, StoreOrder $order, array $input): array
    {
        if (!$order->pos_sale_id || (int)$order->pos_sale_id !== (int)($input['pos_sale_id'] ?? 0)) {
            return ['conflict' => 'not_this_sale'];
        }
        $order->forceFill(['pos_sale_id' => null, 'pos_invoice_no' => null, 'pos_rung_up_at' => null, 'pos_rung_by' => null,
            'pos_sale_total' => null, 'pos_lines' => null, 'pos_matches' => null])->save();
        return ['ok' => true];
    }

    public function cancelSeen(StorePosLink $link, StoreOrder $order): array
    {
        $order->forceFill(['pos_cancel_seen_at' => now()])->save();
        return ['ok' => true];
    }

    // ------------------------------------------------------------ linking products (partner screen)

    /** Suggested menu item for each unlinked POS item: same SKU in the name? exact name match first. */
    public function suggestions(StorePosLink $link): Collection
    {
        $menu = $link->store->brand()->items()->get();
        $byName = $menu->keyBy(fn($i) => self::nameKey($i->name));
        return $link->items()->whereNull('removed_at')->whereNull('store_item_id')->get()
            ->mapWithKeys(function (StorePosItem $p) use ($byName) {
                $hit = $byName->get(self::nameKey($p->fullName())) ?? $byName->get(self::nameKey($p->name));
                return [$p->id => $hit?->id];
            });
    }

    /** "Rice, 5 kg" and "Rice 5 kg" are the same name: case, punctuation and spacing ignored. */
    public static function nameKey(string $name): string
    {
        return Str::squish(preg_replace('/[^\p{L}\p{N}.]+/u', ' ', Str::lower($name)));
    }

    /** The menu name of a POS product: its unit too when it is sold by more than a piece ("Water 1.5 L (Pack of 6)"). */
    public static function menuName(StorePosItem $item): string
    {
        $name = $item->fullName();
        $unit = trim((string)$item->unit);
        if ($unit !== '' && !in_array(Str::lower($unit), ['piece', 'pieces', 'pc', 'pcs', 'unit', 'units', 'each', 'item'], true)
            && !Str::contains(Str::lower($name), Str::lower($unit))) {
            $name .= ' (' . $unit . ')';
        }
        return $name;
    }

    /** Links a POS item to a menu item (or unlinks with null) and applies its stock at once. */
    public function linkItem(StorePosLink $link, StorePosItem $item, ?string $menuItemId): void
    {
        if ($menuItemId) {
            $menu = $link->store->brand()->items()->findOrFail($menuItemId);
            // One POS item per menu item per kitchen.
            $link->items()->where('store_item_id', $menu->id)->where('id', '!=', $item->id)->update(['store_item_id' => null]);
            $item->forceFill(['store_item_id' => $menu->id])->save();
            if (!$menu->image && $item->image_sha1 && ($file = DB::table('store_pos_images')->where('sha1', $item->image_sha1)->value('file'))) {
                // No photo on E-Ride yet: the POS one.
                $name = date('Y-m-d') . '-' . uniqid() . '.' . pathinfo($file, PATHINFO_EXTENSION);
                if (Storage::disk('public')->copy(self::IMAGE_DIR . $file, 'store/item/' . $name)) {
                    $menu->forceFill(['image' => $name])->save();
                }
            }
            $this->applyAvailability($link, $item->fresh());
            return;
        }
        $old = $item->store_item_id;
        $item->forceFill(['store_item_id' => null])->save();
        if ($old) {
            StoreItemState::where('store_id', $link->store_id)->where('store_item_id', $old)->where('not_here', false)->delete();
        }
    }

    /** A new menu item from a POS item (name, description, price, image), linked to it. */
    public function createMenuItem(StorePosLink $link, StorePosItem $item, ?string $categoryId): StoreItem
    {
        $brand = $link->store->brand();
        $menu = StoreItem::create([
            'store_id' => $brand->id,
            'store_menu_category_id' => $categoryId && $brand->categories()->whereKey($categoryId)->exists() ? $categoryId : null,
            'name' => Str::limit(self::menuName($item), 120, ''),
            'description' => $item->description ? Str::limit($item->description, 500, '') : null,
            'price' => max(1, $item->price),
            'is_available' => true,
        ]);
        $this->linkItem($link, $item, $menu->id);
        return $menu->fresh();
    }
}
