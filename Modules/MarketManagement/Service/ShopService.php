<?php

namespace Modules\MarketManagement\Service;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\AdminModule\Entities\AdminNotification;
use Modules\MarketManagement\Entities\MarketBlock;
use Modules\MarketManagement\Entities\MarketFollow;
use Modules\MarketManagement\Entities\MarketItem;
use Modules\MarketManagement\Entities\MarketReferral;
use Modules\MarketManagement\Entities\MarketShop;
use Modules\MarketManagement\Entities\MarketVisit;
use Modules\MarketManagement\Lib\MarketPush;
use Modules\MarketManagement\Lib\MarketSettings;
use Modules\UserManagement\Entities\User;
use Throwable;

/** Shops: opening one (one per rider), its details, verification, follows, blocks, visits and the riders it brought. */
class ShopService
{
    /** Null when this rider may open a shop, else why not. */
    public function cannotOpenReason(User $user): ?string
    {
        if (!MarketSettings::enabled()) {
            return translate('E-Ride Market is not open yet.');
        }
        $shop = MarketShop::where('user_id', $user->id)->first();
        if ($shop && !$shop->isLive()) {
            return translate('Your shop was closed by E-Ride. Contact support if you think this is a mistake.');
        }
        return null;
    }

    public function mine(User $user): ?MarketShop
    {
        return MarketShop::where('user_id', $user->id)->first();
    }

    public function open(User $user, array $data, ?UploadedFile $logo = null): MarketShop
    {
        if ($reason = $this->cannotOpenReason($user)) {
            $this->fail('shop', $reason);
        }
        if ($existing = $this->mine($user)) {
            return $this->update($existing, $data, $logo);
        }
        return DB::transaction(function () use ($user, $data, $logo) {
            $shop = new MarketShop($this->fields($data));
            $shop->user_id = $user->id;
            $shop->slug = $this->slugFor((string)$data['name']);
            $shop->status = MarketShop::LIVE;
            if ($logo) {
                $shop->logo = fileUploader(MarketShop::LOGO_DIR, APPLICATION_IMAGE_FORMAT, $logo);
            }
            $shop->save();
            return $shop->fresh();
        });
    }

    /**
     * "Sell something I own": a person, not a shop. Their seller profile is made from their name
     * (no shop to set up), never on the map. A rider who already has a shop sells from it.
     */
    public function openPrivate(User $user): MarketShop
    {
        if ($reason = $this->cannotOpenReason($user)) {
            $this->fail('shop', $reason);
        }
        if ($existing = $this->mine($user)) {
            return $existing;
        }
        $first = trim((string)$user->first_name);
        $last = trim((string)$user->last_name);
        $name = trim($first . ($last !== '' ? ' ' . mb_strtoupper(mb_substr($last, 0, 1)) . '.' : '')) ?: translate('Private seller');
        return DB::transaction(function () use ($user, $name) {
            $shop = new MarketShop([
                'kind' => MarketShop::KIND_PRIVATE, 'name' => $name, 'map_visible' => false,
                'fulfilment' => ['pickup', 'eride'], 'pay_methods' => ['cash_delivery', 'momo_delivery', 'pickup'],
            ]);
            $shop->user_id = $user->id;
            $shop->slug = $this->slugFor($name);
            $shop->status = MarketShop::LIVE;
            $shop->save();
            return $shop->fresh();
        });
    }

    /** A private seller who grows: their profile becomes a shop (items, ratings and followers stay). */
    public function turnIntoShop(MarketShop $shop, array $data, ?UploadedFile $logo = null): MarketShop
    {
        if (!$shop->isPrivate()) {
            return $this->update($shop, $data, $logo);
        }
        if (trim((string)($data['name'] ?? '')) === '') {
            $this->fail('name', translate('Give your shop a name.'));
        }
        $shop->kind = MarketShop::KIND_SHOP;
        $shop->map_visible = true;
        return $this->update($shop, $data, $logo);
    }

    public function update(MarketShop $shop, array $data, ?UploadedFile $logo = null): MarketShop
    {
        if (!$shop->isLive()) {
            $this->fail('shop', translate('This shop is closed.'));
        }
        $shop->fill($this->fields($data));
        if ($shop->isPrivate()) {
            // A person's place is never on the map.
            $shop->map_visible = false;
        }
        if ($logo) {
            $shop->logo = fileUploader(MarketShop::LOGO_DIR, APPLICATION_IMAGE_FORMAT, $logo, $shop->logo);
        }
        $shop->save();
        return $shop->fresh();
    }

