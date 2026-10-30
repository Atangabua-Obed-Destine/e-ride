<?php

namespace Modules\ChattingManagement\Service;

use App\Events\CustomerRideChatEvent;
use App\Events\DriverRideChatEvent;
use App\Events\RideChatSignalEvent;
use App\Service\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\ChattingManagement\Entities\ChannelConversation;
use Modules\ChattingManagement\Entities\ChannelList;
use Modules\ChattingManagement\Entities\ChannelUser;
use Modules\TripManagement\Entities\TripRequest;
use Modules\AdminModule\Service\Interfaces\AdminNotificationServiceInterface;
use Modules\ChattingManagement\Repository\ChannelConversationRepositoryInterface;
use Modules\ChattingManagement\Service\Interfaces\ChannelListServiceInterface;
use Modules\ChattingManagement\Service\Interfaces\ChannelUserServiceInterface;
use Modules\TripManagement\Repository\TripRequestRepositoryInterface;

class ChannelConversationService extends BaseService implements Interfaces\ChannelConversationServiceInterface
{
    protected $channelConversationRepository;
    protected $tripRequestRepository;
    protected $channelListService;
    protected $channelUserService;
    protected $adminNotificationService;
    public function __construct(
        ChannelConversationRepositoryInterface $channelConversationRepository,
        TripRequestRepositoryInterface         $tripRequestRepository,
        ChannelListServiceInterface            $channelListService,
        ChannelUserServiceInterface            $channelUserService,
        AdminNotificationServiceInterface      $adminNotificationService
    )
    {
        parent::__construct($channelConversationRepository);
        $this->channelConversationRepository = $channelConversationRepository;
        $this->tripRequestRepository = $tripRequestRepository;
        $this->channelListService = $channelListService;
        $this->channelUserService = $channelUserService;
        $this->adminNotificationService = $adminNotificationService;
    }

    public function isVoiceMessageEnabled(): bool
    {
        return businessConfig('chatting_setup_status', CHATTING_SETTINGS)?->value == 1
            && businessConfig('voice_message_status', CHATTING_SETTINGS)?->value == 1;
    }

    public function sendRideMessage(Model $trip, Model $user, array $payload): void
    {
        [$to_user, $sentTime, $conversation] = DB::transaction(function () use ($trip, $user, $payload) {
            $this->channelListService->update(id: $payload['channel_id'], data: ['updated_at' => now()]);
            $this->channelUserService->updatedBy(criteria: ['channel_id' => $payload['channel_id'], 'user_id' => $user->id], data: ['is_read' => false]);

            $attributes = [
                'channel_id' => $payload['channel_id'],
                'message' => $payload['message'] ?? null,
                'user_id' => $user->id,
                'trip_id' => $payload['trip_id'] ?? null,
                'is_read' => 0,
            ];
            if (!empty($payload['files'])) {
                $attributes['files'] = $payload['files'];
            }
            if (!empty($payload['voice_message'])) {
                $attributes['voice_message'] = $payload['voice_message'];
            }
            $channelConversation = $this->create($attributes);
            $channelConversationWithFiles = $this->findOne(id: $channelConversation?->id, relations: ['user', 'conversation_files', 'channel']);

            $to_user = $user->user_type == DRIVER ? $trip->customer : $trip->driver;

            // Replying means the sender has seen everything the other party sent.
            $this->updatedBy(criteria: ['user_id' => $to_user->id, 'channel_id' => $payload['channel_id']], data: ['is_read' => 1]);

            return [$to_user, pushSentTime($channelConversation->created_at), $channelConversationWithFiles];
        });

        // Broadcast after the commit, so the receiving app never gets a message
        // the database does not have yet.
        if ($conversation && checkReverbConnection()) {
            try {
                $user->user_type == DRIVER ? CustomerRideChatEvent::broadcast($trip, $conversation) : DriverRideChatEvent::broadcast($trip, $conversation);
            } catch (\Throwable $exception) {
                Log::warning('Ride chat broadcast failed', ['trip_id' => $trip->id, 'error' => $exception->getMessage()]);
            }
        }

        // The push is read by the recipient, so it uses the recipient's language.
        $locale = $to_user?->current_language_key ?? $user?->current_language_key;
        $senderName = trim(($user?->first_name ?? '') . ' ' . ($user?->last_name ?? ''));
        $push = getNotification('new_message');
        sendDeviceNotification(
            fcm_token: $to_user->fcm_token,
            title: translate(key: $push['title'], locale: $locale),
            description: textVariableDataFormat(value: $push['description'], tripId: $trip->ref_id, userName: $user?->full_name ?? $user?->first_name, sentTime: $sentTime, locale: $locale),
            status: $push['status'],
            ride_request_id: $trip->id,
            type: $payload['channel_id'],
            notification_type: 'chatting',
            action: $push['action'],
            user_id: $to_user->id,
            user_name: $user?->first_name . " " . $user?->last_name,
            notificationData: [
                // Lets the apps show a conversation notification with the actual
                // message, and drop duplicates of what the websocket delivered.
                'extra' => [
                    'message_id' => $conversation?->id,
                    'channel_id' => $payload['channel_id'],
                    'sender_id' => $user->id,
                    'sender_name' => $senderName !== '' ? $senderName : ($user?->full_name ?? ''),
                    'sender_role' => $user->user_type,
                    'chat_body' => $this->previewText($conversation, $locale),
                    'trip_ref' => $trip->ref_id,
                    'sent_at' => $conversation?->created_at?->toIso8601String(),
                ],
            ],
        );
    }

