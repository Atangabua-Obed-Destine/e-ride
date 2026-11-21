<?php

namespace Modules\TripManagement\Service;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Voice notes for the driver. While booking, the customer records where to be
 * picked up, in their own words and language ("I dey for back of Presbyterian
 * church, blue gate"). The recording is uploaded before the request, like Move
 * photos, and joins the trip by its name; only the person who recorded it can
 * attach it. The driver plays it on the request and on the way to pickup.
 * Recordings are deleted after KEEP_DAYS.
 */
class TripVoiceNoteService
{
    public const FOLDER = 'trip/voice/';
    public const MAX_SECONDS = 90;
    public const KEEP_DAYS = 30;
    public const EXTENSIONS = ['aac' => 'audio/aac', 'm4a' => 'audio/mp4', 'mp4' => 'audio/mp4', 'ogg' => 'audio/ogg', 'wav' => 'audio/wav', 'mp3' => 'audio/mp3'];
    protected const OWNER = 'trip_voice_owner_';
    protected const TEXT = 'trip_voice_text_';

    /** Keeps the recording and remembers who made it (for a day: long enough to book). */
    public function store(UploadedFile $file, string $userId, int $seconds): ?string
    {
        $ext = strtolower((string)$file->getClientOriginalExtension());
        if (!isset(self::EXTENSIONS[$ext])) {
            $ext = 'aac';
        }
        $name = now()->format('Ymd') . '-' . Str::uuid() . '.' . $ext;
        $dir = storage_path('app/public/' . self::FOLDER);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }
        $file->move($dir, $name);
        Cache::put(self::OWNER . $name, ['user' => $userId, 'seconds' => max(1, min(self::MAX_SECONDS, $seconds))], now()->addDay());
        return $name;
    }

    public function path(string $name): string
    {
        return storage_path('app/public/' . self::FOLDER . $name);
    }

    public function mime(string $name): string
    {
        return self::EXTENSIONS[strtolower(pathinfo($name, PATHINFO_EXTENSION))] ?? 'audio/aac';
    }

    /** What the customer said, written out, shown to the driver under the player. */
    public function setText(string $name, string $text): void
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)));
        if ($text !== '') {
            Cache::put(self::TEXT . $name, mb_substr($text, 0, 500), now()->addDay());
        }
    }

    /** The trip's voice note columns, when the name is a recording this customer made; else nothing. */
    public function attributes(mixed $name, ?string $userId): array
    {
        if (!is_string($name) || $userId === null || !preg_match('/^[A-Za-z0-9._-]{8,120}$/', $name)) {
            return [];
        }
        $owner = Cache::get(self::OWNER . $name);
        if (!is_array($owner) || ($owner['user'] ?? null) !== $userId || !is_file($this->path($name))) {
            return [];
        }
        return [
            'voice_note' => $name,
            'voice_note_seconds' => (int)($owner['seconds'] ?? 0) ?: null,
            'voice_note_text' => Cache::get(self::TEXT . $name),
        ];
    }

    public function url(?string $name): ?string
    {
        return $name ? dynamicStorage('storage/app/public/' . rtrim(self::FOLDER, '/')) . '/' . $name : null;
    }

    /** Daily: recordings older than KEEP_DAYS are deleted, and their trips forget them. */
    public function sweep(): int
    {
        $dir = storage_path('app/public/' . self::FOLDER);
        if (!is_dir($dir)) {
            return 0;
        }
        $cutoff = now()->subDays(self::KEEP_DAYS)->getTimestamp();
        $gone = [];
        foreach (scandir($dir) ?: [] as $file) {
            $path = $dir . $file;
            if (is_file($path) && filemtime($path) < $cutoff && @unlink($path)) {
                $gone[] = $file;
            }
        }
        foreach (array_chunk($gone, 200) as $chunk) {
            DB::table('trip_requests')->whereIn('voice_note', $chunk)->update(['voice_note' => null, 'voice_note_seconds' => null, 'voice_note_text' => null]);
        }
        return count($gone);
    }
}
