<?php

namespace Modules\MarketManagement\Service;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\AdminModule\Entities\AdminNotification;
use Modules\MarketManagement\Entities\MarketFollow;
use Modules\MarketManagement\Entities\MarketItem;
use Modules\MarketManagement\Entities\MarketItemPhoto;
use Modules\MarketManagement\Entities\MarketShop;
use Modules\MarketManagement\Lib\MarketPush;
use Modules\MarketManagement\Lib\MarketSettings;
use Modules\MarketManagement\Lib\OgImage;
use Modules\UserManagement\Entities\User;
use Throwable;

/**
 * Items: posting and editing, photos and digital previews, the banned-word filter
 * (a match blocks the post and sends it to the admin queue), sold out, and telling
 * followers (at most once a day per shop) that a shop posted.
 */
class ItemService
{
    /**
     * @param UploadedFile[] $photos
     * @param UploadedFile[] $previews digital products: free preview pages (at least one)
     */
    public function create(MarketShop $shop, array $data, array $photos, array $previews = []): MarketItem
    {
        if (!$shop->isLive()) {
            $this->fail('shop', translate('This shop is closed.'));
        }
        $settings = MarketSettings::all();
        $count = $shop->items()->whereNotIn('status', [MarketItem::REMOVED])->count();
        if ($count >= $settings['items_per_shop']) {
            $this->fail('items', translate(key: 'A shop can have up to {count} items. Remove some to post more.', replace: ['count' => $settings['items_per_shop']]));
        }
        $digital = ($data['condition'] ?? 'new') === 'digital';
        $this->checkDigital($digital, $data, count($previews));
        if (!$digital && count($photos) < 1) {
            $this->fail('photos', translate('Add at least one photo.'));
        }

        $item = DB::transaction(function () use ($shop, $data, $photos, $previews, $digital) {
            $item = new MarketItem($this->fields($data, $digital));
            $item->shop_id = $shop->id;
            $item->code = $this->newCode();
            $item->quantity ??= 1;
            $word = $this->bannedWordIn($item->title . ' ' . $item->description);
            $item->status = $word ? MarketItem::BLOCKED : ($item->quantity < 1 ? MarketItem::SOLD_OUT : MarketItem::LIVE);
            $item->blocked_word = $word;
            $item->save();
            $this->addPhotos($item, $photos, false);
            $this->addPhotos($item, $previews, true);
            return $item->fresh(['photos', 'shop']);
        });

        if ($item->status === MarketItem::BLOCKED) {
            $this->safely(fn() => AdminNotification::create(['model' => 'market_item', 'model_id' => $item->id, 'message' => 'Market post blocked by a banned word']));
        } else {
            $this->tellFollowers($shop);
        }
        return $item;
    }

    /**
     * @param UploadedFile[] $photos new ones
     * @param int[] $removePhotoIds
     * @param UploadedFile[] $previews new preview pages
     */
    public function update(MarketItem $item, array $data, array $photos = [], array $removePhotoIds = [], array $previews = []): MarketItem
    {
        if (in_array($item->status, [MarketItem::REMOVED, MarketItem::HIDDEN], true) || !$item->shop?->isLive()) {
            $this->fail('item', translate('This item can no longer be changed.'));
        }
        $digital = ($data['condition'] ?? $item->condition) === 'digital';
        return DB::transaction(function () use ($item, $data, $photos, $removePhotoIds, $previews, $digital) {
            $item->fill($this->fields($data + $item->only(['condition']), $digital));
            $item->photos()->whereIn('id', $removePhotoIds)->get()->each(function (MarketItemPhoto $photo) {
                fileRemover(MarketItemPhoto::DIR, $photo->file);
                OgImage::remove($photo);
                $photo->delete();
            });
            $this->addPhotos($item, $photos, false);
            $this->addPhotos($item, $previews, true);
            $previewCount = $item->photos()->where('preview', true)->count();
            $this->checkDigital($digital, $item->toArray(), $previewCount);
            if (!$digital && $item->photos()->where('preview', false)->count() < 1) {
                $this->fail('photos', translate('Add at least one photo.'));
            }
            // The filter runs again on every edit: a blocked post that no longer matches goes live.
            $word = $this->bannedWordIn($item->title . ' ' . $item->description);
            if ($word) {
                $item->status = MarketItem::BLOCKED;
            } elseif (in_array($item->status, [MarketItem::BLOCKED, MarketItem::LIVE, MarketItem::SOLD_OUT], true)) {
                $item->status = $item->quantity < 1 ? MarketItem::SOLD_OUT : MarketItem::LIVE;
            }
            $item->blocked_word = $word;
            $item->save();
            return $item->fresh(['photos', 'shop']);
        });
    }

