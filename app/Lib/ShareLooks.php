<?php

namespace App\Lib;

use App\Models\ShareLook;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Partners' own look on the pictures they share (WhatsApp Status, poster) and on their
 * pages: Food & Shops brands (kind "store"), Market shops ("market"), bus agencies ("bus").
 *
 * A look: their colour (text turns white or dark by itself so it stays readable), a layout
 * for the Status picture, the item shown big, and a picture they designed themselves, shown
 * with an E-Ride strip (QR code, link) so it still brings people to the app.
 *
 * The admin decides what partners may change (admin > Share pictures): per service on or
 * off, the colours offered, any colour or not, the layouts, own pictures, whether the strip
 * is required, their colour on their pages, the largest upload.
 */
class ShareLooks
{
    public const TYPE = 'share_look_settings';
    public const KINDS = ['store', 'market', 'bus'];
    public const RED = '#E02020';
    public const DEFAULT_PALETTE = ['#E02020', '#1D2D2B', '#0F766E', '#15803D', '#1D4ED8', '#7C3AED', '#DB2777', '#EA580C', '#CA8A04', '#7C2D12'];
    public const LAYOUTS = [
        'store' => ['photos', 'list', 'one', 'brand'],
        'market' => ['photos', 'list', 'one', 'brand'],
        'bus' => ['routes', 'times', 'brand'],
    ];
    /** How many chosen items each layout shows. */
    public const MAX_ITEMS = ['photos' => 4, 'list' => 8, 'one' => 1];
    private const DARK = '#1D2D2B';

    /** What the admin allows. */
    public static function settings(): array
    {
        $get = fn(string $key, $default) => businessConfig($key, self::TYPE)?->value ?? $default;
        $palette = self::colors((string)$get('share_palette', implode(',', self::DEFAULT_PALETTE)));
        $layouts = [];
        foreach (self::LAYOUTS as $kind => $all) {
            $on = array_values(array_intersect($all, explode(',', (string)$get('share_layouts_' . $kind, implode(',', $all)))));
            $layouts[$kind] = $on ?: [$all[0]];
        }
        return [
            'on' => [
                'store' => (bool)(int)$get('share_store_on', 1),
                'market' => (bool)(int)$get('share_market_on', 1),
                'bus' => (bool)(int)$get('share_bus_on', 1),
            ],
            'palette' => $palette ?: self::DEFAULT_PALETTE,
            'any_color' => (bool)(int)$get('share_any_color', 1),
            'layouts' => $layouts,
            'designs' => (bool)(int)$get('share_designs', 1),
            'strip' => in_array($s = (string)$get('share_strip', 'required'), ['required', 'optional'], true) ? $s : 'required',
            'page_color' => (bool)(int)$get('share_page_color', 1),
            'max_mb' => min(10, max(1, (int)$get('share_max_mb', 5))),
        ];
    }

    /** "#e02020, #1d2d2b" → ['#E02020', '#1D2D2B'], invalid ones dropped, at most 16. */
    public static function colors(string $list): array
    {
        $out = [];
        foreach (preg_split('/[\s,;]+/', $list) as $c) {
            $c = strtoupper(trim($c));
            if (preg_match('/^#[0-9A-F]{6}$/', $c) && !in_array($c, $out, true)) {
                $out[] = $c;
            }
        }
        return array_slice($out, 0, 16);
    }

    public static function row(string $kind, string $ownerId): ?ShareLook
    {
        return ShareLook::where('kind', $kind)->where('owner_id', $ownerId)->first();
    }

    /**
     * The look as the pictures use it, with what the admin allows applied: a colour no
     * longer offered falls back to E-Ride red, a layout switched off to the first one.
     * With $editor, also what the partner may change (options) and a takedown notice.
     */
    public static function present(string $kind, string $ownerId, bool $editor = false): array
    {
        $s = self::settings();
        $row = $s['on'][$kind] ? self::row($kind, $ownerId) : null;
        $color = $row?->color && self::allowedColor($row->color, $s) ? $row->color : self::RED;
        $layout = $row?->layout && in_array($row->layout, $s['layouts'][$kind], true) ? $row->layout : $s['layouts'][$kind][0];
        $design = $s['designs'] ? $row?->designUrl() : null;
        $look = [
            'color' => $color,
            'ink' => self::ink($color),
            'own_color' => $color !== self::RED,
            'layout' => $layout,
            // The items they chose, in their order (empty: the picture chooses).
            'items' => self::chosen($row),
            'item' => self::chosen($row)[0] ?? null,
            'design' => $design,
            'design_w' => $design ? $row->design_w : null,
            'design_h' => $design ? $row->design_h : null,
            // The E-Ride strip under their own picture.
            'strip' => $s['strip'] === 'required' || ($row?->strip ?? true),
        ];
        if ($editor) {
            $look['removed'] = $row?->removed_at && !$row->design ? [
                'reason' => translate($row->removed_reason),
                'at' => $row->removed_at->toIso8601String(),
            ] : null;
            $look['options'] = [
                'on' => $s['on'][$kind],
                'palette' => $s['palette'],
                'any_color' => $s['any_color'],
                'layouts' => $s['layouts'][$kind],
                'designs' => $s['designs'],
                'strip_required' => $s['strip'] === 'required',
                'max_mb' => $s['max_mb'],
                'max_items' => self::MAX_ITEMS,
            ];
        }
        return $look;
    }

