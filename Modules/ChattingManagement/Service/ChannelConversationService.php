<?php

namespace Modules\ChattingManagement\Service;

use App\Events\CustomerRideChatEvent;
use App\Events\DriverRideChatEvent;
use App\Service\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
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
        [$to_user, $sentTime] = DB::transaction(function () use ($trip, $user, $payload) {
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

            if (checkReverbConnection()) {
                try {
                    $user->user_type == DRIVER ? CustomerRideChatEvent::broadcast($trip, $channelConversationWithFiles) : DriverRideChatEvent::broadcast($trip, $channelConversationWithFiles);
                } catch (\Exception $exception) {
                }
            }

            $this->updatedBy(criteria: ['user_id' => $to_user->id, 'channel_id' => $payload['channel_id']], data: ['is_read' => 1]);

            return [$to_user, pushSentTime($channelConversation->created_at)];
        });

        $push = getNotification('new_message');
        sendDeviceNotification(
            fcm_token: $to_user->fcm_token,
            title: translate(key: $push['title'], locale: $user?->current_language_key),
            description: textVariableDataFormat(value: $push['description'], tripId: $trip->ref_id, userName: $user?->full_name ?? $user?->first_name, sentTime: $sentTime, locale: $user?->current_language_key),
            status: $push['status'],
            ride_request_id: $trip->id,
            type: $payload['channel_id'],
            notification_type: 'chatting',
            action: $push['action'],
            user_id: $to_user->id,
            user_name: $user?->first_name . " " . $user?->last_name
        );
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