    /** The seller marks it sold out, or available again. */
    public function setSoldOut(MarketItem $item, bool $soldOut): MarketItem
    {
        if (!in_array($item->status, [MarketItem::LIVE, MarketItem::SOLD_OUT], true)) {
            $this->fail('item', translate('This item can no longer be changed.'));
        }
        $item->status = $soldOut ? MarketItem::SOLD_OUT : MarketItem::LIVE;
        if (!$soldOut && $item->quantity < 1) {
            $item->quantity = 1;
        }
        $item->save();
        return $item->fresh(['photos', 'shop']);
    }

    /** The seller deletes it: gone from the shop and the links. */
    public function delete(MarketItem $item): void
    {
        $item->update(['status' => MarketItem::REMOVED, 'status_note' => 'Deleted by the seller']);
        $item->delete();
    }

    // --------------------------------------------------------- rules

    /** The first banned word or phrase in $text (whole words, accents and case ignored), or null. */
    public function bannedWordIn(?string $text): ?string
    {
        $haystack = ' ' . MarketSettings::fold((string)$text) . ' ';
        foreach (MarketSettings::bannedList() as $phrase) {
            if (str_contains($haystack, ' ' . $phrase . ' ')) {
                return $phrase;
            }
        }
        return null;
    }

    private function checkDigital(bool $digital, array $data, int $previews): void
    {
        if (!$digital) {
            return;
        }
        if (!MarketSettings::all()['digital_allowed']) {
            $this->fail('condition', translate('Digital products are not allowed on E-Ride Market right now.'));
        }
        if (!in_array($data['digital_kind'] ?? null, MarketSettings::DIGITAL_KINDS, true)) {
            $this->fail('digital_kind', translate('Choose what kind of digital product it is.'));
        }
        if ($previews < 1) {
            $this->fail('previews', translate('Add a free preview so buyers can see what they pay for.'));
        }
        if (empty($data['owns_rights']) && !isset($data['id'])) {
            $this->fail('owns_rights', translate('Confirm that you made it or have the right to sell it.'));
        }
    }

    private function fields(array $data, bool $digital): array
    {
        $fields = array_intersect_key($data, array_flip(['title', 'description', 'price', 'negotiable', 'condition', 'digital_kind', 'category',
            'quantity', 'pickup', 'eride_delivery', 'seller_delivers']));
        if (isset($fields['title'])) {
            $fields['title'] = trim(preg_replace('/\s+/', ' ', $fields['title']));
        }
        if ($digital) {
            // Nothing to carry: the seller sends the file in the chat.
            $fields['pickup'] = false;
            $fields['eride_delivery'] = false;
            $fields['seller_delivers'] = false;
            $fields['category'] = $fields['category'] ?? 'digital';
        } else {
            $fields['digital_kind'] = null;
        }
        return $fields;
    }

    /** 8 letters and digits people can read out loud (no 0/O, 1/l/I). */
    private function newCode(): string
    {
        do {
            $code = '';
            $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (MarketItem::withTrashed()->where('code', $code)->exists());
        return $code;
    }

    /** @param UploadedFile[] $files */
    private function addPhotos(MarketItem $item, array $files, bool $preview): void
    {
        $max = $preview ? MarketItem::MAX_PREVIEWS : MarketItem::MAX_PHOTOS;
        $count = $item->photos()->where('preview', $preview)->count();
        $sort = (int)$item->photos()->max('sort');
        foreach ($files as $upload) {
            if ($count >= $max) {
                break;
            }
            $file = fileUploader(MarketItemPhoto::DIR, APPLICATION_IMAGE_FORMAT, $upload);
            if ($file) {
                $photo = $item->photos()->create(['file' => $file, 'preview' => $preview, 'sort' => ++$sort]);
                OgImage::make($photo);
                $count++;
            }
        }
    }

    /** "Grace Fabrics posted something new": to followers, at most once a day per shop. */
    public function tellFollowers(MarketShop $shop): int
    {
        if ($shop->followers_notified_at && $shop->followers_notified_at->gt(now()->subDay())) {
            return 0;
        }
        $shop->update(['followers_notified_at' => now()]);
        $sent = 0;
        MarketFollow::where('shop_id', $shop->id)->pluck('user_id')->chunk(200)->each(function ($ids) use ($shop, &$sent) {
            foreach (User::whereIn('id', $ids)->where('is_active', 1)->get() as $user) {
                MarketPush::send($user, 'market_follow_new', '{shop} posted something new', 'Have a look in E-Ride Market.',
                    ['shop' => $shop->name], ['shop_slug' => $shop->slug]);
                $sent++;
            }
        });
        return $sent;
    }

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
