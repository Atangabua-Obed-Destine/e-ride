<?php

namespace Modules\UserManagement\Enums;

enum SuspendReasonEnum: String
{
    case CASH_IN_HAND_LIMIT = 'cash_in_hand_limit';
    case FACE_VERIFICATION  = 'face_verification';
    case ANONYMOUS          = 'anonymous';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
