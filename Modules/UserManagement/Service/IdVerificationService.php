<?php

namespace Modules\UserManagement\Service;

use Aws\Exception\AwsException;
use Aws\Rekognition\RekognitionClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\UserManagement\Entities\IdVerification;
use Modules\UserManagement\Entities\User;
use Throwable;

/**
 * E-Ride ID Verified, for all of E-Ride. A person sends their ID card (front and back) or passport (photo page)
 * and a selfie once. E-Ride checks it as the admin chose (id_verification settings):
 *   staff  an admin compares the document and the selfie and approves or refuses;
 *   auto   AWS Rekognition compares the selfie with the face on the document (the face-verification credentials
 *          set up for drivers); below the threshold it is refused, an AWS failure leaves it to staff.
 * Verified lasts until the document expires or the profile name changes. Photos stay on the private disk.
 */
class IdVerificationService
{
    public const TYPE = 'id_verification';
    public const DISK = 'local';

    public static function settings(): array
    {
        $get = fn(string $key, $default) => businessConfig($key, self::TYPE)?->value ?? $default;
        $method = $get('id_verification_method', 'staff');
        return [
            // staff | auto
            'method' => in_array($method, ['staff', 'auto'], true) ? $method : 'staff',
            'threshold' => min(99, max(70, (int)$get('id_verification_threshold', 85))),
        ];
    }

    /** Whether automatic face match can run (the AWS face-verification credentials are set and on). */
    public static function autoAvailable(): bool
    {
        $cred = businessConfig(key: 'face_verification_api', settingsType: FACE_VERIFICATION_SETTINGS)?->value;
        return is_array($cred) && (int)($cred['status'] ?? 0) === 1 && filled($cred['access_key'] ?? null) && filled($cred['secret_access_key'] ?? null);
    }

    /** The person's live record: verified, or pending, or the last refused one. Null when nothing was sent. */
    public function current(User $user): ?IdVerification
    {
        return IdVerification::where('user_id', $user->id)->where('status', '!=', IdVerification::ENDED)->latest()->first();
    }

    /** A record agencies may rely on: verified, or pending (they can look at the photos themselves). */
    public function usable(User $user): ?IdVerification
    {
        return IdVerification::where('user_id', $user->id)->whereIn('status', [IdVerification::VERIFIED, IdVerification::PENDING])->latest()->first();
    }

    /**
     * input: doc_type, doc_number, full_name, expires_on (Y-m-d, optional), front, back (ID card), selfie
     */
    public function submit(User $user, array $input): IdVerification
    {
        $image = 'required|image|mimes:jpg,jpeg,png,webp|max:10240';
        $data = Validator::make($input, [
            'doc_type' => ['required', Rule::in(IdVerification::TYPES)],
            'doc_number' => ['required', 'string', 'min:5', 'max:40', 'regex:/^[A-Za-z0-9 \-\/]+$/'],
            'full_name' => 'required|string|min:3|max:120',
            'expires_on' => 'nullable|date_format:Y-m-d',
            'front' => $image,
            'back' => (($input['doc_type'] ?? '') === IdVerification::ID_CARD ? 'required' : 'nullable') . '|image|mimes:jpg,jpeg,png,webp|max:10240',
            'selfie' => $image,
        ], [
            'back.required' => translate('Add a photo of the back of your ID card.'),
            'doc_number.regex' => translate('The document number has letters and digits only.'),
        ], [
            'doc_number' => translate('Document number'), 'full_name' => translate('Name as on the document'),
            'front' => translate('Photo of the document'), 'selfie' => translate('Selfie'),
        ])->validate();
        if (!empty($data['expires_on']) && $data['expires_on'] < today()->toDateString()) {
            throw ValidationException::withMessages(['expires_on' => translate('This document has expired. Use one that is still valid.')]);
        }
        if (IdVerification::where('user_id', $user->id)->where('status', IdVerification::PENDING)->where('created_at', '>', now()->subMinutes(2))->exists()) {
            throw ValidationException::withMessages(['doc_type' => translate('Your ID was just sent. Wait for the answer.')]);
        }

        $dir = 'id-verification/' . $user->id;
        $store = fn(?UploadedFile $f, string $name) => $f ? $f->storeAs($dir, $name . '-' . Str::random(12) . '.' . ($f->guessExtension() ?: 'jpg'), self::DISK) : null;
        $record = DB::transaction(function () use ($user, $data, $store) {
            // A new submission replaces any earlier one.
            IdVerification::where('user_id', $user->id)->whereIn('status', [IdVerification::PENDING, IdVerification::VERIFIED, IdVerification::REFUSED])
                ->update(['status' => IdVerification::ENDED, 'end_reason' => 'replaced', 'ended_at' => now()]);
            return IdVerification::create([
                'user_id' => $user->id,
                'doc_type' => $data['doc_type'],
                'doc_number' => Str::upper(preg_replace('/\s+/', '', $data['doc_number'])),
                'full_name' => trim($data['full_name']),
                'expires_on' => $data['expires_on'] ?? null,
                'front_path' => $store($data['front'], 'front'),
                'back_path' => $store($data['back'] ?? null, 'back'),
                'selfie_path' => $store($data['selfie'], 'selfie'),
                'status' => IdVerification::PENDING,
            ]);
        });

        if (self::settings()['method'] === 'auto') {
            $this->autoCheck($record);
        }
        return $record->fresh();
    }