    /** How the shop takes orders: on or paused, ways to get items, ways to pay, hours, minimum. */
    public function saveOrderSettings(MarketShop $shop, array $data): MarketShop
    {
        if (!$shop->isLive()) {
            $this->fail('shop', translate('This shop is closed.'));
        }
        if (array_key_exists('taking_orders', $data)) {
            $shop->taking_orders = (bool)$data['taking_orders'];
        }
        $list = fn($value) => is_array($value) ? $value : (json_decode((string)$value, true) ?: array_filter(explode(',', (string)$value)));
        if (array_key_exists('fulfilment', $data)) {
            $fulfilment = array_values(array_intersect(\Modules\MarketManagement\Entities\MarketOrder::FULFILMENT, $list($data['fulfilment'])));
            if (!$fulfilment) {
                $this->fail('fulfilment', translate('Keep at least one way for buyers to get items.'));
            }
            $shop->fulfilment = $fulfilment;
        }
        if (array_key_exists('pay_methods', $data)) {
            $pay = array_values(array_intersect(\Modules\MarketManagement\Entities\MarketOrder::PAY_METHODS, $list($data['pay_methods'])));
            if (!$pay) {
                $this->fail('pay_methods', translate('Keep at least one way to pay.'));
            }
            $shop->pay_methods = $pay;
        }
        if (array_key_exists('open_from', $data) || array_key_exists('open_to', $data)) {
            $from = trim((string)($data['open_from'] ?? ''));
            $to = trim((string)($data['open_to'] ?? ''));
            $valid = fn($t) => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t) === 1;
            if ($from === '' || $to === '') {
                $shop->open_from = null;
                $shop->open_to = null;
            } elseif ($valid($from) && $valid($to) && $from !== $to) {
                $shop->open_from = $from;
                $shop->open_to = $to;
            } else {
                $this->fail('open_from', translate('Set opening hours like 08:00 and 19:00.'));
            }
        }
        if (array_key_exists('min_order', $data)) {
            $min = (float)$data['min_order'];
            $shop->min_order = $min > 0 ? $min : null;
        }
        $shop->save();
        return $shop->fresh();
    }

    private function fields(array $data): array
    {
        $fields = array_intersect_key($data, array_flip(['name', 'category', 'about', 'area_label', 'pickup_lat', 'pickup_lng', 'pickup_address',
            'map_visible', 'address_note']));
        if (array_key_exists('map_visible', $fields)) {
            $fields['map_visible'] = filter_var($fields['map_visible'], FILTER_VALIDATE_BOOLEAN);
        }
        // Several categories: a list (or "fashion,beauty"); the first is also kept as the main one.
        if (array_key_exists('categories', $data)) {
            $raw = $data['categories'];
            $raw = is_array($raw) ? $raw : (json_decode((string)$raw, true) ?: array_filter(array_map('trim', explode(',', (string)$raw))));
            $list = array_values(array_unique(array_intersect(array_map('strval', (array)$raw), \Modules\MarketManagement\Lib\MarketSettings::CATEGORIES)));
            $fields['categories'] = $list ?: null;
            $fields['category'] = $list[0] ?? null;
        } elseif (array_key_exists('category', $fields)) {
            $fields['categories'] = $fields['category'] ? [$fields['category']] : null;
        }
        if (isset($fields['name'])) {
            $fields['name'] = trim(preg_replace('/\s+/', ' ', $fields['name']));
        }
        return $fields;
    }

    /** "Grace Fabrics & Wax" -> "grace-fabrics-wax", made unique with a short suffix when taken. */
    public function slugFor(string $name): string
    {
        $base = Str::limit(Str::slug($name), 60, '') ?: 'shop';
        $slug = $base;
        while (MarketShop::where('slug', $slug)->exists() || in_array($slug, ['admin', 'api', 'market'], true)) {
            $slug = $base . '-' . strtolower(Str::random(4));
        }
        return $slug;
    }

    // --------------------------------------------------------- verification

    public function requestVerification(MarketShop $shop): MarketShop
    {
        if (!$shop->isLive() || $shop->isVerified()) {
            return $shop;
        }
        if (!$shop->verification_requested_at) {
            $shop->update(['verification_requested_at' => now()]);
            $this->safely(fn() => AdminNotification::create(['model' => 'market_shop', 'model_id' => $shop->id, 'message' => 'Market shop asks to be verified']));
        }
        return $shop->fresh();
    }

    public function verify(MarketShop $shop, User $admin): MarketShop
    {
        $shop->update(['verified_at' => now(), 'verified_by' => $admin->id, 'verification_requested_at' => null]);
        MarketPush::send($shop->owner, 'market_shop_verified', 'Your shop is E-Ride Verified',
            '{shop} now shows the E-Ride Verified badge. Buyers trust verified shops more.', ['shop' => $shop->name], ['shop_slug' => $shop->slug]);
        return $shop->fresh();
    }

    public function declineVerification(MarketShop $shop): MarketShop
    {
        $shop->update(['verification_requested_at' => null]);
        MarketPush::send($shop->owner, 'market_shop_verification', 'Verification not done yet',
            'E-Ride could not verify {shop} yet. You can ask again later.', ['shop' => $shop->name], ['shop_slug' => $shop->slug]);
        return $shop->fresh();
    }

    // --------------------------------------------------------- closing

    /** Closed by E-Ride: every item disappears with it, and its owner cannot open another shop. */
    public function close(MarketShop $shop, string $reason): MarketShop
    {
        $shop->update(['status' => MarketShop::CLOSED, 'closed_reason' => mb_substr($reason, 0, 191), 'closed_at' => now()]);
        MarketPush::send($shop->owner, 'market_shop_closed', 'Your shop was closed',
            'E-Ride closed {shop}: {reason}', ['shop' => $shop->name, 'reason' => $reason], ['shop_slug' => $shop->slug]);
        return $shop->fresh();
    }

    public function reopen(MarketShop $shop): MarketShop
    {
        $shop->update(['status' => MarketShop::LIVE, 'closed_reason' => null, 'closed_at' => null]);
        return $shop->fresh();
    }

    // --------------------------------------------------------- follows and blocks

    /** Follows (with or without new-post alerts; null keeps the current choice, on for a new follow) or unfollows. */
    public function follow(User $user, MarketShop $shop, bool $on, ?bool $notify = null): void
    {
        if ($shop->user_id === $user->id) {
            return;
        }
        if (!$on) {
            MarketFollow::where('user_id', $user->id)->where('shop_id', $shop->id)->delete();
            return;
        }
        $follow = MarketFollow::firstOrCreate(['user_id' => $user->id, 'shop_id' => $shop->id], ['notify' => $notify ?? true]);
        if ($notify !== null && $follow->notify !== $notify) {
            $follow->update(['notify' => $notify]);
        }
    }

    /** Whether $userId gets new-post alerts from the shop (false when not following). */
    public function followNotify(string $userId, string $shopId): bool
    {
        return (bool)MarketFollow::where('user_id', $userId)->where('shop_id', $shopId)->value('notify');
    }

    public function block(User $user, MarketShop $shop, bool $on): void
    {
        if ($shop->user_id === $user->id) {
            return;
        }
        if ($on) {
            MarketBlock::firstOrCreate(['user_id' => $user->id, 'shop_id' => $shop->id]);
            MarketFollow::where('user_id', $user->id)->where('shop_id', $shop->id)->delete();
        } else {
            MarketBlock::where('user_id', $user->id)->where('shop_id', $shop->id)->delete();
        }
    }

    public function follows(string $userId, string $shopId): bool
    {
        return MarketFollow::where('user_id', $userId)->where('shop_id', $shopId)->exists();
    }

    public function blocked(string $userId, string $shopId): bool
    {
        return MarketBlock::where('user_id', $userId)->where('shop_id', $shopId)->exists();
    }

    // --------------------------------------------------------- visits and referrals

    /** Counts a visit once per visitor and day. $visitor: a user id, or a web visitor's IP (hashed here). */
    public function visit(MarketShop|MarketItem $subject, string $visitor, bool $hash = true): void
    {
        try {
            $inserted = MarketVisit::insertOrIgnore([
                'day' => now()->toDateString(),
                'subject' => $subject instanceof MarketShop ? 'shop' : 'item',
                'subject_id' => $subject->id,
                'visitor' => $hash ? sha1($visitor . '|' . config('app.key')) : sha1('u|' . $visitor),
            ]);
            if ($inserted) {
                $subject->newQuery()->whereKey($subject->id)->increment('visits');
                if ($subject instanceof MarketItem) {
                    MarketShop::whereKey($subject->shop_id)->increment('visits');
                }
            }
        } catch (Throwable) {
        }
    }

    /**
     * A new rider who came through a link ("i:<code>" or "s:<slug>", from the install referrer):
     * counted once, for riders who joined in the last 14 days, never for the shop's own owner.
     */
    public function claimReferral(User $user, string $target): bool
    {
        if (!$user->created_at || $user->created_at->lt(now()->subDays(14)) || MarketReferral::where('user_id', $user->id)->exists()) {
            return false;
        }
        [$kind, $key] = array_pad(explode(':', $target, 2), 2, '');
        $item = $kind === 'i' ? MarketItem::where('code', $key)->first() : null;
        $shop = $item?->shop ?? ($kind === 's' ? MarketShop::where('slug', $key)->first() : null);
        if (!$shop || $shop->user_id === $user->id) {
            return false;
        }
        try {
            MarketReferral::create(['user_id' => $user->id, 'shop_id' => $shop->id, 'item_id' => $item?->id]);
        } catch (Throwable) {
            return false;
        }
        $shop->increment('new_users');
        return true;
    }

    // --------------------------------------------------------- helpers

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    private function safely(callable $step): void
    {
        try {
            $step();
        } catch (Throwable) {
        }
    }
}