    /** The items chosen for the picture, in order. */
    private static function chosen(?ShareLook $row): array
    {
        if (!$row) {
            return [];
        }
        return array_values($row->items ?: ($row->item ? [$row->item] : []));
    }

    /** Their colour for their pages (header), or null: E-Ride's own look. */
    public static function page(string $kind, string $ownerId): ?array
    {
        $s = self::settings();
        if (!$s['on'][$kind] || !$s['page_color']) {
            return null;
        }
        $row = self::row($kind, $ownerId);
        if (!$row?->color || !self::allowedColor($row->color, $s) || $row->color === self::RED) {
            return null;
        }
        return ['color' => $row->color, 'ink' => self::ink($row->color)];
    }

    /** Saves the colour, layout, item shown big and the strip choice. */
    public static function save(string $kind, string $ownerId, array $input): ShareLook
    {
        $s = self::settings();
        if (!$s['on'][$kind]) {
            self::fail('color', translate('Changing the look of your pictures is not available.'));
        }
        $row = self::row($kind, $ownerId) ?? new ShareLook(['kind' => $kind, 'owner_id' => $ownerId]);
        if (array_key_exists('color', $input)) {
            $color = $input['color'] ? strtoupper(trim((string)$input['color'])) : null;
            if ($color !== null && !preg_match('/^#[0-9A-F]{6}$/', $color)) {
                self::fail('color', translate('Choose a colour.'));
            }
            if ($color !== null && !self::allowedColor($color, $s)) {
                self::fail('color', translate('Choose one of the colours shown.'));
            }
            $row->color = $color === self::RED ? null : $color;
        }
        if (array_key_exists('layout', $input) && $input['layout'] !== null) {
            if (!in_array($input['layout'], $s['layouts'][$kind], true)) {
                self::fail('layout', translate('Choose one of the layouts shown.'));
            }
            $row->layout = $input['layout'];
        }
        if (array_key_exists('items', $input)) {
            $items = [];
            foreach ((array)($input['items'] ?? []) as $id) {
                $id = mb_substr(trim((string)$id), 0, 64);
                if ($id !== '' && !in_array($id, $items, true)) {
                    $items[] = $id;
                }
            }
            $row->items = array_slice($items, 0, max(self::MAX_ITEMS)) ?: null;
            $row->item = $items[0] ?? null;
        } elseif (array_key_exists('item', $input)) {
            $row->item = $input['item'] ? mb_substr((string)$input['item'], 0, 64) : null;
            $row->items = $row->item ? [$row->item] : null;
        }
        if (array_key_exists('strip', $input) && $input['strip'] !== null) {
            $row->strip = $s['strip'] === 'required' || filter_var($input['strip'], FILTER_VALIDATE_BOOLEAN);
        }
        $row->save();
        return $row;
    }

