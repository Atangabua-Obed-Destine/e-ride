<?php

namespace Modules\BusinessManagement\Rules;

use Closure;
use Illuminate\Contracts\Validation\Rule;
//use Illuminate\Contracts\Validation\ValidationRule;

class ContainsPlaceholders implements Rule
{
    /**
     * Validate if the value contains all required placeholders.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        // Check if the value contains all required placeholders
        return strpos($value, '{CustomerName}') !== false &&
            strpos($value, '{ParcelId}') !== false &&
            strpos($value, '{TrackingLink}') !== false;
    }

    /**
     * Get the error message for validation failure.
     *
     * @return string
     */
    public function message()
    {
        return 'The :attribute must contain the placeholders {CustomerName}, {ParcelId}, and {TrackingLink}.';
    }
}
