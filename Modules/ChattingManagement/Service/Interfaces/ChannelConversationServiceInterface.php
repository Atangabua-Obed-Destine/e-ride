<?php

namespace Modules\ChattingManagement\Service\Interfaces;

use App\Service\BaseServiceInterface;
use Illuminate\Database\Eloquent\Model;

interface ChannelConversationServiceInterface extends BaseServiceInterface
{
    public function isVoiceMessageEnabled(): bool;

    public function sendRideMessage(Model $trip, Model $user, array $payload): void;

    public function sendAdminMessage(Model $user, array $payload): void;

    public function previewText(?Model $conversation, ?string $locale = null): string;

    public function markChannelRead(string $channelId, Model $reader): int;

    public function sendRideChatSignal(string $channelId, Model $sender, string $signal, array $payload = []): bool;

    public function unreadSummary(Model $user): array;
}