    /** Their own designed picture: replaces the one before. */
    public static function saveDesign(string $kind, string $ownerId, UploadedFile $file): ShareLook
    {
        $s = self::settings();
        if (!$s['on'][$kind] || !$s['designs']) {
            self::fail('design', translate('Sharing your own picture is not available.'));
        }
        if (!in_array(strtolower($file->getClientOriginalExtension()), ['jpg', 'jpeg', 'png', 'webp'], true)
            || !in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true)) {
            self::fail('design', translate('Send a JPG, PNG or WEBP picture.'));
        }
        if ($file->getSize() > $s['max_mb'] * 1024 * 1024) {
            self::fail('design', translate(key: 'The picture is too big: {mb} MB at most.', replace: ['mb' => $s['max_mb']]));
        }
        $size = @getimagesize($file->getRealPath());
        if (!$size || $size[0] < 500 || $size[1] < 500) {
            self::fail('design', translate('The picture is too small: at least 500 pixels wide and high.'));
        }
        if ($size[1] / $size[0] > 3 || $size[0] / $size[1] > 3) {
            self::fail('design', translate('The picture is too long or too wide. A Status picture (tall) or a square works best.'));
        }
        $row = self::row($kind, $ownerId) ?? new ShareLook(['kind' => $kind, 'owner_id' => $ownerId]);
        $old = $row->design;
        $row->design = fileUploader(ShareLook::DESIGN_DIR, APPLICATION_IMAGE_FORMAT, $file);
        $row->design_w = $size[0];
        $row->design_h = $size[1];
        $row->design_at = now();
        $row->removed_reason = null;
        $row->removed_at = null;
        $row->removed_by = null;
        $row->save();
        if ($old) {
            fileRemover(ShareLook::DESIGN_DIR, $old);
        }
        return $row;
    }

    /** The partner removes their own picture. */
    public static function removeDesign(string $kind, string $ownerId): void
    {
        $row = self::row($kind, $ownerId);
        if ($row?->design) {
            fileRemover(ShareLook::DESIGN_DIR, $row->design);
            $row->forceFill(['design' => null, 'design_w' => null, 'design_h' => null, 'design_at' => null])->save();
        }
    }

    /** E-Ride staff take a picture down; the partner sees the reason. */
    public static function takeDown(ShareLook $row, string $reason, ?string $adminId): void
    {
        if ($row->design) {
            fileRemover(ShareLook::DESIGN_DIR, $row->design);
        }
        $row->forceFill(['design' => null, 'design_w' => null, 'design_h' => null,
            'removed_reason' => mb_substr($reason, 0, 190), 'removed_at' => now(), 'removed_by' => $adminId])->save();
    }

    /** Who a look belongs to, for the admin list: [name, kind label, link to their page or null]. */
    public static function owner(ShareLook $row): array
    {
        return match ($row->kind) {
            'store' => (function () use ($row) {
                $s = \Modules\StoreManagement\Entities\Store::withTrashed()->find($row->owner_id);
                return [$s?->name ?? '?', translate('Food & Shops'), $s ? \Modules\StoreManagement\Lib\StoreLinks::url($s) : null];
            })(),
            'market' => (function () use ($row) {
                $s = \Modules\MarketManagement\Entities\MarketShop::find($row->owner_id);
                return [$s?->name ?? '?', translate('E-Ride Market'), $s?->slug ? route('market.shop', ['slug' => $s->slug]) : null];
            })(),
            'bus' => (function () use ($row) {
                $a = \Modules\BusManagement\Entities\BusAgency::find($row->owner_id);
                return [$a?->name ?? '?', translate('Bus agency'), $a?->slug ? route('bus.web.agency', ['slug' => $a->slug]) : null];
            })(),
            default => ['?', $row->kind, null],
        };
    }

    /** A QR code of $text as rows of "0"/"1", drawn by the share pages. */
    public static function qrRows(string $text): array
    {
        $matrix = \BaconQrCode\Encoder\Encoder::encode($text, \BaconQrCode\Common\ErrorCorrectionLevel::M())->getMatrix();
        $rows = [];
        for ($y = 0; $y < $matrix->getHeight(); $y++) {
            $row = '';
            for ($x = 0; $x < $matrix->getWidth(); $x++) {
                $row .= $matrix->get($x, $y) === 1 ? '1' : '0';
            }
            $rows[] = $row;
        }
        return $rows;
    }

    private static function allowedColor(string $color, array $s): bool
    {
        return $color === self::RED || $s['any_color'] || in_array($color, $s['palette'], true);
    }

    /** White or dark text on $hex, whichever reads better. */
    public static function ink(string $hex): string
    {
        $l = self::luminance($hex);
        $onWhite = 1.05 / ($l + 0.05);
        $onDark = ($l + 0.05) / (self::luminance(self::DARK) + 0.05);
        return $onWhite >= $onDark ? '#FFFFFF' : self::DARK;
    }

    private static function luminance(string $hex): float
    {
        $rgb = sscanf(ltrim($hex, '#'), '%02x%02x%02x');
        $c = array_map(function ($v) {
            $v = ($v ?? 0) / 255;
            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, $rgb);
        return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
    }

    private static function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
