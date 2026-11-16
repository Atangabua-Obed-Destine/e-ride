<?php

namespace Modules\HotelManagement\Service;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\HotelManagement\Entities\Hotel;
use Modules\HotelManagement\Entities\HotelRoom;
use Modules\HotelManagement\Entities\HotelRoomNight;
use Modules\HotelManagement\Entities\HotelRoomType;
use Modules\HotelManagement\Entities\HotelStaff;
use Modules\PlaceManagement\Entities\Place;
use Modules\UserManagement\Entities\User;

/**
 * Making a verified hotel a partner, its staff, and what the manager sets up once: room types,
 * room numbers, times and the reception phone. A hotel goes live by itself when that is done.
 */
class PartnerService
{
    public const USERNAME_DOMAIN = 'hotel-staff.eride';
    public const PHOTO_DIR = 'hotel/rooms/';

    // ------------------------------------------------------------- E-Ride admin

    /** Makes a verified place to stay a partner hotel, with its manager's login. Returns [hotel, login]. */
    public function makePartner(Place $place, array $input, ?string $adminId): array
    {
        if ($place->explore_category !== 'stay' || !$place->is_active) {
            throw ValidationException::withMessages(['place' => translate('Only an active verified place to stay can become a partner hotel.')]);
        }
        if (Hotel::where('place_id', $place->id)->exists()) {
            throw ValidationException::withMessages(['place' => translate('This place is already a partner hotel.')]);
        }
        return DB::transaction(function () use ($place, $input, $adminId) {
            $hotel = Hotel::create([
                'place_id' => $place->id, 'name' => $place->name, 'status' => Hotel::SETUP,
                'reception_phone' => $place->phone, 'check_in_from' => $this->time($place->check_in) ?? '12:00',
                'check_out_by' => $this->time($place->check_out) ?? '11:00', 'answer_always' => true,
                'verified_by' => $adminId, 'verified_at' => now(),
            ]);
            [, $login] = $this->addStaff($hotel, ['name' => $input['manager_name'] ?? '', 'email' => $input['email'] ?? null,
                'role' => HotelStaff::MANAGER, 'password' => $input['password'] ?? '']);
            return [$hotel, $login];
        });
    }

    public function setPaused(Hotel $hotel, bool $paused, ?string $note): Hotel
    {
        $hotel->update($paused
            ? ['status' => Hotel::PAUSED, 'pause_note' => $note ? mb_substr(trim($note), 0, 300) : null]
            : ['status' => Hotel::SETUP, 'pause_note' => null]);
        return $paused ? $hotel : $this->refreshStatus($hotel);
    }

    // ------------------------------------------------------------- Staff

