<?php

namespace Modules\FareManagement\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Exceptions\HttpResponseException;

class SurgePricingStoreOrUpdateRequest extends FormRequest
{

    protected $stopOnFirstFailure = true;
    public function rules(): array
    {$id = $this->route('id');
        $nameRule = $id ? "required|string|unique:surge_pricing,name,{$id}|max:255" : 'required|string|unique:surge_pricing,name|max:255';

        return [
            'name' => $nameRule,
            'zones' => 'required|array',
            'zones.*' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    if ($value !== 'all' && !\DB::table('zones')->where('id', $value)->exists()) {
                        $fail("The selected zone ($value) is invalid.");
                    }
                }
            ],
            'pricing_for' => 'required|string|in:ride,parcel,both',
            'increase_rate' => 'required_unless:pricing_for,parcel|string|in:different_rate,all_vehicle',
            'ride_surge_multiplier' => [ function ($attribute, $value, $fail) {
                if (request('pricing_for') !== 'parcel' && request('increase_rate') == 'all_vehicle')  {
                    if ($value === null || $value === '') {
                        $fail('The ride surge multiplier field is required.');
                    } elseif (!is_numeric($value) || $value < 0 || $value > 100) {
                        $fail('The ride surge multiplier must be a number between 0 and 100.');
                    } elseif (!preg_match('/^\d+(\.\d{1,2})?$/', $value)) {
                        $fail('The ride surge multiplier may only have up to 2 decimal places.');
                    }
                }
            }],
            'surge_multipliers' => [
                'array',
                'required_if:increase_rate,different_rate',
                function ($attribute, $value, $fail) {
                    if (request('increase_rate') !== 'different_rate' || !in_array(request('pricing_for'), ['ride', 'both']) ) {
                        return;
                    }
                    if (is_array($value)) {
                        $invalidKeys = collect(array_keys($value))
                            ->reject(fn($id) => DB::table('vehicle_categories')->where('id', $id)->exists());
                        if ($invalidKeys->isNotEmpty()) {
                            $fail('The selected vehicle categories are invalid: ' . $invalidKeys->implode(', '));
                        }
                        foreach ($value as $key => $val) {
                            if (!is_numeric($val) || $val < 0 || $val > 100) {
                                $fail("Each surge multiplier must be a number between 0 and 100.");
                            } elseif (!preg_match('/^\d+(\.\d{1,2})?$/', $val)) {
                                $fail('The ride surge multiplier may only have up to 2 decimal places.');
                            }
                        }
                    }
                }
            ],
            'parcel_surge_multiplier' => [
                'nullable',
                'required_unless:pricing_for,ride',
                'numeric',
                'min:0',
                'max:100',
                function ($attribute, $value, $fail) {
                    if ($value === null) return;
                    if (!preg_match('/^\d+(\.\d{1,2})?$/', $value)) {
                        $fail('The ' . str_replace('_', ' ', $attribute) . ' may only have up to 2 decimal places.');
                    }
                }
            ],
            'price_schedule' => 'required|string|in:daily,weekly,custom',
            'date_range_daily' => 'nullable|required_if:price_schedule,daily|string',
            'time_range_daily' => 'nullable|required_if:price_schedule,daily|string',
            'select_days' => 'nullable|required_if:price_schedule,weekly|string',
            'date_range_weekly' => 'nullable|required_if:price_schedule,weekly|string',
            'time_range_weekly' => 'nullable|required_if:price_schedule,weekly|string',
            'date_range_custom' => 'nullable|required_if:price_schedule,custom|array',
            'date_range_custom.*' => 'nullable|string',
            'time_range_custom' => 'nullable|required_if:price_schedule,custom|array',
            'time_range_custom.*' => 'nullable|string',
            'customer_note' => 'nullable|string|max:31',
            'is_active' => 'sometimes|in:on',
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'The name field is required.',
            'name.string' => 'The name must be a valid string.',
            'name.max' => 'The name cannot exceed 255 characters.',

            'zones.required' => 'Please select at least one zone.',
            'zones.array' => 'Zones must be an array.',
            'zones.*.required' => 'Each zone is required.',
            'zones.*.string' => 'Each zone must be a string.',
            'zones.*.exists' => 'Selected zone is invalid.',

            'pricing_for.required' => 'The pricing for field is required.',
            'pricing_for.in' => 'The selected pricing for option is invalid.',

            'increase_rate.required_unless' => 'The increase rate is required unless pricing for is parcel.',
            'increase_rate.in' => 'The selected increase rate is invalid.',

            'ride_surge_multiplier.numeric' => 'The ride surge multiplier must be a number.',
            'ride_surge_multiplier.min' => 'The ride surge multiplier must be at least 0.',
            'ride_surge_multiplier.max' => 'The ride surge multiplier cannot be greater than 100.',

            'surge_multipliers.required_if' => 'Surge multipliers are required when increase rate is different rate.',
            'surge_multipliers.array' => 'Surge multipliers must be an array.',

            'parcel_surge_multiplier.required_unless' => 'The parcel surge multiplier is required unless pricing for is ride.',
            'parcel_surge_multiplier.numeric' => 'The parcel surge multiplier must be a number.',
            'parcel_surge_multiplier.min' => 'The parcel surge multiplier must be at least 0.',
            'parcel_surge_multiplier.max' => 'The parcel surge multiplier cannot be greater than 100.',

            'price_schedule.required' => 'The price schedule field is required.',
            'price_schedule.string' => 'The price schedule must be a string.',
            'price_schedule.in' => 'The selected price schedule is invalid.',

            'note.string' => 'The note must be a string.',
            'note.max' => 'The note cannot exceed 31 characters.',

            'is_active.in' => 'The is_active field must be on or not present.',

            'date_range_daily.required_if' => 'The date range is required when price schedule is daily.',
            'date_range_daily.string' => 'The date range must be a valid string.',
            'time_range_daily.required_if' => 'The time range is required when price schedule is daily.',
            'time_range_daily.string' => 'The time range must be a valid string.',
            'select_days.required_if' => 'Please select at least one day when price schedule is weekly.',
            'select_days.string' => 'The selected days must be a valid string.',
            'date_range_weekly.required_if' => 'The date range is required when price schedule is weekly.',
            'date_range_weekly.string' => 'The date range must be a valid string.',
            'time_range_weekly.required_if' => 'The time range is required when price schedule is weekly.',
            'time_range_weekly.string' => 'The time range must be a valid string.',
            'date_range_custom.required_if' => 'The date range is required when price schedule is custom.',
            'date_range_custom.array' => 'The date range must be an array.',
            'date_range_custom.*.string' => 'Each date in the custom date range must be a valid string.',
            'time_range_custom.required_if' => 'The time range is required when price schedule is custom.',
            'time_range_custom.array' => 'The time range must be an array.',
            'time_range_custom.*.string' => 'Each time in the custom time range must be a valid string.',
        ];
    }


    public function authorize(): bool
    {
        return Auth::check();
    }

   protected function failedValidation(Validator $validator)
   {
       throw new HttpResponseException(response()->json(['errors' => errorProcessor($validator)]));
   }
}
