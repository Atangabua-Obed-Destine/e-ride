<?php

namespace Modules\MarketManagement\Lib;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\MarketManagement\Entities\MarketItemPhoto;
use Throwable;

/**
 * The picture WhatsApp shows in a link preview: the item photo cropped to 1200x630,
 * as a JPEG under ~250 KB (WhatsApp skips large or WebP images in previews).
 */
class OgImage
{
    public const W = 1200;
    public const H = 630;

    public static function make(MarketItemPhoto $photo): bool
    {
        try {
            $disk = Storage::disk('public');
            $source = $disk->path(MarketItemPhoto::DIR . $photo->file);
            if (!is_file($source)) {
                return false;
            }
            $src = match (strtolower((string)(@getimagesize($source)['mime'] ?? ''))) {
                'image/webp' => @imagecreatefromwebp($source),
                'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($source),
                'image/png' => @imagecreatefrompng($source),
                default => false,
            };
            if (!$src) {
                return false;
            }
            $sw = imagesx($src);
            $sh = imagesy($src);
            // Cover: crop the middle to the 1200:630 shape, then scale.
            $ratio = self::W / self::H;
            if ($sw / $sh > $ratio) {
                $cw = (int)round($sh * $ratio);
                $ch = $sh;
            } else {
                $cw = $sw;
                $ch = (int)round($sw / $ratio);
            }
            $cx = (int)(($sw - $cw) / 2);
            $cy = (int)(($sh - $ch) / 2);
            $out = imagecreatetruecolor(self::W, self::H);
            imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
            imagecopyresampled($out, $src, 0, 0, $cx, $cy, self::W, self::H, $cw, $ch);
            if (!$disk->exists(MarketItemPhoto::OG_DIR)) {
                $disk->makeDirectory(MarketItemPhoto::OG_DIR);
            }
            $target = $disk->path(MarketItemPhoto::OG_DIR . $photo->ogFile());
            foreach ([82, 70, 58] as $quality) {
                imagejpeg($out, $target, $quality);
                clearstatcache(true, $target);
                if (filesize($target) <= 250 * 1024) {
                    break;
                }
            }
            imagedestroy($src);
            imagedestroy($out);
            return true;
        } catch (Throwable $e) {
            Log::warning('Market link picture not made', ['photo' => $photo->id, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public static function remove(MarketItemPhoto $photo): void
    {
        Storage::disk('public')->delete(MarketItemPhoto::OG_DIR . $photo->ogFile());
    }
}