    /**
     * One line describing a chat message, for notifications.
     */
    public function previewText(?Model $conversation, ?string $locale = null): string
    {
        if (!$conversation) {
            return '';
        }
        $text = trim((string)$conversation->message);
        if ($text !== '') {
            return Str::limit(preg_replace('/\s+/', ' ', $text), 160);
        }
        $files = $conversation->conversation_files ?? collect();
        $extension = strtolower((string)$files->first()?->file_type);
        if (in_array($extension, ['m4a', 'mp3', 'aac', 'wav', 'ogg', 'opus', 'amr', '3gp'], true)) {
            return "\u{1F3A4} " . translate(key: 'voice_message', locale: $locale);
        }
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'bmp'], true)) {
            return "\u{1F4F7} " . translate(key: $files->count() > 1 ? 'photos' : 'photo', locale: $locale);
        }
        return "\u{1F4CE} " . translate(key: 'attachment', locale: $locale);
    }

    /**
     * Marks everything the other party sent in a chat as read by $reader and,
     * for a ride chat, tells the other party's app (read receipt).
     * Returns how many messages changed.
     */
    public function markChannelRead(string $channelId, Model $reader): int
    {
        $marked = ChannelConversation::query()
            ->where('channel_id', $channelId)
            ->where('user_id', '!=', $reader->id)
            ->where('is_read', 0)
            ->update(['is_read' => 1]);

        ChannelUser::query()
            ->where('channel_id', $channelId)
            ->where('user_id', $reader->id)
            ->update(['is_read' => 1]);

        if ($marked > 0) {
            $this->sendRideChatSignal($channelId, $reader, RideChatSignalEvent::READ, [
                'read_at' => now()->toIso8601String(),
            ]);
        }

        return $marked;
    }

    /**
     * Sends a read or typing signal from $sender to the other party of a ride
     * chat. Only a member of a trip chat can send one.
     */
    public function sendRideChatSignal(string $channelId, Model $sender, string $signal, array $payload = []): bool
    {
        $channel = ChannelList::query()->find($channelId);
        if (!$channel || $channel->channelable_type !== TripRequest::class || !$channel->channelable_id) {
            return false;
        }
        $isMember = ChannelUser::query()->where('channel_id', $channelId)->where('user_id', $sender->id)->exists();
        if (!$isMember || !checkReverbConnection()) {
            return false;
        }

        try {
            broadcast(new RideChatSignalEvent(
                audience: $sender->user_type == DRIVER ? 'customer' : 'driver',
                signal: $signal,
                tripId: $channel->channelable_id,
                payload: array_merge([
                    'channel_id' => $channelId,
                    'trip_id' => $channel->channelable_id,
                    'user_id' => $sender->id,
                ], $payload),
            ));
            return true;
        } catch (\Throwable $exception) {
            Log::warning('Ride chat signal failed', ['channel_id' => $channelId, 'signal' => $signal, 'error' => $exception->getMessage()]);
            return false;
        }
    }

    /**
     * Unread ride-chat messages for $user: the total and a count per chat.
     */
    public function unreadSummary(Model $user): array
    {
        $channelIds = ChannelUser::query()->where('user_id', $user->id)->pluck('channel_id');
        $tripChannels = ChannelList::query()
            ->whereIn('id', $channelIds)
            ->where('channelable_type', TripRequest::class)
            ->pluck('channelable_id', 'id');

        $counts = ChannelConversation::query()
            ->whereIn('channel_id', $tripChannels->keys())
            ->where('user_id', '!=', $user->id)
            ->where('is_read', 0)
            ->selectRaw('channel_id, COUNT(*) as unread')
            ->groupBy('channel_id')
            ->pluck('unread', 'channel_id');

        return [
            'total' => (int)$counts->sum(),
            'channels' => $counts->map(fn($count, $channelId) => [
                'channel_id' => $channelId,
                'trip_id' => $tripChannels[$channelId] ?? null,
                'unread' => (int)$count,
            ])->values(),
        ];
    }

    public function sendAdminMessage(Model $user, array $payload): void
    {
        DB::transaction(function () use ($user, $payload) {
            $this->channelUserService->updatedBy(criteria: ['channel_id' => $payload['channel_id'], ['user_id', '=', $user->id]], data: ['is_read' => true]);

            $attributes = [
                'channel_id' => $payload['channel_id'],
                'message' => $payload['message'] ?? null,
                'user_id' => $user->id,
                'is_read' => 0,
            ];
            if (!empty($payload['files'])) {
                $attributes['files'] = $payload['files'];
            }
            $channelConversation = $this->create($attributes);
            $this->adminNotificationService->create([
                'model' => 'channel_conversation',
                'model_id' => $channelConversation->id,
                'message' => 'new_message_arrived',
            ]);
        });
    }

    public function create(array $data): ?Model
    {
        if (array_key_exists('trip_id', $data) && $data['trip_id']) {
            $trip = $this->tripRequestRepository->findOne($data['trip_id']);
            $conversation = $trip?->conversations()->create($data);
        } else{
            $conversation = $this->channelConversationRepository->create($data);
        }
        if (array_key_exists('files', $data)) {
            foreach ($data['files'] as $file) {
                $extension = $file->getClientOriginalExtension();
                $conversation?->conversation_files()->create([
                    'file_name' => fileUploader('conversation/', $extension, $file),
                    'file_type' => $extension,
                ]);
            }
        }
        if (array_key_exists('voice_message', $data) && $data['voice_message']) {
            $extension = $data['voice_message']->getClientOriginalExtension();
            $conversation?->conversation_files()->create([
                'file_name' => fileUploader('conversation/', $extension, $data['voice_message']),
                'file_type' => $extension,
            ]);
        }
        return $conversation;
    }

}
