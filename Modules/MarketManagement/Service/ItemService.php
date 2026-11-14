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
        if ($shop->isPrivate()) {
            $forSale = $shop->items()->whereIn('status', [MarketItem::LIVE, MarketItem::HIDDEN, MarketItem::BLOCKED])->count();
            if ($forSale >= $settings['private_max_items']) {
                $this->fail('items', translate(key: 'You can have up to {count} items for sale at once. Turn into a shop to sell more.',
                    replace: ['count' => $settings['private_max_items']]));
            }
        }
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
            $added = $this->addPhotos($item, $photos, false);
            $this->addPhotos($item, $previews, true);
            if (!$digital && !$shop->isPrivate() && ($item->sale_type ?? 'fixed') !== 'auction' && $this->applyOptions($item, $data, $added)) {
                if (!$word) {
                    $item->status = $item->quantity < 1 ? MarketItem::SOLD_OUT : MarketItem::LIVE;
                }
                $item->save();
            }
            if (($item->sale_type ?? 'fixed') === 'auction') {
                app(AuctionService::class)->start($item->fresh('shop'), $data);
            }
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
            $added = $this->addPhotos($item, $photos, false);
            $this->addPhotos($item, $previews, true);
            if (!$digital && !$item->shop?->isPrivate() && $item->sale_type !== 'auction') {
                $this->applyOptions($item, $data, $added);
            }
            if ($item->sale_type === 'auction' && (array_key_exists('auction_start', $data) || array_key_exists('auction_days', $data))) {
                app(AuctionService::class)->start($item->fresh('shop'), $data);
                $item->refresh();
            }
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
            if ($item->hasOptions()) {
                $this->fail('stock', translate('Set how many you have of each colour and size first.'));
            }
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
            'quantity', 'pickup', 'eride_delivery', 'seller_delivers', 'wholesale_price', 'wholesale_min', 'sale_type', 'used_grade']));
        // How it is sold: a fixed price, open to offers (negotiable), or an auction.
        if (isset($fields['sale_type'])) {
            $fields['sale_type'] = in_array($fields['sale_type'], MarketItem::SALE_TYPES, true) ? $fields['sale_type'] : 'fixed';
            $fields['negotiable'] = $fields['sale_type'] === 'offers';
            if ($fields['sale_type'] === 'auction') {
                $fields['price'] = $data['auction_start'] ?? ($fields['price'] ?? null);
                $fields['quantity'] = 1;
                unset($fields['wholesale_price'], $fields['wholesale_min']);
            }
        }
        if (array_key_exists('used_grade', $fields)) {
            $fields['used_grade'] = in_array($fields['used_grade'], MarketItem::USED_GRADES, true) && ($data['condition'] ?? 'used') === 'used' ? $fields['used_grade'] : null;
        }
        // A wholesale price only counts when it is lower than the price and starts from 2 pieces.
        if (array_key_exists('wholesale_price', $fields) || array_key_exists('wholesale_min', $fields)) {
            $price = (float)($data['price'] ?? 0);
            $wp = isset($fields['wholesale_price']) && $fields['wholesale_price'] !== '' ? (float)$fields['wholesale_price'] : null;
            $wm = isset($fields['wholesale_min']) && $fields['wholesale_min'] !== '' ? (int)$fields['wholesale_min'] : null;
            if ($wp === null || $wm === null || $wm < 2 || $wp <= 0 || ($price > 0 && $wp >= $price)) {
                $wp = null;
                $wm = null;
            }
            $fields['wholesale_price'] = $wp;
            $fields['wholesale_min'] = $wm;
        }
        if (isset($fields['title'])) {
            $fields['title'] = trim(preg_replace('/\s+/', ' ', $fields['title']));
        }
        if ($digital) {
            $fields['wholesale_price'] = null;
            $fields['wholesale_min'] = null;
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

    /**
     * @param UploadedFile[] $files
     * @return MarketItemPhoto[] the photos added, in upload order
     */
    private function addPhotos(MarketItem $item, array $files, bool $preview): array
    {
        $added = [];
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
                $added[] = $photo;
                $count++;
            }
        }
        return $added;
    }

    /**
     * Colours (each may point to one of the item's photos, or to one uploaded in this request by
     * its index), sizes, and stock per colour and size. With options, the quantity is the sum.
     * Returns whether options were given.
     * @param MarketItemPhoto[] $added
     */
    private function applyOptions(MarketItem $item, array $data, array $added): bool
    {
        if (!array_key_exists('options', $data)) {
            return false;
        }
        $raw = is_array($data['options']) ? $data['options'] : (json_decode((string)$data['options'], true) ?: []);
        $photoIds = $item->photos()->where('preview', false)->pluck('id')->map(fn($id) => (int)$id)->all();
        $colours = [];
        foreach (array_slice((array)($raw['colours'] ?? []), 0, MarketItem::MAX_COLOURS) as $c) {
            $name = mb_substr(trim((string)(is_array($c) ? ($c['name'] ?? '') : $c)), 0, 40);
            if ($name === '' || in_array(mb_strtolower($name), array_map(fn($x) => mb_strtolower($x['name']), $colours), true)) {
                continue;
            }
            $photoId = null;
            if (is_array($c) && isset($c['photo_index']) && isset($added[(int)$c['photo_index']])) {
                $photoId = (int)$added[(int)$c['photo_index']]->id;
            } elseif (is_array($c) && isset($c['photo_id']) && in_array((int)$c['photo_id'], $photoIds, true)) {
                $photoId = (int)$c['photo_id'];
            }
            $colours[] = ['name' => $name, 'photo_id' => $photoId];
        }
        $sizes = [];
        foreach (array_slice((array)($raw['sizes'] ?? []), 0, MarketItem::MAX_SIZES) as $size) {
            $size = mb_substr(trim((string)$size), 0, 20);
            if ($size !== '' && !in_array($size, $sizes, true)) {
                $sizes[] = $size;
            }
        }
        $item->options = ($colours || $sizes) ? ['colours' => $colours, 'sizes' => $sizes] : null;
        if (!$item->hasOptions()) {
            $item->stock = null;
            return true;
        }
        $given = $data['stock'] ?? [];
        $given = is_array($given) ? $given : (json_decode((string)$given, true) ?: []);
        $stock = [];
        foreach ($colours ?: [['name' => null]] as $c) {
            foreach ($sizes ?: [null] as $size) {
                $key = MarketItem::stockKey($c['name'], $size);
                $stock[$key] = max(0, min(100000, (int)($given[$key] ?? 0)));
            }
        }
        $item->stock = $stock;
        $item->quantity = array_sum($stock);
        return true;
    }

    /** "Grace Fabrics posted something new": to followers, at most once a day per shop. */
    public function tellFollowers(MarketShop $shop): int
    {
        if ($shop->followers_notified_at && $shop->followers_notified_at->gt(now()->subDay())) {
            return 0;
        }
        $shop->update(['followers_notified_at' => now()]);
        $sent = 0;
        MarketFollow::where('shop_id', $shop->id)->where('notify', true)->pluck('user_id')->chunk(200)->each(function ($ids) use ($shop, &$sent) {
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
