<?php

namespace Modules\StoreManagement\Http\Requests;

use Modules\StoreManagement\Entities\Store;

/** Validation for the weekly opening-hours grid shared by the admin and partner forms. */
class StoreHoursRules
{
    public static function rules(): array
    {
        $rules = ['opening_hours' => 'nullable|array'];
        foreach (Store::DAYS as $day) {
            $rules["opening_hours.$day.open"] = 'nullable|date_format:H:i';
            $rules["opening_hours.$day.close"] = "nullable|date_format:H:i|after:opening_hours.$day.open";
            $rules["opening_hours.$day.closed"] = 'nullable|boolean';
        }
        return $rules;
    }
}