    /** Adds a manager or receptionist with their own login (an email, or a username made for them). Returns [staff, login]. */
    public function addStaff(Hotel $hotel, array $input): array
    {
        $data = Validator::make($input, [
            'name' => 'required|string|max:80',
            'email' => 'nullable|email|max:120',
            'role' => ['required', Rule::in(HotelStaff::ROLES)],
            'password' => 'required|string|min:8|max:100',
        ], [], ['name' => translate('name'), 'password' => translate('password')])->validate();
        $email = filled($data['email'] ?? null) ? strtolower(trim($data['email'])) : null;
        if ($email && User::where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => translate('This email already has an E-Ride account. Use another email, or leave it empty for a username.')]);
        }
        $login = $email ?? $this->freeUsername($data['name'], $hotel);
        return DB::transaction(function () use ($hotel, $data, $email, $login) {
            $user = User::create([
                'first_name' => trim($data['name']),
                'email' => $email ?? ($login . '@' . self::USERNAME_DOMAIN),
                'password' => Hash::make($data['password']),
                'user_type' => PARTNER_USER_TYPE,
                'is_active' => 1,
            ]);
            $staff = HotelStaff::create(['hotel_id' => $hotel->id, 'user_id' => $user->id, 'role' => $data['role'], 'active' => true]);
            return [$staff, $login];
        });
    }

    public function setStaffActive(Hotel $hotel, HotelStaff $staff, bool $active, HotelStaff $by): HotelStaff
    {
        if ((string)$staff->hotel_id !== (string)$hotel->id) {
            abort(404);
        }
        if (!$active && $staff->id === $by->id) {
            throw ValidationException::withMessages(['staff' => translate('You cannot turn off your own access.')]);
        }
        if (!$active && $staff->role === HotelStaff::MANAGER
            && HotelStaff::where('hotel_id', $hotel->id)->where('role', HotelStaff::MANAGER)->where('active', true)->count() <= 1) {
            throw ValidationException::withMessages(['staff' => translate('A hotel needs at least one manager.')]);
        }
        $staff->update(['active' => $active]);
        User::whereKey($staff->user_id)->update(['is_active' => $active ? 1 : 0]);
        return $staff;
    }

    /** What the person types to sign in: an email, or a username that stands for one. */
    public static function emailFor(string $typed): string
    {
        $typed = strtolower(trim($typed));
        return str_contains($typed, '@') ? $typed : $typed . '@' . self::USERNAME_DOMAIN;
    }

    protected function freeUsername(string $name, Hotel $hotel): string
    {
        $base = Str::of(Str::ascii($name))->lower()->replaceMatches('/[^a-z0-9]+/', '')->limit(12, '')->value() ?: 'staff';
        $suffix = Str::of(Str::ascii($hotel->name))->lower()->replaceMatches('/[^a-z0-9]+/', '')->limit(6, '')->value() ?: 'hotel';
        $login = $base . '.' . $suffix;
        for ($i = 2; User::where('email', $login . '@' . self::USERNAME_DOMAIN)->exists(); $i++) {
            $login = $base . $i . '.' . $suffix;
        }
        return $login;
    }

    // ------------------------------------------------------------- Set up once

    /** Saves a room type (new when $type is null). New photos are added to the kept ones, up to the limit. */
    public function saveType(Hotel $hotel, ?HotelRoomType $type, array $input, array $newPhotos = []): HotelRoomType
    {
        if ($type) {
            $this->own($hotel, $type->hotel_id);
        }
        $data = Validator::make($input, [
            'name' => 'required|string|max:80',
            'beds' => 'nullable|string|max:80',
            'max_guests' => 'required|integer|min:1|max:20',
            'price_from' => 'required|integer|min:500|max:5000000',
            'description' => 'nullable|string|max:600',
            'amenities' => 'nullable|array',
            'amenities.*' => [Rule::in(HotelRoomType::AMENITIES)],
            'keep_photos' => 'nullable|array',
            'keep_photos.*' => 'string|max:120',
        ], [], ['price_from' => translate('price from')])->validate();
        foreach ($newPhotos as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid() || !in_array(strtolower($file->getClientOriginalExtension()), ['jpg', 'jpeg', 'png', 'webp'], true) || $file->getSize() > 8 * 1024 * 1024) {
                throw ValidationException::withMessages(['photos' => translate('Photos must be JPG, PNG or WEBP, 8 MB at most.')]);
            }
        }
        $old = $type?->photos ?? [];
        // The form says which photos stay (keep_photos_sent marks a form that listed them, even all unticked).
        $listed = array_key_exists('keep_photos', $input) || !empty($input['keep_photos_sent']);
        $keep = $listed ? array_values(array_intersect($old, (array)($data['keep_photos'] ?? []))) : $old;
        if (count($keep) + count($newPhotos) > HotelRoomType::MAX_PHOTOS) {
            throw ValidationException::withMessages(['photos' => translate(key: 'At most {n} photos per room type.', replace: ['n' => HotelRoomType::MAX_PHOTOS])]);
        }
        foreach ($newPhotos as $file) {
            $keep[] = fileUploader(self::PHOTO_DIR, $file->getClientOriginalExtension() === 'png' ? 'png' : 'webp', $file);
        }
        foreach (array_diff($old, $keep) as $gone) {
            fileRemover(self::PHOTO_DIR, $gone);
        }
        $fields = [
            'name' => trim($data['name']), 'beds' => $data['beds'] ?? null, 'max_guests' => $data['max_guests'],
            'price_from' => $data['price_from'], 'description' => $data['description'] ?? null,
            'amenities' => array_values(array_intersect(HotelRoomType::AMENITIES, (array)($data['amenities'] ?? []))), 'photos' => array_values(array_filter($keep)),
        ];
        $type = $type ? tap($type)->update($fields) : HotelRoomType::create($fields + ['hotel_id' => $hotel->id, 'sort' => (int)HotelRoomType::where('hotel_id', $hotel->id)->max('sort') + 1]);
        $this->refreshStatus($hotel);
        return $type;
    }

    /** Takes a room type off (its rooms with it). Refused while rooms of it are taken on coming nights. */
    public function removeType(Hotel $hotel, HotelRoomType $type): void
    {
        $this->own($hotel, $type->hotel_id);
        $ids = HotelRoom::where('room_type_id', $type->id)->pluck('id');
        if (HotelRoomNight::whereIn('room_id', $ids)->where('night', '>=', today()->toDateString())->exists()) {
            throw ValidationException::withMessages(['type' => translate('Rooms of this type are taken on coming nights. Move or end those stays first.')]);
        }
        DB::transaction(function () use ($type, $ids) {
            HotelRoom::whereIn('id', $ids)->update(['active' => false]);
            $type->update(['active' => false]);
        });
        $hotel->touchBoard();
    }

    /**
     * Adds room numbers to a type: "101-105, 108, A2". A number taken before by a removed room comes back.
     * Returns how many were added.
     */
    public function addRooms(Hotel $hotel, HotelRoomType $type, string $numbers, ?string $floor): int
    {
        $this->own($hotel, $type->hotel_id);
        $list = [];
        foreach (preg_split('/[,;\s]+/', trim($numbers)) as $part) {
            if ($part === '') {
                continue;
            }
            if (preg_match('/^(\d{1,4})-(\d{1,4})$/', $part, $m) && (int)$m[2] >= (int)$m[1] && (int)$m[2] - (int)$m[1] < 100) {
                foreach (range((int)$m[1], (int)$m[2]) as $n) {
                    $list[] = (string)$n;
                }
            } elseif (preg_match('/^[A-Za-z0-9.\/-]{1,12}$/', $part)) {
                $list[] = strtoupper($part);
            } else {
                throw ValidationException::withMessages(['numbers' => translate(key: '"{part}" is not a room number. Use numbers like 101-105, 108.', replace: ['part' => mb_substr($part, 0, 20)])]);
            }
        }
        $list = array_values(array_unique($list));
        if (!$list) {
            throw ValidationException::withMessages(['numbers' => translate('Type the room numbers, like 101-105, 108.')]);
        }
        $existing = HotelRoom::where('hotel_id', $hotel->id)->whereIn('number', $list)->get()->keyBy('number');
        $clash = $existing->filter(fn($r) => $r->active && $r->room_type_id !== $type->id)->keys()->all();
        if ($clash) {
            throw ValidationException::withMessages(['numbers' => translate(key: 'Already used by another room type: {list}.', replace: ['list' => implode(', ', $clash)])]);
        }
        $added = 0;
        DB::transaction(function () use ($hotel, $type, $list, $existing, $floor, &$added) {
            foreach ($list as $number) {
                $room = $existing[$number] ?? null;
                if ($room && $room->active) {
                    continue;
                }
                if ($room) {
                    $room->update(['active' => true, 'room_type_id' => $type->id, 'floor' => $floor ?: $room->floor, 'state' => HotelRoom::READY]);
                } else {
                    HotelRoom::create(['hotel_id' => $hotel->id, 'room_type_id' => $type->id, 'number' => $number, 'floor' => $floor ? mb_substr(trim($floor), 0, 40) : null, 'state' => HotelRoom::READY]);
                }
                $added++;
            }
        });
        $hotel->touchBoard();
        $this->refreshStatus($hotel);
        return $added;
    }

    public function removeRoom(Hotel $hotel, HotelRoom $room): void
    {
        $this->own($hotel, $room->hotel_id);
        if (HotelRoomNight::where('room_id', $room->id)->where('night', '>=', today()->toDateString())->exists()) {
            throw ValidationException::withMessages(['room' => translate(key: 'Room {room} is taken on coming nights. End those stays first.', replace: ['room' => $room->number])]);
        }
        $room->update(['active' => false]);
        $hotel->touchBoard();
        $this->refreshStatus($hotel);
    }

    /** Check-in and check-out times, reception hours and phone, how long a room can be kept. */
    public function saveTimes(Hotel $hotel, array $input): Hotel
    {
        $time = 'nullable|date_format:H:i';
        $data = Validator::make($input, [
            'check_in_from' => 'required|date_format:H:i',
            'check_out_by' => 'required|date_format:H:i',
            'answer_always' => 'required|boolean',
            'answer_from' => $time . '|required_if:answer_always,0',
            'answer_until' => $time . '|required_if:answer_always,0',
            'reception_phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9 ]{8,20}$/'],
            'keep_max_hours' => 'required|integer|min:1|max:24',
        ], [], ['reception_phone' => translate('reception phone')])->validate();
        $hotel->update([
            'check_in_from' => $data['check_in_from'], 'check_out_by' => $data['check_out_by'], 'answer_always' => (bool)$data['answer_always'],
            'answer_from' => $data['answer_always'] ? null : $data['answer_from'], 'answer_until' => $data['answer_always'] ? null : $data['answer_until'],
            'reception_phone' => preg_replace('/\s+/', ' ', trim($data['reception_phone'])), 'keep_max_hours' => $data['keep_max_hours'],
        ]);
        return $this->refreshStatus($hotel);
    }

    /** What is left before guests can ask: [key => done?]. */
    public static function checklist(Hotel $hotel): array
    {
        $types = HotelRoomType::where('hotel_id', $hotel->id)->where('active', true)->withCount(['rooms' => fn($q) => $q->where('active', true)])->get();
        return [
            'types' => $types->isNotEmpty(),
            'photos' => $types->isNotEmpty() && $types->every(fn($t) => count($t->photos ?? []) >= 1),
            'rooms' => $types->isNotEmpty() && $types->every(fn($t) => $t->rooms_count >= 1),
            'phone' => filled($hotel->reception_phone),
        ];
    }

    /** Setting up goes live once the checklist is done; a live hotel that breaks it goes back to setting up. */
    public function refreshStatus(Hotel $hotel): Hotel
    {
        if ($hotel->status === Hotel::PAUSED) {
            return $hotel;
        }
        $ready = !in_array(false, self::checklist($hotel), true);
        $status = $ready ? Hotel::LIVE : Hotel::SETUP;
        if ($hotel->status !== $status) {
            $hotel->update(['status' => $status]);
            if ($status === Hotel::LIVE && !$hotel->board_touched_at) {
                $hotel->touchBoard();
            }
        }
        return $hotel;
    }

    protected function time(?string $value): ?string
    {
        return $value && preg_match('/^(\d{1,2}):(\d{2})/', $value, $m) ? sprintf('%02d:%s', min(23, (int)$m[1]), $m[2]) : null;
    }

    protected function own(Hotel $hotel, ?string $hotelId): void
    {
        if ((string)$hotelId !== (string)$hotel->id) {
            abort(404);
        }
    }
}
