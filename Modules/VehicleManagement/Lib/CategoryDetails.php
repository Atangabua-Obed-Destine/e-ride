<?php

namespace Modules\VehicleManagement\Lib;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\VehicleManagement\Entities\VehicleCategory;

/**
 * What a customer sees about a vehicle category on Trip Setup: what the admin
 * wrote (seats, bags, features, example photos) and the vehicle models drivers
 * really have in it.
 */
class CategoryDetails
{
    public const MAX_PHOTOS = 6;
    public const MAX_FEATURES = 8;
    public const GALLERY_DIR = 'vehicle/category/gallery/';

    /** One feature per line, trimmed, at most MAX_FEATURES. */
    public static function parseFeatures(?string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', (string)$text) ?: [];
        $out = [];
        foreach ($lines as $line) {
            $line = trim(preg_replace('/^[\-\*\x{2022}]\s*/u', '', $line));
            if ($line !== '' && !in_array($line, $out, true)) {
                $out[] = mb_substr($line, 0, 60);
            }
        }
        return array_slice($out, 0, self::MAX_FEATURES);
    }

    /**
     * Photos kept after removals, then new uploads, up to MAX_PHOTOS. Removed
     * files are deleted from storage.
     *
     * @param UploadedFile[] $uploads
     */
    public static function gallery(array $current, array $remove, array $uploads): array
    {
        $kept = [];
        foreach ($current as $file) {
            if (in_array($file, $remove, true)) {
                Storage::disk('public')->delete(self::GALLERY_DIR . $file);
            } else {
                $kept[] = $file;
            }
        }
        foreach ($uploads as $upload) {
            if (count($kept) >= self::MAX_PHOTOS) {
                break;
            }
            if ($upload instanceof UploadedFile && ($name = fileUploader(self::GALLERY_DIR, APPLICATION_IMAGE_FORMAT, $upload))) {
                $kept[] = $name;
            }
        }
        return $kept;
    }

    public static function forget(string $categoryId): void
    {
        Cache::forget('vehicle_category_details_' . $categoryId);
    }

    /** The customer API payload, cached ten minutes. */
    public static function payload(VehicleCategory $category): array
    {
        return Cache::remember('vehicle_category_details_' . $category->id, 600, function () use ($category) {
            $base = dynamicStorage('storage/app/public/vehicle');
            $models = DB::table('vehicles as v')
                ->join('vehicle_models as m', 'm.id', '=', 'v.model_id')
                ->leftJoin('vehicle_brands as b', 'b.id', '=', 'v.brand_id')
                ->where('v.category_id', $category->id)
                ->where('v.is_active', 1)
                ->where(fn($q) => $q->whereNull('v.vehicle_request_status')->orWhere('v.vehicle_request_status', 'approved'))
                ->where(fn($q) => $q->whereNull('v.draft')->orWhere('v.draft', 0))
                ->whereNull('v.deleted_at')
                ->groupBy('m.id', 'm.name', 'm.image', 'm.seat_capacity', 'm.hatch_bag_capacity', 'b.name')
                ->orderByDesc(DB::raw('COUNT(*)'))
                ->limit(8)
                ->get(['m.id', 'm.name', 'm.image', 'm.seat_capacity', 'm.hatch_bag_capacity', 'b.name as brand', DB::raw('COUNT(*) as vehicles')]);

            $gallery = array_values(array_filter((array)($category->gallery ?? [])));
            return [
                'id' => $category->id,
                'name' => $category->name,
                'type' => $category->type,
                'description' => trim((string)$category->description),
                'image' => $category->image ? "$base/category/{$category->image}" : null,
                'seats' => $category->seats,
                'bags' => $category->bags,
                'features' => array_values((array)($category->features ?? [])),
                'gallery' => array_map(fn($f) => "$base/category/gallery/$f", $gallery),
                'models' => $models->map(fn($m) => [
                    'name' => trim(($m->brand ? $m->brand . ' ' : '') . $m->name),
                    'image' => $m->image ? "$base/model/{$m->image}" : null,
                    'seats' => $m->seat_capacity ? (int)$m->seat_capacity : null,
                    'vehicles' => (int)$m->vehicles,
                ])->values()->all(),
            ];
        });
    }
}
