<?php

namespace Modules\UserManagement\Enums;

enum PauseReasonEnum: String
{
    case CASH_IN_HAND_LIMIT = 'cash_in_hand_limit';
    case FACE_VERIFICATION  = 'face_verification';
    case ANONYMOUS          = 'anonymous';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function systemValues(): array
    {
        return [
            self::CASH_IN_HAND_LIMIT->value,
            self::FACE_VERIFICATION->value,
        ];
    }

    public static function isSystem(?string $reason): bool
    {
        return in_array($reason, self::systemValues(), true);
    }
}
