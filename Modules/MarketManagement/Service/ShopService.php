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

    public function update(MarketShop $shop, array $data, ?UploadedFile $logo = null): MarketShop
    {
        if (!$shop->isLive()) {
            $this->fail('shop', translate('This shop is closed.'));
        }
        $shop->fill($this->fields($data));
        if ($logo) {
            $shop->logo = fileUploader(MarketShop::LOGO_DIR, APPLICATION_IMAGE_FORMAT, $logo, $shop->logo);
        }
        $shop->save();
        return $shop->fresh();
    }

    private function fields(array $data): array
    {
        $fields = array_intersect_key($data, array_flip(['name', 'category', 'about', 'area_label', 'pickup_lat', 'pickup_lng', 'pickup_address']));
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

    public function follow(User $user, MarketShop $shop, bool $on): void
    {
        if ($shop->user_id === $user->id) {
            return;
        }
        $on ? MarketFollow::firstOrCreate(['user_id' => $user->id, 'shop_id' => $shop->id])
            : MarketFollow::where('user_id', $user->id)->where('shop_id', $shop->id)->delete();
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