    /** Selfie against the face on the document. Below the threshold: refused. AWS unavailable: left to staff. */
    public function autoCheck(IdVerification $record): IdVerification
    {
        if (!self::autoAvailable()) {
            return $record;
        }
        $cred = businessConfig(key: 'face_verification_api', settingsType: FACE_VERIFICATION_SETTINGS)->value;
        try {
            $client = new RekognitionClient([
                'version' => 'latest',
                'region' => $cred['region'],
                'credentials' => ['key' => $cred['access_key'], 'secret' => $cred['secret_access_key']],
            ]);
            $result = $client->compareFaces([
                'SourceImage' => ['Bytes' => Storage::disk(self::DISK)->get($record->selfie_path)],
                'TargetImage' => ['Bytes' => Storage::disk(self::DISK)->get($record->front_path)],
                'SimilarityThreshold' => 60,
            ])->toArray();
        } catch (AwsException $e) {
            Log::warning('ID verification face match failed, left to staff', ['record' => $record->id, 'error' => $e->getAwsErrorCode()]);
            return $record;
        } catch (Throwable $e) {
            Log::warning('ID verification face match failed, left to staff', ['record' => $record->id, 'error' => $e->getMessage()]);
            return $record;
        }
        $similarity = (float)($result['FaceMatches'][0]['Similarity'] ?? 0);
        $record->similarity = $similarity;
        $record->method = 'auto';
        $record->save();
        return $similarity >= self::settings()['threshold']
            ? $this->approve($record, null)
            : $this->refuse($record, null, translate('Your selfie and the photo on the document do not look like the same person. Take them again in good light.'));
    }

    public function approve(IdVerification $record, ?string $adminId): IdVerification
    {
        if ($record->status !== IdVerification::PENDING) {
            throw ValidationException::withMessages(['record' => translate('This ID was already reviewed.')]);
        }
        $record->update(['status' => IdVerification::VERIFIED, 'method' => $adminId ? 'staff' : 'auto', 'reviewed_by' => $adminId,
            'reviewed_at' => now(), 'refuse_reason' => null]);
        $this->push($record, 'id_verified', 'Your ID is verified', 'E-Ride verified your ID. Agencies and other people on E-Ride see the badge, so you will not need to send it again.');
        return $record;
    }

    public function refuse(IdVerification $record, ?string $adminId, string $reason): IdVerification
    {
        if ($record->status !== IdVerification::PENDING) {
            throw ValidationException::withMessages(['record' => translate('This ID was already reviewed.')]);
        }
        $reason = Str::limit(trim($reason), 300, '');
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => translate('Say why, so the person can fix it.')]);
        }
        $record->update(['status' => IdVerification::REFUSED, 'method' => $adminId ? 'staff' : 'auto', 'reviewed_by' => $adminId,
            'reviewed_at' => now(), 'refuse_reason' => $reason]);
        $this->push($record, 'id_refused', 'Your ID could not be verified', '{reason} Send it again from your profile.', ['reason' => $reason]);
        return $record;
    }

    /** The profile name changed: the badge must be earned again. */
    public function nameChanged(User $user): void
    {
        IdVerification::where('user_id', $user->id)->where('status', IdVerification::VERIFIED)
            ->update(['status' => IdVerification::ENDED, 'end_reason' => 'name_changed', 'ended_at' => now()]);
    }

    /** Daily: documents past their expiry date stop counting. */
    public function expireDue(): int
    {
        $ended = 0;
        IdVerification::where('status', IdVerification::VERIFIED)->whereNotNull('expires_on')->where('expires_on', '<', today()->toDateString())
            ->get()->each(function (IdVerification $r) use (&$ended) {
                $r->update(['status' => IdVerification::ENDED, 'end_reason' => 'expired', 'ended_at' => now()]);
                $this->push($r, 'id_expired', 'Your ID has expired', 'Your verified ID reached its expiry date. Verify a valid one from your profile.');
                $ended++;
            });
        return $ended;
    }

    /** For the app: the badge and what to do next. */
    public function present(?IdVerification $r): array
    {
        return [
            // none | pending | verified | refused
            'status' => $r?->status ?? 'none',
            'doc_type' => $r?->doc_type,
            'doc_number' => $r?->doc_number,
            'masked_number' => $r?->maskedNumber(),
            'full_name' => $r?->full_name,
            'expires_on' => $r?->expires_on?->toDateString(),
            'refuse_reason' => $r?->status === IdVerification::REFUSED ? $r->refuse_reason : null,
            'submitted_at' => $r?->created_at?->toIso8601String(),
            'method' => self::settings()['method'] === 'auto' && self::autoAvailable() ? 'auto' : 'staff',
        ];
    }

    public function file(IdVerification $record, string $side): ?string
    {
        $path = match ($side) {
            'front' => $record->front_path,
            'back' => $record->back_path,
            'selfie' => $record->selfie_path,
            default => null,
        };
        return $path && Storage::disk(self::DISK)->exists($path) ? $path : null;
    }

    protected function push(IdVerification $record, string $action, string $title, string $body, array $replace = []): void
    {
        $user = User::find($record->user_id);
        if (!$user) {
            return;
        }
        $locale = $user->current_language_key;
        try {
            sendDeviceNotification(
                fcm_token: $user->fcm_token,
                title: translate(key: $title, replace: $replace, locale: $locale),
                description: translate(key: $body, replace: $replace, locale: $locale),
                status: 1,
                type: 'id_verification',
                notification_type: 'id_verification',
                action: $action,
                user_id: $user->id,
            );
        } catch (Throwable $e) {
            Log::warning('ID verification push failed', ['action' => $action, 'error' => $e->getMessage()]);
        }
    }
}
