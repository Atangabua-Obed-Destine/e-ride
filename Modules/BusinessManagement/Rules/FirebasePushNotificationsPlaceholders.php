<?php

namespace Modules\BusinessManagement\Rules;
use Illuminate\Contracts\Validation\Rule;


class FirebasePushNotificationsPlaceholders implements Rule
{
    protected array $requiredPlaceholders;
    protected $attribute;

    public function __construct(array $requiredPlaceholders)
    {
        $this->requiredPlaceholders = $requiredPlaceholders;
    }

    public function passes($attribute, $value): bool
    {
        $this->attribute = $attribute;
        foreach ($this->requiredPlaceholders as $placeholder) {
            if (strpos($value, "{{$placeholder}}") === false) {
                return false;
            }
        }
        return true;
    }

    public function message(): array
    {
        $notificationKey = ucwords(str_replace('_', ' ', explode('.', $this->attribute)[1]));
        $words = explode(' ', $notificationKey);
        if (count($words) > 1) {
            $notificationKey = $words[0] . ' - ' . implode(' ', array_slice($words, 1));
        }

        return [
            'notificationKey' => $notificationKey,
            'placeHolders' => implode(', ', array_map(fn($p): string => "{{$p}}", $this->requiredPlaceholders))
        ];
    }
}
